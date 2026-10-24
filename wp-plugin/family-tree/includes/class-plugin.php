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
 * Wires the plugin into WordPress. Later steps register post types, REST routes
 * and the editor from here.
 */
final class Plugin {

	/**
	 * Attach hooks. Called once when the plugin file loads.
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', array( self::class, 'on_plugins_loaded' ) );
	}

	/**
	 * Runs after all plugins are loaded. Nothing to do yet.
	 */
	public static function on_plugins_loaded(): void {
		// Intentionally empty: the data model arrives in Phase 2.
	}

	/**
	 * Runs once when the plugin is activated. Records the installed version.
	 */
	public static function activate(): void {
		update_option( 'family_tree_version', FAMILY_TREE_VERSION );
	}
}
