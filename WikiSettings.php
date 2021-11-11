<?php

/*

		moziloWiki - WikiSettings.php
		
		A lightweight settings management for moziloWiki. 
		Feel free to change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

require_once("Properties.php");

class WikiSettings {

	var $wikisettings;

	function __construct() {
		global $WIKIMGT_SETTINGS;
		$this->wikisettings = new Properties($WIKIMGT_SETTINGS);
	}

	// return the life time of the pages
	function getPagesLifeTime() {
		if ($this->wikisettings->get("pageslifetime") == "")
			return 0;
		else
			return $this->wikisettings->get("pageslifetime");
	}

	// set the life time of the pages
	function setPagesLifeTime($days) {
		return $this->wikisettings->set("pageslifetime", $days);
	}

	// return the life time of the files
	function getFilesLifeTime() {
		if ($this->wikisettings->get("fileslifetime") == "")
			return 0;
		else
			return $this->wikisettings->get("fileslifetime");
	}

	// set the life time of the files
	function setFilesLifeTime($days) {
		return $this->wikisettings->set("fileslifetime", $days);
	}

	// return the time of inactivity till logout
	function getUserIdleTime() {
		if (($this->wikisettings->get("useridletime") == "") || ($this->wikisettings->get("useridletime") == 0))
			return 15;
		else
			return $this->wikisettings->get("useridletime");
	}

	// set the time of inactivity till logout
	function setUserIdleTime($minutes) {
		return $this->wikisettings->set("useridletime", $minutes);
	}

	// return the pages' lock time
	function getPagesLockTime() {
		if (($this->wikisettings->get("pageslocktime") == "") || ($this->wikisettings->get("pageslocktime") == 0))
			return 15;
		else
			return $this->wikisettings->get("pageslocktime");
	}

	// set the pages' lock time
	function setPagesLockTime($minutes) {
		return $this->wikisettings->set("pageslocktime", $minutes);
	}
	
	// return default page pattern
	function getDefaultPagePattern() {
		return $this->wikisettings->get("defaultpagepattern");
	}

	// set default page pattern
	function setDefaultPagePattern($pattern) {
		return $this->wikisettings->set("defaultpagepattern", $pattern);
	}
	
	// return if the html tag is allowed
	function getUseHtmlTag() {
		return $this->wikisettings->get("usehtmltag");
	}

	// set if the html tag is allowed
	function setUseHtmlTag($value) {
		return $this->wikisettings->set("usehtmltag", $value);
	}
	
	// return wiki name
	function getWikiName() {
		return $this->wikisettings->get("wikiname");
	}

	// set wiki name
	function setWikiName($name) {
		return $this->wikisettings->set("wikiname", $name);
	}
	
	// return meta desc
	function getMetaDesc() {
		return $this->wikisettings->get("metadesc");
	}

	// set meta desc
	function setMetaDesc($name) {
		return $this->wikisettings->set("metades", $name);
	}
	
		// return meta keywords
	function getMetaKey() {
		return $this->wikisettings->get("metakey");
	}

	// set meta keywords
	function setMetaKey($name) {
		return $this->wikisettings->set("metakey", $name);
	}
	
	// return time format
	function getTimeFormat() {
		return $this->wikisettings->get("timeformat");
	}

	// set time format
	function setTimeFormat($string) {
		return $this->wikisettings->set("timeformat", $string);
	}

	// return if anonymous read access is allowed
	function getAnonymousAccess() {
		return $this->wikisettings->get("anonymousaccess");
	}

	// set if anonymous read access is allowed
	function setAnonymousAccess($value) {
		return $this->wikisettings->set("anonymousaccess", $value);
	}
	
	// return forbidden/allowed file extensions
	function getUploadExtensions() {
		return $this->wikisettings->get("uploadextensions");
	}

	// set forbidden/allowed file extensions
	function setUploadExtensions($string) {
		return $this->wikisettings->set("uploadextensions", $string);
	}
	
	// return the maximum upload filesize
	function getFilesMaxUploadSize() {
		if ($this->wikisettings->get("maxuploadsize") == "")
			return 0;
		else
			return $this->wikisettings->get("maxuploadsize");
	}

	// set the maximum upload filesize
	function setFilesMaxUploadSize($kb) {
		return $this->wikisettings->set("maxuploadsize", $kb);
	}

	// return if extensions are allowed
	function getUploadExtensionsAllowed() {
		if ($this->wikisettings->get("uploadextensionsallow") == "true")
			return true;
		return false;
	}

	// set if extensions are allowed
	function setUploadExtensionsAllowed($value) {
		return $this->wikisettings->set("uploadextensionsallow", $value);
	}
	
	// get if given page is an admin page
	function getIsAdminPage($page) {
		$adminpages = explode(",", $this->wikisettings->get("adminpages"));
		if (in_array($page, $adminpages))
			return true;
		return false;
	}
	
	// set if given page is an admin page
	function setIsAdminPage($page, $value) {
		$adminpages = explode(",", $this->wikisettings->get("adminpages"));
		if ($value) {
			if (!in_array($page, $adminpages))
				array_push($adminpages, $page);
		}
		else {
			if (in_array($page, $adminpages)) {
				// delete from array
				unset($adminpages[array_search($page, $adminpages)]);
				$adminpages = array_values($adminpages);
			}
		}
		$arraystring = "";
		foreach ($adminpages as $adminpage)
			if ($adminpage <> "")
				$arraystring .= $adminpage.",";
		return $this->wikisettings->set("adminpages", $arraystring);
	}
	
	// return default wiki style
	function getDefaultWikiStyle() {
		return $this->wikisettings->get("defaultstyle");
	}

	// set default wiki style
	function setDefaultWikiStyle($string) {
		return $this->wikisettings->set("defaultstyle", $string);
	}
	
	// return default wiki language
	function getDefaultWikiLanguage() {
		return $this->wikisettings->get("defaultlanguage");
	}

	// set default wiki language
	function setDefaultWikiLanguage($string) {
		return $this->wikisettings->set("defaultlanguage", $string);
	}
	
	// return number of characters for shortening a name
	function getShortenNameLength() {
		return $this->wikisettings->get("shortennamelength");
	}

	// set number of characters for shortening a name
	function setShortenNameLength($value) {
		return $this->wikisettings->set("shortennamelength", $value);
	}

	// return number of characters for shortening a name
	function getShortenLinkLength() {
		return $this->wikisettings->get("shortenlinklength");
	}

	// set number of characters for shortening a name
	function setShortenLinkLength($value) {
		return $this->wikisettings->set("shortenlinklength", $value);
	}

	// return if "users online list" is shown
	function getShowUsersOnlineList() {
		return $this->wikisettings->get("showusersonlinelist");
	}

	// set if "users online list" is shown
	function setShowUsersOnlineList($value) {
		return $this->wikisettings->set("showusersonlinelist", $value);
	}
	
	// return number of characters for shortening a name
	function getFailLogins() {
		return $this->wikisettings->get("failedloginsallowed");
	}

	// set number of characters for shortening a name
	function setFailLogins($value) {
		return $this->wikisettings->set("failedloginsallowed", $value);
	}

	// return number of characters for shortening a name
	function getFailLoginBanTime() {
		return $this->wikisettings->get("failedloginbantime");
	}

	// set number of characters for shortening a name
	function setFailLoginBanTime($value) {
		return $this->wikisettings->set("failedloginbantime", $value);
	}

}
?>
