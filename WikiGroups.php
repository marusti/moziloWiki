<?php

/*

		moziloWiki - WikiGroups.php
		
		A lightweight group management for moziloWiki. Feel free to
		change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

require_once("Properties.php");
require_once("CSV.php");

class WikiGroups {

	// the arrays which contain the group data
	var $mygroups = array();
	var $groups;
	var $groupdata;

	// 		0: unrestricted user, possibly with user management rights (admin)
	// 		1: user which can change every wiki content (registered user)
	// 		2: user which only can read and search (guest)
	function __construct() {
		global $USRMGT_GROUPS;
		global $USRMGT_GROUPDATA;
		$this->groups = new CSV($USRMGT_GROUPS);
		$this->groupdata = new Properties($USRMGT_GROUPDATA);
		$this->loadWikiGroups();
	}

	// load data from files
	function loadWikiGroups()  {
		$groups = $this->groups->toArray();
		foreach ($groups as $groupid) {
			if ($groupid == "")
				continue;
			$anonymous = $this->groupdata->get("$groupid.anonymous");
			$changepw = $this->groupdata->get("$groupid.changepw");
			$editcontent = $this->groupdata->get("$groupid.editcontent");
			$editusers = $this->groupdata->get("$groupid.editusers");
			$member = $this->groupdata->get("$groupid.member");
			$name = $this->groupdata->get("$groupid.name");
			$this->mygroups[$groupid] = array($anonymous, $changepw, $editcontent, $editusers, $member, $name);
		}
	}

	// return the given group's right to login multiple
	function isAnonymous($number) {
		$groupdata = $this->getGroupData($number);
		if (!$groupdata == null)
			if ($groupdata[0] == "true")
				return true;
			else
				return false;
		// return false, if the group doesn't exist
		else 
			return false;
	}

	// return the given group's right to change pw
	function canChangePw($number) {
		$groupdata = $this->getGroupData($number);
		if (!$groupdata == null)
			if ($groupdata[1] == "true")
				return true;
			else
				return false;
		// return false, if the group doesn't exist
		else 
			return false;
	}

	// return the given group's right to edit content
	function canEditContent($number) {
		$groupdata = $this->getGroupData($number);
		if (!$groupdata == null)
			if ($groupdata[2] == "true")
				return true;
			else
				return false;
		// return false, if the group doesn't exist
		else 
			return false;
	}

	// return the given group's right to edit users
	function canEditUsers($number) {
		$groupdata = $this->getGroupData($number);
		if (!$groupdata == null)
			if ($groupdata[3] == "true")
				return true;
			else
				return false;
		// return false, if the group doesn't exist
		else 
			return false;
	}

	// return the given group's member name
	function getMemberName($number) {
		$groupdata = $this->getGroupData($number);
		if (!$groupdata == null)
			return $groupdata[4];
		// return null, if the group doesn't exist
		else 
			return null;
	}

	// return the given group's name
	function getName($number) {
		$groupdata = $this->getGroupData($number);
		if (!$groupdata == null)
			return $groupdata[5];
		// return null, if the group doesn't exist
		else 
			return null;
	}

	// return the given group's full data
	function getGroupData($number) {
		if (isSet($this->mygroups[$number]) && $this->mygroups[$number])
			return $this->mygroups[$number];
		// return null, if the group doesn't exist
		else
			return null;
	}
	
	// return number of existent groups
	function getGroupCount() {
		return sizeof($this->mygroups);
	}
	
	// return a sorted array of all groups
	function getAllGroups() {
		asort($this->mygroups);
		return $this->mygroups;
	}

	// return a group's right
	function getGroupRight($group, $right) {
		return $this->groupdata->get($group.".".$right);
	}

	// add group
	function addGroup($groupid, $anonymous, $changepw, $editcontent, $editusers, $member, $name, $replace) {
		return false;
		// --------------
		//  STILL TO DO!
		// --------------
		// - wenn anonymous, dann nicht alle anderen
		// - wenn changepw, dann editcontent
		// - wenn editcontent, dann changepw
		// - wenn editusers, dann editcontent und changepw
	}

	// delete group
	function deleteGroup($groupid) {
		return false;
		// --------------
		//  STILL TO DO!
		// --------------
		// nur, wenn kein benutzer dieser gruppe angehört!
	}
}
?>
