<?php
/**
 * Sanitizers for every field. Each takes one raw value and returns a clean one.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure cleaning functions, so bad data cannot reach the database no matter
 * which route it arrives by (WP-CLI, import, editor, or a future API).
 */
final class Sanitize {

	public const SEX            = array( 'unknown', 'female', 'male' );
	public const UNION_TYPES    = array( 'unknown', 'married', 'partnered', 'none' );
	public const UNION_STATUSES = array( 'unknown', 'together', 'separated', 'divorced', 'widowed' );
	public const RELATIONS      = array( 'unknown', 'biological', 'adopted', 'step', 'foster' );

	/**
	 * Single-line text.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function text( $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Multi-line text.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function textarea( $value ): string {
		return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
	}

	/**
	 * Boolean. Understands true, 1, "1", "true" and "on".
	 *
	 * @param mixed $value Raw value.
	 */
	public static function boolean( $value ): bool {
		return rest_sanitize_boolean( $value );
	}

	/**
	 * Partial date: "YYYY", "YYYY-MM", "YYYY-MM-DD", or empty for unknown.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function date( $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( ! preg_match( '/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/', $value, $parts ) ) {
			return '';
		}

		$year  = (int) $parts[1];
		$month = isset( $parts[2] ) ? (int) $parts[2] : 0;
		$day   = isset( $parts[3] ) ? (int) $parts[3] : 0;

		if ( isset( $parts[2] ) && ( $month < 1 || $month > 12 ) ) {
			return '';
		}
		if ( isset( $parts[3] ) && ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Sex: unknown, female or male.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sex( $value ): string {
		return self::one_of( $value, self::SEX );
	}

	/**
	 * Union type: unknown, married, partnered or none.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function union_type( $value ): string {
		return self::one_of( $value, self::UNION_TYPES );
	}

	/**
	 * Union status: unknown, together, separated, divorced or widowed.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function union_status( $value ): string {
		return self::one_of( $value, self::UNION_STATUSES );
	}

	/**
	 * Non-negative whole number.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sequence( $value ): int {
		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Partners: a list of at most two distinct person IDs.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]
	 */
	public static function partners( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_filter( array_map( 'absint', array_filter( $value, 'is_scalar' ) ) );

		return array_slice( array_values( array_unique( $ids ) ), 0, 2 );
	}

	/**
	 * Children: an ordered list of { person_id, relation }. Order is the birth order.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, array{person_id: int, relation: string}>
	 */
	public static function children( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		$seen  = array();

		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$person_id = isset( $item['person_id'] ) && is_scalar( $item['person_id'] ) ? absint( $item['person_id'] ) : 0;
			if ( 0 === $person_id || isset( $seen[ $person_id ] ) ) {
				continue;
			}

			$seen[ $person_id ] = true;
			$clean[]            = array(
				'person_id' => $person_id,
				'relation'  => self::one_of( $item['relation'] ?? '', self::RELATIONS ),
			);
		}

		return $clean;
	}

	/**
	 * Return the value if it is in the allowed list, else the first allowed value.
	 *
	 * @param mixed    $value   Raw value.
	 * @param string[] $allowed Allowed values; the first is the fallback.
	 */
	private static function one_of( $value, array $allowed ): string {
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return in_array( $value, $allowed, true ) ? $value : $allowed[0];
	}
}
