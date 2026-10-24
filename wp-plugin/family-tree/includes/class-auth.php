<?php
/**
 * Checks the shared API key between the Next.js server and WordPress.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure functions, so the security-critical comparison is unit-tested.
 */
final class Auth {

	public const MIN_KEY_LENGTH = 32;

	/**
	 * A key counts only if it is a string of at least 32 characters. An empty or
	 * short key in wp-config.php must never open the API.
	 *
	 * @param mixed $key Key from configuration.
	 */
	public static function is_strong_key( $key ): bool {
		return is_string( $key ) && strlen( $key ) >= self::MIN_KEY_LENGTH;
	}

	/**
	 * Compare the key a caller sent with the configured key, in constant time so
	 * response timing leaks nothing about how close a guess was.
	 *
	 * @param mixed $provided Key from the request header.
	 * @param mixed $expected Key from configuration.
	 */
	public static function matches( $provided, $expected ): bool {
		return self::is_strong_key( $expected ) && is_string( $provided ) && hash_equals( $expected, $provided );
	}
}
