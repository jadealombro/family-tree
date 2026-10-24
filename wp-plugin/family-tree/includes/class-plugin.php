<?php
/**
 * Plugin bootstrap class.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin into WordPress. Later steps register REST routes and the
 * editor from here.
 */
final class Plugin {

	public const VERSION_OPTION = 'family_tree_version';

	/**
	 * Attach hooks. Called once when the plugin file loads.
	 */
	public static function init(): void {
		add_action( 'init', array( Post_Types::class, 'register' ) );
		add_action( 'init', array( Meta::class, 'register' ) );
		add_action( 'rest_api_init', array( Rest::class, 'register_routes' ) );
		// Priority 20: after the post types exist, because roles are built from them.
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 20 );
	}

	/**
	 * Run the install and upgrade work whenever the code version differs from
	 * the version stored in the database. Unlike an activation hook, this also
	 * runs when new code is deployed over an already-active plugin.
	 */
	public static function maybe_upgrade(): void {
		if ( FAMILY_TREE_VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		Roles::install();
		update_option( self::VERSION_OPTION, FAMILY_TREE_VERSION );
	}
}
