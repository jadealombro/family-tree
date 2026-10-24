<?php
/**
 * The REST route the Next.js server calls.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/family-tree/v1/tree returns the whole family as JSON, but only
 * to a caller that sends the secret key in the X-Family-Tree-Key header.
 */
final class Rest {

	public const REST_NAMESPACE = 'family-tree/v1';

	/**
	 * Register the route. Hooked to `rest_api_init`.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/tree',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_tree' ),
				'permission_callback' => array( self::class, 'check_key' ),
			)
		);
	}

	/**
	 * Allow the request only if it carries the configured key.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return true|\WP_Error
	 */
	public static function check_key( \WP_REST_Request $request ) {
		$expected = defined( 'FAMILY_TREE_API_KEY' ) ? FAMILY_TREE_API_KEY : '';

		if ( ! Auth::is_strong_key( $expected ) ) {
			return new \WP_Error(
				'family_tree_not_configured',
				'The API key is missing or too short in wp-config.php.',
				array( 'status' => 503 )
			);
		}

		if ( Auth::matches( $request->get_header( 'x_family_tree_key' ), $expected ) ) {
			return true;
		}

		return new \WP_Error(
			'family_tree_forbidden',
			'Missing or invalid key.',
			array( 'status' => 401 )
		);
	}

	/**
	 * Build and return the tree.
	 */
	public static function get_tree(): \WP_REST_Response {
		list( $people, $families ) = Tree_Source::load();

		$root_option = (int) get_option( 'family_tree_root_family_id', 0 );
		$payload     = Tree_Builder::build( $people, $families, $root_option > 0 ? $root_option : null );

		$response = new \WP_REST_Response( $payload );
		$response->header( 'Cache-Control', 'private, no-store' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );

		return $response;
	}
}
