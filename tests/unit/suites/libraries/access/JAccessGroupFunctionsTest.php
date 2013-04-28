<?php
/**
 * @package	    Joomla.UnitTest
 * @subpackage  Menu
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license	    GNU General Public License version 2 or later; see LICENSE
 */

jimport('joomla.user.user');
jimport('joomla.user.helper');


// Define constants for the groups
if (!defined('GPUBLIC'))
{
	define('GPUBLIC',      1);   // PUBLIC is a PHP keyword
	define('REGISTERED',   2);
	define('AUTHOR',       3);
	define('EDITOR',       4);
	define('PUBLISHER',    5);
	define('MANAGER',      6);
	define('ADMIN',        7);
	define('SUPER_USER',   8);
	define('INV_AUTHOR',  10);
	define('CUSTOMER',    12);
	define('GUEST',       13);
}


/**
 * Test class for JMenuSite.
 * Generated by PHPUnit on 2012-07-26 at 20:37:58.
 */
class JAccessGroupFunctions extends TestCaseDatabase
{
	/**
	 * @var default groups from test DB file JAccessTest.xml
	 */
	protected $groups = Array( GPUBLIC     => 'Public',
							   MANAGER     =>     'Manager',
							   ADMIN       =>     'Administrator',
							   GUEST       =>     'Guest',
							   REGISTERED  =>     'Registered',
							   CUSTOMER    =>         'Customer Group',
 							   AUTHOR      =>         'Author',
							   INV_AUTHOR  =>             'Invoice Author',
							   EDITOR      =>             'Editor',
							   PUBLISHER   =>                 'Publisher',
							   SUPER_USER  =>     'Super Users',
							   );

	/**
	 * @var core actions
	 */
	protected $core_actions = Array( "core.admin", "core.manage",
									 "core.create", "core.delete",
									 "core.edit", "core.edit.own", "core.edit.state" );

	/**
	 * @var string representation of default root rule
	 */
	protected $reference_root_rule = '{"core.login.site":{"6":1,"2":1},"core.login.admin":{"6":1},"core.admin":{"8":1},"core.manage":{"7":1},"core.create":{"6":1,"3":1},"core.delete":{"6":1},"core.edit":{"6":1,"4":1},"core.edit.state":{"6":1,"5":1},"core.edit.own":{"6":1,"3":1}}';


	/**
	 * Gets the data set to be loaded into the database during setup
	 */
	protected function getDataSet()
	{
		return $this->createXMLDataSet(__DIR__ . '/data/JAccessTest.xml');
	}


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		parent::setUp();

		// Get the mocks
		$this->saveFactoryState();
		JFactory::$session = $this->getMockSession();
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
	}

	
	/**
	 * Verifies that we have the expected groups
	 *
	 * This function also tests the getGroupId function
	 */
	public function testVerifyGroups()
	{
		foreach ($this->groups as $gid => $group_name)
		{
			$group_id = JAccess::getGroupId($group_name);
			$this->assertEquals($gid, $group_id, "Failed group ID round trip test (id $gid: $group_name)" );
		}
	}


	/**
	 * Test JAccess::removeDescendentGroups($groups)
	 */
	public function testRemoveDescendentGroups()
	{
		// GPUBLIC(1)     => 'Public',
		// MANAGER(6)     =>     'Manager',
		// ADMIN(7)       =>     'Administrator',
		// GUEST(13)      =>     'Guest',
		// REGISTERED(2)  =>     'Registered',
		// CUSTOMER(12)   =>         'Customer Group',
		// AUTHOR(3)      =>         'Author',
		// INV_AUTHOR(10) =>             'Invoice Author',
		// EDITOR(4)      =>             'Editor',
		// PUBLISHER(5)   =>                 'Publisher',
		// SUPER_USER(8)  =>     'Super Users',

		// Test 10
		$result = JAccess::removeDescendentGroups(Array(AUTHOR, EDITOR, PUBLISHER));
		$errmsg = "[Test 10] Given (3, 4, 5), expected (3) but got (" . implode(', ', $result) . "). \n" .
			"(Expected only AUTHOR(3) since EDITOR(4) and PUBLISHER(5) are its descendents.)";
		$this->assertEquals(Array(AUTHOR), $result, $errmsg);

		// Test 11
		$result = JAccess::removeDescendentGroups(Array(AUTHOR, PUBLISHER));
		$errmsg = "[Test 11] Given (3, 5), expected (3) but got (" . implode(', ', $result) . "). \n" .
			"(Expected only AUTHOR(3) since PUBLISHER(5) is its descendent.)";
		$this->assertEquals(Array(AUTHOR), $result, $errmsg);

		// Test 12
		$result = JAccess::removeDescendentGroups(Array(AUTHOR, INV_AUTHOR, MANAGER, PUBLISHER));
		$errmsg = "[Test 12] Given (3, 10, 6, 5), expected (3, 6) but got (" . implode(', ', $result) . "). \n" .
			"(Expected only (AUTHOR(3), MANAGER(6)) since the rest are thier descendents.)";
		$this->assertEquals(Array(AUTHOR, MANAGER), $result, $errmsg);

		// Test 13
		$result = JAccess::removeDescendentGroups(Array(GPUBLIC, AUTHOR, INV_AUTHOR, MANAGER, PUBLISHER));
		$errmsg = "[Test 13] Given (1, 3, 10, 6, 5), expected (1) but got (" . implode(', ', $result) . "). \n" .
			"(Expected only (PUBLIC(1)) since the rest are its descendents.)";
		$this->assertEquals(Array(GPUBLIC), $result, $errmsg);

		// Test 14
		$result = JAccess::removeDescendentGroups(Array(GUEST, CUSTOMER, AUTHOR, EDITOR, PUBLISHER, ADMIN));
		$errmsg = "[Test 14] Given (13, 12, 3, 4, 5, 7), expected (3, 7, 12, 13) but got (" . implode(', ', $result) . "). \n" .
			"(Expected only (3,7,12,13) since EDITOR(4) and PUBLISHER(5) are descendents of AUTHOR(3).)";
		$this->assertEquals(Array(AUTHOR, ADMIN, CUSTOMER, GUEST), $result, $errmsg);
	}

	/**
	 * Test JAccess::leastAuthoritativeGroup($groups, $asset)
	 */
	public function testLeastAuthoritativeGroup()
	{
		// GPUBLIC(1)     => 'Public',
		// MANAGER(6)     =>     'Manager',
		// ADMIN(7)       =>     'Administrator',
		// GUEST(13)      =>     'Guest',
		// REGISTERED(2)  =>     'Registered',
		// CUSTOMER(12)   =>         'Customer Group',
		// AUTHOR(3)      =>         'Author',
		// INV_AUTHOR(10) =>             'Invoice Author',
		// EDITOR(4)      =>             'Editor',
		// PUBLISHER(5)   =>                 'Publisher',
		// SUPER_USER(8)  =>     'Super Users',

		// Test 20
		$result = JAccess::leastAuthoritativeGroup(Array(AUTHOR, EDITOR, PUBLISHER));
		$errmsg = "[Test 20] Given (3, 4, 5), expected (3) but got ($result). \n" .
			"(Expected AUTHOR(3) since EDITOR(4) and PUBLISHER(5) are its descendents.)";
		$this->assertEquals(AUTHOR, $result, $errmsg);

		// Test 21
		$result = JAccess::leastAuthoritativeGroup(Array(ADMIN, SUPER_USER));
		$errmsg = "[Test 21] Given (7, 8), expected (7) but got ($result). \n" .
			"(Expected ADMIN(7) since SUPER_USER(8) has more authority, usually.)";
		$this->assertEquals(ADMIN, $result, $errmsg);

		// Test 22
		$result = JAccess::leastAuthoritativeGroup(Array(ADMIN, SUPER_USER, EDITOR));
		$errmsg = "[Test 22] Given (7, 8, 4), expected (4) but got ($result). \n" .
			"(Expected EDITOR(4) because we prefer normal to ADMIN(7) or SUPER_USER(8).)";
		$this->assertEquals(EDITOR, $result, $errmsg);

		// Test 23
		$result = JAccess::leastAuthoritativeGroup(Array(MANAGER, GUEST, CUSTOMER, PUBLISHER, AUTHOR, EDITOR));
		$errmsg = "[Test 23] Given (6, 13, 12, 5, 3, 4), expected CUSTOMER(12) but got ($result). \n" .
			"(Expected CUSTOMER(12) because both CUSTOMER(12) and GUEST(13) " .
			"have no privileges, so choose lowest number.)";
		$this->assertEquals(CUSTOMER, $result, $errmsg);

		// Test 24
		$result = JAccess::leastAuthoritativeGroup(Array(MANAGER, ADMIN, GUEST, REGISTERED, SUPER_USER));
		$errmsg = "[Test 24] Given (6, 7, 12, 2, 8), expected REGISTERED(2) but got ($result). \n" .
			"(Expected REGISTERED(2) because both REGISTERED(2) and GUEST(13) " .
			"have no privileges, so choose lowest number.)";
		$this->assertEquals(REGISTERED, $result, $errmsg);
	}

}