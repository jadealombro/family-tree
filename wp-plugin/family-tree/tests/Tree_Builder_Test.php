<?php
/**
 * Tests for the tree builder, using an invented family.
 *
 * @package FamilyTree
 */

namespace FamilyTree\Tests;

use FamilyTree\Tree_Builder;
use PHPUnit\Framework\TestCase;

/**
 * The invented family (no real names):
 *
 *   Family 100: Lola (1) + Lolo (2) -> Ana (3), Ben (4), Cora (5)
 *   Family 101: Ana + Dan (6, married in) -> Eli (7), Fia (8)
 *   Family 102: Ben + Gia (9) -> Hal (10)
 *   Family 103: Ben + Ida (11), second marriage -> Jun (12)   (half-siblings Hal and Jun)
 *   Family 104: Eli + Jun, cousins who married -> Kai (13)
 *   Family 105: Fia alone, other parent unknown -> three unnamed children (14, 15, 16)
 *   Family 106: Outsiders (17, 18), unconnected to the tree
 *   Family 107: bad data, Kai listed as a parent of his own ancestor Ana (cycle)
 *   Family 108: Lolo's second marriage to Mira (19) -> Nico (20)
 */
final class Tree_Builder_Test extends TestCase {

	/**
	 * Build a raw person.
	 *
	 * @param int                  $id    Person ID.
	 * @param string               $name  Display name.
	 * @param array<string, mixed> $extra Extra fields.
	 * @return array<string, mixed>
	 */
	private function person( int $id, string $name, array $extra = array() ): array {
		return array_merge(
			array(
				'id'           => $id,
				'display_name' => $name,
			),
			$extra
		);
	}

	/**
	 * Build a raw family.
	 *
	 * @param int                  $id       Family ID.
	 * @param int[]                $partners Partner IDs in order.
	 * @param int[]                $children Child IDs in birth order.
	 * @param array<string, mixed> $extra    Extra fields.
	 * @return array<string, mixed>
	 */
	private function family( int $id, array $partners, array $children, array $extra = array() ): array {
		return array_merge(
			array(
				'id'       => $id,
				'partners' => $partners,
				'children' => array_map(
					static function ( int $child_id ): array {
						return array(
							'person_id' => $child_id,
							'relation'  => 'biological',
						);
					},
					$children
				),
			),
			$extra
		);
	}

	/**
	 * The invented people.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function people(): array {
		return array(
			$this->person( 1, 'Lola' ),
			$this->person( 2, 'Lolo' ),
			$this->person( 3, 'Ana' ),
			$this->person( 4, 'Ben' ),
			$this->person( 5, 'Cora', array( 'never_married' => true ) ),
			$this->person( 6, 'Dan' ),
			$this->person( 7, 'Eli' ),
			$this->person( 8, 'Fia' ),
			$this->person( 9, 'Gia' ),
			$this->person( 10, 'Hal' ),
			$this->person( 11, 'Ida' ),
			$this->person( 12, 'Jun' ),
			$this->person( 13, 'Kai' ),
			$this->person( 14, '', array( 'name_unknown' => true ) ),
			$this->person( 15, '', array( 'name_unknown' => true ) ),
			$this->person( 16, '', array( 'name_unknown' => true ) ),
			$this->person( 17, 'Outsider One' ),
			$this->person( 18, 'Outsider Two' ),
			$this->person( 19, 'Mira' ),
			$this->person( 20, 'Nico' ),
		);
	}

	/**
	 * The invented families.
	 *
	 * @param int[] $cousins Partner order for the cousin couple in family 104.
	 * @return array<int, array<string, mixed>>
	 */
	private function families( array $cousins = array( 7, 12 ) ): array {
		return array(
			$this->family( 100, array( 1, 2 ), array( 3, 4, 5 ), array( 'sequence' => 1 ) ),
			$this->family( 101, array( 3, 6 ), array( 7, 8 ) ),
			$this->family( 102, array( 4, 9 ), array( 10 ), array( 'sequence' => 1 ) ),
			$this->family( 103, array( 4, 11 ), array( 12 ), array( 'sequence' => 2 ) ),
			$this->family( 104, $cousins, array( 13 ) ),
			$this->family( 105, array( 8 ), array( 14, 15, 16 ) ),
			$this->family( 106, array( 17, 18 ), array() ),
			$this->family( 107, array( 13 ), array( 3 ) ),
			$this->family( 108, array( 2, 19 ), array( 20 ), array( 'sequence' => 2 ) ),
		);
	}

	/**
	 * Build the default payload.
	 *
	 * @return array<string, mixed>
	 */
	private function payload(): array {
		return Tree_Builder::build( $this->people(), $this->families() );
	}

	/**
	 * The founding family with the most descendants becomes the root.
	 */
	public function test_root_is_the_largest_founding_family(): void {
		$this->assertSame( 100, $this->payload()['rootFamilyId'] );
	}

	/**
	 * A requested root family overrides the automatic choice.
	 */
	public function test_requested_root_is_used(): void {
		$payload = Tree_Builder::build( $this->people(), $this->families(), 101 );

		$this->assertSame( 101, $payload['rootFamilyId'] );
		$this->assertSame( array( 7, 8 ), array_column( $payload['branches'], 'id' ) );
	}

	/**
	 * Generations count down the tree; people who married in share their partner's.
	 */
	public function test_generations(): void {
		$people = $this->payload()['people'];

		$this->assertSame( 0, $people[1]['generation'] );
		$this->assertSame( 1, $people[3]['generation'] );
		$this->assertSame( 1, $people[6]['generation'], 'Dan married in beside Ana.' );
		$this->assertSame( 2, $people[7]['generation'] );
		$this->assertSame( 3, $people[13]['generation'] );
		$this->assertSame( 3, $people[14]['generation'] );
	}

	/**
	 * Each of the root's children starts a branch; descendants and in-laws follow it.
	 */
	public function test_branches(): void {
		$payload = $this->payload();
		$people  = $payload['people'];

		$this->assertSame( array( 3, 4, 5, 20 ), array_column( $payload['branches'], 'id' ), 'Nico, child of the second marriage, starts a branch.' );
		$this->assertSame( 3, $people[3]['branch'] );
		$this->assertSame( 3, $people[6]['branch'], 'Dan takes his partner\'s branch.' );
		$this->assertSame( 3, $people[14]['branch'] );
		$this->assertSame( 4, $people[10]['branch'] );
		$this->assertSame( 4, $people[12]['branch'] );
		$this->assertSame( 20, $people[20]['branch'] );
	}

	/**
	 * People who married in are flagged; blood relatives are not.
	 */
	public function test_married_in_flag(): void {
		$people = $this->payload()['people'];

		$this->assertTrue( $people[6]['marriedIn'] );
		$this->assertTrue( $people[19]['marriedIn'] );
		$this->assertFalse( $people[3]['marriedIn'] );
		$this->assertFalse( $people[7]['marriedIn'] );
	}

	/**
	 * A child of cousins takes the branch of the first listed partner.
	 */
	public function test_cousin_marriage_follows_partner_order(): void {
		$first   = Tree_Builder::build( $this->people(), $this->families( array( 7, 12 ) ) );
		$flipped = Tree_Builder::build( $this->people(), $this->families( array( 12, 7 ) ) );

		$this->assertSame( 3, $first['people'][13]['branch'] );
		$this->assertSame( 4, $flipped['people'][13]['branch'] );
	}

	/**
	 * A remarriage gives half-siblings and orders a person's families first-to-last.
	 */
	public function test_remarriage_and_half_siblings(): void {
		$people = $this->payload()['people'];

		$this->assertSame( array( 102, 103 ), $people[4]['partnerFamilies'] );
		$this->assertSame( array( 102 ), $people[10]['childFamilies'] );
		$this->assertSame( array( 103 ), $people[12]['childFamilies'] );
	}

	/**
	 * Child counts and descendant counts, with married-in partners not counted.
	 */
	public function test_counts(): void {
		$families = $this->payload()['families'];

		$this->assertSame( 3, $families[100]['childCount'] );
		$this->assertSame( 11, $families[100]['descendantCount'] );
		$this->assertSame( 3, $families[105]['childCount'] );
		$this->assertSame( 0, $families[106]['childCount'] );
	}

	/**
	 * Birth order of children is kept exactly as given.
	 */
	public function test_children_keep_birth_order(): void {
		$children = $this->payload()['families'][100]['children'];

		$this->assertSame( array( 3, 4, 5 ), array_column( $children, 'personId' ) );
	}

	/**
	 * People not connected to the root have no generation or branch.
	 */
	public function test_unconnected_people_have_no_branch(): void {
		$people = $this->payload()['people'];

		$this->assertNull( $people[17]['branch'] );
		$this->assertNull( $people[17]['generation'] );
	}

	/**
	 * Bad data that makes a person their own ancestor must not hang or crash.
	 */
	public function test_cycle_in_bad_data_terminates(): void {
		$payload = $this->payload();

		$this->assertSame( 1, $payload['families'][107]['childCount'] );
		$this->assertSame( 1, $payload['people'][3]['generation'], 'Ana keeps her real generation.' );
	}

	/**
	 * Unknown people in a family are dropped, and a family with no valid partner is dropped.
	 */
	public function test_unknown_references_are_dropped(): void {
		$payload = Tree_Builder::build(
			array( $this->person( 1, 'Only' ) ),
			array(
				$this->family( 1, array( 1, 99 ), array( 98 ) ),
				$this->family( 2, array( 97 ), array( 1 ) ),
			)
		);

		$this->assertSame( array( 1 ), $payload['families'][1]['partners'] );
		$this->assertSame( 0, $payload['families'][1]['childCount'] );
		$this->assertArrayNotHasKey( 2, $payload['families'] );
	}

	/**
	 * An empty database produces an empty but valid payload.
	 */
	public function test_empty_input(): void {
		$payload = Tree_Builder::build( array(), array() );

		$this->assertNull( $payload['rootFamilyId'] );
		$this->assertSame( array(), $payload['branches'] );
		$this->assertSame( array(), $payload['people'] );
	}

	/**
	 * Names fall back from title to nickname to given name plus surname.
	 */
	public function test_name_fallbacks(): void {
		$payload = Tree_Builder::build(
			array(
				$this->person( 1, '', array( 'nickname' => 'Bebot' ) ),
				$this->person(
					2,
					'',
					array(
						'given_name'      => 'Nena',
						'surname_birth'   => 'Cruz',
						'surname_married' => 'Lim',
					)
				),
				$this->person( 3, '' ),
			),
			array()
		);

		$this->assertSame( 'Bebot', $payload['people'][1]['name'] );
		$this->assertSame( 'Nena Lim', $payload['people'][2]['name'] );
		$this->assertSame( '', $payload['people'][3]['name'] );
	}

	/**
	 * Guard rail against leaks: a person in the payload has exactly these keys.
	 * Adding a field must be a deliberate change to this test, so private data such
	 * as dates or notes cannot slip into the public payload by accident.
	 */
	public function test_person_keys_are_an_exact_allow_list(): void {
		$people = $this->payload()['people'];

		$this->assertSame(
			array(
				'id',
				'name',
				'nickname',
				'givenName',
				'surnameBirth',
				'surnameMarried',
				'nameUnknown',
				'isLiving',
				'neverMarried',
				'lungsod',
				'occupation',
				'photo',
				'generation',
				'branch',
				'marriedIn',
				'partnerFamilies',
				'childFamilies',
			),
			array_keys( $people[3] )
		);
	}

	/**
	 * Private fields passed in by mistake are not copied to the output.
	 */
	public function test_private_fields_never_reach_the_payload(): void {
		$payload = Tree_Builder::build(
			array(
				$this->person(
					1,
					'Test',
					array(
						'birth_date' => '1950-01-01',
						'notes'      => 'secret',
					)
				),
			),
			array()
		);

		$json = (string) wp_json_encode( $payload );
		$this->assertStringNotContainsString( '1950-01-01', $json );
		$this->assertStringNotContainsString( 'secret', $json );
	}
}
