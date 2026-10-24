<?php
/**
 * Reads people and families out of WordPress for the tree builder.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only place that reads fields for the public payload, and it reads from an
 * explicit allow-list. Birth and death dates, union dates, sex and notes are never
 * read here, so they cannot leak by accident.
 */
final class Tree_Source {

	/**
	 * Load every published person and family.
	 *
	 * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} People and families.
	 */
	public static function load(): array {
		$person_ids = self::ids( Post_Types::PERSON );
		$family_ids = self::ids( Post_Types::FAMILY );

		// One query fetches all meta for these posts, so the loops below hit the cache.
		update_meta_cache( 'post', array_merge( $person_ids, $family_ids ) );

		$people = array();
		foreach ( $person_ids as $id ) {
			$photo    = get_the_post_thumbnail_url( $id, 'medium' );
			$people[] = array(
				'id'              => $id,
				'display_name'    => get_post_field( 'post_title', $id ),
				'given_name'      => self::meta( $id, 'given_name' ),
				'nickname'        => self::meta( $id, 'nickname' ),
				'surname_birth'   => self::meta( $id, 'surname_birth' ),
				'surname_married' => self::meta( $id, 'surname_married' ),
				'name_unknown'    => (bool) self::meta( $id, 'name_unknown' ),
				'is_living'       => (bool) self::meta( $id, 'is_living' ),
				'never_married'   => (bool) self::meta( $id, 'never_married' ),
				'lungsod'         => self::meta( $id, 'lungsod' ),
				'occupation'      => self::meta( $id, 'occupation' ),
				'photo'           => false === $photo ? null : $photo,
			);
		}

		$families = array();
		foreach ( $family_ids as $id ) {
			$families[] = array(
				'id'         => $id,
				'partners'   => self::meta( $id, 'partners' ),
				'children'   => self::meta( $id, 'children' ),
				'union_type' => self::meta( $id, 'union_type' ),
				'status'     => self::meta( $id, 'status' ),
				'sequence'   => self::meta( $id, 'sequence' ),
			);
		}

		return array( $people, $families );
	}

	/**
	 * IDs of all published posts of a type, oldest first.
	 *
	 * @param string $post_type Post type slug.
	 * @return int[]
	 */
	private static function ids( string $post_type ): array {
		$query = new \WP_Query(
			array(
				'post_type'     => $post_type,
				'post_status'   => 'publish',
				'fields'        => 'ids',
				'nopaging'      => true,
				'no_found_rows' => true,
				'orderby'       => 'ID',
				'order'         => 'ASC',
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Read one of our fields. Missing values fall back to the default registered in Meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key without prefix.
	 * @return mixed
	 */
	private static function meta( int $post_id, string $key ) {
		return get_post_meta( $post_id, Meta::PREFIX . $key, true );
	}
}
