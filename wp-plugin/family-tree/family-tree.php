<?php
/**
 * Plugin Name:       Family Tree
 * Description:       Stores people and family relationships and serves them to the family tree app.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Jade
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       family-tree
 *
 * @package FamilyTree
 */

namespace FamilyTree;

// Stop direct access: this file only runs when WordPress loads it.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FAMILY_TREE_VERSION', '0.2.0' );
define( 'FAMILY_TREE_FILE', __FILE__ );
define( 'FAMILY_TREE_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Autoloader: FamilyTree\Some_Thing is loaded from includes/class-some-thing.php,
 * so classes never need a manual require.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = FAMILY_TREE_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

Plugin::init();
