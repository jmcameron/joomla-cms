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


/**
 * Test class for JMenuSite.
 * Generated by PHPUnit on 2012-07-26 at 20:37:58.
 */
class JAccessDefaultRulesTest extends TestCaseDatabase
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
	 * Get a copy of the root/default access rule
	 *
	 * @return JAccessRules a copy of the root/default access rule
	 */
	protected function getDefaultRule()
	{
		// Save a copy of the root rule for reference
		$root = JTable::getInstance('asset');
		$root->loadByName('root.1');
		return new JAccessRules($root->rules);
	}


	/**
	 * Get the group IDs of all groups allowed to do an action by default
	 *
	 * @param string  $action name of the action to check (eg, 'example.docustom')
	 */
	protected function getDefaultGroupsForAction($action)
	{
		// Get the root rules
		$root = JTable::getInstance('asset');
		$root->loadByName('root.1');
		$root_rules = new JAccessRules($root->rules);
		if ( array_key_exists($action, $root_rules->getData()) )
		{
			$data = $root_rules->getData();
			$groups = array_keys(get_object_vars(json_decode($data[$action])));
			return $groups;
		}
		else
		{
			return Array();
		}
	}

	/**
	 * Get the current default permissions for a set of actions
	 */
	protected function getDefaultPermissions($actions)
	{
		// Force JAccess to throw away its cache and recompute the rules
		JAccess::clearStatics();
		
		$perms = Array();

		foreach ($actions as $action)
		{
			$perms[$action] = Array();

			foreach ($this->groups as $gid => $group_name)
			{
				$perms[$action][$gid] = (boolean)JAccess::checkGroup($gid, $action, 'com_example');
			}
		}

		return $perms;
	}


	/**
	 * Check the permissions to make sure that the modified values match the
	 * expected values for a specific action.
	 *
	 * @param string  $test name of the test to put into the error message
	 * @param string  $action name of the action to check (eg, 'example.docustom')
	 * @param array   $expected of expected permissions ( each entry is: groupid => boolean )
	 * @param array   $modified of permissions from loaded access.xml file ( each entry is: groupid => boolean )
	 *
	 * No return value
	 */
	protected function checkPermissions($test, $action, $expected, $modified)
	{
		foreach ($this->groups as $gid => $group_name)
		{
			$perm_mod = $modified[$action][$gid];
			$perm_exp = $expected[$gid];
			$errmsg = "[$test] Unexpected permission for custom action '$action' for group '$group_name':";
			$this->assertEquals($perm_exp, $perm_mod, $errmsg);
		}
	}


	/**
	 * Check that the correct groups have permission to an action
	 *
	 * @param string  $test name of the test to put into the error message
	 * @param string  $action name of the action to check (eg, 'example.docustom')
	 * @param array   $expected_groups id or array of IDs of expected groups (single group ID or array of group IDs)
	 *
	 * No return value
	 */
	protected function checkGroups($test, $action, $expected_groups)
	{
		// Process the expected groups
		if (!is_array($expected_groups))
		{
			$expected_groups = Array($expected_groups);
		}
		$num_expected = count($expected_groups);
		$expected_group_names = Array();
		foreach ($expected_groups as $grp)
		{
			$expected_group_names[] = $this->groups[$grp] . "($grp)";
		}

		// Get the groups allowed
		$allowed_groups = $this->getDefaultGroupsForAction($action);
		$num_allowed = count($allowed_groups);
		$allowed_group_names = Array();
		foreach ($allowed_groups as $grp)
		{
			$allowed_group_names[] = $this->groups[$grp] . "($grp)";
		}

		// Verify the right number of groups 
		$errmsg = "[$test] Only expected $num_expected group(s) to have permission to do action '$action' " .
			      "for 'com_example', got " . $num_allowed . ".";
		$this->assertEquals($num_expected, $num_allowed, $errmsg);

		// Verify the groups
		$errmsg = "[$test] Selected group for action '$action' should have been " .
			      implode(', ', $expected_group_names) . " but is " .
			      implode(', ', $allowed_group_names) . ".";
		$this->assertEquals(array_diff($expected_groups, $allowed_groups), Array(), $errmsg);
	}

	
	/**
	 * Verifies that we have the expected groups
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
	 * Verify that the root rule loaded from the test database is what we expect
	 */
	public function testVerifyRootRule()
	{
		$this->assertEquals((string)$this->getDefaultRule(),
							$this->reference_root_rule,
							'Default rules do not match expected value');
	}



	/**
	 * testInstallRules1 - Normal cases
	 *
	 * Note: Uses basically the same rule set as in the test component (com_permtest).
	 */
	public function testInstallAccess1()
	{
		// Lists of the actions to check
		$custom_actions = Array( "example.default",
								 "example.custom.author1",
								 "example.custom.author2",
								 "example.custom.editor1",
								 "example.custom.editor2",
								 "example.custom.publisher1",
								 "example.custom.publisher2",
								 "example.custom.manager1",
								 "example.custom.manager2",
								 "example.custom.authmanage1",
								 "example.custom.authmanage2",
								 "example.custom.administrator1",
								 "example.custom.administrator2",
								 "example.custom.superuser1",
								 "example.custom.superuser2",
								 "example.custom.editor3",
								 );
		$all_actions = array_merge( $this->core_actions, $custom_actions );

		// Get the pristine, unmodified default permissions
		$initial_permissions = $this->getDefaultPermissions($all_actions);

		// Verify that the defaults for all the custom rules are 'denied'
		foreach ($custom_actions as $action)
		{
			foreach ($this->groups as $gid => $group_name)
			{
				$perm_init = $initial_permissions[$action][$gid];
				$errmsg = "Custom rule '$action' for group '$group_name' defaulted to true before installing custom defaults";
				$this->assertFalse($perm_init, $errmsg);
			}
		}

		// First install the rules for the other component (com_test)
		$install_ok = JAccess::installComponentDefaultRules('com_test', __DIR__ . '/data/access_test.xml');
		$errmsg = 'Problem installing component default rules from data/access_test.xml';
		$this->assertTrue($install_ok, $errmsg);
		// Make sure it has editor permissions
		$errmsg .= "\n (Editor does not have requested permission for action 'core.edit' on component 'com_test')";
		$this->assertTrue(JAccess::checkGroup(EDITOR, 'core.edit', 'com_test'), $errmsg);

		// Install the new default rules form access1.xml
		$install_ok = JAccess::installComponentDefaultRules('com_example', __DIR__ . '/data/access1.xml');
		$this->assertTrue($install_ok, 'Problem installing component default rules from data/access1.xml');

		// Get the modified permissions
		$modified_permissions = $this->getDefaultPermissions($all_actions);

		// None of the core rules should have changed
		foreach ($this->core_actions as $action)
		{
			foreach ($this->groups as $gid => $group_name)
			{
				$perm_init = $initial_permissions[$action][$gid];
				$perm_mod  = $modified_permissions[$action][$gid];
				$errmsg = "Core rule '$action' for group '$group_name' modified by installing 'access1.xml'";
				$this->assertEquals($perm_init, $perm_mod, $errmsg);
			}
		}
 
 		//------------------------------------------------------------
 		// Test 1: Verify the default rule is denied for all
 		//
 		// No default given in access xml file
 		//
 		$action = 'example.default';
 		foreach ($this->groups as $gid => $group_name)
 		{
			$perm_init = $initial_permissions[$action][$gid];
			$errmsg1 = "[Test 1] Unmodified custom action '$action' is allowed for group '$group_name'";
			$this->assertEquals($perm_init, false, $errmsg1);
			$perm_mod  = $modified_permissions[$action][$gid];
			$errmsg2 = "[Test 1] Default custom action '$action' for group '$group_name' modified by installing 'access1.xml'";
			$this->assertEquals($perm_init, $perm_mod, $errmsg2);

			// Double-check the group (should be null because there is no rule)
			$allowed_groups = $this->getDefaultGroupsForAction($action);
			if ( count($allowed_groups) != 0 )
			{
				$errmsg3 = "[Test 1] Unmodified custom action '$action' is allowed for groups: " . implode(', ', $allowed_groups);
				$this->assertNull($allowed_groups, $errmsg3);
			}
 		}

		//----------------------------------------------------------------------
		// Test 2: Verify enabling permission for author (has to figure it out)
		//
		// In the XML file:  default="com_content:core.create"
		//
		$test = 'Test 2';
		$action = 'example.custom.author1';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      =>  true,  //         Author (Should choose this)
		    INV_AUTHOR  =>  true,  //             Invoice Author (inherits from Author)
			EDITOR      =>  true,  //             Editor (inherits from Author)
			PUBLISHER   =>  true,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, AUTHOR);

		//------------------------------------------------------------
		// Test 3: Verify enabling permission for author (with suggested group)
		//
		// In the XML file:  default="com_content:core.create[Author]"
		//
		$test = 'test 3';
		$action = 'example.custom.author2';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      =>  true,  //         Author (Should choose this)
		    INV_AUTHOR  =>  true,  //             Invoice Author (inherits from Author)
			EDITOR      =>  true,  //             Editor (inherits from Author)
			PUBLISHER   =>  true,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, AUTHOR);

		//------------------------------------------------------------
		// Test 4: Verify enabling permission for editor (without suggested group)
		//
		// In the XML file:  default="com_content:core.edit"
		//
		$test = 'test 4';
		$action = 'example.custom.editor1';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author (Should choose this)
		    INV_AUTHOR  => false,  //             Invoice Author (inherits from Author)
			EDITOR      =>  true,  //             Editor (inherits from Author)
			PUBLISHER   =>  true,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, EDITOR);

		//------------------------------------------------------------
		// Test 5: Verify enabling permission for editor (with suggested group)
		//
		// In the XML file:  default="com_content:core.edit[Editor]"
		//
		$test = 'test 5';
		$action = 'example.custom.editor2';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author (Should choose this)
		    INV_AUTHOR  => false,  //             Invoice Author (inherits from Author)
			EDITOR      =>  true,  //             Editor (inherits from Author)
			PUBLISHER   =>  true,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, EDITOR);

		//------------------------------------------------------------
		// Test 6: Verify enabling permission for publisher (without suggested group)
		//
		// In the XML file:  default="com_content:core.edit.state"
		//
		$test = 'test 6';
		$action = 'example.custom.publisher1';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author
		    INV_AUTHOR  => false,  //             Invoice Author
			EDITOR      => false,  //             Editor
			PUBLISHER   =>  true,  //                 Publisher (Should choose this)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, PUBLISHER);

		//------------------------------------------------------------
		// test 7: Verify enabling permission for publisher (with suggested group)
		//
		// In the XML file:  default="com_content:core.edit.state[Publisher]"
		//
		$test = 'test 7';
		$action = 'example.custom.publisher2';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author
		    INV_AUTHOR  => false,  //             Invoice Author
			EDITOR      => false,  //             Editor
			PUBLISHER   =>  true,  //                 Publisher (Should choose this)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, PUBLISHER);

		//------------------------------------------------------------
		// test 8: Verify enabling permission for Manager (without suggested group)
		//
		// In the XML file:  default="com_content:core.delete"
		//
		$test = 'test 8';
		$action = 'example.custom.manager1';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     =>  true,  //     Manager
			ADMIN       =>  true,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author
		    INV_AUTHOR  => false,  //             Invoice Author
			EDITOR      => false,  //             Editor
			PUBLISHER   => false,  //                 Publisher (Should choose this)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, MANAGER);

		//------------------------------------------------------------
		// test 9: Verify enabling permission for Manager (with suggested group)
		//
		// In the XML file:  default="com_content:core.delete[Manager]"
		//
		$test = 'test 9';
		$action = 'example.custom.manager2';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     =>  true,  //     Manager
			ADMIN       =>  true,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author
		    INV_AUTHOR  => false,  //             Invoice Author
			EDITOR      => false,  //             Editor
			PUBLISHER   => false,  //                 Publisher (Should choose this)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, MANAGER);

		//------------------------------------------------------------
		// test 10: Verify enabling permission for author+manager (without suggested groups)
		//
		// In the XML file:  default="com_content:core.create,com_content:core.delete"
		//
		$test = 'test 10';
		$action = 'example.custom.authmanage1';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     =>  true,  //     Manager
			ADMIN       =>  true,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      =>  true,  //         Author
		    INV_AUTHOR  =>  true,  //             Invoice Author
			EDITOR      =>  true,  //             Editor
			PUBLISHER   =>  true,  //                 Publisher (Should choose this)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, Array(AUTHOR, MANAGER));

		//------------------------------------------------------------
		// test 11: Verify enabling permission for author+manager (with suggested groups)
		//
		// In the XML file:  default="com_content:core.create[Author],com_content:core.delete[Manager]"
		//
		$test = 'test 11';
		$action = 'example.custom.authmanage2';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     =>  true,  //     Manager
			ADMIN       =>  true,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      =>  true,  //         Author
		    INV_AUTHOR  =>  true,  //             Invoice Author
			EDITOR      =>  true,  //             Editor
			PUBLISHER   =>  true,  //                 Publisher (Should choose this)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, Array(AUTHOR, MANAGER));

		//------------------------------------------------------------
		// test 12: Verify enabling permission for Administrator (without suggested groups)
		//
		// In the XML file:  default="com_content:core.manage"
		//
		$test = 'test 12';
		$action = 'example.custom.administrator1';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       =>  true,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author
		    INV_AUTHOR  => false,  //             Invoice Author
			EDITOR      => false,  //             Editor
			PUBLISHER   => false,  //                 Publisher (Should choose this)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, ADMIN);

 		//------------------------------------------------------------
		// test 13: Verify enabling permission for Administrator (with suggested groups)
		//
		// In the XML file:  default="com_content:core.manage[Administrator]"
		//
		$test = 'test 13';
		$action = 'example.custom.administrator2';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       =>  true,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author
		    INV_AUTHOR  => false,  //             Invoice Author
			EDITOR      => false,  //             Editor
			PUBLISHER   => false,  //                 Publisher (Should choose this)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, ADMIN);

		//------------------------------------------------------------
		// test 14: Verify enabling permission for Superuser (without suggested groups)
		//
		// In the XML file:  default="com_content:core.manage"
		//
		$test = 'test 14';
		$action = 'example.custom.superuser1';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author
		    INV_AUTHOR  => false,  //             Invoice Author
			EDITOR      => false,  //             Editor
			PUBLISHER   => false,  //                 Publisher
			SUPER_USER  =>  true,  //     Super Users (Should choose this)
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, SUPER_USER);

 		//------------------------------------------------------------
		// test 15: Verify enabling permission for Superuser (with suggested groups)
		//
		// In the XML file:  default="com_content:core.manage[Super Users]"
		//
		$test = 'test 15';
		$action = 'example.custom.superuser2';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author
		    INV_AUTHOR  => false,  //             Invoice Author
			EDITOR      => false,  //             Editor
			PUBLISHER   => false,  //                 Publisher
			SUPER_USER  =>  true,  //     Super Users (Should choose this)
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, SUPER_USER);


		//------------------------------------------------------------
		// test 16: Verify enabling permission for editor (with suggested group)
		//
		// In the XML file:  default="com_content:core.edit[Editor]"
		//
		$test = 'test 16';
		$action = 'example.custom.editor3';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author (Should choose this)
		    INV_AUTHOR  => false,  //             Invoice Author (inherits from Author)
			EDITOR      =>  true,  //             Editor (inherits from Author)
			PUBLISHER   =>  true,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, EDITOR);

	}


	/**
	 * testInstallRules1 - Normal cases
	 *
	 * Note: Uses basically the same rule set as in the test component (com_permtest).
	 */
	public function testInstallAccess2()
	{
		// Lists of the actions to check
		$custom_actions = Array( "example.custom.test1",
 								 "example.custom.test2",
 								 "example.custom.test3",
 								 "example.custom.test4",
								 );
		$all_actions = array_merge( $this->core_actions, $custom_actions );

		// Get the pristine, unmodified default permissions
		$initial_permissions = $this->getDefaultPermissions($all_actions);

		// Verify that the defaults for all the custom rules are 'denied'
		foreach ($custom_actions as $action)
		{
			foreach ($this->groups as $gid => $group_name)
			{
				$perm_init = $initial_permissions[$action][$gid];
				$errmsg = "Custom rule '$action' for group '$group_name' defaulted to true before installing custom defaults";
				$this->assertFalse($perm_init, $errmsg);
			}
		}

		// Install the new default rules form access1.xml
		$install_ok = JAccess::installComponentDefaultRules('com_example', __DIR__ . '/data/access2.xml');
		$this->assertTrue($install_ok, 'Problem installing component default rules from data/access2.xml');

		// Get the modified permissions
		$modified_permissions = $this->getDefaultPermissions($all_actions);

		// None of the core rules should have changed
		foreach ($this->core_actions as $action)
		{
			foreach ($this->groups as $gid => $group_name)
			{
				$perm_init = $initial_permissions[$action][$gid];
				$perm_mod  = $modified_permissions[$action][$gid];
				$errmsg = "Core rule '$action' for group '$group_name' modified by installing 'access1.xml'";
				$this->assertEquals($perm_init, $perm_mod, $errmsg);
			}
		}

		//----------------------------------------------------------------------
		// test 1: Verify enabling permission for author (has to figure it out)
		//
		// In the XML file:  default="com_content:core.create"
		//
		$test = 'test 1';
		$action = 'example.custom.test1';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      =>  true,  //         Author (Should choose this)
		    INV_AUTHOR  =>  true,  //             Invoice Author (inherits from Author)
			EDITOR      =>  true,  //             Editor (inherits from Author)
			PUBLISHER   =>  true,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, AUTHOR);

		//------------------------------------------------------------
		// test 2: Verify enabling permission for author (with suggested group)
		//
		// In the XML file:  default="com_content:core.create[Author]"
		//
		$test = 'test 2';
		$action = 'example.custom.test2';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      =>  true,  //         Author (Should choose this)
		    INV_AUTHOR  =>  true,  //             Invoice Author (inherits from Author)
			EDITOR      =>  true,  //             Editor (inherits from Author)
			PUBLISHER   =>  true,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, AUTHOR);

		//------------------------------------------------------------
		// test 3: Verify enabling permission for author (with bad group)
		//
		// Take suggeston of Manager since it has permission for the action and was suggested
		//
		// In the XML file:  default="com_content:core.create[Manager]"
		//
		$test = 'test 3';
		$action = 'example.custom.test3';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     =>  true,  //     Manager
			ADMIN       =>  true,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      => false,  //         Author (Should choose this)
		    INV_AUTHOR  => false,  //             Invoice Author (inherits from Author)
			EDITOR      => false,  //             Editor (inherits from Author)
			PUBLISHER   => false,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, MANAGER);

		//------------------------------------------------------------
		// test 4: Verify enabling permission for author (with bad group)
		//
		// Suggest Registered, which should be ignored to pick Author
		//
		// In the XML file:  default="com_content:core.create[Registered]"
		//
		$test = 'test 4';
		$action = 'example.custom.test4';
		$expected_permission = Array(
			GPUBLIC     => false,  // Public
			MANAGER     => false,  //     Manager
			ADMIN       => false,  //         Administrator
			GUEST       => false,  //     Guest
			REGISTERED  => false,  //     Registered
			CUSTOMER    => false,  //         Customer GroupRegistered
			AUTHOR      =>  true,  //         Author (Should choose this)
		    INV_AUTHOR  =>  true,  //             Invoice Author (inherits from Author)
			EDITOR      =>  true,  //             Editor (inherits from Author)
			PUBLISHER   =>  true,  //                 Publisher (inherits from Author)
			SUPER_USER  => false,  //     Super Users
			);
		$this->checkPermissions($test, $action, $expected_permission, $modified_permissions);
		$this->checkGroups($test, $action, AUTHOR);



	}


	/**
	 * testInstallInstallFail1
	 *
	 * This install will fail because it tries to modify the core content
	 * rules by specifying a component 'com_core'.
	 */
	public function testInstallFail1()
	{
		$exception_msg = "ERROR: Cannot override core rule defaults (component='com_core')";
		$this->setExpectedException('InvalidArgumentException', $exception_msg);
		JAccess::installComponentDefaultRules('com_core', __DIR__ . '/data/access_bad1.xml');
	}


	/**
	 * testInstallInstallFail2
	 *
	 * This install will fail because it tries to modify the core rules (for a non-core component)
	 */
	public function testInstallFail2()
	{
		$exception_msg = "ERROR: Cannot override default core rule 'core.edit' for component 'com_example'";
		$this->setExpectedException('Exception', $exception_msg);
		JAccess::installComponentDefaultRules('com_example', __DIR__ . '/data/access_bad1.xml');
	}


	/**
	 * testInstallInstallFail3
	 *
	 * This install will fail because it tries illegal syntax for a default (no colon)
	 */
	public function testInstallFail3()
	{
		$exception_msg = "ERROR: Bad rule in 'access_bad2.xml', default syntax for rule " .
			             "'example.custom.nocolon'. Should be like: 'com_content:core.edit'";
		$this->setExpectedException('Exception', $exception_msg);
		JAccess::installComponentDefaultRules('com_example', __DIR__ . '/data/access_bad2.xml');
	}


	/**
	 * testInstallInstallFail4
	 *
	 * This install will fail because it tries illegal syntax for a default
	 */
	public function testInstallFail4()
	{
		$exception_msg = "ERROR: Bad rule in 'access_bad2.xml', default syntax for rule " .
			             "'example.custom.nocolon'. Should be like: 'com_content:core.edit'";
		$this->setExpectedException('Exception', $exception_msg);
		JAccess::installComponentDefaultRules('com_example', __DIR__ . '/data/access_bad2.xml');
	}


	/**
	 * testInstallInstallFail5
	 *
	 * This install will fail because it tries illegal syntax for a default
	 */
	public function testInstallFail5()
	{
		$exception_msg = "ERROR: Error in 'access_bad3.xml' rule for rule 'example.custom.badcomp'. " .
			             "Component name (mod_bad) does not begin with 'com_' (e.g. 'com_content')";
		$this->setExpectedException('Exception', $exception_msg);
		JAccess::installComponentDefaultRules('com_example', __DIR__ . '/data/access_bad3.xml');
	}
	

	/**
	 * testInstallInstallFail1
	 *
	 * This install will fail because it tries to modify the core rules
	 */
	public function testPurgeFail1()
	{
		$exception_msg = "Error: Cannot purge core rules!";
		$this->setExpectedException('InvalidArgumentException', $exception_msg);
		JAccess::purgeComponentDefaultRules('com_core');
	}


	/**
	 * testInstallInstallFail2
	 *
	 * This install will fail because the component name is malformed
	 */
	public function testPurgeFail2()
	{
		$exception_msg = "ERROR: Component name (mod_example) is malformed; it should be like 'com_xyz'";
		$this->setExpectedException('Exception', $exception_msg);
		JAccess::purgeComponentDefaultRules('mod_example');
	}


}