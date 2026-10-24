<?php
/**
 * Turns raw people and families into the JSON payload the tree app draws.
 *
 * @package FamilyTree
 */

namespace FamilyTree;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure PHP: no WordPress functions are called here, so every rule can be tested
 * quickly with invented data. The WordPress side lives in Tree_Source.
 *
 * Derived data computed here (SPEC.md section 3.4): generation, branch, married-in
 * flag, child counts and descendant counts. Nobody types these in.
 */
final class Tree_Builder {

	public const PAYLOAD_VERSION = 1;

	private const PERSON_DEFAULTS = array(
		'display_name'    => '',
		'given_name'      => '',
		'nickname'        => '',
		'surname_birth'   => '',
		'surname_married' => '',
		'name_unknown'    => false,
		'is_living'       => true,
		'never_married'   => false,
		'lungsod'         => '',
		'occupation'      => '',
		'photo'           => null,
	);

	/**
	 * Build the payload.
	 *
	 * @param array<int, array<string, mixed>> $people         Raw people, each with an `id`.
	 * @param array<int, array<string, mixed>> $families       Raw families: id, partners, children, union_type, status, sequence.
	 * @param int|null                         $root_family_id Family to start from; null picks the founding family with most descendants.
	 * @return array<string, mixed>
	 */
	public static function build( array $people, array $families, ?int $root_family_id = null ): array {
		$people_by_id   = self::index_people( $people );
		$families_by_id = self::index_families( $families, $people_by_id );
		$partner_of     = self::index_by_partner( $families_by_id );
		$child_of       = self::index_by_child( $families_by_id );
		$root_id        = self::pick_root( $families_by_id, $partner_of, $child_of, $root_family_id );
		$state          = array();
		$branch_ids     = array();

		if ( null !== $root_id ) {
			list( $state, $branch_ids ) = self::assign( $root_id, $families_by_id );
		}

		$branches = array();
		foreach ( $branch_ids as $branch_id ) {
			$branches[] = array(
				'id'   => $branch_id,
				'name' => self::name( $people_by_id[ $branch_id ] ),
			);
		}

		$people_out = array();
		foreach ( $people_by_id as $id => $person ) {
			$people_out[ $id ] = array(
				'id'              => $id,
				'name'            => self::name( $person ),
				'nickname'        => $person['nickname'],
				'givenName'       => $person['given_name'],
				'surnameBirth'    => $person['surname_birth'],
				'surnameMarried'  => $person['surname_married'],
				'nameUnknown'     => $person['name_unknown'],
				'isLiving'        => $person['is_living'],
				'neverMarried'    => $person['never_married'],
				'lungsod'         => $person['lungsod'],
				'occupation'      => $person['occupation'],
				'photo'           => $person['photo'],
				'generation'      => $state[ $id ]['generation'] ?? null,
				'branch'          => $state[ $id ]['branch'] ?? null,
				'marriedIn'       => $state[ $id ]['married_in'] ?? false,
				'partnerFamilies' => $partner_of[ $id ] ?? array(),
				'childFamilies'   => $child_of[ $id ] ?? array(),
			);
		}

		$families_out = array();
		foreach ( $families_by_id as $id => $family ) {
			$families_out[ $id ] = array(
				'id'              => $id,
				'partners'        => $family['partners'],
				'unionType'       => $family['union_type'],
				'status'          => $family['status'],
				'sequence'        => $family['sequence'],
				'children'        => array_map(
					static function ( array $child ): array {
						return array(
							'personId' => $child['person_id'],
							'relation' => $child['relation'],
						);
					},
					$family['children']
				),
				'childCount'      => count( $family['children'] ),
				'descendantCount' => count( self::descendants_of( $id, $families_by_id, $partner_of ) ),
			);
		}

		return array(
			'version'      => self::PAYLOAD_VERSION,
			'rootFamilyId' => $root_id,
			'branches'     => $branches,
			'people'       => $people_out,
			'families'     => $families_out,
		);
	}

	/**
	 * Key people by ID and fill in missing fields with defaults.
	 *
	 * @param array<int, array<string, mixed>> $people Raw people.
	 * @return array<int, array<string, mixed>>
	 */
	private static function index_people( array $people ): array {
		$indexed = array();
		foreach ( $people as $person ) {
			$id = isset( $person['id'] ) ? (int) $person['id'] : 0;
			if ( $id < 1 ) {
				continue;
			}
			$indexed[ $id ] = array_merge( self::PERSON_DEFAULTS, $person, array( 'id' => $id ) );
		}
		return $indexed;
	}

	/**
	 * Key families by ID and drop references to people who do not exist.
	 * A family with no valid partner is dropped; a person cannot be their own child.
	 *
	 * @param array<int, array<string, mixed>> $families     Raw families.
	 * @param array<int, array<string, mixed>> $people_by_id Indexed people.
	 * @return array<int, array<string, mixed>>
	 */
	private static function index_families( array $families, array $people_by_id ): array {
		$indexed = array();
		foreach ( $families as $family ) {
			$id = isset( $family['id'] ) ? (int) $family['id'] : 0;
			if ( $id < 1 ) {
				continue;
			}

			$partners = array();
			foreach ( (array) ( $family['partners'] ?? array() ) as $partner_id ) {
				$partner_id = (int) $partner_id;
				if ( isset( $people_by_id[ $partner_id ] ) && ! in_array( $partner_id, $partners, true ) ) {
					$partners[] = $partner_id;
				}
			}
			if ( array() === $partners ) {
				continue;
			}

			$children = array();
			foreach ( (array) ( $family['children'] ?? array() ) as $child ) {
				$child_id = isset( $child['person_id'] ) ? (int) $child['person_id'] : 0;
				if ( isset( $people_by_id[ $child_id ] ) && ! in_array( $child_id, $partners, true ) ) {
					$children[] = array(
						'person_id' => $child_id,
						'relation'  => (string) ( $child['relation'] ?? 'unknown' ),
					);
				}
			}

			$indexed[ $id ] = array(
				'id'         => $id,
				'partners'   => array_slice( $partners, 0, 2 ),
				'children'   => $children,
				'union_type' => (string) ( $family['union_type'] ?? 'unknown' ),
				'status'     => (string) ( $family['status'] ?? 'unknown' ),
				'sequence'   => (int) ( $family['sequence'] ?? 0 ),
			);
		}
		return $indexed;
	}

	/**
	 * For each person, the families they are a partner in, first marriage first.
	 *
	 * @param array<int, array<string, mixed>> $families_by_id Indexed families.
	 * @return array<int, int[]>
	 */
	private static function index_by_partner( array $families_by_id ): array {
		$map = array();
		foreach ( $families_by_id as $id => $family ) {
			foreach ( $family['partners'] as $partner_id ) {
				$map[ $partner_id ][] = $id;
			}
		}
		foreach ( $map as $person_id => $ids ) {
			usort(
				$ids,
				static function ( int $a, int $b ) use ( $families_by_id ): int {
					return array( $families_by_id[ $a ]['sequence'], $a ) <=> array( $families_by_id[ $b ]['sequence'], $b );
				}
			);
			$map[ $person_id ] = $ids;
		}
		return $map;
	}

	/**
	 * For each person, the families they are a child in.
	 *
	 * @param array<int, array<string, mixed>> $families_by_id Indexed families.
	 * @return array<int, int[]>
	 */
	private static function index_by_child( array $families_by_id ): array {
		$map = array();
		foreach ( $families_by_id as $id => $family ) {
			foreach ( $family['children'] as $child ) {
				$map[ $child['person_id'] ][] = $id;
			}
		}
		return $map;
	}

	/**
	 * Choose the root family: the one asked for, else the founding family
	 * (no partner is anyone's child) with the most descendants, lowest ID on a tie.
	 *
	 * @param array<int, array<string, mixed>> $families_by_id Indexed families.
	 * @param array<int, int[]>                $partner_of     Families by partner.
	 * @param array<int, int[]>                $child_of       Families by child.
	 * @param int|null                         $requested      Requested root family ID.
	 */
	private static function pick_root( array $families_by_id, array $partner_of, array $child_of, ?int $requested ): ?int {
		if ( null !== $requested && isset( $families_by_id[ $requested ] ) ) {
			return $requested;
		}

		$best       = null;
		$best_count = 0;
		foreach ( $families_by_id as $id => $family ) {
			$is_founding = true;
			foreach ( $family['partners'] as $partner_id ) {
				if ( isset( $child_of[ $partner_id ] ) ) {
					$is_founding = false;
				}
			}
			if ( ! $is_founding ) {
				continue;
			}

			$count = count( self::descendants_of( $id, $families_by_id, $partner_of ) );
			if ( $count > $best_count ) {
				$best       = $id;
				$best_count = $count;
			}
		}
		return $best;
	}

	/**
	 * Everyone descended from a family through its children, once each. Married-in
	 * partners are not descendants. Safe on bad data: each family is visited once.
	 *
	 * @param int                              $family_id      Starting family.
	 * @param array<int, array<string, mixed>> $families_by_id Indexed families.
	 * @param array<int, int[]>                $partner_of     Families by partner.
	 * @return array<int, bool> Person IDs as keys.
	 */
	private static function descendants_of( int $family_id, array $families_by_id, array $partner_of ): array {
		$found = array();
		$seen  = array( $family_id => true );
		$stack = array( $family_id );

		while ( array() !== $stack ) {
			$current = array_pop( $stack );
			foreach ( $families_by_id[ $current ]['children'] as $child ) {
				$child_id           = $child['person_id'];
				$found[ $child_id ] = true;
				foreach ( $partner_of[ $child_id ] ?? array() as $next ) {
					if ( ! isset( $seen[ $next ] ) ) {
						$seen[ $next ] = true;
						$stack[]       = $next;
					}
				}
			}
		}
		return $found;
	}

	/**
	 * Assign generation, branch and married-in to everyone reachable from the root family.
	 *
	 * @param int                              $root_id        Root family ID.
	 * @param array<int, array<string, mixed>> $families_by_id Indexed families.
	 * @return array{0: array<int, array<string, mixed>>, 1: int[]} State by person, and branch root IDs in order.
	 */
	private static function assign( int $root_id, array $families_by_id ): array {
		$state      = array();
		$branch_ids = array();
		$root       = $families_by_id[ $root_id ];

		foreach ( $root['partners'] as $partner_id ) {
			$state[ $partner_id ] = array(
				'generation' => 0,
				'branch'     => null,
				'married_in' => false,
			);
		}
		foreach ( $root['children'] as $child ) {
			$child_id = $child['person_id'];
			if ( ! isset( $state[ $child_id ] ) ) {
				$state[ $child_id ] = array(
					'generation' => 1,
					'branch'     => $child_id,
					'married_in' => false,
				);
				$branch_ids[]       = $child_id;
			}
		}

		$processed = array( $root_id => true );

		while ( true ) {
			$next_id = self::next_family( $families_by_id, $state, $processed );
			if ( null === $next_id ) {
				break;
			}
			$processed[ $next_id ] = true;
			self::process_family( $families_by_id[ $next_id ], $state, $branch_ids );
		}

		return array( $state, $branch_ids );
	}

	/**
	 * The next unprocessed family that has at least one assigned partner, shallowest
	 * generation first, so a person's own parents are always handled before their
	 * own family.
	 *
	 * @param array<int, array<string, mixed>> $families_by_id Indexed families.
	 * @param array<int, array<string, mixed>> $state          Assignments so far.
	 * @param array<int, bool>                 $processed      Families already handled.
	 */
	private static function next_family( array $families_by_id, array $state, array $processed ): ?int {
		$best     = null;
		$best_gen = PHP_INT_MAX;

		foreach ( $families_by_id as $id => $family ) {
			if ( isset( $processed[ $id ] ) ) {
				continue;
			}
			$gens = array();
			foreach ( $family['partners'] as $partner_id ) {
				if ( isset( $state[ $partner_id ] ) ) {
					$gens[] = $state[ $partner_id ]['generation'];
				}
			}
			if ( array() === $gens ) {
				continue;
			}
			$gen = min( $gens );
			if ( $gen < $best_gen ) {
				$best     = $id;
				$best_gen = $gen;
			}
		}
		return $best;
	}

	/**
	 * Assign the unassigned partners (married in) and children of one family.
	 * Children take the branch of the first listed partner that has one. Children
	 * of a founder with no branch (a remarriage of the root couple) start new branches.
	 *
	 * @param array<string, mixed>             $family     The family.
	 * @param array<int, array<string, mixed>> $state      Assignments, updated in place.
	 * @param int[]                            $branch_ids Branch roots, updated in place.
	 */
	private static function process_family( array $family, array &$state, array &$branch_ids ): void {
		$anchor = null;
		foreach ( $family['partners'] as $partner_id ) {
			if ( isset( $state[ $partner_id ] ) ) {
				$anchor = $state[ $partner_id ];
				break;
			}
		}
		if ( null === $anchor ) {
			return;
		}

		foreach ( $family['partners'] as $partner_id ) {
			if ( ! isset( $state[ $partner_id ] ) ) {
				$state[ $partner_id ] = array(
					'generation' => $anchor['generation'],
					'branch'     => $anchor['branch'],
					'married_in' => true,
				);
			}
		}

		$generations = array();
		$branch      = null;
		foreach ( $family['partners'] as $partner_id ) {
			$generations[] = $state[ $partner_id ]['generation'];
			if ( null === $branch && null !== $state[ $partner_id ]['branch'] ) {
				$branch = $state[ $partner_id ]['branch'];
			}
		}
		$min_generation = min( $generations );

		foreach ( $family['children'] as $child ) {
			$child_id = $child['person_id'];
			if ( isset( $state[ $child_id ] ) ) {
				continue;
			}

			$starts_branch      = ( null === $branch && 0 === $min_generation );
			$state[ $child_id ] = array(
				'generation' => $min_generation + 1,
				'branch'     => $starts_branch ? $child_id : $branch,
				'married_in' => false,
			);
			if ( $starts_branch ) {
				$branch_ids[] = $child_id;
			}
		}
	}

	/**
	 * Name shown on the tree: the title, else the nickname, else given name plus surname.
	 * May be empty for a person whose name is unknown; the app shows its own label then.
	 *
	 * @param array<string, mixed> $person Indexed person.
	 */
	private static function name( array $person ): string {
		$title = trim( (string) $person['display_name'] );
		if ( '' !== $title ) {
			return $title;
		}

		$nickname = trim( (string) $person['nickname'] );
		if ( '' !== $nickname ) {
			return $nickname;
		}

		$surname = trim( (string) $person['surname_married'] );
		if ( '' === $surname ) {
			$surname = trim( (string) $person['surname_birth'] );
		}
		return trim( $person['given_name'] . ' ' . $surname );
	}
}
