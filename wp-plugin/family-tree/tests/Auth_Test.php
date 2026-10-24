<?php
/**
 * Tests for the API key check.
 *
 * @package FamilyTree
 */

namespace FamilyTree\Tests;

use FamilyTree\Auth;
use PHPUnit\Framework\TestCase;

/**
 * The key check is the only thing between the internet and the family data.
 */
final class Auth_Test extends TestCase {

	private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/**
	 * The right key opens the door.
	 */
	public function test_matching_key_is_accepted(): void {
		$this->assertTrue( Auth::matches( self::KEY, self::KEY ) );
	}

	/**
	 * A wrong key, an empty key, and a near miss are all refused.
	 */
	public function test_wrong_keys_are_refused(): void {
		$this->assertFalse( Auth::matches( 'wrong', self::KEY ) );
		$this->assertFalse( Auth::matches( '', self::KEY ) );
		$this->assertFalse( Auth::matches( substr( self::KEY, 0, -1 ), self::KEY ) );
		$this->assertFalse( Auth::matches( self::KEY . 'a', self::KEY ) );
	}

	/**
	 * Anything that is not a string is refused, for example a missing header.
	 */
	public function test_non_string_keys_are_refused(): void {
		$this->assertFalse( Auth::matches( null, self::KEY ) );
		$this->assertFalse( Auth::matches( array( self::KEY ), self::KEY ) );
		$this->assertFalse( Auth::matches( 12345, self::KEY ) );
	}

	/**
	 * If the server key is empty or too short, nothing opens, not even an empty guess.
	 */
	public function test_weak_configured_key_never_matches(): void {
		$this->assertFalse( Auth::matches( '', '' ) );
		$this->assertFalse( Auth::matches( 'short', 'short' ) );
		$this->assertFalse( Auth::matches( 'x', null ) );
		$this->assertFalse( Auth::is_strong_key( str_repeat( 'a', 31 ) ) );
		$this->assertTrue( Auth::is_strong_key( str_repeat( 'a', 32 ) ) );
	}
}
