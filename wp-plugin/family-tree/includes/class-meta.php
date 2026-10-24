<?php
/**
 * Registers the meta fields for Person and Family, with sanitizers and defaults.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One place that lists every field. Keys are stored with a leading underscore
 * so WordPress treats them as protected and hides them from the Custom Fields box;
 * all writes then go through the sanitizers registered here.
 */
final class Meta {

	public const PREFIX = '_ft_';

	/**
	 * Register all fields. Hooked to `init`.
	 */
	public static function register(): void {
		foreach ( self::fields_for_person() as $key => $field ) {
			self::register_field( Post_Types::PERSON, $key, $field );
		}
		foreach ( self::fields_for_family() as $key => $field ) {
			self::register_field( Post_Types::FAMILY, $key, $field );
		}
	}

	/**
	 * Person fields (SPEC.md section 3.1). The display name is the post title and
	 * the portrait is the featured image.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields_for_person(): array {
		return array(
			'given_name'        => self::field( 'string', '', 'text' ),
			'nickname'          => self::field( 'string', '', 'text' ),
			'surname_birth'     => self::field( 'string', '', 'text' ),
			'surname_married'   => self::field( 'string', '', 'text' ),
			'name_unknown'      => self::field( 'boolean', false, 'boolean' ),
			'sex'               => self::field( 'string', 'unknown', 'sex' ),
			'is_living'         => self::field( 'boolean', true, 'boolean' ),
			'never_married'     => self::field( 'boolean', false, 'boolean' ),
			'birth_date'        => self::field( 'string', '', 'date' ),
			'birth_date_approx' => self::field( 'boolean', false, 'boolean' ),
			'death_date'        => self::field( 'string', '', 'date' ),
			'lungsod'           => self::field( 'string', '', 'text' ),
			'occupation'        => self::field( 'string', '', 'text' ),
			'notes'             => self::field( 'string', '', 'textarea' ),
		);
	}

	/**
	 * Family fields (SPEC.md section 3.2).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields_for_family(): array {
		return array(
			'partners'   => self::field( 'array', array(), 'partners' ),
			'union_type' => self::field( 'string', 'unknown', 'union_type' ),
			'status'     => self::field( 'string', 'unknown', 'union_status' ),
			'start_date' => self::field( 'string', '', 'date' ),
			'end_date'   => self::field( 'string', '', 'date' ),
			'sequence'   => self::field( 'integer', 0, 'sequence' ),
			'children'   => self::field( 'array', array(), 'children' ),
		);
	}

	/**
	 * Only people allowed to edit the family tree may change these fields.
	 */
	public static function can_edit(): bool {
		return current_user_can( 'edit_family_tree' );
	}

	/**
	 * Build one field definition.
	 *
	 * @param string $type      Meta type: string, boolean, integer or array.
	 * @param mixed  $default_value Value returned when nothing has been saved.
	 * @param string $sanitizer Method name on the Sanitize class.
	 * @return array<string, mixed>
	 */
	private static function field( string $type, $default_value, string $sanitizer ): array {
		return array(
			'type'     => $type,
			'default'  => $default_value,
			'sanitize' => array( Sanitize::class, $sanitizer ),
		);
	}

	/**
	 * Register one field with WordPress.
	 *
	 * @param string               $post_type Post type slug.
	 * @param string               $key       Field key without prefix.
	 * @param array<string, mixed> $field     Definition from field().
	 */
	private static function register_field( string $post_type, string $key, array $field ): void {
		register_post_meta(
			$post_type,
			self::PREFIX . $key,
			array(
				'type'              => $field['type'],
				'single'            => true,
				'default'           => $field['default'],
				'sanitize_callback' => $field['sanitize'],
				'auth_callback'     => array( self::class, 'can_edit' ),
				'show_in_rest'      => false,
			)
		);
	}
}
