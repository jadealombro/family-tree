<?php
/**
 * Bootstrap for the unit tests. These tests exercise pure PHP classes only, so
 * WordPress is not loaded; we just satisfy the ABSPATH guard and load the classes.
 *
 * @package FamilyTree
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Minimal stand-in for WordPress's wp_json_encode, so no WordPress is needed.
	 *
	 * @param mixed $data Data to encode.
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-auth.php';
require_once dirname( __DIR__ ) . '/includes/class-tree-builder.php';
