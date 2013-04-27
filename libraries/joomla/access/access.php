<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Access
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

jimport('joomla.utilities.arrayhelper');

/**
 * Class that handles all access authorisation routines.
 *
 * @package     Joomla.Platform
 * @subpackage  Access
 * @since       11.1
 */
class JAccess
{
	/**
	 * Array of view levels
	 *
	 * @var    array
	 * @since  11.1
	 */
	protected static $viewLevels = array();

	/**
	 * Array of rules for the asset
	 *
	 * @var    array
	 * @since  11.1
	 */
	protected static $assetRules = array();

	/**
	 * Array of user groups.
	 *
	 * @var    array
	 * @since  11.1
	 */
	protected static $userGroups = array();

	/**
	 * Array of user group paths.
	 *
	 * @var    array
	 * @since  11.1
	 */
	protected static $userGroupPaths = array();

	/**
	 * Array of cached groups by user.
	 *
	 * @var    array
	 * @since  11.1
	 */
	protected static $groupsByUser = array();

	/**
	 * Method for clearing static caches.
	 *
	 * @return  void
	 *
	 * @since   11.3
	 */
	public static function clearStatics()
	{
		self::$viewLevels = array();
		self::$assetRules = array();
		self::$userGroups = array();
		self::$userGroupPaths = array();
		self::$groupsByUser = array();
	}

	/**
	 * Method to check if a user is authorised to perform an action, optionally on an asset.
	 *
	 * @param   integer  $userId  Id of the user for which to check authorisation.
	 * @param   string   $action  The name of the action to authorise.
	 * @param   mixed    $asset   Integer asset id or the name of the asset as a string.  Defaults to the global asset node.
	 *
	 * @return  boolean  True if authorised.
	 *
	 * @since   11.1
	 */
	public static function check($userId, $action, $asset = null)
	{
		// Sanitise inputs.
		$userId = (int) $userId;

		$action = strtolower(preg_replace('#[\s\-]+#', '.', trim($action)));
		$asset = strtolower(preg_replace('#[\s\-]+#', '.', trim($asset)));

		// Default to the root asset node.
		if (empty($asset))
		{
			$db = JFactory::getDbo();
			$assets = JTable::getInstance('Asset', 'JTable', array('dbo' => $db));
			$rootId = $assets->getRootId();
			$asset = $rootId;
		}

		// Get the rules for the asset recursively to root if not already retrieved.
		if (empty(self::$assetRules[$asset]))
		{
			self::$assetRules[$asset] = self::getAssetRules($asset, true);
		}

		// Get all groups against which the user is mapped.
		$identities = self::getGroupsByUser($userId);
		array_unshift($identities, $userId * -1);

		return self::$assetRules[$asset]->allow($action, $identities);
	}

	/**
	 * Method to check if a group is authorised to perform an action, optionally on an asset.
	 *
	 * @param   integer  $groupId  The path to the group for which to check authorisation.
	 * @param   string   $action   The name of the action to authorise.
	 * @param   mixed    $asset    Integer asset id or the name of the asset as a string.  Defaults to the global asset node.
	 *
	 * @return  boolean  True if authorised.
	 *
	 * @since   11.1
	 */
	public static function checkGroup($groupId, $action, $asset = null)
	{
		// Sanitize inputs.
		$groupId = (int) $groupId;
		$action = strtolower(preg_replace('#[\s\-]+#', '.', trim($action)));
		$asset = strtolower(preg_replace('#[\s\-]+#', '.', trim($asset)));

		// Get group path for group
		$groupPath = self::getGroupPath($groupId);

		// Default to the root asset node.
		if (empty($asset))
		{
			$db = JFactory::getDbo();
			$assets = JTable::getInstance('Asset', 'JTable', array('dbo' => $db));
			$rootId = $assets->getRootId();
		}

		// Get the rules for the asset recursively to root if not already retrieved.
		if (empty(self::$assetRules[$asset]))
		{
			self::$assetRules[$asset] = self::getAssetRules($asset, true);
		}

		return self::$assetRules[$asset]->allow($action, $groupPath);
	}

	/**
	 * Remove all groups in the array of group IDs that have ancestors that
	 * are in the provided array of groups.
	 *
	 * This has the effect of only leaving the lowest level group on each line
	 * of descent.
	 *
	 * @param  array  $groupsIds array of group IDs to be purged (called 'group list' below)
	 *
	 * @return a new array of group IDs with descendent groups removed
	 */
	public static function removeDescendentGroups($groupIds)
	{
		// Get the needed info for each group
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->quoteName('id'));
		$query->select($db->quoteName('parent_id'));
		$query->select($db->quoteName('title'));
		$query->from($db->quoteName('#__usergroups'));
		$db->setQuery($query);
		$gdata = $db->loadObjectList();
		$parent = Array();
		foreach ($gdata as $grp)
		{
			$parent[(int)$grp->id] = (int)$grp->parent_id;
		}

		// Array of the eldest groups (that have no ancestors in the group list)
		$eldest = Array();

		// Check each of the groups to see whether it has descendents in the group list
		$unprocessed = $groupIds;
		while (count($unprocessed) > 0)
		{
			// Check the next group
			$target_gid = array_pop($unprocessed);
			
			// See if it has any ancestors in the group list
			$descendent_found = false;
			foreach ($groupIds as $check_id)
			{
				// Do not check itself
				if ($check_id == $target_gid)
				{
					continue;
				}

				// See if this group id is an ancestor of $target_gid
				$check_id = $parent[$target_gid];
				while ($check_id > 0)
				{
					if ( in_array($check_id, $groupIds) )
					{
						$descendent_found = true;
						break 2;
					}

					$check_id = $parent[$check_id];
				}
			}

			if (!$descendent_found)
			{
				// If we found no descendents for this target group, we can 
				// save it to array of known 'eldest' groups (eg, that have 
				// no ancestors in our group list)

				$elders[] = $target_gid;
			}
		}

		// Always sort the resulting groups (helps testing)
		sort($elders);

		// Return the resulting groups
		return $elders;
	}

	/**
	 * Gets the parent groups that a leaf group belongs to in its branch back to the root of the tree
	 * (including the leaf group id).
	 *
	 * @param   mixed  $groupId  An integer or array of integers representing the identities to check.
	 *
	 * @return  mixed  True if allowed, false for an explicit deny, null for an implicit deny.
	 *
	 * @since   11.1
	 */
	protected static function getGroupPath($groupId)
	{
		// Preload all groups
		if (empty(self::$userGroups))
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true)
				->select('parent.id, parent.lft, parent.rgt')
				->from('#__usergroups AS parent')
				->order('parent.lft');
			$db->setQuery($query);
			self::$userGroups = $db->loadObjectList('id');
		}

		// Make sure groupId is valid
		if (!array_key_exists($groupId, self::$userGroups))
		{
			return array();
		}

		// Get parent groups and leaf group
		if (!isset(self::$userGroupPaths[$groupId]))
		{
			self::$userGroupPaths[$groupId] = array();

			foreach (self::$userGroups as $group)
			{
				if ($group->lft <= self::$userGroups[$groupId]->lft && $group->rgt >= self::$userGroups[$groupId]->rgt)
				{
					self::$userGroupPaths[$groupId][] = $group->id;
				}
			}
		}

		return self::$userGroupPaths[$groupId];
	}


	/**
	 * Find out the lowest ancestor (closet to the root group whose id=0) in the groups
	 *
	 * Checks to see that all the groups are on the same line of descent.
	 * If not, it returns null.
	 *
	 * returns the lowest (or least derived) one, eg closest to the root group with id=0
	 *
	 * @param  array  $groups  An array of the IDs of the groups to test
	 *
	 * @return mixed the group closest to the root group (id=0), i.e, the
	 *               least derived group.  Returns null if the groups are
	 *               not all in the same line of descent.
	 *
	 * @todo This method is generic and should probably be in a group helper class
	 */
	public static function lowestAncestorGroup($groups)
	{
		// Get the all the paths, finding the shortest and longest paths
		$shortest = 99999;
		$shortest_id = null;
		$longest = 0;
		$longest_id = null;
		$paths = Array();
		foreach ($groups as $gid)
		{	
			// Get the group IDs in increasing order
			$grp_keys = array_keys(array_flip(self::getGroupPath($gid)));

			// sort($grp_keys);
			$paths[$gid] = $grp_keys;
			$path_len = count($grp_keys);
			if ($path_len > $longest)
			{
				$longest = $path_len;
				$longest_id = $gid;
			}
			if ($path_len < $shortest)
			{
				$shortest = $path_len;
				$shortest_id = $gid;
			}
		}
		
		// Check to make sure each path is in the longest path (eg, in the
		// same line of ancestry)
		$longest_path = $paths[$longest_id];

		foreach ($groups as $gid)
		{
			if ($gid != $longest_id)
			{
				// Check the path element by element
				$test_path = $paths[$gid];
				for ($i = 0; $i < count($test_path); $i += 1)
				{
					if ($test_path[$i] != $longest_path[$i])
					{
						return null;
					}
				}
			}
		}

		// Since all the paths are on the same line of descent,
		// just return  the last group ID of the shortest path
		$shortest_path = $paths[$shortest_id];
		return $shortest_path[count($shortest_path) - 1];
	}
		

	/**
	 * Method to return the ID for a group name
	 *
	 * @param   string  $groupname   The group name (title)
	 *
	 * @return  integer  the group id (0 if not found)
	 *
	 * @todo This method is generic and should probably be in a group helper class
	 */
	public static function getGroupId($groupname)
	{
		// Set up
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		// The query
		$query->select($db->quoteName('id'));
		$query->from($db->quoteName('#__usergroups'));
		$query->where($db->quoteName('title') . ' = ' . $db->quote($groupname));

		// Get the results
		$db->setQuery($query, 0, 1);
		return $db->loadResult();
	}

	/**
	 * Method to return the JAccessRules object for an asset.  The returned object can optionally hold
	 * only the rules explicitly set for the asset or the summation of all inherited rules from
	 * parent assets and explicit rules.
	 *
	 * @param   mixed    $asset      Integer asset id or the name of the asset as a string.
	 * @param   boolean  $recursive  True to return the rules object with inherited rules.
	 *
	 * @return  JAccessRules   JAccessRules object for the asset.
	 *
	 * @since   11.1
	 */
	public static function getAssetRules($asset, $recursive = false)
	{
		// Get the database connection object.
		$db = JFactory::getDbo();

		// Build the database query to get the rules for the asset.
		$query = $db->getQuery(true)
			->select($recursive ? 'b.rules' : 'a.rules')
			->from('#__assets AS a');

		// SQLsrv change
		$query->group($recursive ? 'b.id, b.rules, b.lft' : 'a.id, a.rules, a.lft');

		// If the asset identifier is numeric assume it is a primary key, else lookup by name.
		if (is_numeric($asset))
		{
			$query->where('(a.id = ' . (int) $asset . ')');
		}
		else
		{
			$query->where('(a.name = ' . $db->quote($asset) . ')');
		}

		// If we want the rules cascading up to the global asset node we need a self-join.
		if ($recursive)
		{
			$query->join('LEFT', '#__assets AS b ON b.lft <= a.lft AND b.rgt >= a.rgt')
				->order('b.lft');
		}

		// Execute the query and load the rules from the result.
		$db->setQuery($query);
		$result = $db->loadColumn();

		// Get the root even if the asset is not found and in recursive mode
		if (empty($result))
		{
			$db = JFactory::getDbo();
			$assets = JTable::getInstance('Asset', 'JTable', array('dbo' => $db));
			$rootId = $assets->getRootId();
			$query = $db->getQuery(true)
				->select('rules')
				->from('#__assets')
				->where('id = ' . $db->quote($rootId));
			$db->setQuery($query);
			$result = $db->loadResult();
			$result = array($result);
		}
		// Instantiate and return the JAccessRules object for the asset rules.
		$rules = new JAccessRules;
		$rules->mergeCollection($result);

		return $rules;
	}

	/**
	 * Method to return a list of user groups mapped to a user. The returned list can optionally hold
	 * only the groups explicitly mapped to the user or all groups both explicitly mapped and inherited
	 * by the user.
	 *
	 * @param   integer  $userId     Id of the user for which to get the list of groups.
	 * @param   boolean  $recursive  True to include inherited user groups.
	 *
	 * @return  array    List of user group ids to which the user is mapped.
	 *
	 * @since   11.1
	 */
	public static function getGroupsByUser($userId, $recursive = true)
	{
		// Creates a simple unique string for each parameter combination:
		$storeId = $userId . ':' . (int) $recursive;

		if (!isset(self::$groupsByUser[$storeId]))
		{
			// TODO: Uncouple this from JComponentHelper and allow for a configuration setting or value injection.
			if (class_exists('JComponentHelper'))
			{
				$guestUsergroup = JComponentHelper::getParams('com_users')->get('guest_usergroup', 1);
			}
			else
			{
				$guestUsergroup = 1;
			}

			// Guest user (if only the actually assigned group is requested)
			if (empty($userId) && !$recursive)
			{
				$result = array($guestUsergroup);
			}
			// Registered user and guest if all groups are requested
			else
			{
				$db = JFactory::getDbo();

				// Build the database query to get the rules for the asset.
				$query = $db->getQuery(true)
					->select($recursive ? 'b.id' : 'a.id');
				if (empty($userId))
				{
					$query->from('#__usergroups AS a')
						->where('a.id = ' . (int) $guestUsergroup);
				}
				else
				{
					$query->from('#__user_usergroup_map AS map')
						->where('map.user_id = ' . (int) $userId)
						->join('LEFT', '#__usergroups AS a ON a.id = map.group_id');
				}

				// If we want the rules cascading up to the global asset node we need a self-join.
				if ($recursive)
				{
					$query->join('LEFT', '#__usergroups AS b ON b.lft <= a.lft AND b.rgt >= a.rgt');
				}

				// Execute the query and load the rules from the result.
				$db->setQuery($query);
				$result = $db->loadColumn();

				// Clean up any NULL or duplicate values, just in case
				JArrayHelper::toInteger($result);

				if (empty($result))
				{
					$result = array('1');
				}
				else
				{
					$result = array_unique($result);
				}
			}

			self::$groupsByUser[$storeId] = $result;
		}

		return self::$groupsByUser[$storeId];
	}

	/**
	 * Method to return a list of user Ids contained in a Group
	 *
	 * @param   integer  $groupId    The group Id
	 * @param   boolean  $recursive  Recursively include all child groups (optional)
	 *
	 * @return  array
	 *
	 * @since   11.1
	 * @todo    This method should move somewhere else
	 */
	public static function getUsersByGroup($groupId, $recursive = false)
	{
		// Get a database object.
		$db = JFactory::getDbo();

		$test = $recursive ? '>=' : '=';

		// First find the users contained in the group
		$query = $db->getQuery(true)
			->select('DISTINCT(user_id)')
			->from('#__usergroups as ug1')
			->join('INNER', '#__usergroups AS ug2 ON ug2.lft' . $test . 'ug1.lft AND ug1.rgt' . $test . 'ug2.rgt')
			->join('INNER', '#__user_usergroup_map AS m ON ug2.id=m.group_id')
			->where('ug1.id=' . $db->quote($groupId));

		$db->setQuery($query);

		$result = $db->loadColumn();

		// Clean up any NULL values, just in case
		JArrayHelper::toInteger($result);

		return $result;
	}

	/**
	 * Method to return a list of view levels for which the user is authorised.
	 *
	 * @param   integer  $userId  Id of the user for which to get the list of authorised view levels.
	 *
	 * @return  array    List of view levels for which the user is authorised.
	 *
	 * @since   11.1
	 */
	public static function getAuthorisedViewLevels($userId)
	{
		// Get all groups that the user is mapped to recursively.
		$groups = self::getGroupsByUser($userId);

		// Only load the view levels once.
		if (empty(self::$viewLevels))
		{
			// Get a database object.
			$db = JFactory::getDbo();

			// Build the base query.
			$query = $db->getQuery(true)
				->select('id, rules')
				->from($db->quoteName('#__viewlevels'));

			// Set the query for execution.
			$db->setQuery($query);

			// Build the view levels array.
			foreach ($db->loadAssocList() as $level)
			{
				self::$viewLevels[$level['id']] = (array) json_decode($level['rules']);
			}
		}

		// Initialise the authorised array.
		$authorised = array(1);

		// Find the authorised levels.
		foreach (self::$viewLevels as $level => $rule)
		{
			foreach ($rule as $id)
			{
				if (($id < 0) && (($id * -1) == $userId))
				{
					$authorised[] = $level;
					break;
				}
				// Check to see if the group is mapped to the level.
				elseif (($id >= 0) && in_array($id, $groups))
				{
					$authorised[] = $level;
					break;
				}
			}
		}

		return $authorised;
	}

	/**
	 * Method to return a list of actions for which permissions can be set given a component and section.
	 *
	 * @param   string  $component  The component from which to retrieve the actions.
	 * @param   string  $section    The name of the section within the component from which to retrieve the actions.
	 *
	 * @return  array  List of actions available for the given component and section.
	 *
	 * @since   11.1
	 *
	 * @deprecated  12.3  Use JAccess::getActionsFromFile or JAccess::getActionsFromData instead.
	 *
	 * @codeCoverageIgnore
	 *
	 */
	public static function getActions($component, $section = 'component')
	{
		JLog::add(__METHOD__ . ' is deprecated. Use JAccess::getActionsFromFile or JAcces::getActionsFromData instead.', JLog::WARNING, 'deprecated');
		$actions = self::getActionsFromFile(
			JPATH_ADMINISTRATOR . '/components/' . $component . '/access.xml',
			"/access/section[@name='" . $section . "']/"
		);
		if (empty($actions))
		{
			return array();
		}
		else
		{
			return $actions;
		}
	}

	/**
	 * Method to return a list of actions from a file for which permissions can be set.
	 *
	 * @param   string  $file   The path to the XML file.
	 * @param   string  $xpath  An optional xpath to search for the fields.
	 *
	 * @return  boolean|array   False if case of error or the list of actions available.
	 *
	 * @since   12.1
	 */
	public static function getActionsFromFile($file, $xpath = "/access/section[@name='component']/")
	{
		if (!is_file($file) || !is_readable($file))
		{
			// If unable to find the file return false.
			return false;
		}
		else
		{
			// Else return the actions from the xml.
			$xml = simplexml_load_file($file);
			return self::getActionsFromData($xml, $xpath);
		}
	}

	/**
	 * Method to return a list of actions from a string or from an xml for which permissions can be set.
	 *
	 * @param   string|SimpleXMLElement  $data   The XML string or an XML element.
	 * @param   string                   $xpath  An optional xpath to search for the fields.
	 *
	 * @return  boolean|array   False if case of error or the list of actions available.
	 *
	 * @since   12.1
	 */
	public static function getActionsFromData($data, $xpath = "/access/section[@name='component']/")
	{
		// If the data to load isn't already an XML element or string return false.
		if ((!($data instanceof SimpleXMLElement)) && (!is_string($data)))
		{
			return false;
		}

		// Attempt to load the XML if a string.
		if (is_string($data))
		{
			try
			{
				$data = new SimpleXMLElement($data);
			}
			catch (Exception $e)
			{
				return false;
			}

			// Make sure the XML loaded correctly.
			if (!$data)
			{
				return false;
			}
		}

		// Initialise the actions array
		$actions = array();

		// Get the elements from the xpath
		$elements = $data->xpath($xpath . 'action[@name][@title][@description]');

		// If there some elements, analyse them
		if (!empty($elements))
		{
			foreach ($elements as $action)
			{
				// Add the action to the actions array
				$actions[] = (object) array(
					'name' => (string) $action['name'],
					'title' => (string) $action['title'],
					'description' => (string) $action['description'],
					'default' => (string) $action['default']
				);
			}
		}

		// Finally return the actions array
		return $actions;
	}


	/**
	 * Replace the default rules for the target component in the root asset record
	 *
	 * The access rules for an component reside in an 'access.xml' file
	 * belonging to that component.
	 *  
	 * No defaults are set if the role is not found.   The core rules may not be overriden.
	 *
	 * WARNING: Cannot be called before component initialization (ok in install post-flight)
	 *
	 * @param   string   $component  name of the target component (eg, 'com_xyz')
	 * @param   file     A file (full path) for the 'access.xml' file to be used (for testing)
	 *
	 * @return  boolean  success or failure
	 */
	public static function installComponentDefaultRules($component, $file = null)
	{
		// Make sure we do not try to modify any core rules!
		if (strtolower($component) == 'com_core')
		{
			throw new InvalidArgumentException("ERROR: Cannot override core rule defaults (component='$component')");
		}

		// Create an empty set of rules to receive the rules for the component
		$new_rules = new JAccessRules();

		// Get the actions for this component (which contain the defaults)
		if ( $file === null )
		{
			$actions = JAccess::getActions($component);
			$file = 'access.xml';
		}
		else
		{
			// Load the actions from the specified file
			$actions = self::getActionsFromFile($file, "/access/section[@name='component']/");
			$file = basename($file);
		}
		
		foreach ($actions as $rule_action)
		{
			// Process each default
			if ( $rule_action->default )
			{
				$rule_name = $rule_action->name;

				// Make sure the rule is not a core rule
				if ( strncmp($rule_name, 'core.', 5) === 0 )
				{
					throw new Exception("ERROR: Cannot override default core rule '$rule_name' " .
										"for component '$component'");
				}

				// Process each comma-separated defaults clause 
				$rule_set = explode(',', $rule_action->default);
				foreach ($rule_set as $raw_rule)
				{
					$group_name = null;
					$group_id = null;
					$action = null;

					// Parse the rule
 					$rule = trim($raw_rule);
 					if (strpos($rule, ':') !== false)
 					{
 						$parts = explode(':', $rule);
 						$asset = trim($parts[0]);
 						$action = trim($parts[1]);
					}
					else
					{
						// Syntax error
						throw new Exception("ERROR: Bad rule in '$file', default syntax for " .
											"rule '$rule_name'. Should be like: 'com_content:core.edit'");
					}

					// Make sure the component name is reasonable
					if (strncmp($asset, 'com_', 4) !== 0)
					{
						throw new Exception("ERROR: Error in '$file' rule for rule '$rule_name'. " .
											"Component name ($asset) does not begin with 'com_' (e.g. 'com_content')");
					}

					// Deal with the group name hint (if specified)
					if ( strpos($action, '[') !== false ) 
					{
						$parts = explode('[', $action);
						$action = trim($parts[0]);
						$group_name = trim(trim($parts[1], '[] '));
						$group_id = JAccess::getGroupId($group_name);

						// if the group exists, check it
						if ($group_id == 0)
						{
							// The group was not found, ignore it (quietly)
							$group_id = null;
							$group_name = null;
						}
						else
						{
							// A group name was specified, check it
							if ( !JAccess::checkGroup($group_id, $action, $asset) )
							{
								// The group does not have the required permission, ignore it (quietly)
								$group_id = null;
								$group_name = null;
							}
						}
					}

					// Find the necessary group
					if ( $group_id === null )
					{
						// First, get the information about all the users
						$db = JFactory::getDbo();
						$query = $db->getQuery(true);
						$query->select($db->quoteName('id'));
						$query->select($db->quoteName('parent_id'));
						$query->select($db->quoteName('title'));
						$query->from($db->quoteName('#__usergroups'));
						$db->setQuery($query);
						$group_info = $db->loadObjectList();
						$groups = Array();

						// Scan through the groups to find all groups with the required permission
						$good_groups = Array();
						foreach ($group_info as $g)
						{
							$gid = $g->id;
							$groups[$gid] = $g;
							if (JAccess::checkGroup($gid, $action, $asset))
							{
								$good_groups[$gid] = $g;
							}
						}

						if (empty($good_groups))
						{
							JLog::add("WARNING: For default permission rule '$rule_name' cannot find a " .
									  "group with permission to do  '$action' for '$asset' on this system!",
									  JLog::WARNING);
						}

						// Figure out which permitted group is least
						// 'authoritative'
						//
						// Rank the rules from least authoritative to most
                        // authoritative (somewhat arbitrary).  For each
                        // group that can do the required action, sum the
                        // ranks for all core rules that it can do to form a
                        // total 'authority' index.  Then choose the group
                        // with the lowest total 'authority' index.
						$core_action_ranking = Array( 'core.create' => 1,
													  'core.edit.own' => 1,
													  'core.edit' => 3,
													  'core.delete' => 3,
													  'core.edit.state' => 5,
													  'core.manage' => 7,
													  'core.admin' => 10
													  );
						$best = Array();
						$best_rank = 999999;
					
						foreach ($good_groups as $grp)
						{
							// Sum the ranks of the permitted core actions
							$rank_total = 0;
							foreach ($core_action_ranking as $caction => $rank)
							{
								if (JAccess::checkGroup($grp->id, $caction, $asset))
								{
									$rank_total += $rank;
								}
							}

							// Check for lowest ranked group so far
							if ( $rank_total < $best_rank )
							{
								$best = Array($grp);
								$best_rank = $rank_total;
							}
							else if ( $rank_total == $best_rank )
							{
								$best[] = $grp;
							}
						}

						// We have multiple permitted groups with the same
						// rank total
						if (count($best) > 1)
						{
							// See if the groups are all one ancestor line
							$test_groups = Array();
							foreach ($best as $grp)
							{
								$test_groups[] = $grp->id;
							}
							$lowest = self::lowestAncestorGroup($test_groups);

							if ($lowest !== null)
							{
								// Case 1: All the groups are on the same line of
								//         descent - If so pick the lowest (or
								//         least derived) one, eg closest to
								//         the root group with id=0
								$best = $groups[(int)$lowest];
							}
							else
							{
								// Case 2: the groups are unrelated, just pick
								//         the one that closest to the root group

								// ties, pick the one closest to the root
								$best_dist = 99999;
								$best_dist_grp = null;
								foreach ($best as $grp)
								{
									$dist = 1;
									$g = $grp;
									$parent_id = $grp->parent_id;
									while ($parent_id != 0)
									{
										$dist += 1;
										$g = $groups[$parent_id];
										$parent_id = $g->parent_id;
									}
									if ( $dist < $best_dist )
									{
										$best_dist = $dist;
										$best_dist_grp = $g;
									}
								}
								$best = $best_dist_grp;
							}
						}
						else
						{
							// Just one acceptable group, so use it
							$best = $best[0];
						}

						// Note the best rule
						$group_id = $best->id;
						$group_name = $best->title;
					}

 					// If no suitable rule has been found, skip it
					if ( $group_id === null )
					{
						continue;
					}

					// Construct the rule for this action
					$new_rule = new JAccessRules(Array($rule_name => Array($group_id => 1 )));

					// Merge it with the rest of the new rules
					$new_rules->merge($new_rule);
				}

				// ??? echo "For '$rule_name', GROUP($group_name, $group_id)\n";
			}
		}

		// Purge any existing custom rules for this component
		JAccess::purgeComponentDefaultRules($component);

		// Get the root rules
		$root = JTable::getInstance('asset');
		$root->loadByName('root.1');
		$root_rules = new JAccessRules($root->rules);

		// Merge the new rules into the root default rules and save it
		$root_rules->merge($new_rules);
		$root->rules = (string)$root_rules;

		// Save the updated root rule
		return $root->store();
	}


	/**
	 * Purge all defaults for custom actions/rules for a specified component
	 *
	 * NOTE: For component 'com_xyz', this function will remove all top-level
	 *       default rules for custom actions that belong to the component, in
	 *       other words, rules with actions that begin with 'xyz.'
	 *
	 * WARNING: this is intended for non-core components and will abort if the
	 *          user attempts to purge any core rules by passing in 'com_core'.
	 *
	 * @param   string   $component  name of the target component (eg, 'com_xyz')
	 *
	 * @return  mixed  false for failure, otherwise the updated rules
	 */
	public static function purgeComponentDefaultRules($component)
	{
		// make sure we do not purge any core rules!
		if (strtolower($component) == 'com_core')
		{
			throw new InvalidArgumentException("Error: Cannot purge core rules!");
		}

		// Remove the leading 'com_' to get the search prefix
		$cname = strtolower($component);
		if (strpos($cname, 'com_', 0) === 0)
		{
			$cname = substr($cname, 4);
		}
		else
		{
			throw new Exception("ERROR: Component name ($component) is malformed; it should be like 'com_xyz'");
		}
		
		// Get the root rules
		$root = JTable::getInstance('asset');
		$root->loadByName('root.1');
		$root_rules = new JAccessRules($root->rules);

		// remove each custom rule for this component
		$action_pattern = '/^' . $cname . '\./';
		$root_rules->removeActions($action_pattern);

		// Save the updated root rule
		$root->rules = (string)$root_rules;
		return $root->store();
	}

}
