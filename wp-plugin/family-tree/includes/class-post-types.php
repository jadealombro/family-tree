<?php
/**
 * Registers the two custom post types: Person and Family.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A Person is one human. A Family is a unit of up to two partners plus their
 * ordered children. All relationships live on Family records (SPEC.md section 3).
 */
final class Post_Types {

	public const PERSON = 'ft_person';
	public const FAMILY = 'ft_family';

	/**
	 * Register both post types. Hooked to `init`.
	 */
	public static function register(): void {
		register_post_type(
			self::PERSON,
			array(
				'labels'              => array(
					'name'          => __( 'People', 'family-tree' ),
					'singular_name' => __( 'Person', 'family-tree' ),
					'add_new_item'  => __( 'Add Person', 'family-tree' ),
					'edit_item'     => __( 'Edit Person', 'family-tree' ),
					'menu_name'     => __( 'Family Tree', 'family-tree' ),
				),
				'description'         => 'One person in the family tree.',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'thumbnail' ),
				'menu_icon'           => 'dashicons-groups',
				'capability_type'     => array( 'ft_person', 'ft_people' ),
				'map_meta_cap'        => true,
			)
		);

		register_post_type(
			self::FAMILY,
			array(
				'labels'              => array(
					'name'          => __( 'Families', 'family-tree' ),
					'singular_name' => __( 'Family', 'family-tree' ),
					'add_new_item'  => __( 'Add Family', 'family-tree' ),
					'edit_item'     => __( 'Edit Family', 'family-tree' ),
				),
				'description'         => 'A couple or single parent, with their ordered children.',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::PERSON,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'capability_type'     => array( 'ft_family', 'ft_families' ),
				'map_meta_cap'        => true,
			)
		);
	}
}
