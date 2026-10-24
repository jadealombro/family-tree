<?php
/**
 * The Family Editor role and the capabilities behind it.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gives the editor (Jade's aunt) exactly the permissions the editor screen needs,
 * and gives administrators the same set.
 */
final class Roles {

	public const EDITOR_ROLE = 'family_editor';
	public const EDIT_CAP    = 'edit_family_tree';

	/**
	 * Create or refresh the role. Safe to run repeatedly. Needs the post types
	 * to be registered first, because their capability names come from them.
	 */
	public static function install(): void {
		$caps = array(
			'read'         => true,
			'upload_files' => true,
			self::EDIT_CAP => true,
		);

		foreach ( array( Post_Types::PERSON, Post_Types::FAMILY ) as $post_type ) {
			foreach ( self::post_type_caps( $post_type ) as $cap ) {
				$caps[ $cap ] = true;
			}
		}

		$editor = get_role( self::EDITOR_ROLE );
		if ( null === $editor ) {
			add_role( self::EDITOR_ROLE, __( 'Family Editor', 'family-tree' ), $caps );
		} else {
			foreach ( $caps as $cap => $grant ) {
				$editor->add_cap( $cap, $grant );
			}
		}

		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( $caps as $cap => $grant ) {
				$admin->add_cap( $cap, $grant );
			}
		}
	}

	/**
	 * The real (primitive) capabilities a post type needs, read from WordPress
	 * itself so we never hand-type them.
	 *
	 * @param string $post_type Post type slug.
	 * @return string[]
	 */
	private static function post_type_caps( string $post_type ): array {
		$object = get_post_type_object( $post_type );
		if ( null === $object ) {
			return array();
		}

		$caps = (array) $object->cap;
		unset( $caps['edit_post'], $caps['read_post'], $caps['delete_post'] );

		return array_values( array_unique( $caps ) );
	}
}
