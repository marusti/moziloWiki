<?php

/*

		moziloWiki - WikiUsers.php
		
		A lightweight user management for moziloWiki. Feel free to
		change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

require_once("Properties.php");
require_once("CSV.php");
require_once("Crypt.php");
require_once("WikiGlobals.php");

// Standard user: admin
$ADMIN_FULLNAME	= "Administrator";
$ADMIN_PASSWORD	= "admin";
$ADMIN_GROUP		= 1;
$ADMIN_LIFETIME	= 0;

class WikiUsers {
	
	// the arrays which contain the user data
	var $myusers = array();
	var $users;
	var $crypt;
	var $userdata;
	var $userstats;
	var $separator;

	// initialization: read users from files
	function __construct() {
		global $USRMGT_USERDATA;
		global $USRMGT_USERS;
		global $STATS_USERS;
		$this->users = new CSV($USRMGT_USERS);
		$this->crypt = new Crypt("Hehe you cracked me dude");
		$this->userdata = new Properties($USRMGT_USERDATA);
		$this->userstats = new Properties($STATS_USERS);
		$this->loadWikiUsers();
		$this->separator = ",";
	}
	
	// load data from files
	function loadWikiUsers()  {
		global $ADMIN_FULLNAME;
		global $ADMIN_PASSWORD;
		global $ADMIN_GROUP;
		global $ADMIN_LIFETIME;
		global $DEFAULT_WIKI_STYLE;
		// standard user: administrator
		if (!$this->users->exists("admin")) {// || ($this->users->count() == 0)) {
			$this->users->add("admin");
			$this->userdata->set("admin.fullname", $ADMIN_FULLNAME);
			$this->userdata->set("admin.password", $this->crypt->encrypt($ADMIN_PASSWORD));
			$this->userdata->set("admin.group", $ADMIN_GROUP);
			$this->userdata->set("admin.lifetime", $ADMIN_LIFETIME);
			$this->userdata->set("admin.wikistyle", $DEFAULT_WIKI_STYLE);
			$this->userdata->set("admin.showsmileys", true);
		}
		$users = $this->users->toArray();
		foreach ($users as $userid) {
			if ($userid == "")
				continue;
			$fullname = $this->userdata->get("$userid.fullname");
			$group = $this->userdata->get("$userid.group");
			$password = $this->crypt->decrypt($this->userdata->get("$userid.password"));
			$lifetime = $this->userdata->get("$userid.lifetime");

/*		
			THE NEXT LINES ARE EXPERIMENTAL AND SHOULD NOT BE UNCOMMENTED
			
			if ($fullname == "") {
				$fullname = ucfirst($userid);
				$this->userdata->set($userid.".fullname", $fullname);
			}
			if ($group == "") {
				$group = 3;
				$this->userdata->set($userid.".group", $group);
			}
			if ($password == "") {
				$password = $userid;
				$this->userdata->set($userid.".password", $this->crypt->encrypt($password));
			}
			if ($lifetime == "") {
				$lifetime = 0;
				$this->userdata->set($userid.".lifetime", $lifetime);
			}
*/

			$this->myusers[$userid] = array($password, $fullname, $group, $lifetime);
		}
	}

	// return the given user's password
	function getPassword($id) {
		$userdata = $this->getUserData($id);
		if (!$userdata == null)
			return $userdata[0];
		// return null, if the user doesn't exist
		else 
			return null;
	}

	// return the given user's password
	function setPassword($id, $password) {
		if ($password == "")
			$password = $id;
		$this->userdata->set($id.".password", $this->crypt->encrypt($password));
	}

	// return the given user's full name
	function getFullName($id) {
		$userdata = $this->getUserData($id);
		if (!$userdata == null)
			return $userdata[1];
		// return null, if the user doesn't exist
		else 
			return null;
	}
	
	// return the given user's status
	function getUserGroup($id) {
		$userdata = $this->getUserData($id);
		if (!$userdata == null)
			return $userdata[2];
		// return null, if the user doesn't exist
		else 
			return null;
	}
	
	// return the username of the given full name
	function getIDByFullName($fullname) {
		foreach ($this->myusers as $id => $userdata) {
			if ($userdata[1] == $fullname)
				return $id;
		}
		// return null, if the user doesn't exist
		return null;
	}
	
	// return whether user still has initial password
	function hasInitialPw($id) {
		if ($this->userstats->get("$id.initialpw") == "true")
			return true;
		else 
			return false;
	}
	
	// set that user still has initial password
	function setHasInitialPw($id, $value) {
		if ($id == "")
			return false;
		return $this->userstats->set("$id.initialpw", $value);
	}
	
	// return if user is online
	function isOnline($id) {
		if ($this->userstats->get("$id.isonline") == "true")
			return true;
		else
			return false;
	}
	
	// set user's online status
	function setIsOnline($id, $value) {
		if ($id == "")
			return false;
		return $this->userstats->set("$id.isonline", $value);
	}
	
	// return time of first action
	function getFirstAction($id) {
		return $this->userstats->get("$id.firstaction");
	}
	
	// set time of first action
	function setFirstAction($id, $value) {
		if ($id == "")
			return false;
		return $this->userstats->set("$id.firstaction", $value);
	}
	
	// return time of last action
	function getLastAction($id) {
		return $this->userstats->get("$id.lastaction");
	}
	
	// set time of last action
	function setLastAction($id, $value) {
		if ($id == "")
			return false;
		return $this->userstats->set("$id.lastaction", $value);
	}
	
	// return login count
	function getLoginCount($id) {
		if ($this->userstats->get("$id.logincount") == "")
			return 0;
		else
			return $this->userstats->get("$id.logincount");
	}
	
	// set login count
	function setLoginCount($id, $value) {
		if ($id == "")
			return false;
		return $this->userstats->set("$id.logincount", $value);
	}
	
	// return count of erroneous logins
	function getFalseLoginCount($id) {
		if ($this->userstats->get("$id.falselogincount") == "")
			return 0;
		else
			return $this->userstats->get("$id.falselogincount");
	}
	
	// set count of erroneous logins
	function setFalseLoginCount($id, $value) {
		if ($id == "")
			return false;
		return $this->userstats->set("$id.falselogincount", $value);
	}
	
	// return "showsmileys"
	function getShowSmileys($id) {
		if ($this->userdata->get("$id.showsmileys") == "true")
			return true;
		else
			return false;
	}

	// set "showsmileys"
	function setShowSmileys($id, $value) {
		if ($id == "")
			return false;
		return $this->userdata->set("$id.showsmileys", $value);
	}
	
	// return "cssfile"
	function getWikiStyle($id) {
		global $DEFAULT_WIKI_STYLE;
		if ($this->userdata->get("$id.wikistyle") <> "")
			return $this->userdata->get("$id.wikistyle");
		else
			return $DEFAULT_WIKI_STYLE;
			
	}

	// set "cssfile"
	function setWikiStyle($id, $value) {
		if ($id == "")
			return false;
		return $this->userdata->set("$id.wikistyle", $value);
	}
	
	// return "languagefile"
	function getWikiLanguage($id) {
		return $this->userdata->get("$id.languagefile");
	}

	// set "languagefile"
	function setWikiLanguage($id, $value) {
		if ($id == "")
			return false;
		return $this->userdata->set("$id.languagefile", $value);
	}
	
	// return "lifetime"
	function getLifeTime($id) {
		return $this->userdata->get("$id.lifetime");
	}

	// set "cssfile"
	function setLifeTime($id, $value) {
		if ($id == "")
			return false;
		return $this->userdata->set("$id.lifetime", $value);
	}
	
	// return ban status
	function getIsBanned($id) {
		if ($this->userdata->get("$id.isbanned") == "false")
			return "0";
		else
			return $this->userdata->get("$id.isbanned");
	}
	
	// set ban status
	function setIsBanned($id, $value) {
		if ($id == "")
			return false;
		return $this->userdata->set("$id.isbanned", $value);
	}
	
	// return ban status
	function getBanTime($id) {
		if ($this->userdata->get("$id.bantime") == "")
			return 0;
		else
			return $this->userdata->get("$id.bantime");
	}
	
	// set ban status
	function setBanTime($id, $value) {
		if ($id == "")
			return false;
		return $this->userdata->set("$id.bantime", $value);
	}
	
	// return an array of user's favourites
	function getFavouritesArray($id) {
		if ($id == "")
			return array();
		$array = explode(",", $this->userdata->get("$id.favourites"));
		if ((count($array) == 1) && ($array[0] == ""))
			return array();
		return $array;
	}

	// set user's favourites from given array
	function setFavourites($id, $array) {
		$favouritesstring = "";
		foreach ($array as $fav)
			if ($fav <> "")
				$favouritesstring .= $fav.$this->separator;
		$favouritesstring = substr($favouritesstring, 0, strlen($favouritesstring)-strlen($this->separator));
		return $this->userdata->set($id.".favourites", $favouritesstring);
	}

	// add one to user's favourites
	function addFavourite($id, $page) {
		$array = $this->getFavouritesArray($id);
		if (!in_array($page, $array)) 
			array_push($array, $page);
		usort($array, "strnatcasecmp");
		return $this->setFavourites($id, $array);
	}

	// delete one of user's favourites
	function deleteFavourite($id, $page) {
		$array = $this->getFavouritesArray($id);
		if (in_array($page, $array)) {
			unset($array[array_search($page, $array)]);
			$array = array_values($array);
			sort($array);
			return $this->setFavourites($id, $array);
		}
		else
			return false;
	}

	
	// return the given user's full data
	function getUserData($id) {
		if (isSet($this->myusers[$id]) && $this->myusers[$id])
			return $this->myusers[$id];
		// return null, if the user doesn't exist
		else
			return null;
	}
	
	// return a sorted array of all users matching the query
	function getAllUsers($query) {
		ksort($this->myusers);
		if ($query == "")
			return $this->myusers;
		$matchingusers = array();
		foreach ($this->myusers as $user=>$userdata) {
			if (
					// matching id
					(substr_count(strtolower($user), strtolower($query)) > 0)
					// matching fullname
					|| (substr_count(strtolower($userdata[1]), strtolower($query)) > 0)
					) {
				$matchingusers[$user] = $this->getUserData($user);
			}
		}
		
		//$this->myusers[$userid] = array($password, $fullname, $group, $lifetime);
		ksort($matchingusers);
		return $matchingusers;
	}

	// return user existance
	function userExists($user) {
		return $this->users->exists($user);
	}

	// add user
	function addUser($userid, $password, $fullname, $group, $lifetime, $isbanned, $bantime, $defaultstyle, $replace) {
		global $DEFAULT_WIKI_LANG;
		if ($userid == "")
			return false;
		if ($fullname == "")
			$fullname = ucfirst($userid);
		// check for duplicate ids and fullnames
		foreach ($this->myusers as $id => $userdata) {
			if (($id <> $userid) && ($this->userdata->valueExists("^$id\.fullname\$", "^$fullname\$")))
				return false;
		}
		// write to cvs
		if (!$this->users->exists($userid))
			$this->users->add($userid);
		elseif (!$replace)
				return false;
		// write to properties
		if ($password == "")
			$password = $userid;
		$this->userdata->set($userid.".password", $this->crypt->encrypt($password));
		$this->userdata->set($userid.".fullname", $fullname);
		if ($group == "")
			$group = 3;
		$this->userdata->set($userid.".group", $group);
		$this->userdata->set($userid.".lifetime", $lifetime);
		$this->userdata->set($userid.".isbanned", $isbanned);
		$this->userdata->set($userid.".bantime", $bantime);
		$this->userdata->set($userid.".wikistyle", $defaultstyle);
		// set defaults for new users
		if (!$replace) {
			$this->setHasInitialPw($userid, "true");
			$this->setWikiStyle($userid, $defaultstyle);
			$this->setWikiLanguage($userid, $DEFAULT_WIKI_LANG);
			$this->setShowSmileys($userid, "true");
			$this->setIsBanned($userid, "false");
			$this->setBanTime($userid, "0");
		}
		return true;
	}

	// delete user
	function deleteUser($userid) {
		if ($userid == "")
			return false;
		// delete from users
		if (!$this->users->delete($userid))
			return false;
		// delete from userdata
		$this->userdata->delete($userid.".bantime");
		$this->userdata->delete($userid.".cssfile");
		$this->userdata->delete($userid.".fullname");
		$this->userdata->delete($userid.".group");
		$this->userdata->delete($userid.".isbanned");
		$this->userdata->delete($userid.".languagefile");
		$this->userdata->delete($userid.".lifetime");
		$this->userdata->delete($userid.".password");
		$this->userdata->delete($userid.".showsmileys");
		$this->userdata->delete($userid.".wikistyle");
		$this->userdata->delete($userid.".favourites");
		//delete from userstats
		$this->userstats->delete($userid.".firstaction");
		$this->userstats->delete($userid.".initialpw");
		$this->userstats->delete($userid.".isonline");
		$this->userstats->delete($userid.".lastaction");
		$this->userstats->delete($userid.".logincount");
		$this->userstats->delete($userid.".falselogincount");
		// delete from array
		unset($this->myusers[$userid]);
		return true;
	}
	
	// return the number of registered users
	function getUserCount() {
		return count($this->users->toArray());
	}
	
	// return the latest registered user
	function getLatestUser() {
		$latestuser = "";
		$allusers = $this->getAllUsers("");
		$usersfirstactionsarray = array();
		foreach ($allusers as $id => $userdata) {
			$usersfirstactionsarray[$id] = $this->getFirstAction($id);
		}
		// latest is last
		asort($usersfirstactionsarray);
		foreach($usersfirstactionsarray as $user => $time) 
			$latestuser = $user;
		return $latestuser;
	}

	// return user with most logins
	function getMostLoginsUser() {
		$allusers = $this->getAllUsers("");
		$alluserslogins = array();
		$mostloginsusers = array();
		foreach ($allusers as $id => $userdata) {
			$alluserslogins[$id] = $this->getLoginCount($id);
		}
		arsort($alluserslogins);
		$highestcount = 0;
		// go through array and add most logged in users (can be more than one at the same time)
		foreach ($alluserslogins as $user => $count) {
			if ($count >= $highestcount) {
				$mostloginsusers[$user] = $count;
				$highestcount = $count;
			}
		}
		// sort and return
		natcasesort($mostloginsusers);
		return $mostloginsusers;
	}
}
?>
