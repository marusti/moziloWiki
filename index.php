<?php
/*

		moziloWiki - index.php
		
		A small flatfile-based wiki with lightweight user management. 
		moziloWiki bases on the free roWiki by Marc Rohlfing and is 
		distributed under GPL (see license.txt for details). Feel 
		free to suit it to your personal purposes.
		
		moziloWiki 2021 <kontakt@mozilo.de>
		
*/

	// finish script even if user connection broke
	ignore_user_abort(true);

	// Initial: Fehlerausgabe unterdrücken, um Path-Disclosure-Attacken ins Leere laufen zu lassen
	@ini_set("display_errors", 0);

	require_once("WikiBacklinks.php");			// wiki backlinks
	require_once("WikiGlobals.php");				// global wiki data
	require_once("WikiGroups.php");					// wiki group data
	require_once("WikiLanguage.php");				// wiki language
	require_once("WikiPageversions.php");		// wiki page versions
	require_once("WikiSettings.php");				// wiki main settings
	require_once("WikiSmileys.php");				// wiki smileys
	require_once("WikiStatistics.php");			// wiki statistics
	require_once("WikiStringCleaner.php"); 	// clean string from special characters functions
	require_once("WikiSyntax.php");					// wiki syntax conversion
	require_once("WikiUsers.php");					// wiki user data

	session_start();
	
	// Session Fixation durch Vergabe einer neuen Session-ID beim ersten Login verhindern
	if (!isset($_SESSION['PHPSESSID'])) {
		session_regenerate_id(true);
		$_SESSION['PHPSESSID'] = true;
	}

	// DEEEEEEEEEEEBUG ;)
	// Return all given values for debugging
	
	/*
	echo "<h2>POST</h2>";
	echo "<h2>GET</h2>";
	foreach ($_GET as $a => $b)
		echo $a." -> ".$b."<br />";
	foreach ($_POST as $a => $b)
		echo $a." -> ".$b."<br />";
	echo "<h2>SESSION</h2>";
	foreach ($_SESSION as $a => $b)
		echo $a." -> ".$b."<br />";
	*/

// get action parameter
	$ACTION = strip_tags(htmlentities($_GET["action"]));
	
// MAIN SETTINGS
	$mainsettings = new WikiSettings();

// USER MANAGEMENT
	$wikiusers = new WikiUsers();
	$wikigroups = new WikiGroups();
	// get current user
	$CURRENTUSER = $_SESSION['username'];
	if ($CURRENTUSER == "") {
		// if anonymous acess isn't allowed, send to login
		if ($mainsettings->getAnonymousAccess() == "true") {
			$GRP_ANONYMOUS = true;
			$GRP_CHANGEPW = false;
			$GRP_EDITCONTENT = false;
			$GRP_EDITUSERS = false;
		}
		else
			header("location:login.php?logout=true");
	}
	
	// prepare statistics
	$wikistats = new WikiStatistics();

	// look for users inactive more than setted value or with expired lifetime
	foreach ($wikiusers->getAllUsers($query) as $key => $value) {
		// kick inactive users
		if (($wikiusers->isOnline($key)) && (time() - $wikiusers->getLastAction($key) > $mainsettings->getUserIdleTime()*60))
			$wikiusers->setIsOnline($key, "false");
		// ban expired users permenantly
		if (
			($wikiusers->getLifeTime($key) <> 0)
			&& ($wikiusers->getLifeTime($key) <> "")
			&& ($wikiusers->getFirstAction($key) <> "")
			&& ((time() - $wikiusers->getFirstAction($key)) > ($wikiusers->getLifeTime($key)*24*60*60))
			) {
			$wikiusers->setIsBanned($key, time());
			$wikiusers->setBanTime($key, 0);
		}
		// unban user, if bantime expired
		if (($wikiusers->getIsBanned($key) > 0) && ($wikiusers->getBanTime($key) > 0) && (time() - $wikiusers->getIsBanned($key) > $wikiusers->getBanTime($key)*60)) {
			$wikiusers->setIsBanned($key, 0);
			$wikiusers->setBanTime($key, 0);
		}
	}

	if (!$GRP_ANONYMOUS) {
		// get the user group's rights
		$GRP_ANONYMOUS 		= $wikigroups->isAnonymous($wikiusers->getUserGroup($CURRENTUSER));
		$GRP_CHANGEPW 		= $wikigroups->canChangePw($wikiusers->getUserGroup($CURRENTUSER));
		$GRP_EDITCONTENT	= $wikigroups->canEditContent($wikiusers->getUserGroup($CURRENTUSER));
		$GRP_EDITUSERS 		= $wikigroups->canEditUsers($wikiusers->getUserGroup($CURRENTUSER));

		// if user still has his initial pw, show pw change form after login
		if (($wikiusers->hasInitialPw($CURRENTUSER)) && ($_GET['login'] == "true") && (!$GRP_ANONYMOUS) && ($ACTION <> "changepw")) {
			header("location:index.php?action=changepw");
		}

		// write first login to stats
		if ($wikiusers->getFirstAction($CURRENTUSER) == "")
			$wikiusers->setFirstAction($CURRENTUSER, time());

		// kick user immediately, if banned
		if ($wikiusers->getIsBanned($CURRENTUSER)) {
			$wikiusers->setIsOnline($CURRENTUSER, "false");
			header("location:login.php?logout=true");
		}
	}

	// prepare backlink data
	$wikibacklinks = new WikiBacklinks();
	
	// prepare smileys
	$wikismileys = new WikiSmileys();

	// prepare language
	$lang = $wikiusers->getWikiLanguage($CURRENTUSER);
	if ($lang == "")
		$lang = $mainsettings->getDefaultWikiLanguage();
	$wikilanguage = new WikiLanguage($lang.".prop");
	$wikistringcleaner = new WikiStringCleaner($wikilanguage->get("LANG_SPECIALCHARS"), $wikilanguage->get("LANG_REPLACEMENT"));
	
	if (!$GRP_ANONYMOUS) {
		// show login page, if "isonline" in users.stats is false and it's not the initial login
		if ((!$wikiusers->isOnline($CURRENTUSER)) && (!$_GET['login'] == "true"))
			header("location:login.php?logout=true");

		// Check login
		if (isset($_SESSION['login_okay']) && ($_SESSION['login_okay'] == true)) {
			$wikiusers->setIsOnline($CURRENTUSER, "true");
		}
		else
			header("location:login.php?logout=true&page=".htmlentities($_GET['page'])."&action=$ACTION");
	
		// Increase login counter
		if (($_GET['login'] == "true") && (!$wikiusers->getIsBanned($CURRENTUSER))) {
			$wikiusers->setLoginCount($CURRENTUSER, $wikiusers->getLoginCount($CURRENTUSER)+1);
		}

		$wikiusers->setLastAction($CURRENTUSER, date("U"));
		$wikiusers->setIsOnline($CURRENTUSER, "true");
	}
	
	// look for expired lockfiles and delete them
	$locksdir = opendir(getcwd() . "/$DIR_LOCKS");
	while ($file = readdir($locksdir)) {
		if (($file <> ".") && ($file <> "..")) {
			if (
				(time() - filemtime($DIR_LOCKS . $file) > $mainsettings->getPagesLockTime()*60) 	// if a lockfile is older than setted value...
				|| (!$wikiusers->isOnline(getLocker(substr($file, 0, strlen($file)-strlen($DEFAULT_LOCKSEXT))))) // or locking user isn't online anymore
				)
				unlink("$DIR_LOCKS$file");						// ...then delete it.
			}
	}
    
	// look for expired pages and delete them
	$pagesdir = opendir(getcwd() . "/$DIR_PAGES");
	while (($mainsettings->getPagesLifeTime() <> 0) && ($file = readdir($pagesdir))) {
		if (($file <> ".") && ($file <> "..")) {
			if ((time() - filemtime($DIR_PAGES . $file)) > ($mainsettings->getPagesLifeTime()*24*60*60)) { 	// if a page is older than the setted time...
				deleteFile("page", substr($file, 0, strlen($file)-strlen($DEFAULT_PAGESEXT)));
			}
		}
	}

	// look for expired files and delete them
	$filesdir = opendir(getcwd() . "/$DIR_FILES");
	while (($mainsettings->getFilesLifeTime() <> 0) && ($file = readdir($filesdir))) {
		if (($file <> ".") && ($file <> "..")) {
			if ((time() - filemtime($DIR_FILES . $file)) > ($mainsettings->getFilesLifeTime()*24*60*60)) { 	// if a file is older than the setted time...
				deleteFile("file", $file);
			}
		}
	}

	// prepare syntax
	$wikisyntax = new WikiSyntax($DEFAULT_PAGESEXT,$DIR_FILES,$DIR_PAGES,$GRP_ANONYMOUS,$GRP_EDITCONTENT,$wikilanguage,$wikistringcleaner,false);

	// determine page to display
	if (!$PAGE_TITLE = stripslashes(strip_tags(htmlentities($_GET["page"])))) {
		if (($ACTION == "searchpages") || ($ACTION == "searchfiles") || ($ACTION == "searchusers"))
			$PAGE_TITLE = $wikilanguage->get("LANG_SEARCHRESULTSFOR")." \"".strip_tags(htmlentities($_GET[query]))."\"";
		elseif ($ACTION == "recentpages")
			$PAGE_TITLE = $wikilanguage->get("LANG_RECENTPAGES");
		elseif ($ACTION == "recentfiles")
			$PAGE_TITLE = $wikilanguage->get("LANG_RECENTFILES");
		elseif ($ACTION == "trashfiles")
			$PAGE_TITLE = $wikilanguage->get("LANG_TRASHFILES");
		elseif ($ACTION == "trashpages")
			$PAGE_TITLE = $wikilanguage->get("LANG_TRASHPAGES");
		elseif ($ACTION == "allpages")
			$PAGE_TITLE = $wikilanguage->get("LANG_ALLPAGES");
		elseif ($ACTION == "orphanedpages")
			$PAGE_TITLE = $wikilanguage->get("LANG_ORPHANEDPAGES");
		elseif ($ACTION == "pageversions")
			$PAGE_TITLE = $wikilanguage->get("LANG_PAGEVERSIONS");
		elseif ($ACTION == "allfiles")
			$PAGE_TITLE = $wikilanguage->get("LANG_ALLFILES");
		elseif (($ACTION == "upload") || ($_POST["upload"] == "true"))
			$PAGE_TITLE = $wikilanguage->get("LANG_UPLOAD");
		elseif ($ACTION == "allusers")
			$PAGE_TITLE = $wikilanguage->get("LANG_ALLUSERS");
		elseif ($ACTION == "edituser")
			$PAGE_TITLE = $wikilanguage->get("LANG_EDITUSERS");
		elseif (($ACTION == "changepw") || ($_POST["changepw"] == "true"))
			$PAGE_TITLE = $wikilanguage->get("LANG_CHANGEPW");
		elseif ($ACTION == "usersettings")
			$PAGE_TITLE = $wikilanguage->get("LANG_USERSETTINGS");
		elseif ($ACTION == "wikisettings")
			$PAGE_TITLE = $wikilanguage->get("LANG_WIKISETTINGS");
		elseif ($ACTION == "editpattern")
			$PAGE_TITLE = $wikilanguage->get("LANG_EDITPATTERNS");
		elseif ($ACTION == "fileinfo")
			$PAGE_TITLE = $wikilanguage->get("LANG_FILEINFO");
		elseif ($ACTION == "statistics")
			$PAGE_TITLE = $wikilanguage->get("LANG_STATISTICS");
		else
			$PAGE_TITLE = $DEFAULT_STARTPAGE;
	}

	// prepare page version management
	$wikipageversions = new WikiPageversions($DIR_BACKUP);

	// catch malicious paths
	if (preg_match("/[\/|\.|\\\]/", htmlentities($_GET["page"])))
		$PAGE_TITLE = $DEFAULT_STARTPAGE;

	// if user cancelled an edit operation, delete lockfile
	if ($_GET["unlock"] == true) {
		if (file_exists($DIR_LOCKS.$PAGE_TITLE.$DEFAULT_LOCKSEXT))
			unlink($DIR_LOCKS.$PAGE_TITLE.$DEFAULT_LOCKSEXT);
		if (!file_exists($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT))
			$PAGE_TITLE = $DEFAULT_STARTPAGE;
	}

// write changes to page, if there are any
	if ($_POST["unlock"] == true)
		if (file_exists($_POST["lockfile"]))
			unlink($_POST["lockfile"]);
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		// change user's password
		if ($_POST["changepw"] == "true") {
			$ACTION = "changepw";
		}
		// upload files
		elseif ($_POST["upload"] == "true") {
			$ACTION = "upload";
		}
		else {
			$page = $wikistringcleaner->cleanThatString(htmlentities($_POST["page"]), false);
			if (($page == "") || (!$file = @fopen($DIR_PAGES.$page.$DEFAULT_PAGESEXT, "w"))) 
				die("Could not write page $page!");
			if (get_magic_quotes_gpc())
				fputs($file, trim(stripslashes($_POST['content'])));
			else
				fputs($file, trim($_POST['content']));
			fclose($file);
			// statistics: save changer
			$wikistats->setLastPageChanger($page, $CURRENTUSER);
			// save if page is a admin page
			if ($_POST['adminpage'] == "on")
				$mainsettings->setIsAdminPage($page, true);
			else
				$mainsettings->setIsAdminPage($page, false);
			if ($DIR_BACKUP <> '') {
				// write backup of old version to file
				savePageBackup($page, "");
				$wikipageversions->setChangerOfTime(time(), $page, $CURRENTUSER);
			}
			header("location:".urlencode(stripslashes($page)));
		}
	}

	// get user's wiki style
	if (!$GRP_ANONYMOUS) {
		$currentwikistyle = $wikiusers->getWikiStyle($CURRENTUSER);
		// if style path doesn't exist, use default wiki style and set it as user style
		if (!file_exists($MAINDIR_STYLES.$wikiusers->getWikiStyle($CURRENTUSER))) {
			$currentwikistyle = $mainsettings->getDefaultWikiStyle();
			$wikiusers->setWikiStyle($CURRENTUSER, $currentwikistyle);
		}
	}
	// else use default wiki style
	else
		$currentwikistyle = $mainsettings->getDefaultWikiStyle();
 
 	// Read and parse template
	if (!$file = @fopen($MAINDIR_STYLES.$currentwikistyle."/".$TEMPLATE_FILE, "r"))
		die("Could not read template file $TEMPLATE_FILE!");
	$template = fread($file, filesize($MAINDIR_STYLES.$currentwikistyle."/".$TEMPLATE_FILE));
	fclose($file);
	$template = preg_replace("/{STYLE_PATH}/", $MAINDIR_STYLES.$currentwikistyle."/".$CSS_FILE, $template);


	if (file_exists($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT)) {
		$BACKLINKS = showBacklinks($PAGE_TITLE);
	}
	else {
		$BACKLINKS = "";
	}
	

// Read page contents and time of last change
	if (($file = @fopen($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT, "r")) || $ACTION <> "") {
		$TIME = strftime($mainsettings->getTimeFormat(), @filemtime($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT));
		$PRINTLINK = "<a href=\"print.php?page=".$PAGE_TITLE."\" target=\"_blank\" title=\"".$wikilanguage->get("LANG_SHOWPRINTVIEW").": &quot;$PAGE_TITLE&quot;\" accesskey=\"p\"><img src=\"pic/printpageicon.gif\" alt=\"".$wikilanguage->get("LANG_SHOWPRINTVIEW")."\" /></a>";
		$CONTENT = "\n" . @fread($file, @filesize($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT)) . "\n";
		@fclose($file);
	}
	// if page not existent, open it in edit mode
	else {
		$ACTION = "edit";
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}

// Determine access mode
	if ($ACTION == "trashfiles")
  	if (
			// trash available to delete...
			(returnQuery($DIR_TRASHFILES, "", false, false, false, 0) <> null)
			&& 
			// ...last trashfile is not being to removed manually (a few lines later)...
			!((count(returnQuery($DIR_TRASHFILES, "", false, false, false, 0)) == 1) && ($_GET["restore"] == "true"))
			&&
			// ...the whole trash directory is not being to be cleared (a few lines later)
			!($_GET["emptytrash"] == "true")
			&&
			// and current user is admin
			$GRP_EDITUSERS
			)
			$html = preg_replace('/{EDIT}/', "<a href=\"index.php?action=trashfiles&amp;emptytrash=true\">".$wikilanguage->get("LANG_DELETEALL")."</a>", $template);
		else
			$html = preg_replace('/{EDIT}/', getNoActionString($wikilanguage->get("LANG_NOACTION")), $template);
	elseif ($ACTION == "trashpages")
		if (
			// trash available to delete...
			(returnQuery($DIR_TRASHPAGES, "", false, false, false, 0) <> null)
			&& 
			// ...last trashfile is not being to removed manually (a few lines later)...
			!((count(returnQuery($DIR_TRASHPAGES, "", false, false, false, 0)) == 1) && ($_GET["restore"] == "true"))
			&&
			// ...the whole trash directory is not being to be cleared (a few lines later)
			!($_GET["emptytrash"] == "true")
			&&
			// and current user is admin
			$GRP_EDITUSERS
			)
			$html = preg_replace('/{EDIT}/', "<a href=\"index.php?action=trashpages&amp;emptytrash=true\">".$wikilanguage->get("LANG_DELETEALL")."</a>", $template);
		else
			$html = preg_replace('/{EDIT}/', getNoActionString($wikilanguage->get("LANG_NOACTION")), $template);

	elseif (($ACTION == "recentfiles") || ($ACTION == "recentpages"))
		$html = preg_replace('/{EDIT}/', "<a href=\"index.php?action=$ACTION&amp;count=10\">10</a> | <a href=\"index.php?action=$ACTION&amp;count=20\">20</a> | <a href=\"index.php?action=$ACTION&amp;count=50\">50</a>", $template);

	else if (($ACTION == "edit") || ($ACTION <> "")) {
		$html = preg_replace('/{EDIT}/', getNoActionString($wikilanguage->get("LANG_NOACTION")), $template);
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}
	elseif (fileIsReadonly($DIR_PAGES, $PAGE_TITLE.$DEFAULT_PAGESEXT))
		$html = preg_replace('/{EDIT}/', $wikilanguage->get("LANG_ISPROTECTED"), $template);
	elseif (!$GRP_EDITCONTENT)
		$html = preg_replace('/{EDIT}/', getNoActionString($wikilanguage->get("LANG_NOACTION")), $template);
	elseif (entryIsLocked($PAGE_TITLE))
		$html = preg_replace('/{EDIT}/', $wikilanguage->get("LANG_EDITLINKLOCKED")." ".$wikiusers->getFullName(getLocker($PAGE_TITLE)), $template);
	elseif ($mainsettings->getIsAdminPage($PAGE_TITLE) && !$GRP_EDITUSERS)
		$html = preg_replace('/{EDIT}/', getNoActionString($wikilanguage->get("LANG_NOACTION")), $template);
	else
		$html = preg_replace('/{EDIT}/', "<a href=\"$PAGE_TITLE&amp;action=edit\" title=\"".$wikilanguage->get("LANG_EDITLINK").": &quot;$PAGE_TITLE&quot;\" accesskey=\"e\"><img src=\"pic/editpageicon.gif\" alt=\"".$wikilanguage->get("LANG_EDITLINK")."\" /></a>", $template);
	
	if ($GRP_ANONYMOUS)
		$html = preg_replace('/{RECENT_PAGES}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{RECENT_PAGES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_RECENTPAGES")."</em>", $html);
	elseif ($ACTION == "recentpages")
		$html = preg_replace('/{RECENT_PAGES}/', "<a href=\"index.php?action=recentpages&amp;count=$DEFAULT_RECENTCOUNT\" class=\"activemenupoint\">".$wikilanguage->get("LANG_RECENTPAGES")."</a>", $html);
	else
		$html = preg_replace('/{RECENT_PAGES}/', "<a href=\"index.php?action=recentpages&amp;count=$DEFAULT_RECENTCOUNT\">".$wikilanguage->get("LANG_RECENTPAGES")."</a>", $html);
	
	if ($GRP_ANONYMOUS)
		$html = preg_replace('/{RECENT_FILES}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{RECENT_FILES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_RECENTFILES")."</em>", $html);
	elseif ($ACTION == "recentfiles")
		$html = preg_replace('/{RECENT_FILES}/', "<a href=\"index.php?action=recentfiles&amp;count=$DEFAULT_RECENTCOUNT\" class=\"activemenupoint\">".$wikilanguage->get("LANG_RECENTFILES")."</a>", $html);
	else
		$html = preg_replace('/{RECENT_FILES}/', "<a href=\"index.php?action=recentfiles&amp;count=$DEFAULT_RECENTCOUNT\">".$wikilanguage->get("LANG_RECENTFILES")."</a>", $html);
	
	if (!$GRP_EDITCONTENT)
		$html = preg_replace('/{TRASH_FILES}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{TRASH_FILES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_TRASHFILES")."</em>", $html);
	elseif ($ACTION == "trashfiles")
		$html = preg_replace('/{TRASH_FILES}/', "<a href=\"index.php?action=trashfiles\" class=\"activemenupoint\">".$wikilanguage->get("LANG_TRASHFILES")."</a>", $html);
	else
		$html = preg_replace('/{TRASH_FILES}/', "<a href=\"index.php?action=trashfiles\">".$wikilanguage->get("LANG_TRASHFILES")."</a>", $html);
	
	if (!$GRP_EDITCONTENT)
		$html = preg_replace('/{TRASH_PAGES}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{TRASH_PAGES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_TRASHPAGES")."</em>", $html);
	elseif ($ACTION == "trashpages")
		$html = preg_replace('/{TRASH_PAGES}/', "<a href=\"index.php?action=trashpages\" class=\"activemenupoint\">".$wikilanguage->get("LANG_TRASHPAGES")."</a>", $html);
	else
		$html = preg_replace('/{TRASH_PAGES}/', "<a href=\"index.php?action=trashpages\">".$wikilanguage->get("LANG_TRASHPAGES")."</a>", $html);

	if ($ACTION == "allpages")
		$html = preg_replace('/{ALL_PAGES}/', "<a href=\"index.php?action=allpages\" class=\"activemenupoint\">".$wikilanguage->get("LANG_ALLPAGES")."</a>", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{ALL_PAGES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_ALLPAGES")."</em>", $html);
	else
		$html = preg_replace('/{ALL_PAGES}/', "<a href=\"index.php?action=allpages\">".$wikilanguage->get("LANG_ALLPAGES")."</a>", $html);

	if ($GRP_ANONYMOUS)
		$html = preg_replace('/{ORPHANED_PAGES}/', "", $html);
	elseif ($ACTION == "orphanedpages")
		$html = preg_replace('/{ORPHANED_PAGES}/', "<a href=\"index.php?action=orphanedpages\" class=\"activemenupoint\">".$wikilanguage->get("LANG_ORPHANEDPAGES")."</a>", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{ORPHANED_PAGES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_ORPHANEDPAGES")."</em>", $html);
	else
		$html = preg_replace('/{ORPHANED_PAGES}/', "<a href=\"index.php?action=orphanedpages\">".$wikilanguage->get("LANG_ORPHANEDPAGES")."</a>", $html);

	if ($ACTION == "allfiles")
		$html = preg_replace('/{ALL_FILES}/', "<a href=\"index.php?action=allfiles\" class=\"activemenupoint\">".$wikilanguage->get("LANG_ALLFILES")."</a>", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{ALL_FILES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_ALLFILES")."</em>", $html);
	else
		$html = preg_replace('/{ALL_FILES}/', "<a href=\"index.php?action=allfiles\">".$wikilanguage->get("LANG_ALLFILES")."</a>", $html);

	if (!$GRP_EDITCONTENT)
		$html = preg_replace('/{ALL_USERS}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{ALL_USERS}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_ALLUSERS")."</em>", $html);
	elseif ($ACTION == "allusers")
		$html = preg_replace('/{ALL_USERS}/', "<a href=\"index.php?action=allusers\" class=\"activemenupoint\">".$wikilanguage->get("LANG_ALLUSERS")."</a>", $html);
	else
		$html = preg_replace('/{ALL_USERS}/', "<a href=\"index.php?action=allusers\">".$wikilanguage->get("LANG_ALLUSERS")."</a>", $html);

	if (!$GRP_EDITCONTENT)
		$html = preg_replace('/{SEARCH_USERS}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{SEARCH_USERS}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("BUTTON_SEARCHUSERS")."</em>", $html);
	else
		$html = preg_replace('/{SEARCH_USERS}/', "<form name=\"searchusers\" method=\"get\" action=\"index.php\"><input type=\"hidden\" name=\"action\" value=\"searchusers\" /><input class=\"menutext\" type=\"text\" name=\"query\" value=\"\" /><input class=\"menubutton\" type=\"submit\" value=\"".$wikilanguage->get("BUTTON_SEARCHUSERS")."\" /></form>", $html);

	if (!$GRP_EDITCONTENT)
		$html = preg_replace('/{UPLOAD}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{UPLOAD}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_UPLOAD")."</em>", $html);
	elseif ($ACTION == "upload")
		$html = preg_replace('/{UPLOAD}/', "<a href=\"index.php?action=upload\" class=\"activemenupoint\">".$wikilanguage->get("LANG_UPLOAD")."</a>", $html);
	else
		$html = preg_replace('/{UPLOAD}/', "<a href=\"index.php?action=upload\">".$wikilanguage->get("LANG_UPLOAD")."</a>", $html);

// put values into template
	$html = preg_replace('/{PAGE_TITLE}/', "$PAGE_TITLE", $html);
	if ($ACTION == "edit")
		$html = preg_replace('/{HOME}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_STARTPAGE")."</em>", $html);
	elseif ($PAGE_TITLE == $DEFAULT_STARTPAGE)
		$html = preg_replace('/{HOME}/', "<a href=\"index.php\" class=\"activemenupoint\">".$wikilanguage->get("LANG_STARTPAGE")."</a>", $html);
	else
		$html = preg_replace('/{HOME}/', "<a href=\"index.php\">".$wikilanguage->get("LANG_STARTPAGE")."</a>", $html);

	if (!$GRP_EDITCONTENT)
			$html = preg_replace('/{TESTPAGE}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{TESTPAGE}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_TESTPAGE")."</em>", $html);
	elseif (!file_exists($DIR_PAGES.$DEFAULT_TESTPAGE.$DEFAULT_PAGESEXT))
		$html = preg_replace('/{TESTPAGE}/', "<a href=\"$DEFAULT_TESTPAGE\" class=\"pending\">".$wikilanguage->get("LANG_TESTPAGE")."</a>", $html);
	elseif ($PAGE_TITLE == $DEFAULT_TESTPAGE)
		$html = preg_replace('/{TESTPAGE}/', "<a href=\"$DEFAULT_TESTPAGE\" class=\"activemenupoint\">".$wikilanguage->get("LANG_TESTPAGE")."</a>", $html);
	else
		$html = preg_replace('/{TESTPAGE}/', "<a href=\"$DEFAULT_TESTPAGE\">".$wikilanguage->get("LANG_TESTPAGE")."</a>", $html);

	if (!file_exists($DIR_PAGES.$DEFAULT_FUNCTIONSPAGE.$DEFAULT_PAGESEXT))
		$html = preg_replace('/{FUNCTIONS}/', "<a href=\"$DEFAULT_FUNCTIONSPAGE\" class=\"pending\">".$wikilanguage->get("LANG_FUNCTIONSPAGE")."</a>", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{FUNCTIONS}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_FUNCTIONSPAGE")."</em>", $html);
	elseif ($PAGE_TITLE == $DEFAULT_FUNCTIONSPAGE)
		$html = preg_replace('/{FUNCTIONS}/', "<a href=\"$DEFAULT_FUNCTIONSPAGE\" class=\"activemenupoint\">".$wikilanguage->get("LANG_FUNCTIONSPAGE")."</a>", $html);
	else
		$html = preg_replace('/{FUNCTIONS}/', "<a href=\"$DEFAULT_FUNCTIONSPAGE\">".$wikilanguage->get("LANG_FUNCTIONSPAGE")."</a>", $html);

	if (!$GRP_EDITCONTENT)
		$html = preg_replace('/{USER_PAGE}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{USER_PAGE}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_USERPAGE")."</em>", $html);
	elseif (!file_exists($DIR_PAGES.$wikiusers->getFullName($CURRENTUSER).$DEFAULT_PAGESEXT))
		$html = preg_replace('/{USER_PAGE}/', "<a href=\"".$wikiusers->getFullName($CURRENTUSER)."\" class=\"pending\">".$wikilanguage->get("LANG_USERPAGE")."</a>", $html);
	elseif ($PAGE_TITLE == $wikiusers->getFullName($CURRENTUSER))
		$html = preg_replace('/{USER_PAGE}/', "<a href=\"".$wikiusers->getFullName($CURRENTUSER)."\" class=\"activemenupoint\">".$wikilanguage->get("LANG_USERPAGE")."</a>", $html);
	else
		$html = preg_replace('/{USER_PAGE}/', "<a href=\"".$wikiusers->getFullName($CURRENTUSER)."\">".$wikilanguage->get("LANG_USERPAGE")."</a>", $html);

	if (!$GRP_EDITCONTENT)
		$html = preg_replace('/{NEW_PAGE}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{NEW_PAGE}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("BUTTON_NEWPAGE")."</em>", $html);
	else
		$html = preg_replace('/{NEW_PAGE}/', "<form name=\"new\" method=\"get\" action=\"index.php\"><input class=\"menutext\" type=\"text\" name=\"page\" value=\"\" />".getPagePatternListAsSelect()."<br /><input class=\"menubutton\" type=\"submit\" value=\"".$wikilanguage->get("BUTTON_NEWPAGE")."\" /><input type=\"hidden\" name=\"action\" value=\"cleanparameter\" /></form>", $html);

	if ($GRP_ANONYMOUS)
		$html = preg_replace('/{USER_SETTINGS}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{USER_SETTINGS}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_USERSETTINGS")."</em>", $html);
	elseif (($ACTION == "usersettings") || ($ACTION == "changepw"))
		$html = preg_replace('/{USER_SETTINGS}/', "<a href=\"index.php?action=usersettings\" class=\"activemenupoint\">".$wikilanguage->get("LANG_USERSETTINGS")."</a>", $html);
	else
		$html = preg_replace('/{USER_SETTINGS}/', "<a href=\"index.php?action=usersettings\">".$wikilanguage->get("LANG_USERSETTINGS")."</a>", $html);

	if (!$GRP_EDITUSERS)
		$html = preg_replace('/{WIKI_SETTINGS}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{WIKI_SETTINGS}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_WIKISETTINGS")."</em>", $html);
	elseif ($ACTION == "wikisettings")
		$html = preg_replace('/{WIKI_SETTINGS}/', "<a href=\"index.php?action=wikisettings\" class=\"activemenupoint\">".$wikilanguage->get("LANG_WIKISETTINGS")."</a>", $html);
	else
		$html = preg_replace('/{WIKI_SETTINGS}/', "<a href=\"index.php?action=wikisettings\">".$wikilanguage->get("LANG_WIKISETTINGS")."</a>", $html);

	if ($GRP_ANONYMOUS)
		$html = preg_replace('/{WIKI_STATISTICS}/', "", $html);
	elseif ($ACTION == "edit")
		$html = preg_replace('/{WIKI_STATISTICS}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_STATISTICS")."</em>", $html);
	elseif ($ACTION == "statistics")
		$html = preg_replace('/{WIKI_STATISTICS}/', "<a href=\"index.php?action=statistics\" class=\"activemenupoint\">".$wikilanguage->get("LANG_STATISTICS")."</a>", $html);
	else
		$html = preg_replace('/{WIKI_STATISTICS}/', "<a href=\"index.php?action=statistics\">".$wikilanguage->get("LANG_STATISTICS")."</a>", $html);

	if ($GRP_ANONYMOUS) {
		$html = preg_replace('/{SEARCH_FILES}/', "", $html);
		$html = preg_replace('/{USER_LOGOUT}/', "", $html);
		$html = preg_replace('/{LATEST_PAGES_LIST}/', "", $html);
		$html = preg_replace('/{LATEST_FILES_LIST}/', "", $html);
		$html = preg_replace('/{USER_NAME}/', $mainsettings->getWikiName(), $html);
		$html = preg_replace('/{FAVOURITE_LINK}/', getNoActionString($wikilanguage->get("LANG_NOACTION")), $html);
		$html = preg_replace('/{LOGIN_FORM}/', getLoginForm(), $html);
	}
	else {
		$html = preg_replace('/{LOGIN_FORM}/', "", $html);
		if ($ACTION == "edit") {
			$html = preg_replace('/{SEARCH_PAGES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("BUTTON_SEARCHPAGES")."</em>", $html);
			$html = preg_replace('/{SEARCH_FILES}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("BUTTON_SEARCHFILES")."</em>", $html);
			$html = preg_replace('/{USER_LOGOUT}/', "<img src=\"pic/logoutinactiveicon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_LOGOUT")."\" />", $html);
			$html = preg_replace('/{LATEST_PAGES_LIST}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_LATESTPAGESLIST")."</em>", $html);
			$html = preg_replace('/{LATEST_FILES_LIST}/', "<em class=\"inactivemenupoint\">".$wikilanguage->get("LANG_LATESTFILESLIST")."</em>", $html);
		}
		else {
			$html = preg_replace('/{USER_LOGOUT}/', "<a href=\"login.php?logout=true&amp;page=".strip_tags(htmlentities($_GET['page']))."\" title=\"".$wikilanguage->get("LANG_LOGOUT")."\" accesskey=\"x\"><img src=\"pic/logouticon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_LOGOUT")."\" /></a>", $html);
			$html = preg_replace('/{SEARCH_PAGES}/', "<form name=\"searchpages\" method=\"get\" action=\"index.php\"><input type=\"hidden\" name=\"action\" value=\"searchpages\" /><input class=\"menutext\" type=\"text\" name=\"query\" value=\"\" /><input class=\"menubutton\" type=\"submit\" value=\"".$wikilanguage->get("BUTTON_SEARCHPAGES")."\" /></form>", $html);
			$html = preg_replace('/{SEARCH_FILES}/', "<form name=\"searchfiles\" method=\"get\" action=\"index.php\"><input type=\"hidden\" name=\"action\" value=\"searchfiles\" /><input class=\"menutext\" type=\"text\" name=\"query\" value=\"\" /><input class=\"menubutton\" type=\"submit\" value=\"".$wikilanguage->get("BUTTON_SEARCHFILES")."\" /></form>", $html);
			$html = preg_replace('/{LATEST_PAGES_LIST}/', "<a href=\"index.php?action=recentpages&amp;count=10\">".$wikilanguage->get("LANG_LATESTPAGESLIST")."</a>", $html);
			$html = preg_replace('/{LATEST_FILES_LIST}/', "<a href=\"index.php?action=recentfiles&amp;count=10\">".$wikilanguage->get("LANG_LATESTFILESLIST")."</a>", $html);
		}
	}

	$html = preg_replace('/{SEARCH_PAGES}/', "<form name=\"searchpages\" method=\"get\" action=\"index.php\"><input type=\"hidden\" name=\"action\" value=\"searchpages\" /><input class=\"menutext\" type=\"text\" name=\"query\" value=\"\" /><input class=\"menubutton\" type=\"submit\" value=\"".$wikilanguage->get("BUTTON_SEARCHPAGES")."\" /></form>", $html);
	$html = preg_replace('/{FAVOURITE_LINK}/', getFavLink($PAGE_TITLE), $html);
	$html = preg_replace('/{USER_NAME}/', $wikiusers->getFullName($CURRENTUSER), $html);
	$html = preg_replace('/{WIKI_TITLE}/', $mainsettings->getWikiName(), $html);
	$html = preg_replace('/{META_DESC}/', $mainsettings->getMetaDesc(), $html);
	$html = preg_replace('/{META_KEY}/', $mainsettings->getMetaKey(), $html);
	$html = preg_replace('/{LAST_CHANGE}/', $wikilanguage->get("LANG_LASTCHANGED"), $html);
	$html = preg_replace('/{AT}/', $wikilanguage->get("LANG_AT"), $html);
	$html = preg_replace('/{BY}/', $wikilanguage->get("LANG_BY"), $html);
	$html = preg_replace('/{LAST_CHANGER}/', getLastChanger("page", $PAGE_TITLE), $html);
	$html = preg_replace('/{PAGES}/', $wikilanguage->get("LANG_PAGES"), $html);
	$html = preg_replace('/{FILES}/', $wikilanguage->get("LANG_FILES"), $html);

// edit
	if (($ACTION == "edit") && $GRP_EDITCONTENT) {
		// no editing for non-admins in admin pages
		if ($mainsettings->getIsAdminPage($PAGE_TITLE) && !$GRP_EDITUSERS)
			header("location:$PAGE_TITLE");
		$lockfile = $DIR_LOCKS.$PAGE_TITLE.$DEFAULT_LOCKSEXT;
		if (!file_exists($lockfile)) {
			// create lockfile
			$handle = fopen($lockfile, "w");
			fputs($handle, $CURRENTUSER);
			fclose($handle);
			if ($CONTENT == "")
				$CONTENT = getNewPageContent($PAGE_TITLE);
			$CONTENT = "<form method=\"post\" action=\"index.php\" name=\"form\">".getFormatToolbar()."<textarea name=\"content\" rows=\"8\">".htmlentities($CONTENT)."</textarea><input type=\"hidden\" name=\"page\" value=\"$PAGE_TITLE\" /><input type=\"hidden\" name=\"unlock\" value=\"true\" /><input type=\"hidden\" name=\"lockfile\" value=\"$lockfile\" />";
			if ($GRP_EDITUSERS) {
				$checked = "";
				if ($mainsettings->getIsAdminPage($PAGE_TITLE))
					$checked = " checked=\"checked\"";
				$CONTENT .= "<input type=\"checkbox\" name=\"adminpage\"$checked /> ".$wikilanguage->get("LANG_ADMINPAGE")."&nbsp;";
			}
			$CONTENT .= "<input type=\"submit\" value=\"".$wikilanguage->get("BUTTON_SAVE")."\" accesskey=\"s\" /></form>&nbsp;"
			."<form method=\"get\" action=\"index.php\"><input type=\"hidden\" name=\"page\" value=\"$PAGE_TITLE\" /><input type=\"hidden\" name=\"unlock\" value=\"true\" /><input type=\"submit\" value=\"".$wikilanguage->get("BUTTON_CANCEL")."\" accesskey=\"a\" /></form>";
		}
		else
			$CONTENT = getWarningReport("<a href=\"$PAGE_TITLE\" class=\"page\">$PAGE_TITLE</a> ".$wikilanguage->get("LANG_ISLOCKED"));
	}
// search pages
	else if ($ACTION == "searchpages") {
		// delete given page
		if ($_GET["delete"] == "true")
			$CONTENT .= deleteFile("page", $_GET["deletefile"]);
		// display pages
		$CONTENT .= displayFiles("pages", returnQuery($DIR_PAGES, $_GET["query"], true, false, false, 0));
	}
	// search files
	else if ($ACTION == "searchfiles") {
		// delete given file
		if ($_GET["delete"] == "true")
			$CONTENT .= deleteFile("file", $_GET["deletefile"]);
		// display files
		$CONTENT .= displayFiles("files", returnQuery($DIR_FILES, $_GET["query"], false, false, false, 0));
	}
// display all pages
	elseif ($ACTION == "allpages") {
		// delete given page
		if ($_GET["delete"] == "true")
			$CONTENT .= deleteFile("page", $_GET["deletefile"]);
		// unlock given page
		if ($_GET["unlockpage"] == "true")
			$CONTENT .= deleteFile("lock", $_GET["deletefile"]);
		// display pages
		$CONTENT .= displayFiles("pages", returnQuery($DIR_PAGES, $DEFAULT_PAGESEXT, false, false, false, 0));
	}
// display orphaned pages
	elseif ($ACTION == "orphanedpages") {
		// delete given page
		if ($_GET["delete"] == "true")
			$CONTENT .= deleteFile("page", $_GET["deletefile"]);
		// unlock given page
		if ($_GET["unlockpage"] == "true")
			$CONTENT .= deleteFile("lock", $_GET["deletefile"]);
		// display pages
		$CONTENT .= displayFiles("pages", $wikibacklinks->getOrphanesArray(returnQuery($DIR_PAGES, $DEFAULT_PAGESEXT, false, false, false, 0)));
	}
// display page versions
	elseif ($ACTION == "pageversions") {
		$CONTENT .= displayPageVersions();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}
// display recent pages
	elseif ($ACTION == "recentpages") {
		$count = $_GET['count'];
		if (!preg_match("/^\d+$/", $count)) // numerals only
			$count = $DEFAULT_RECENTCOUNT;
		// delete given page
		if ($_GET["delete"] == "true")
			$CONTENT .= deleteFile("page", $_GET["deletefile"]);
		// unlock given page
		if ($_GET["unlockpage"] == "true")
			$CONTENT .= deleteFile("lock", $_GET["deletefile"]);
		// display pages
		$CONTENT .= displayFiles("pages", returnQuery($DIR_PAGES, $DEFAULT_PAGESEXT, false, true, true, $count));
	}	
// display all files
	elseif ($ACTION == "allfiles") {
		// delete given file
		if ($_GET["delete"] == "true")
			$CONTENT .= deleteFile("file", $_GET["deletefile"]);
		// display files
		$CONTENT .= displayFiles("files", returnQuery($DIR_FILES, "", false, false, false, 0));
	}
// display all users
	elseif ($ACTION == "allusers") {
		$CONTENT .= displayUsers("");
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}
// edit a user
	elseif ($ACTION == "edituser") {
		$CONTENT .= editUser();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}
// search users
	elseif ($ACTION == "searchusers") {
		$CONTENT .= displayUsers($wikistringcleaner->cleanThatString(htmlentities($_GET["query"]), false));
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}
// display recent files
	elseif ($ACTION == "recentfiles") {
		$count = $_GET['count'];
		if (!preg_match("/^\d+$/", $count)) // numerals only
			$count = $DEFAULT_RECENTCOUNT;
		// delete given file
		if ($_GET["delete"] == "true")
			$CONTENT .= deleteFile("file", $_GET["deletefile"]);
		// display files
		$CONTENT .= displayFiles("files", returnQuery($DIR_FILES, "", false, true, true, $count));
	}
// display trash files
	elseif ($ACTION == "trashfiles") {
		// delete all trash files
		$emptytrash = $_GET["emptytrash"];
		$confirm = $_GET["confirm"];
		if ($emptytrash == "true") {
			if ($confirm == "true")
				$CONTENT .= clearTrash("files", $DIR_TRASHFILES);
			else
				$CONTENT .= getWarningReport($wikilanguage->get("CONFIRM_CLEARTRASHFILES")." <a href=\"index.php?action=trashfiles&amp;emptytrash=true&amp;confirm=true\">".$wikilanguage->get("LANG_YES")."</a> - <a href=\"index.php?action=trashfiles\">".$wikilanguage->get("LANG_NO")."</a>");
		}
		// restore given file
		if ($_GET["restore"] == "true")
			$CONTENT .= restoreFile("file", $_GET["restorefile"]);
		// display files
			$CONTENT .= displayFiles("files", returnQuery($DIR_TRASHFILES, "", false, true, false, 0));
	}
// display trash pages
	elseif ($ACTION == "trashpages") {
		// delete all trash pages
		$emptytrash = $_GET["emptytrash"];
		$confirm = $_GET["confirm"];
		if ($emptytrash == "true") {
			if ($confirm == "true")
				$CONTENT .= clearTrash("pages", $DIR_TRASHPAGES);
			else
				$CONTENT .= getWarningReport($wikilanguage->get("CONFIRM_CLEARTRASHPAGES")." <a href=\"index.php?action=trashpages&amp;emptytrash=true&amp;confirm=true\">".$wikilanguage->get("LANG_YES")."</a> - <a href=\"index.php?action=trashpages\">".$wikilanguage->get("LANG_NO")."</a>");
		}
		// restore given page
		if ($_GET["restore"] == "true")
			$CONTENT .= restoreFile("page", $_GET["restorefile"]);
		// display pages
		$CONTENT .= displayFiles("pages", returnQuery($DIR_TRASHPAGES, "", false, true, false, 0));
	}
// display upload form
	elseif ($ACTION == "upload") {
		$CONTENT = showUploadForm();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}
// clean given page parameter from special characters    
	elseif ($ACTION == "cleanparameter") {
		header("location:" . $wikistringcleaner->cleanThatString(htmlentities($_GET["page"]), false)."&pattern=".$_GET['pattern']);
	}	   
// display password change
	elseif ($ACTION == "changepw") {
		$CONTENT = changePassword();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}	   

// display user settings
	elseif ($ACTION == "usersettings") {
		$CONTENT = showUserSettings();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}	   

// display wiki settings
	elseif ($ACTION == "wikisettings") {
		$CONTENT = showWikiSettings();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}	   

// edit page patterns
	elseif ($ACTION == "editpattern") {
		$CONTENT .= editPattern();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}

// file infos
	elseif ($ACTION == "fileinfo") {
		$CONTENT .= showFileInfo();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}

// wiki statistics
	elseif ($ACTION == "statistics") {
		$CONTENT .= showWikiStatistics();
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
	}

// page formatting
	elseif ($ACTION != "edit") {
		// replace wiki syntaxwith HTML
		$CONTENT = $wikisyntax->convertWikiSyntax($PAGE_TITLE, $CONTENT, true);
		// replace emoticons with smiley graphics
		$CONTENT = replaceEmoticons($CONTENT);
		// cut first "<br />"
		$CONTENT = substr($CONTENT, 6, strlen($CONTENT) - 6);
	}

	if (!$GRP_ANONYMOUS) {
		// add page to favourites
		if ((isset($_GET['addfav'])) &&  ($_GET['addfav'] == "true")) {
			$CONTENT = addFav(true).$CONTENT;
			$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
			$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
		}
		// delete page from favourites
		elseif ((isset($_GET['delfav'])) &&  ($_GET['delfav'] == "true")) {
			$CONTENT = addFav(false).$CONTENT;
			$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
			$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
		}
	}

// highlight searched phrases
	if ((isset($_GET['highlight'])) &&  ($_GET['highlight'] <> ""))
		$CONTENT = highlight($CONTENT, $_GET['highlight']);

// show warning, if erroneous logins occured
	if (!$GRP_ANONYMOUS) {
		if ($wikiusers->getFalseLoginCount($CURRENTUSER) > 0) {
			$CONTENT = getWarningReport($wikilanguage->get("REPORT_FALSELOGINS")." ".$wikiusers->getFalseLoginCount($CURRENTUSER)) . $CONTENT;
			$wikiusers->setFalseLoginCount($CURRENTUSER, 0);
		}
	// ask user to change his password, if it's still the initial one
	if ($wikiusers->hasInitialPw($CURRENTUSER) && !$GRP_ANONYMOUS)
	  if ($ACTION == "changepw")
			$CONTENT = getWarningReport($wikilanguage->get("REPORT_STILLHAVEINITIALPW")) . $CONTENT;
	  else
			$CONTENT = getWarningReport($wikilanguage->get("REPORT_PLEASECHANGEPW")) . $CONTENT;
	}

// finally: display page
	$html = preg_replace('/{USER_FAVOURITES}/', getUserFavourites(), $html);
	$html = preg_replace('/{USERSONLINE_LIST}/', getUsersOnlineList(), $html);
	$html = preg_replace("/{CONTENT}/", "$CONTENT", $html);
	$html = preg_replace('/{TIME}/', $TIME, $html);
	$html = preg_replace('/{PRINT_LINK}/', $PRINTLINK, $html);
	$html = preg_replace('/{BACKLINKS_LIST}/', $BACKLINKS, $html);
	$html = preg_replace('/{LATEST_PAGES_MENU}/', getLatestChanges("page", 10), $html);
	$html = preg_replace('/{LATEST_FILES_MENU}/', getLatestChanges("file", 10), $html);
	$html = preg_replace('/{WIKI_INFO}/', wikiInfo(), $html);

	echo $html;
    
    



// ----------------------
//  additional functions
// ----------------------

// change password
	function changePassword() {
		global $wikiusers;
		global $wikilanguage;
		global $CURRENTUSER;
		global $GRP_ANONYMOUS;

		// this function doesn't work for anonymous users
		if ($GRP_ANONYMOUS) {
			header("location:index.php");
		}

		$old = $_POST["old"];
		$new = $_POST["new"];
		$repeat = $_POST["repeat"];
		if ($_SERVER["REQUEST_METHOD"] == "POST"){

			if (
			// Alle Felder übergeben...
			isset($_POST['old']) && isset($_POST['new']) && isset($_POST['repeat'])
			// ...und keines leer?
			&& ($_POST['old'] <> "" ) && ($_POST['new'] <> "" ) && ($_POST['repeat'] <> "" )
			// Altes PW korrekt? 
			&& ($_POST['old'] == $wikiusers->getPassword($CURRENTUSER))
			// Neues Paßwort zweimal exakt gleich eingegeben?
			&& ($_POST['new'] == $_POST['repeat'])
			// Neues Paßwort wenigstens sechs Zeichen lang und mindestens aus kleinen und großen Buchstaben sowie Zahlen bestehend?
			&& (strlen($_POST['new']) >= 6) && preg_match("/[0-9]/", $_POST['new']) && preg_match("/[a-z]/", $_POST['new']) && preg_match("/[A-Z]/", $_POST['new'])
			) {
				$wikiusers->addUser($CURRENTUSER, $_POST['new'], $wikiusers->getFullName($CURRENTUSER), $wikiusers->getUserGroup($CURRENTUSER), $wikiusers->getLifeTime($CURRENTUSER), $wikiusers->getIsBanned($CURRENTUSER), $wikiusers->getBanTime($CURRENTUSER), $wikiusers->getWikiStyle($CURRENTUSER), true);
				$wikiusers->setHasInitialPw($CURRENTUSER, "false");
				$msg = getOkReport($wikilanguage->get("REPORT_PWD_CHANGED"));
			}
			else
				$msg = getWarningReport($wikilanguage->get("REPORT_PWD_ERROR"));
		}
		$form = "<form method=\"post\" action=\"index.php\"><input type=\"hidden\" name=\"changepw\" value=\"true\" />"
		. "<div class=\"myWiki\"><div class=\"myWiki-header\">".$wikilanguage->get("LANG_CHANGEPW")."</div>"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_PASSWORDOLD")." </div><div><input type=\"password\" name=\"old\" /></div></div>"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_PASSWORDNEW")." </div><div><input type=\"password\" name=\"new\" /></div></div>"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_PASSWORDREPEAT")." </div><div><input type=\"password\" name=\"repeat\" /></div></div>"
		. "<div class=\"summary\"><input type=\"image\" name=\"submit\" value=\"true\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" title=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" /><!--<input type=\"submit\" name=\"submit\" value=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" />--></div>"
		. "</div></form>";
		return $msg.$form;
	}

// delete all files in given directory
	function clearTrash($type, $path) {
		global $DEFAULT_PAGESEXT;
		global $DIR_BACKUP;
		global $wikistats;
		global $wikilanguage;

		$type =	strip_tags($type);
		$path =	strip_tags($path);

		if ($type == "files")
			$dirname = $wikilanguage->get("LANG_TRASHFILES");
		else
			$dirname = $wikilanguage->get("LANG_TRASHPAGES");
		$errorflag = false;
		$dir = opendir(getcwd() . "/$path");
    while ($file = readdir($dir))  {
    	if (($file <> ".") && ($file <> "..")) {
	   		if(!unlink($path . $file))
	   			$errorflag = true;
	   		else if ($type == "files") {
	   			// delete from filestats
	   			$wikistats->deleteFileStats($file);
	   		}
	   		elseif ($type == "pages") {
	   			// delete from statistics
	   			$wikistats->deleteLastPageChanger(substr($file, 0, strlen($file) - strlen($DEFAULT_PAGESEXT)));
	   			// also delete page history
	   			if (file_exists($DIR_BACKUP.substr($file, 0, strlen($file) - strlen($DEFAULT_PAGESEXT)))) {
						$handle = opendir($DIR_BACKUP.substr($file, 0, strlen($file) - strlen($DEFAULT_PAGESEXT)));
						while (($subfile = readdir($handle)) && ($subfile <> ".") && ($subfile <> "..")) {
							unlink($DIR_BACKUP.substr($file, 0, strlen($file) - strlen($DEFAULT_PAGESEXT))."/".$subfile);
						}
					}
				}
			}
		}
		// has an error occured?
    if ($errorflag)
    	return getWarningReport($wikilanguage->get("REPORT_DIR_CLEAR_ERROR")." &quot;$dirname&quot;");
    else
    	return getOkReport($wikilanguage->get("REPORT_DIR_CLEAR_SUCCESS")." &quot;$dirname&quot;");
	}
	
// return content for a page to be created
	function getNewPageContent($pagename) {
		global $CURRENTUSER;
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGEPATTERNS;
		global $mainsettings;
		global $wikiusers;
		
		$pagename =	strip_tags($pagename);

		// load given pattern
		if ((file_exists($DIR_PAGEPATTERNS.htmlentities($_GET['pattern']).$DEFAULT_PAGESEXT)) && (htmlentities($_GET['pattern']) <> ""))
			$patternfile = $DIR_PAGEPATTERNS.htmlentities($_GET['pattern']).$DEFAULT_PAGESEXT;
		// load default pattern
		elseif (file_exists($DIR_PAGEPATTERNS.$mainsettings->getDefaultPagePattern().$DEFAULT_PAGESEXT))
			$patternfile = $DIR_PAGEPATTERNS.$mainsettings->getDefaultPagePattern().$DEFAULT_PAGESEXT;
		else
			return "";
		if (!$file = @fopen($patternfile, "r"))
			die("Could not read ".$patternfile."!");
		$pattern = fread($file, filesize($patternfile));
		fclose($file);
		$pattern = str_replace("{PATTERN_PAGETITLE}", $pagename, $pattern);
		$pattern = str_replace("{PATTERN_PAGEDATE}", strftime($mainsettings->getTimeFormat(), time()), $pattern);
		$pattern = str_replace("{PATTERN_PAGEUSER}", $wikiusers->getFullName($CURRENTUSER), $pattern);
		return $pattern;
	}
	
// delete given file or page
	function deleteFile($type, $deletefile) {
		global $CURRENTUSER;
		global $DIR_FILES;
		global $DIR_PAGES;
		global $DIR_LOCKS;
		global $DEFAULT_LOCKSEXT;
		global $DEFAULT_PAGESEXT;
		global $DIR_TRASHFILES;
		global $DIR_TRASHPAGES;
		global $wikibacklinks;
		global $wikilanguage;
		global $wikistats;

		$type =	strip_tags($type);
		$deletefile =	strip_tags(htmlentities($deletefile));
		
		if (($deletefile <> "") && ($deletefile <> ".") && ($deletefile <> "..")) {
			// delete file
			if ($type == "file") {
	    		if (file_exists($DIR_FILES.$deletefile)) {
		    		if (copy($DIR_FILES.$deletefile, $DIR_TRASHFILES.$deletefile) && unlink($DIR_FILES.$deletefile)) {
		    			$wikistats->setLastFileChanger($deletefile, $CURRENTUSER);
		    			return getOkReport($wikilanguage->get("REPORT_FILE_DEL_SUCCESS")." &quot;$deletefile&quot;");
		    		}
	    			else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_FILE_DEL_ERROR")." &quot;$deletefile&quot;");
	    		}
	    		else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_FILE_NOT_EXISTS")." &quot;$deletefile&quot;");
			}
			// delete lock
			elseif ($type == "lock") {
	    		if (file_exists($DIR_LOCKS.$deletefile.$DEFAULT_LOCKSEXT)) {
		    		if (unlink($DIR_LOCKS.$deletefile.$DEFAULT_LOCKSEXT)) {
		    			return getOkReport($wikilanguage->get("REPORT_UNLOCK_SUCCESS")." &quot;$deletefile&quot;");
		    		}
	    			else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_UNLOCK_ERROR")." &quot;$deletefile&quot;");
	    		}
    			else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_UNLOCK_ERROR")." &quot;$deletefile&quot;");
			}
			//delete page
			elseif ($type == "page") {
	    		if (file_exists($DIR_PAGES.$deletefile.$DEFAULT_PAGESEXT)) {
		    		if (copy($DIR_PAGES.$deletefile.$DEFAULT_PAGESEXT, $DIR_TRASHPAGES.$deletefile.$DEFAULT_PAGESEXT) && unlink($DIR_PAGES.$deletefile.$DEFAULT_PAGESEXT)) {
		    			$wikistats->setLastPageChanger($deletefile, $CURRENTUSER);
	   					// delete from backlink data
	   					$wikibacklinks->deletePageFromBacklinkKey($deletefile);
		    			return getOkReport($wikilanguage->get("REPORT_PAGE_DEL_SUCCESS")." &quot;$deletefile&quot;");
		    		}
	    			else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_PAGE_DEL_ERROR")." &quot;$deletefile&quot;");
	    		}
	    		else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_PAGE_NOT_EXISTS")." &quot;$deletefile&quot;");
		    }
		    // wrong parameter
		    else
		    	die("Erroneous parameter \"\$type\" given at function deleteFile().");
		}
		// erroneous deletefile given
		else 
			die("Erroneous parameter \"\$deletefile\" given at function deleteFile().");
	}

// display all users
	function displayUsers($query) {
		global $CURRENTUSER;
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGES;
		global $DIR_TRASHPAGES;
		global $GRP_ANONYMOUS;
		global $GRP_EDITCONTENT;
		global $GRP_EDITUSERS;
		global $mainsettings;
		global $wikibacklinks;
		global $wikigroups;
		global $wikiusers;
		global $wikistats;
		global $wikilanguage;
		global $wikistringcleaner;
		
		$query =	strip_tags($query);

		// this function doesn't work for guests
		if (!$GRP_EDITCONTENT) {
			header("location:index.php");
		}
		
		$table = "";
		$msg = "";
		
		// create new user
		if ((isset($_GET['createuser'])) && ($_GET['createuser'] == "true")) {
			if (!preg_match("/^[a-z0-9]+$/", $_GET['id']))
				$msg = getWarningReport($wikilanguage->get("REPORT_USERID_INVALID")." &quot;".$_GET['id']."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['newlifetime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".$_GET['newlifetime']."&quot;");
			elseif ($wikiusers->addUser($_GET['id'], $_GET['id'], $wikistringcleaner->cleanThatString(htmlentities($_GET['newfullname']), false), $_GET['newgroup'], $_GET['newlifetime'], 0, 0, $mainsettings->getDefaultWikiStyle(), false)) {
				$wikiusers->loadWikiUsers();
				// create userpage
				if (($_GET['createpage'] == "on") && (!file_exists($DIR_PAGES.$wikiusers->getFullName($_GET['id']).$DEFAULT_PAGESEXT)))  {
					if (!$file = @fopen($DIR_PAGES.$wikiusers->getFullName($_GET['id']).$DEFAULT_PAGESEXT, "w")) 
						die("Could not write userpage &quot;".$wikiusers->getFullName(strip_tags($_GET['id']))."&quot;!");
					fputs($file, getNewPageContent($wikiusers->getFullName($_GET['id'])));	    
					fclose($file);
					$wikistats->setLastPageChanger($wikiusers->getFullName($_GET['id']), $CURRENTUSER);
				}
				$msg = getOkReport($wikilanguage->get("REPORT_USER_ADDED")." &quot;".strip_tags($_GET['id'])."&quot;");
			}
			else
				$msg = getWarningReport($wikilanguage->get("REPORT_USER_NOT_ADDED")." &quot;".strip_tags($_GET['id'])."&quot;");
		}
		// edit existing user
		elseif ((isset($_GET['edituser'])) && ($_GET['edituser'] == "true")) {
			if (!preg_match("/^[0-9]+$/", $_GET[$_GET['submit'].'_newlifetime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET[$_GET['submit'].'_newlifetime'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET[$_GET['submit'].'_newbantime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET[$_GET['submit'].'_newbantime'])."&quot;");
			else {
				if ($_GET[$_GET['submit'].'_newisbanned'] == "on") {
					$isbanned = time();
					$bantime = $_GET[$_GET['submit'].'_newbantime'];
				}
				else {
					$isbanned = 0;
					$bantime = 0;
				}
				if ($wikiusers->addUser($_GET['submit'], $wikiusers->getPassword($_GET['submit']), $wikistringcleaner->cleanThatString($_GET[$_GET['submit'].'_newfullname'], false), $_GET[$_GET['submit'].'_newgroup'], $_GET[$_GET['submit'].'_newlifetime'], $isbanned, $bantime, $wikiusers->getWikiStyle($_GET['submit']), true)) {
					$wikiusers->loadWikiUsers();
					$msg = getOkReport($wikilanguage->get("REPORT_USER_CHANGED")." &quot;".$wikiusers->getFullName($_GET['submit'])."&quot;");
				}
				else
					$msg = getWarningReport($wikilanguage->get("REPORT_USER_NOT_CHANGED")." &quot;".$_GET['id']."&quot;");
				}
		}
		// delete existing user
		elseif ((isset($_GET['deleteuser'])) && ($_GET['deleteuser'] == "true")) {
			$fullname = $wikiusers->getFullName($_GET['id']);
			if ($fullname == "") $fullname = $_GET['id'];
			if ($_GET['confirm'] == "true") {
				// also delete userpage, if given
				$pagedelmsg = "";
				if ($_GET['deleteuserpage'] == "true") {
					if (file_exists($DIR_PAGES.$fullname.$DEFAULT_PAGESEXT)) {
						if (copy($DIR_PAGES.$fullname.$DEFAULT_PAGESEXT, $DIR_TRASHPAGES.$fullname.$DEFAULT_PAGESEXT) && unlink($DIR_PAGES.$fullname.$DEFAULT_PAGESEXT)) {
							$wikistats->setLastPageChanger($fullname, $CURRENTUSER);
							$wikibacklinks->deletePageFromBacklinkKey($fullname);
							$pagedelmsg = "<br />".$wikilanguage->get("REPORT_PAGE_DEL_SUCCESS")." &quot;".$fullname."&quot;";
						}
						else
							$pagedelmsg = "<br />".$wikilanguage->get("REPORT_PAGE_DEL_ERROR")." &quot;".$fullname."&quot;";
					}
					else
						$pagedelmsg = "<br />".$wikilanguage->get("REPORT_PAGE_NOT_EXISTS")." &quot;".$fullname."&quot;";
				}
				if ($wikiusers->deleteUser($_GET['id'])) {
					$wikiusers->loadWikiUsers();
					$msg = getOkReport($wikilanguage->get("REPORT_USER_DELETED")." &quot;".$fullname."&quot;$pagedelmsg");
				}
				else
					$msg = getWarningReport($wikilanguage->get("REPORT_USER_NOT_DELETED")." &quot;".$fullname."&quot;$pagedelmsg");
			}
			else
				$msg = getWarningReport("&quot;".$fullname."&quot; ".$wikilanguage->get("CONFIRM_DELETEUSER")." <a href=\"index.php?action=allusers&amp;deleteuser=true&amp;id=".$_GET['id']."&amp;confirm=true\">".$wikilanguage->get("LANG_YES")."</a> - <a href=\"index.php?action=allusers&amp;deleteuser=true&amp;id=".$_GET['id']."&amp;deleteuserpage=true&amp;confirm=true\">".$wikilanguage->get("LANG_YESWITHPAGE")."</a> - <a href=\"index.php?action=allusers\">".$wikilanguage->get("LANG_NO")."</a>");
		}
		// kick user
		elseif ((isset($_GET['kickuser'])) && ($_GET['kickuser'] == "true")) {
			$wikiusers->setIsOnline($_GET['id'], "false");
			$msg = getOkReport($wikilanguage->get("REPORT_USER_KICKED")." &quot;".$wikiusers->getFullName(strip_tags($_GET['id']))."&quot;");
		}

		// admins only: row "make new user"
		if ($GRP_EDITUSERS) {
			$table .= "<h3>".$wikilanguage->get("LANG_USERSNEW")."</h3>"
			. "<form method=\"get\" action=\"index.php\" name=\"newuser\"><input type=\"hidden\" name=\"action\" value=\"allusers\" /><input type=\"hidden\" name=\"createuser\" value=\"true\" /><table class=\"wikiTable\"><tr>"
			// column "id"
			. "<th>".$wikilanguage->get("LANG_ID")."</th>"
			// column "fullname"
			. "<th>".$wikilanguage->get("LANG_USERNAME")."</th>"
			// column "group"
			. "<th>".$wikilanguage->get("LANG_GROUP")."</th>"
			// column "make userpage"
			. "<th>".$wikilanguage->get("LANG_USERCREATEPAGE")."</th>"
			// column "user's lifetime"
			. "<th>".$wikilanguage->get("LANG_USERLIFETIME")."</th>"
			. "</tr>"
			// table start
			. "<tr class=".returnTdClass(0).">"
			// column "id"
			. "<td><input type=\"text\" name=\"id\" /></td>"
			// column "fullname"
			. "<td><input type=\"text\" name=\"newfullname\" /></td>"
			// column "group"
			. "<td><select name=\"newgroup\" class=\"content\">";
			for ($i=1;$i<=$wikigroups->getGroupCount();$i++) {
				$table .= "<option selected value=\"$i\">".$wikigroups->getName($i)."</option>";
			}
			$table .= "</select></td>"
			// column "make userpage"
			. "<td><input type=\"checkbox\" name=\"createpage\" checked /></td>"
			// column "user's lifetime"
			. "<td><input type=\"text\" name=\"newlifetime\" value=\"0\" class=\"narrow\" /> ".$wikilanguage->get("LANG_DAYS")."</td></tr></table>"
			// summary row
			. "<div class=\"summary\">"
			. "<input type=\"image\" name=\"submit\" value=\"submit\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_USERCREATE")."\" title=\"".$wikilanguage->get("LANG_USERCREATE")."\" />"
			. "</div></form><hr />"
			. "<h3>".$wikilanguage->get("LANG_USERSEXISTENT")."</h3>";		
		}
		
		// count all matching users
		$alluserscount = 0;

		if ($GRP_EDITUSERS)
			$table .= "<form method=\"get\" action=\"index.php\" name=\"users\"><input type=\"hidden\" name=\"action\" value=\"allusers\" /><input type=\"hidden\" name=\"edituser\" value=\"true\" />";
		$table .= "<table class=\"wikiTable\">";
		$usergroups = $wikigroups->getAllGroups();
		arsort($usergroups);
		
		foreach ($usergroups as $group => $groupdata) {
			// show only groups with matching members
			$groupcount = 0;
			// empty row
			if ($alluserscount > 0) {
				if ($GRP_EDITUSERS)
					$table .= "<tr><td class=\"empty\" colspan=\"8\"></td></tr>";
				else
					$table .= "<tr><td class=\"empty\" colspan=\"4\"></td></tr>";
			}
			foreach ($wikiusers->getAllUsers($query) as $username => $userdata) {
				if ($wikiusers->getUserGroup($username) == $group)
					$groupcount++;
			}
			if ($groupcount == 0)
				continue;
			if ($GRP_EDITUSERS)
				$table .= "<tr><td class=\"empty\" colspan=\"8\"><h6>".$wikigroups->getName($group)."</h6></td></tr>";
			else
				$table .= "<tr><td class=\"empty\" colspan=\"4\"><h6>".$wikigroups->getName($group)."</h6></td></tr>";
			// table head
			$table .= "<tr>";
			// column "id"
			if ($GRP_EDITUSERS)
				$table .= "<th>".$wikilanguage->get("LANG_USERID")."</th>";
			//column "fullname"
			$table .= "<th>".$wikilanguage->get("LANG_USERNAME")."</th>";
			// column "firstaction"
			if ($GRP_EDITUSERS)
				$table .= "<th>".$wikilanguage->get("LANG_FIRSTONLINE")."<br />".$wikilanguage->get("LANG_LASTONLINE")."</th>";
			else
				$table .= "<th>".$wikilanguage->get("LANG_LASTONLINE")."</th>";
			// column "currentlyonline"
			$table .= "<th>".$wikilanguage->get("LANG_CURRENTLYONLINE")."</th>"
			// column "logins"
			. "<th>".$wikilanguage->get("LANG_LOGINS")."</th>";
			// column "isbanned"
			if ($GRP_EDITUSERS)
				$table .= "<th>".$wikilanguage->get("LANG_BANNED")."</th>";
			// column "change"
			if ($GRP_EDITUSERS)
				$table .= "<th>&nbsp;</th>";
			// column "delete"
			if ($GRP_EDITUSERS)
				$table .= "<th>&nbsp;</th>";
			$table .= "</tr>";
		
			$counter = 0;
			foreach ($wikiusers->getAllUsers($query) as $username => $userdata) {
				if ($wikiusers->getUserGroup($username) <> $group)
					continue;
				
				$alluserscount++;
				$tdclass = returnTdClass($counter);
				// preparing
				$counter++;
				$currentlyonline = onlineStatusIcon($username);
				$lastaction = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
				$firstaction = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
				if ($wikiusers->getLastAction($username) <> "")
					$lastaction = strftime($mainsettings->getTimeFormat(), $wikiusers->getLastAction($username));
				if ($wikiusers->getFirstAction($username) <> "")
					$firstaction = strftime($mainsettings->getTimeFormat(), $wikiusers->getFirstAction($username));
				// row start
				$table .= "<tr class=$tdclass >";
				// column "id"
				if ($GRP_EDITUSERS)
					$table .= "<td style=\"font-weight:bold;\">$username</td>";
				// column "fullname"
				$table .= "<td>".getLink("page", $DIR_PAGES, $wikiusers->getFullName($username), false, false)."</td>";
				// column "firstonline/lastonline"
				if ($GRP_EDITUSERS)
					$table .= "<td>$firstaction<br />$lastaction</td>";
				else
					$table .= "<td>$lastaction</td>";
				// column "currentlyonline"
				$table .= "<td>$currentlyonline</td>"
				// column "logins"
				. "<td>".$wikiusers->getLoginCount($username)."</td>";
				// column "isbanned"
				if ($GRP_EDITUSERS) {
					$table .= "<td>";
					if ($wikiusers->getIsBanned($username) > 0)
						$table .= $wikilanguage->get("LANG_YES");
					$table .= "</td>";
				}
				// column "change"
				if ($GRP_EDITUSERS)
					$table .= "<td><a href=\"index.php?action=edituser&amp;id=$username\" title=\"".$wikilanguage->get("LANG_EDITUSER")." &quot;".$wikiusers->getFullName($username)."&quot;\"><img src=\"pic/editusericon.gif\" alt=\"".$wikilanguage->get("LANG_EDITUSER")." &quot;".$wikiusers->getFullName($username)."&quot;\" /></a></td>";
				// column "delete"
				if ($GRP_EDITUSERS) {
					$table .= "<td>";
					if (($username <> "admin") && ($username <> $CURRENTUSER))
						// if user is online: kick
						if ($wikiusers->isOnline($username))
							$table .= "<a href=\"index.php?action=allusers&amp;kickuser=true&amp;id=$username\" title=\"".$wikilanguage->get("LANG_USERKICK")." &quot;".$wikiusers->getFullName($username)."&quot;\"><img src=\"pic/kickusericon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_USERKICK")." &quot;".$wikiusers->getFullName($username)."&quot;\" /></a>";
						// if user is offline: delete
						else
							$table .= "<a href=\"index.php?action=allusers&amp;deleteuser=true&amp;id=$username\" title=\"".$wikilanguage->get("LANG_USERDELETE")." &quot;".$wikiusers->getFullName($username)."&quot;\"><img src=\"pic/deleteusericon.gif\" alt=\"".$wikilanguage->get("LANG_USERDELETE")." &quot;".$wikiusers->getFullName($username)."&quot;\" /></a>";
					else
						$table .= getNoActionString($wikilanguage->get("LANG_NOACTION"));
					$table .= "</td>";
				}
				// row end
				$table .= "</tr>";
			}
			// summary row
			if ($GRP_EDITUSERS)
				$table .= "<tr><td class=\"summary\" colspan=\"8\">".$wikigroups->getName($group).": $counter</td></tr>";
			else
				$table .= "<tr><td class=\"summary\" colspan=\"4\">".$wikigroups->getName($group).": $counter</td></tr>";
		}
		// table end
		$table .= "</table>";
		if ($GRP_EDITUSERS)
				$table .= "</form>";
		// no matching users?
		if ($alluserscount == 0)
			$table .= $wikilanguage->get("LANG_NOUSERFOUND");
		// return the whole thing
		return $msg.$table;
	}

// display all files or pages of given array in table (returns a content-string)
	function displayFiles($type,$filearray) {
		global $ACTION;
		global $DEFAULT_LOCKSEXT;
		global $DEFAULT_PAGESEXT;
		global $DEFAULT_PAGESEXT;
		global $DIR_FILES;
		global $DIR_LOCKS;
		global $DIR_PAGES;
		global $DIR_TRASHFILES;
		global $DIR_TRASHPAGES;
		global $GRP_ANONYMOUS;
		global $GRP_EDITCONTENT;
		global $GRP_EDITUSERS;
		global $PRINTLINK;
		global $TIME;
		global $mainsettings;
		global $wikibacklinks;
		global $wikilanguage;
		global $wikiusers;
		global $wikistringcleaner;
		$table = "";
		$counter = 0;
		
		// this function doesn't work with trashpages/trashfiles for guests/anonymous
		if (!$GRP_EDITCONTENT && (($ACTION == "trashpages") || ($ACTION == "trashfiles"))) {
			header("location:index.php");
		}

		// it also doesn't work with trashpages/trashfiles for anonymous
		if ($GRP_ANONYMOUS && (($ACTION == "recentpages") || ($ACTION == "recentfiles"))) {
			header("location:index.php");
		}

		// number of files to show in recent files / recent pages mode
		$count = $_GET['count'];
		if (!preg_match("/^\d+$/", $count)) // numerals only
			$count = $DEFAULT_RECENTCOUNT;

		if (count($filearray) == 0)
			$table = $wikilanguage->get("LANG_DIRISEMPTY");

		// display pages
		elseif ($type == "pages") {
			$table .= "<table class=\"wikiTable\"><thead>"
			// column "page name"
			. "<th>".$wikilanguage->get("LANG_PAGE")."</th>"
			// column "last changed"
			. "<th>".$wikilanguage->get("LANG_LASTCHANGED")."</th>"
			// column "last changer"
			. "<th>".$wikilanguage->get("LANG_BY")."</th>";
			// column "status"
			if ($GRP_EDITCONTENT)
				$table .= "<th>".$wikilanguage->get("LANG_STATUS")."</th>";
			// column "backlinks"
			if (!$GRP_ANONYMOUS && ($ACTION <> "trashpages"))
				$table .= "<th>".$wikilanguage->get("LANG_BACKLINKS")."</th>";
			// column "favourites"
			if (!$GRP_ANONYMOUS && ($ACTION <> "trashpages"))
				$table .= "<th>&nbsp;</th>";
			// column "action"
			if ($GRP_EDITCONTENT)
				$table .= "<th>&nbsp;</th>";
			// column "view history"
			if ($GRP_EDITUSERS && ($ACTION <> "trashpages"))
				$table .= "<th>&nbsp;</th>";
			$table .= "</thead>";
			foreach ($filearray as $filename => $timestamp) {
				$tdclass = returnTdClass($counter);
				$filename = substr($filename, 0, strlen($filename) - strlen($DEFAULT_PAGESEXT));
				// cut too long names
				$editedfilename = $wikistringcleaner->shortenName($filename);
				$table .= "<tr class=$tdclass>";
				// column "page name"
				if ($ACTION == "trashpages")
					$table .= "<td>".getLastChangeMarker($timestamp)."$editedfilename</td>";
				else
					$table .= "<td>".getLink("page", $DIR_PAGES, $filename, true, false)."</td>";
				// column "last changed"
				$table .= "<td>" . strftime($mainsettings->getTimeFormat(), $timestamp) . "</td>";
				// column "last changer"
				$table .= "<td>".getLastChanger("page", $filename)." </td>";
				// column "status"
				if ($GRP_EDITCONTENT) {
					$table .= "<td>";
					if ($ACTION == "trashpages")
						$currentdir = $DIR_TRASHPAGES;
					else
						$currentdir = $DIR_PAGES;
					if (fileIsReadonly($currentdir, $filename.$DEFAULT_PAGESEXT)) {
						$table .= $wikilanguage->get("LANG_ISPROTECTEDSHORT");
						if ($mainsettings->getIsAdminPage($filename))
							$table .= "; ".$wikilanguage->get("LANG_ADMINPAGE");
					}
					elseif (entryIsLocked($filename)) {
						$table .= $wikilanguage->get("LANG_ISLOCKEDSHORT");
						if ($GRP_EDITCONTENT) {
							// get time till unlock
							$locktime = time() - filemtime($DIR_LOCKS.$filename.$DEFAULT_LOCKSEXT);
							$table .= " ".$wikilanguage->get("LANG_BY")." ".$wikiusers->getFullName(getLocker($filename))." (".$locktime."s)";
						}
						if ($mainsettings->getIsAdminPage($filename))
							$table .= ";<br />".$wikilanguage->get("LANG_ADMINPAGE");
					}
					else {
						if ($mainsettings->getIsAdminPage($filename))
							$table .= $wikilanguage->get("LANG_ADMINPAGE");
						$table .= "&nbsp;";
					}
					$table .= "</td>";
				}
				// column "backlinks"						
				if (!$GRP_ANONYMOUS && ($ACTION <> "trashpages")) {
					$table .= "<td>".count($wikibacklinks->getBacklinkList($filename))."</td>";
				}
				// column "add/delete favourite"
				if (!$GRP_ANONYMOUS && ($ACTION <> "trashpages")) {
					$table .= "<td>".getFavLink($filename)."</td>";
				}
				// column "action"
				if ($GRP_EDITCONTENT) {
					if (fileIsReadonly($currentdir, $filename.$DEFAULT_PAGESEXT))
						$table .= "<td><img class=\"noborder\" alt=\"-\" src=\"pic/deletepageiconlocked.gif\"  title=\"".$wikilanguage->get("LANG_ISPROTECTEDSHORT")."\" /></td>";
					elseif (($mainsettings->getIsAdminPage($filename) || pageIsStrangersUserpageOrDefaultpage($filename)) && !$GRP_EDITUSERS)
						$table .= "<td><img class=\"noborder\" alt=\"-\" src=\"pic/deletepageiconlocked.gif\"  title=\"".$wikilanguage->get("LANG_ISPROTECTEDPAGE")."\" /></td>";
					elseif (entryIsLocked($filename))
						if ($GRP_EDITUSERS)
							$table .= "<td><a href=\"index.php?action=$ACTION&amp;unlockpage=true&amp;deletefile=$filename&amp;query=".htmlentities($_GET['query'])."\" title=\"".$wikilanguage->get("LANG_UNLOCK")." &quot;$filename&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_UNLOCK")." &quot;$filename&quot;\" src=\"pic/unlockpageicon.gif\" /></a></td>";
						else
							$table .= "<td><img class=\"noborder\" alt=\"-\" src=\"pic/deletepageiconlocked.gif\"  title=\"".$wikilanguage->get("LANG_ISLOCKEDSHORT")."\" /></td>";
					else {
						if ($ACTION == "trashpages")
							$table .= "<td><a href=\"index.php?action=trashpages&amp;restore=true&amp;restorefile=$filename&amp;query=".htmlentities($_GET['query'])."\" title=\"".$wikilanguage->get("LANG_RESTORE")." &quot;$filename&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_RESTORE")." &quot;$filename&quot;\" src=\"pic/restorepageicon.gif\" /></a></td>";
						else
							$table .= "<td><a href=\"index.php?action=$ACTION&amp;count=$count&amp;delete=true&amp;deletefile=$filename&amp;query=".htmlentities($_GET['query'])."\" title=\"".$wikilanguage->get("LANG_DELETE")." &quot;$filename&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_DELETE")." &quot;$filename&quot;\" src=\"pic/deletepageicon.gif\" /></a></td>";
					}
				}
				// column "view history"
				if ($GRP_EDITUSERS && ($ACTION <> "trashpages")) {
					$table .= "<td><a href=\"index.php?action=pageversions&amp;vpage=$filename\" title=\"".$wikilanguage->get("LANG_VERSIONS_VIEW")." &quot;$filename&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_VERSIONS_VIEW")." &quot;$filename&quot;\" src=\"pic/versionsicon.gif\" /></a></td>";
				}
				$table .= "</tr>";
				$counter++;
			}
			// summary row
			// anonymous
			if ($GRP_ANONYMOUS)
				$rows = 3;
			else {
				if ($ACTION == "trashpages")
					$rows = 5;
				// admins
				if ($GRP_EDITUSERS)
					$rows = 8;
				// users
				elseif ($GRP_EDITCONTENT)
					$rows = 7;
				// guests
				else
					$rows = 5;
			}
			$table .= "</table>"
			."<div class=\"summary\">".$wikilanguage->get("LANG_PAGES").": $counter</div>";
		}
		
		// display files
		elseif ($type == "files") {
			// don't show files to anonymous users
			if ($GRP_ANONYMOUS) {
				$table = $wikilanguage->get("LANG_FILEAFTERLOGIN");
			}
			else {
				if ($ACTION == "trashfiles") 
					$table .= "<table class=\"wikiTable\"><tr><th>".$wikilanguage->get("LANG_FILE")."</th><th>".$wikilanguage->get("LANG_DELETED")."</th><th>".$wikilanguage->get("LANG_BY")."</th><th>".$wikilanguage->get("LANG_FILESIZE")."</th><th>&nbsp;</th></tr>";
				else {
					$table .= "<table class=\"wikiTable\"><tr><th>".$wikilanguage->get("LANG_FILE")."</th><th>".$wikilanguage->get("LANG_LASTCHANGED")."</th><th>".$wikilanguage->get("LANG_BY")."</th><th>".$wikilanguage->get("LANG_FILESIZE")."</th>";
					if ($GRP_EDITCONTENT)
						$table .= "<th>&nbsp;</th>";
					$table .= "</tr>";
				}
				foreach ($filearray as $filename => $timestamp) {
					$tdclass = returnTdClass($counter);
					$table .= "<tr class=$tdclass><td>".getLastChangeMarker($timestamp);
					if ($ACTION == "trashfiles") {
						$table .= getLink("file", $DIR_TRASHFILES, $filename, true, true);
					}
					else
						$table .= getLink("file", $DIR_FILES, $filename, true, true);
					$table .= "</td><td>" . strftime($mainsettings->getTimeFormat(), $timestamp) . "</td><td>".getLastChanger("file", $filename)." </td><td>";
					if ($ACTION == "trashfiles")
						$mysize = convertFileSizeUnit(filesize($DIR_TRASHFILES . $filename));
					else
						$mysize = convertFileSizeUnit(filesize($DIR_FILES . $filename));
        	$table .= $mysize;
		        $table .= "</td>";
			        if ($GRP_EDITCONTENT) {
								$table .= "<td>";
			        if ($ACTION == "trashfiles") {
			        	if (fileIsReadonly($DIR_TRASHFILES, $filename))
			        		$table .= "<img class=\"noborder\" alt=\"-\" src=\"pic/restorefileiconlocked.gif\" title=\"".$wikilanguage->get("LANG_ISPROTECTEDSHORT")."\" />";
			        	else
			        		$table .= "<a href=\"index.php?action=trashfiles&amp;restore=true&amp;restorefile=$filename\" title=\"".$wikilanguage->get("LANG_RESTORE")." &quot;$filename&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_RESTORE")." &quot;$filename&quot;\" src=\"pic/restorefileicon.gif\" /></a>";
			        }
			        else {
			        	if (fileIsReadonly($DIR_FILES, $filename))
			        		$table .= "<img class=\"noborder\" alt=\"-\" src=\"pic/deletefileiconlocked.gif\" title=\"".$wikilanguage->get("LANG_ISPROTECTEDSHORT")."\" />";
			        	else {
					        if ($ACTION == "allfiles")
					        	$table .= "<a href=\"index.php?action=allfiles&amp;delete=true&amp;deletefile=$filename\" title=\"".$wikilanguage->get("LANG_DELETE")." &quot;$filename&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_DELETE")." &quot;$filename&quot;\" src=\"pic/deletefileicon.gif\" /></a>";
					        elseif ($ACTION == "recentfiles") 
					        	$table .= "<a href=\"index.php?action=recentfiles&amp;count=$count&amp;delete=true&amp;deletefile=$filename\" title=\"".$wikilanguage->get("LANG_DELETE")." &quot;$filename&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_DELETE")." &quot;$filename&quot;\" src=\"pic/deletefileicon.gif\" /></a>";
					        elseif ($ACTION == "searchfiles") 
					        	$table .= "<a href=\"index.php?action=searchfiles&amp;delete=true&amp;deletefile=$filename&amp;query=".htmlentities($_GET['query'])."\" title=\"".$wikilanguage->get("LANG_DELETE")." &quot;$filename&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_DELETE")." &quot;$filename&quot;\" src=\"pic/deletefileicon.gif\" /></a>";
			        	}
			        }
			        $table .= "</td>";
			      }
			      $table .= "</tr>";
						$counter++;
				}
				// summary row
	#			if ($GRP_EDITCONTENT)
	#				$rows = 5;
	#			else
	#				$rows = 4;
				$table .= "</table>"
				. "<div class=\"summary\">".$wikilanguage->get("LANG_FILES").": $counter</div>";
			}
			$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
			$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
  	}		
		// catch wrong parameter
		else
			die("Erroneous parameter \"\$type\" given at function displayFiles().");
		
		$TIME = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$PRINTLINK = getNoActionString($wikilanguage->get("LANG_NOACTION"));
		// return complete string
		return $table;
	}
	
// display page versions
	function displayPageVersions() {
		global $CURRENTUSER;
		global $DEFAULT_PAGESEXT;
		global $DIR_BACKUP;
		global $DIR_PAGES;
		global $GRP_ANONYMOUS;
		global $GRP_EDITUSERS;
		global $mainsettings;
		global $wikilanguage;
		global $wikiusers;
		global $wikipageversions;
		global $wikisyntax;

		// this function only works for admins
		if (!$GRP_EDITUSERS) {
			header("location:index.php");
		}
		
		$vpage = strip_tags(htmlentities($_GET['vpage']));
		$version = strip_tags(htmlentities($_GET['version']));
		
		$table = "";
		// do versions of the given page exist?
		$versions = array();
		$handle = opendir($DIR_BACKUP);
		while ($file = readdir($handle)) {
			if(($file <> ".") && ($file <> "..") && ($file <> "versions.conf") && (substr($file,0,strlen($vpage)) == $vpage)) {
				array_push($versions, substr($file, strlen($vpage)+1, strlen($file)-strlen($DEFAULT_PAGESEXT)-strlen($vpage)-1 ));
			}
		}
		rsort($versions);
		
		if (!file_exists($DIR_PAGES.$vpage.$DEFAULT_PAGESEXT))
			return getWarningReport($wikilanguage->get("REPORT_PAGE_NOT_EXISTS")." &quot;$vpage&quot;");
		// is there any previous version of the given page?
		if (count($versions) == 0)
			return getWarningReport($wikilanguage->get("REPORT_VERSIONS_NOT_EXIST")." &quot;$vpage&quot;");
			
		// view selected version
		if ($_GET['view'] == "true") {
			// restore given version
			if ($_GET['restore'] == "true") {
				if ($_GET['confirm'] == "true") {
					if (copy($DIR_BACKUP.$vpage."_".$version.$DEFAULT_PAGESEXT, $DIR_PAGES.$vpage.$DEFAULT_PAGESEXT)) {
						savePageBackup($vpage, $version);
						$wikipageversions->setChangerOfTime(time(), $vpage, $CURRENTUSER);
						$msg = getOkReport($wikilanguage->get("REPORT_VERSION_RESTORED")." &quot;".strftime($mainsettings->getTimeFormat(), $version)."&quot;");
					}
					else
						$msg = getWarningReport("&quot;".strftime($mainsettings->getTimeFormat(), $version)."&quot; ".$wikilanguage->get("REPORT_RESTOREVERSION_ERR"));
				}
				else {
					$msg = getWarningReport("&quot;".strftime($mainsettings->getTimeFormat(), $version)."&quot; ".$wikilanguage->get("CONFIRM_RESTOREVERSION")." <a href=\"index.php?action=pageversions&amp;view=true&amp;vpage=$vpage&amp;version=$version&amp;restore=true&amp;confirm=true\">".$wikilanguage->get("LANG_YES")."</a> - <a href=\"index.php?action=pageversions&amp;view=true&amp;vpage=$vpage&amp;version=$version\">".$wikilanguage->get("LANG_NO")."</a>");
				}
			}
			$table .= "<h3>$vpage</h3>"
			// table head
			. "<table class=\"wikiTable\">"
			. "<tr><th>".$wikilanguage->get("LANG_LASTCHANGED")."</th><th>".$wikilanguage->get("LANG_BY")."</th><th>&nbsp;</th></tr>"
			. "<tr class=".returnTdClass($i).">"
			// column "changed"
			. "<td>".strftime($mainsettings->getTimeFormat(), $version)."</td>"
			// column "changer"
			. "<td>".getLink("page", $DIR_PAGES, $wikiusers->getFullName($wikipageversions->getChangerOfTime($version, $vpage)), false, false)."</td>"
			// column "restore"
			. "<td>"
			. "<a href=\"index.php?action=pageversions&amp;vpage=$vpage&amp;version=$version&amp;view=true&amp;restore=true\" title=\"".$wikilanguage->get("LANG_VERSION_RESTORE")." &quot;".strftime($mainsettings->getTimeFormat(), $version)."&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_VERSION_RESTORE")." &quot;".strftime($mainsettings->getTimeFormat(), $version)."&quot;\" src=\"pic/restoreversionicon.gif\" /></a>"
			. "</td></tr>"
			// summary row
			. "</table>"
						. "<div class=\"summary\">"
			. "<span><a href=\"index.php?action=pageversions&amp;vpage=$vpage\">".$wikilanguage->get("LANG_PAGEVERSIONS")."</a></span>"
			. "</div>"
			// version selection
			. "<h3>".$wikilanguage->get("LANG_CHOOSEVERSION")."</h3>"
			. "<form method=\"get\" action=\"index.php\" name=\"selectversion\"><input type=\"hidden\" name=\"action\" value=\"pageversions\" /><input type=\"hidden\" name=\"vpage\" value=\"$vpage\" /><input type=\"hidden\" name=\"view\" value=\"true\" />"
			. "<select name=\"version\" class=\"content\">";
			foreach ($versions as $currentversion) {
				$selected = "";
				if ($currentversion == $version)
					$selected = " selected";
				$table .= "<option$selected value=\"$currentversion\">".strftime($mainsettings->getTimeFormat(), $currentversion)." (".$wikiusers->getFullName($wikipageversions->getChangerOfTime($currentversion, $vpage)).")</option>";
			}
			$table .= "</select>"
			. "<input type=\"image\" name=\"submit\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_SHOWVERSION")." &quot;".strftime($mainsettings->getTimeFormat(), $currentversion)."&quot;\" title=\"".$wikilanguage->get("LANG_SHOWVERSION")." &quot;".strftime($mainsettings->getTimeFormat(), $currentversion)."&quot;\" />"
			. "</form>";

			if (!$file = @fopen($DIR_BACKUP.$vpage."_".$version.$DEFAULT_PAGESEXT, "r"))
				die("Could not read ".$DIR_BACKUP.$vpage."_".$version.$DEFAULT_PAGESEXT."!");
			$content = fread($file, filesize($DIR_BACKUP.$vpage."_".$version.$DEFAULT_PAGESEXT));
			fclose($file);
			
			$table .= "<hr />".replaceEmoticons($wikisyntax->convertWikiSyntax("", $content, true));
		}
		
		// show all versions
		else {
			$table .= "<h3>$vpage</h3>"
			// table head
			. "<table class=\"wikiTable\">"
			. "<tr><th>".$wikilanguage->get("LANG_LASTCHANGED")."</th><th>".$wikilanguage->get("LANG_BY")."</th><th>&nbsp;</th></tr>";
			for ($i=0;$i<count($versions);$i++) {
				$table .= "<tr class=".returnTdClass($i).">"
				// column "changed"
				. "<td> ".strftime($mainsettings->getTimeFormat(), $versions[$i])."</td>"
				// column "changer"
				. "<td>".$wikiusers->getFullName($wikipageversions->getChangerOfTime($versions[$i], $vpage))."</td>"
				// column "view"
				. "<td>"
				. "<a href=\"index.php?action=pageversions&amp;view=true&amp;vpage=$vpage&amp;version=$versions[$i]\" title=\"".$wikilanguage->get("LANG_VERSION_VIEW")." &quot;".strftime($mainsettings->getTimeFormat(), $versions[$i])."&quot;\"><img class=\"noborder\" alt=\"".$wikilanguage->get("LANG_VERSION_VIEW")." &quot;".strftime($mainsettings->getTimeFormat(), $versions[$i])."&quot;\" src=\"pic/viewversionicon.gif\" /></a>"
				. "</td>"
				. "</tr>";
			}
			if ($i == 0)
				return getWarningReport($wikilanguage->get("REPORT_VERSIONS_NOT_EXIST")." &quot;$vpage&quot;");
			$table .= "</table>"
			. "<div class=\"summary\">".$wikilanguage->get("LANG_PAGEVERSIONS").": $i</div>";
		}
		return $msg.$table;
	}

// check if given page is currently locked
	function entryIsLocked($entry) {
		global $DIR_LOCKS;
		global $DEFAULT_LOCKSEXT;
		return file_exists($DIR_LOCKS.$entry.$DEFAULT_LOCKSEXT);
	}
	
// check if given file or page is read only
	function fileIsReadonly($dir, $file) {
		return (!is_writable($dir . $file));
	}

// check if given file is a user page or a wiki standard page
	function pageIsStrangersUserpageOrDefaultpage($page) {
		global $CURRENTUSER;
		global $DEFAULT_FUNCTIONSPAGE;
		global $DEFAULT_STARTPAGE;
		global $DEFAULT_TESTPAGE;
		global $wikiusers;

		$page = strip_tags($page);

		// check user pages
		foreach ($wikiusers->getAllUsers("") as $user=>$userdata) {
			if (($page == $wikiusers->getFullName($user)) && ($page <> $wikiusers->getFullName($CURRENTUSER)))
				return true;
		}
		// check default pages
		if (
				($page == $DEFAULT_FUNCTIONSPAGE) ||
				($page == $DEFAULT_STARTPAGE) ||
				($page == $DEFAULT_TESTPAGE)
		)
			return true;
		// no matches
		return false;
	}
	
// return a graphical hint, if file's latest change was within today
	function getLastChangeMarker($changedate) {
		// &rsaquo;
		// last within today
		if (date("dWmY") == date("dWmY", strftime($changedate)))
			return "<span class=\"changedtodaymarker\">&bull;</span>&nbsp;";
		// changed within last seven days
		elseif (time() - $changedate < 60*60*24*7) // seconds*minutes*hours*days = 1 week
			return "<span class=\"changedlastsevendaysmarker\">&bull;</span>&nbsp;";
		// changed earlier
		else 
			return "";
	}

// get the user who changed a file at last
	function getLastChanger($type, $file) {
		global $wikistats;
		global $wikiusers;
		global $wikilanguage;
		global $ACTION;
		global $DIR_PAGES;
		global $GRP_ANONYMOUS;
		
		// get user id
		if ($type == "file")
    	$lastchanger = $wikistats->getLastFileChanger($file);
		elseif ($type == "page")
    	$lastchanger = $wikistats->getLastPageChanger($file);
    if (($lastchanger == null) || (!$wikiusers->userExists($lastchanger)))
    	return getNoActionString($wikilanguage->get("LANG_NOUSERFOUND"));
    
    // return user name and online status icon depending on current edit status
		if ($ACTION == "edit")
			return "<em class=\"inactivemenupoint\">".$wikiusers->getFullName($lastchanger).onlineStatusIcon($lastchanger)."</em>";
		else
    	return getLink("page", $DIR_PAGES, $wikiusers->getFullName($lastchanger), false, false).onlineStatusIcon($lastchanger);
	}

// return the latest changed files/pages for the right menu
	function getLatestChanges($type, $count) {
		global $ACTION;
		global $DIR_PAGES;
		global $DIR_FILES;
		global $GRP_ANONYMOUS;
		global $PAGE_TITLE;
		global $mainsettings;
		global $wikilanguage;
		global $wikistringcleaner;
		$dirtocheck = "";
		$string = "";
		$link = "";

		// the latest files menu is not shown to anonymous viewers
		if ($GRP_ANONYMOUS && ($type == "file")) 
			return "";
		
		$string = "<div class=\"submenu\"><div class=\"submenudescription\">";
		// pages
		if ($type == "page") {
			$string .= $wikilanguage->get("LANG_LATESTPAGESMENU");
			$dirtocheck = $DIR_PAGES;
		}
		// files
		else {
			$string .= $wikilanguage->get("LANG_LATESTFILESMENU");
			$dirtocheck = $DIR_FILES;
		}
		$string .= "</div>"
		. "<div class=\"submenucontent\">";
		
		// read directory
		$filearray = returnQuery($dirtocheck, "", false, true, true, $count);
		if ($filearray == null) {
			$string .= "<div class=\"menuitem\">".$wikilanguage->get("LANG_DIRISEMPTY")."</div>";
		}
		else {
			foreach ($filearray as $filename => $timestamp) {
				// cut pages names
				if ($type == "page")
					$filename = substr($filename, 0, strlen($filename) - 4);
				// cut too long names
				$shortfilename = $wikistringcleaner->shortenName($filename);
				if ($ACTION == "edit")
					$string .= "<div class=\"menuitem\"><em class=\"inactivemenupoint\">".str_replace('_', ' ', $shortfilename)."</em></div>";
				else {
					// pages
					if ($type == "page"){
						if ($filename == $PAGE_TITLE)
							$linkclass = "class=\"activemenupoint\" ";
						else
							$linkclass = "";
						$string .= "<div class=\"menuitem\">".getLastChangeMarker($timestamp)."<a ".$linkclass."href=\"$filename\" title=\"".strftime($mainsettings->getTimeFormat(), $timestamp)." - ".str_replace('_', ' ', $filename)."\">".str_replace('_', ' ', $shortfilename)."</a></div>";
					}
					// files
					elseif ($type == "file")
						$string .= "<div class=\"menuitem\">".getLastChangeMarker($timestamp)."<a href=\"index.php?action=fileinfo&amp;file=$filename\" title=\"".strftime($mainsettings->getTimeFormat(), $timestamp)." - $filename\">".str_replace('_', ' ', $shortfilename)."</a></div>";
				}
			}
		}
		$string .= "</div></div>";
		return $string;
	}
	
// returning a link depending on parameters
	function getLink($type, $dir, $file, $shorten, $linktofileinfo) {
		global $DEFAULT_PAGESEXT;
		global $DIR_TRASHFILES;
		global $GRP_ANONYMOUS;
		global $GRP_EDITUSERS;
		global $wikilanguage;
		global $wikistringcleaner;
		
		$type = strip_tags($type);
		$dir = strip_tags($dir);
		$file = strip_tags($file);

		// cut too long names
		if ($shorten)
			$editedfilename = $wikistringcleaner->shortenName($file);
		else
			$editedfilename = $file;
		if ($type == "file") {
			if ($GRP_ANONYMOUS)
				return "<em class=\"inactivemenupoint\" title=\"".$wikilanguage->get("LANG_FILEAFTERLOGIN")."\">".$wikilanguage->get("LANG_FILE")."</em>";
			else {
				if ($dir == $DIR_TRASHFILES)
					$trash = "&amp;trashfile=true";
				else
					$trash = "";
				// show file info button? in trash only for admins!
				if ($linktofileinfo && (($dir != $DIR_TRASHFILES) || $GRP_EDITUSERS))
					return "<a href=\"download.php?file=$file$trash\" title=\"".$wikilanguage->get("LANG_SHOWFILE")." &quot;$file&quot;\" class=\"file\">$editedfilename</a>&nbsp;<a href=\"index.php?action=fileinfo&amp;file=$file$trash\" title=\"".$wikilanguage->get("LANG_FILEINFO").": &quot;$file&quot;\">&#9432;</a>";
				else
					return "<a href=\"download.php?file=$file$trash\" title=\"".$wikilanguage->get("LANG_SHOWFILE")." &quot;$file&quot;\" class=\"file\">$editedfilename</a>";
			}
		}
		elseif ($type == "page"){
			if (file_exists($dir.$file.$DEFAULT_PAGESEXT)) {
				if (isset($_GET['query']))
					$highlight = "&amp;highlight=".htmlentities($_GET['query']);
				else
					$highlight = "";
				return "<a href=\"$file$highlight\" class=\"page\" title=\"".$wikilanguage->get("LANG_SHOWPAGE")." &quot;$file&quot;\">".str_replace('_', ' ', $editedfilename)."</a>";
			}
			elseif ($GRP_ANONYMOUS)
				return "<a class=\"pending\" title=\"".$wikilanguage->get("LANG_NOSUCHPAGE")." &quot;$file&quot;\">$editedfilename</a>";
			else
				return "<a href=\"$file\" class=\"pending\" title=\"".$wikilanguage->get("LANG_SHOWNEWPAGE")." &quot;$file&quot;\">$editedfilename</a>";
		}
		else
			die ("Erroneous parameter given at function getLink()");
	}

// return user which currently locks the given page
	function getLocker($page) {
		global $DEFAULT_LOCKSEXT;
		global $DIR_LOCKS;
		global $wikiusers;
		// get locking user
		$handle = fopen($DIR_LOCKS.$page.$DEFAULT_LOCKSEXT, "r");
		$lockinguser = fread($handle, filesize($DIR_LOCKS.$page.$DEFAULT_LOCKSEXT));
	  fclose($handle);
		return $lockinguser;
	}
	
// return an ok report with the given phrase 
	function getOkReport($phrase) {
		return "<p class=\"reportok\">".$phrase."</p>";
	}

// return a warning report with the given phrase 
	function getWarningReport($phrase) {
		return "<p class=\"reportwarning\">".$phrase."</p>";
	}

// 
	function getNoActionString($msg) {
		return "<span title=\"".strip_tags($msg)."\">#</span>";
	}

// highlight given phrases in given content
	function highlight($content, $phrase) {
		$content = preg_replace("/((<[^>]*)|$phrase)/ie", '"\2"=="\1"? "\1":"<em class=\"highlight\">\1</em>"', $content);
		return $content;
	}
	
// replace emoticons with smileys
	function replaceEmoticons($content) {
		global $wikismileys;
		global $wikiusers;
		global $CURRENTUSER;
		if ($wikiusers->getShowSmileys($CURRENTUSER) || $CURRENTUSER == "")
			return $wikismileys->replaceEmoticons($content);
		else
			return $content;
	}

// restore file or page
	function restoreFile($type, $restorefile) {
		global $wikistats;
		global $wikilanguage;
		global $DIR_PAGES;
		global $DIR_FILES;
		global $DIR_TRASHPAGES;
		global $DIR_TRASHFILES;
		global $DEFAULT_PAGESEXT;
		global $CURRENTUSER;
		
		$type = strip_tags(htmlentities($type));
		$restorefile = strip_tags(htmlentities($restorefile));

		if (($restorefile <> "") && ($restorefile <> ".") && ($restorefile <> "..")) {
			// restore file
			if ($type == "file") {
	    		if (file_exists($DIR_TRASHFILES.$restorefile)) {
	    			if (!file_exists($DIR_FILES.$restorefile)) {
	    				if (copy($DIR_TRASHFILES.$restorefile,$DIR_FILES.$restorefile) && unlink($DIR_TRASHFILES.$restorefile)) {
	    					$wikistats->setLastFileChanger($restorefile, $CURRENTUSER);
		    				return getOkReport($wikilanguage->get("REPORT_FILE_REST_SUCCESS")." &quot;$restorefile&quot;");
		    			}
		    			else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_FILE_REST_ERROR")." &quot;$restorefile&quot;");
		    		}
		    		else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_FILE_REST_EXISTS")." &quot;$restorefile&quot;");				
		    	}
		    	else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_FILE_NOT_EXISTS")." &quot;$restorefile&quot;");
			}
			// restore page
			elseif ($type =="page") {
	    		if (file_exists($DIR_TRASHPAGES.$restorefile.$DEFAULT_PAGESEXT)) {
	    			if (!file_exists($DIR_PAGES.$restorefile.$DEFAULT_PAGESEXT)) {
	    				if (copy($DIR_TRASHPAGES.$restorefile.$DEFAULT_PAGESEXT,$DIR_PAGES.$restorefile.$DEFAULT_PAGESEXT) && unlink($DIR_TRASHPAGES.$restorefile.$DEFAULT_PAGESEXT)) {
	    					
		    				return getOkReport($wikilanguage->get("REPORT_PAGE_REST_SUCCESS")." &quot;$restorefile&quot;");
		    			}
		    			else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_PAGE_REST_ERROR")." &quot;$restorefile&quot;");
		    		}
		    		else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_PAGE_REST_EXISTS")." &quot;$restorefile&quot;");
		    	}
		    	else return getWarningReport($wikilanguage->get("REPORT_NOTHING_DONE")." ".$wikilanguage->get("REPORT_PAGE_NOT_EXISTS")." &quot;$restorefile&quot;");
	    	}		
	    }
	    // erroneous deletefile given
		else 
			die("Erroneous parameter \"\$type\" given at function restoreFile()");
	}

// return array of all files contained in given directory, matching the query (or null if no file matches)
	function returnQuery($dirtocheck, $query, $querycontent, $sort, $slice, $count) {
		$query = strip_tags(htmlentities($query));
		$dir = opendir(getcwd() . "/" . $dirtocheck);
		while ($file = readdir($dir)) {
			if(($file <> ".") && ($file <> "..")) {
				// no query string given? return every file
				if ($query == "") {
					$filearray[$file] = filemtime($dirtocheck . $file);
					continue;
				}
				// query filename and file's content
				if (($querycontent) && (filesize($dirtocheck.$file) > 0)) {
					$handle = fopen($dirtocheck . $file, "r");
					$content = fread($handle, filesize($dirtocheck . $file));
					fclose($handle);
					if ((substr_count(strtolower($content), strtolower($query)) > 0) || (substr_count(strtolower($file), strtolower($query)) > 0))
						$filearray[$file] = filemtime($dirtocheck . $file);
				}
				// only query filename
				elseif (!$querycontent) {
					if ($query == "")
						$filearray[$file] = filemtime($dirtocheck . $file);
					else
						if (substr_count(strtolower($file), strtolower($query))) {
							$filearray[$file] = filemtime($dirtocheck . $file);
						}
				}
			}
		}
    // if neccessary, sort files by last change
		if (($sort == true) && ($filearray <> null))
			arsort($filearray);
    // else sort files by name
		else 
			if ($filearray <> null)
				uksort($filearray, "strnatcasecmp");
   	// if neccessary, pop given number of files
		if (($slice == true) && ($filearray <> null))
			$filearray = array_slice($filearray, 0, $count);
		// finally: return the array
		return $filearray;
	}
	

// return background-color for td depending on given number
	function returnTdClass($number) {
		// even number
		if ($number%2 == 0)
			return "\"even\"";
		// uneven number
		else
			return "\"uneven\"";
	}
	
// show user settings
	function showUserSettings() {
		global $CURRENTUSER;
		global $DIR_LANGSETTINGS;
		global $GRP_ANONYMOUS;
		global $GRP_EDITUSERS;
		global $MAINDIR_STYLES;
		global $wikilanguage;
		global $wikiusers;
		
		// this function doesn't work for anonymous users
		if ($GRP_ANONYMOUS) {
			header("location:index.php");
		}

		// check and change to new settings
		if ($_GET['changesettings'] == "true") {
			$msg = "";
			$wikiusers->setShowSmileys($CURRENTUSER, $_GET['showsmileys']);
			$wikiusers->setWikiStyle($CURRENTUSER, $_GET['wikistyle']);
			$wikiusers->setWikiLanguage($CURRENTUSER, $_GET['languagefile']);
			$msg = getOkReport($wikilanguage->get("REPORT_CHANGES_APPLIED_NEXTACTION"));
		}
	// table
		$table .= "<form method=\"get\" action=\"index.php\" name=\"usersettings\"><input type=\"hidden\" name=\"action\" value=\"usersettings\" /><input type=\"hidden\" name=\"changesettings\" value=\"true\" />"
		. "<div class=\"myWiki\"><div class=\"myWiki-header\">".$wikilanguage->get("LANG_USERSETTINGS")."</div>";
	// row "showsmileys"
		$selected1 = "";
		$selected2 = "";
		if ($wikiusers->getShowSmileys($CURRENTUSER))
			$selected1 = " selected";
		else
			$selected2 = " selected";
		$table .= "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_SHOWSMILEYS")."</div><div><select name=\"showsmileys\" class=\"content\"><option$selected1 value=\"true\">".$wikilanguage->get("LANG_YES")."</option><option$selected2 value=\"false\">".$wikilanguage->get("LANG_NO")."</option></select></div></div>"
	// row "wikistyle"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_WIKISTYLE")."</div><div><select name=\"wikistyle\" class=\"content\">";
		$cssdir = opendir($MAINDIR_STYLES);
		$stylearray = array();
		while ($file = readdir($cssdir))
			if ($file[0] <> ".")
				array_push($stylearray, $file);
		natcasesort($stylearray);
		foreach($stylearray as $file) {
			$selected1 = "";
			if ($wikiusers->getWikiStyle($CURRENTUSER) == $file)
				$selected1 = " selected";
			$table .= "<option$selected1 value=\"$file\">$file</option>";
		}
		$table .= "</select></div></div>"
	// row "languagefile"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_WIKILANGUAGE")."</div><div><select name=\"languagefile\" class=\"content\">";
		$languagedir = opendir($DIR_LANGSETTINGS);
		while ($file = readdir($languagedir)) {
			$selected1 = "";
			if (($file <> ".") && ($file <> "..")) {
				if ($wikiusers->getWikiLanguage($CURRENTUSER).".prop" == $file)
					$selected1 = " selected";
				$table .= "<option$selected1 value=\"".substr($file, 0, strlen($file)-strlen(".prop"))."\">".substr($file, 0, strlen($file)-strlen(".prop"))."</option>";
			}
		}
		$table .= "</select></div></div>"
	// final row 
		. "<div class=\"summary\"><input type=\"image\" name=\"submit\" value=\"true\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" title=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" /><!--<input type=\"submit\" value=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" />--></div>"
		. "</div>"
		. "</form>"
		. "<a href=\"index.php?action=changepw\">".$wikilanguage->get("LANG_CHANGEPW")."</a>";
		return $msg.$table;
	}
	
// show wiki settings
	function showWikiSettings() {
		global $DIR_LANGSETTINGS;
		global $DIR_PAGEPATTERNS;
		global $DEFAULT_PAGESEXT;
		global $GRP_EDITUSERS;
		global $MAINDIR_STYLES;
		global $mainsettings;
		global $wikilanguage;
		global $wikistringcleaner;
		
		// this function works for admins only
		if (!$GRP_EDITUSERS) {
			header("location:index.php");
		}

		// check and change to new settings
		if ($_GET['changesettings'] == "true") {
			if (!preg_match("/^[0-9]+$/", $_GET['pageslifetime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['pageslifetime'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['fileslifetime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['fileslifetime'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['useridletime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['useridletime'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['pageslocktime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['pageslocktime'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['shortennamelength']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['shortennamelength'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['shortenlinklength']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['shortenlinklength'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['filesuploadsize']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['filesuploadsize'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['allowedfailedlogins']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['allowedfailedlogins'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['failedloginbantime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['failedloginbantime'])."&quot;");
			else {
				$mainsettings->setWikiName($_GET['wikiname']);
				$mainsettings->setMetaDesc($_GET['metadesc']);
				$mainsettings->setMetaKey($_GET['metakey']);
				$mainsettings->setDefaultWikiLanguage($_GET['defaultlang']);
				$mainsettings->setPagesLifeTime($_GET['pageslifetime']);
				$mainsettings->setFilesLifeTime($_GET['fileslifetime']);
				$mainsettings->setUserIdleTime($_GET['useridletime']);
				$mainsettings->setPagesLockTime($_GET['pageslocktime']);
				$mainsettings->setDefaultPagePattern($_GET['defaultpattern']);
				$mainsettings->setUseHtmlTag($_GET['usehtmltag']);
				$mainsettings->setAnonymousAccess($_GET['anonymousaccess']);
				$mainsettings->setShowUsersOnlineList($_GET['showonlineusers']);
				$mainsettings->setTimeFormat($_GET['timeformat']);
				$mainsettings->setUploadExtensionsAllowed($_GET['uploadextallow']);
				$mainsettings->setUploadExtensions($_GET['uploadext']);
				$mainsettings->setDefaultWikiStyle($_GET['defaultstyle']);
				$mainsettings->setShortenNameLength($_GET['shortennamelength']);
				$mainsettings->setShortenLinkLength($_GET['shortenlinklength']);
				$mainsettings->setFilesMaxUploadSize($_GET['filesuploadsize']);
				$mainsettings->setFailLogins($_GET['allowedfailedlogins']);
				$mainsettings->setFailLoginBanTime($_GET['failedloginbantime']);
				$msg = getOkReport($wikilanguage->get("REPORT_CHANGES_APPLIED"));
			}
		}
	// table
		$table .= "<h3>".$wikilanguage->get("LANG_WIKISETTINGS")."</h3><form method=\"get\" action=\"index.php\" name=\"wikisettings\"><input type=\"hidden\" name=\"action\" value=\"wikisettings\" /><input type=\"hidden\" name=\"changesettings\" value=\"true\" />"
		. "<div class=\"myWiki\">"
// general settings
		. "<div class=\"myWiki-header\">".$wikilanguage->get("LANG_GENERALSETTINGS")."</div>"
	// row "wiki name"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_WIKINAME")."</div>"
		. "<div><input type=\"text\" name=\"wikiname\" value=\"".htmlentities($mainsettings->getWikiName())."\" /></div>"
		. "</div>"
	// row "meta describtion"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_METADESC")."</div>"
		. "<div><input type=\"text\" name=\"metadesc\" value=\"".htmlentities($mainsettings->getMetaDesc())."\" /></div>"
		. "</div>"	
		// row "meta keywords"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_METAKEY")."</div>"
		. "<div><input type=\"text\" name=\"metakey\" value=\"".htmlentities($mainsettings->getMetaKey())."\" /></div>"
		. "</div>"	
	// row "default language"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_DEFAULTLANGUAGE")."</div>"
		. "<div><select name=\"defaultlang\" class=\"content\">";
		$languagedir = opendir($DIR_LANGSETTINGS);
		while ($file = readdir($languagedir)) {
			$selected1 = "";
			if (($file <> ".") && ($file <> "..")) {
				if ($mainsettings->getDefaultWikiLanguage().".prop" == $file)
					$selected1 = " selected";
				$table .= "<option$selected1 value=\"".substr($file, 0, strlen($file)-strlen(".prop"))."\">".substr($file, 0, strlen($file)-strlen(".prop"))."</option>";
			}
		}
		$table .= "</select></div></div>"
	// row "default wiki style"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_DEFAULTWIKISTYLE")."</div>"
		. "<div><select name=\"defaultstyle\" class=\"content\">";
		$cssdir = opendir($MAINDIR_STYLES);
		$stylearray = array();
		while ($file = readdir($cssdir))
			if ($file[0] <> ".")
				array_push($stylearray, $file);
		natcasesort($stylearray);
		foreach($stylearray as $file) {
			$selected1 = "";
			if ($mainsettings->getDefaultWikiStyle() == $file)
				$selected1 = " selected";
			$table .= "<option$selected1 value=\"$file\">$file</option>";
		}
		$table .= "</select></div></div>";
	// row "allow html tag"
		$selected1 = "";
		$selected2 = "";
		if ($mainsettings->getUseHtmlTag() == "true")
			$selected1 = " selected";
		else
			$selected2 = " selected";
		$table .= "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_USEHTMLTAG")."</div>"
		. "<div><select name=\"usehtmltag\" class=\"content\"><option$selected1 value=\"true\">".$wikilanguage->get("LANG_YES")."</option><option$selected2 value=\"false\">".$wikilanguage->get("LANG_NO")."</option></select></div></div>"
	// row "time format"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_TIMEFORMAT")."</div>"
		. "<div><select name=\"timeformat\" class=\"content\">";
		// 31.12.1999 23:59:59
		$selected1 = "";
		if ($mainsettings->getTimeFormat() == "%d.%m.%Y, %H:%M:%S")
			$selected1 = " selected";
		$table .= "<option$selected1 value=\"%d.%m.%Y, %H:%M:%S\">".strftime("%d.%m.%Y, %H:%M:%S", time())."</option>";
		// 1999-12-31 23:59:59
		$selected1 = "";
		if ($mainsettings->getTimeFormat() == "%Y-%m-%d, %H:%M:%S")
			$selected1 = " selected";
		$table .= "<option$selected1 value=\"%Y-%m-%d, %H:%M:%S\">".strftime("%Y-%m-%d, %H:%M:%S", time())."</option>";
		// 1999-31-12 23:59:59
		$selected1 = "";
		if ($mainsettings->getTimeFormat() == "%Y-%d-%m, %H:%M:%S")
			$selected1 = " selected";
		$table .= "<option$selected1 value=\"%Y-%d-%m, %H:%M:%S\">".strftime("%Y-%d-%m, %H:%M:%S", time())."</option>";
		// 31/12/99 23:59:59
		$selected1 = "";
		if ($mainsettings->getTimeFormat() == "%d/%m/%y, %H:%M:%S")
			$selected1 = " selected";
		$table .= "<option$selected1 value=\"%d/%m/%y, %H:%M:%S\">".strftime("%d/%m/%y, %H:%M:%S", time())."</option>";
		// 31 Dec 1999 23:59:59
		$selected1 = "";
		if ($mainsettings->getTimeFormat() == "%d %b %Y, %H:%M:%S")
			$selected1 = " selected";
		$table .= "<option$selected1 value=\"%d %b %Y, %H:%M:%S\">".strftime("%d %b %Y, %H:%M:%S", time())."</option>";
		// end of select
		$table .= "</select></div></div>"
	// row "shorten file names"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_SHORTENNAMELENGTH")."</div>"
		. "<div><input type=\"text\" name=\"shortennamelength\" value=\"".$mainsettings->getShortenNameLength()."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_CHARACTERS")."</div></div>"

	// row "shorten links"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_SHORTENLINKLENGTH")."</div>"
		. "<div><input type=\"text\" name=\"shortenlinklength\" value=\"".$mainsettings->getShortenLinkLength()."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_CHARACTERS")."</div></div>"

// user settings
		. "<div class=\"myWiki-header\">".$wikilanguage->get("LANG_USERS")."</div>";
	// row "allow anonymous access"
		$selected1 = "";
		$selected2 = "";
		if ($mainsettings->getAnonymousAccess() == "true")
			$selected1 = " selected";
		else
			$selected2 = " selected";
		$table .= "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_ALLOWANONYMOUSACCESS")."</div><div><select name=\"anonymousaccess\" class=\"content\"><option$selected1 value=\"true\">".$wikilanguage->get("LANG_YES")."</option><option$selected2 value=\"false\">".$wikilanguage->get("LANG_NO")."</option></select></div></div>"
	// row "user idle time"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_USERIDLETIME")."</div><div><input type=\"text\" name=\"useridletime\" value=\"".$mainsettings->getUserIdleTime()."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_MINUTES")."</div></div>"
	// row "ban after x failed logins"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_FAILLOGINSALLOWED")."</div><div><input type=\"text\" name=\"allowedfailedlogins\" value=\"".$mainsettings->getFailLogins()."\" class=\"narrow\" /></div></div>"
	// row "ban time after failed logins"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_FAILLOGINBANTIME")."</div><div><input type=\"text\" name=\"failedloginbantime\" value=\"".$mainsettings->getFailLoginBanTime()."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_MINUTES")."</div></div>";
	// row "show users online list"
		$selected1 = "";
		$selected2 = "";
		if ($mainsettings->getShowUsersOnlineList() == "true")
			$selected1 = " selected";
		else
			$selected2 = " selected";
		$table .= "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_SHOWUSERSONLINELIST")."</div><div><select name=\"showonlineusers\" class=\"content\"><option$selected1 value=\"true\">".$wikilanguage->get("LANG_YES")."</option><option$selected2 value=\"false\">".$wikilanguage->get("LANG_NO")."</option></select></div></div>"

// pages settings
		. "<div class=\"myWiki-header\">".$wikilanguage->get("LANG_PAGES")."</div>"
	// row "default page pattern"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_DEFAULTPATTERN")."</div>"
		. "<div>";
		$selectbox = "<select name=\"defaultpattern\" class=\"content\">";
		$i = 0;
		$patternsdir = opendir(getcwd() . "/$DIR_PAGEPATTERNS");
		while ($file = readdir($patternsdir)) {
			if (($file <> ".") && ($file <> "..")) {
				$selected = "";
				$file = substr($file, 0, strlen($file)-strlen($DEFAULT_PAGESEXT));
				if ($file == $mainsettings->getDefaultPagePattern())
					$selected = " selected";
				$selectbox .= "<option$selected value=\"$file\">$file</option>";
				$i++;
			}
		}
		$selectbox .= "</select>";
		if ($i > 0)
			$table .= $selectbox;
		$table .= " <a href=\"index.php?action=editpattern\" title=\"".$wikilanguage->get("LANG_EDITPATTERNS")."\"><img src=\"pic/editpatternicon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_EDITPATTERNS")."\" /></a></div></div>"
	// row "pages lock time"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_PAGESLOCKTIME")."</div>"
		. "<div><input type=\"text\" name=\"pageslocktime\" value=\"".$mainsettings->getPagesLockTime()."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_MINUTES")."</div></div>"
	// row "set life time of pages"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_PAGESLIFETIME")."</div>"
		. "<div><input type=\"text\" name=\"pageslifetime\" value=\"".$mainsettings->getPagesLifeTime()."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_DAYS")."</div></div>"
// files settings
		. "<div class=\"myWiki-header\">".$wikilanguage->get("LANG_FILES")."</div>"
	// row "maximum upload size"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_FILESMAXUPLOADSIZE")."</div>"
		. "<div><input type=\"text\" name=\"filesuploadsize\" value=\"".$mainsettings->getFilesMaxUploadSize()."\" class=\"narrow\" /> kB</div></div>";
	// row "forbidden/allowed extensions"
		$checked1 = "";
		$checked2 = "";
		if ($mainsettings->getUploadExtensionsAllowed())
			$checked1 = "checked=\"checked\" ";
		else
			$checked2 = "checked=\"checked\" ";
		$table .= "<div class=".returnTdClass(1)."><div style=\"display:flex; align-items:center\">".$wikilanguage->get("LANG_EXTUPLOAD")." <input type=\"radio\" name=\"uploadextallow\" value=\"true\" $checked1/> ".$wikilanguage->get("LANG_EXTALLOW")." <input type=\"radio\" name=\"uploadextallow\" value=\"false\" $checked2/>".$wikilanguage->get("LANG_EXTFORBID")."</div>"
		. "<div><input type=\"text\" name=\"uploadext\" value=\"".htmlentities($mainsettings->getUploadExtensions())."\" /></div></div>"
	// row "set life time of files"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_FILESLIFETIME")."</div>"
		. "<div><input type=\"text\" name=\"fileslifetime\" value=\"".$mainsettings->getFilesLifeTime()."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_DAYS")."</div></div>"

	// summary row
		. "<div class=\"summary\"><input type=\"image\" name=\"submit\" value=\"true\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" title=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" /></div>"
		. "</div>"
		. "</form>";
		return $msg.$table;
	}
	
// show file upload form
	function showUploadForm() {
		global $CURRENTUSER;
		global $DIR_FILES;
		global $GRP_EDITCONTENT;
		global $GRP_EDITUSERS;
		global $mainsettings;
		global $wikilanguage;
		global $wikistats;
		global $wikistringcleaner;
		
		// this function doesn't work for guests
		if (!$GRP_EDITCONTENT) {
			header("location:index.php");
		}
		
		$content = "";
		$msg = "";
		// upload given file
		if ($_SERVER["REQUEST_METHOD"] == "POST"){
		  if (isset($_FILES['uploadfile']) and !$_FILES['uploadfile']['error']) {
		  	// allowed extension?
 		    if (!$GRP_EDITUSERS && !allowedToUpload($_FILES['uploadfile']['name'])) {
		    	$msg = getWarningReport($wikilanguage->get("REPORT_UPLOAD_EXT_ERROR")." ".$wikistringcleaner->cleanThatString($_FILES['uploadfile']['name'], true));
		    }
		    // file size within upload limit?
 		    elseif (!$GRP_EDITUSERS && !filesizeWithinLimit($_FILES['uploadfile']['size'])) {
		    	$msg = getWarningReport($wikilanguage->get("REPORT_UPLOAD_SIZE_ERROR")." ".$wikistringcleaner->cleanThatString($_FILES['uploadfile']['name'], true));
		    }
		    // already existant in files directory?
		    elseif (file_exists($DIR_FILES.$wikistringcleaner->cleanThatString($_FILES['uploadfile']['name'], true))) {
		    	$msg = getWarningReport($wikilanguage->get("REPORT_UPLOAD_ERROR")." ".$wikistringcleaner->cleanThatString($_FILES['uploadfile']['name'], true));
		    }
		    // everything is fine - store the file!
		    else {
		    	move_uploaded_file($_FILES['uploadfile']['tmp_name'], $DIR_FILES.$wikistringcleaner->cleanThatString($_FILES['uploadfile']['name'], true));
		    	$wikistats->setFileComment($_FILES['uploadfile']['name'], $_POST['comment']);
		    	$wikistats->setLastFileChanger($_FILES['uploadfile']['name'], $CURRENTUSER);
//		    	$wikistats->setFileDownloadCount($_FILES['uploadfile']['name'], "0");
		    	if ($_POST['comment'] != "")
		    		$wikistats->setFileCommentChanger($_FILES['uploadfile']['name'], $CURRENTUSER);
					$msg = getOkReport($wikilanguage->get("REPORT_UPLOAD_SUCCESS")." ".$wikistringcleaner->cleanThatString($_FILES['uploadfile']['name'], true)." (".$_FILES['uploadfile']['size']." Bytes; ".$_FILES['uploadfile']['type'].")");
			    $wikistats->setLastFileChanger($wikistringcleaner->cleanThatString($_FILES['uploadfile']['name'], true), $CURRENTUSER);
			  }
			}
		}
		// show upload form
		$content .= "<form method=\"post\" action=\"index.php\" enctype=\"multipart/form-data\"><input type=\"hidden\" name=\"upload\" value=\"true\" /><div class=\"myWiki\"><div class=\"myWiki-header\">".$wikilanguage->get("LANG_UPLOAD")."</div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_FILE")."</div><div><input type=\"file\" name=\"uploadfile\" /></div></div>"
			."<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_COMMENT")."</div><div><input type=\"text\" name=\"comment\" value=\"\" /></div></div>"
			."<div class=\"summary\"><input type=\"image\" name=\"submit\" value=\"true\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("BUTTON_UPLOAD")."\" title=\"".$wikilanguage->get("BUTTON_UPLOAD")."\" /></div>"
			
			//<input type=\"submit\" value=\"".$wikilanguage->get("BUTTON_UPLOAD")."\" /></td></tr>"
			."</div></form>";
		return $msg.$content;
	}
	
	
// edit a single user
	function editUser() {
		global $CURRENTUSER;
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGES;
		global $DIR_TRASHPAGES;
		global $GRP_EDITUSERS;
		global $mainsettings;
		global $wikigroups;
		global $wikilanguage;
		global $wikistats;
		global $wikistringcleaner;
		global $wikiusers;

		$id = strip_tags(htmlentities($_GET['id']));

		// this function works for admins only
		if (!$GRP_EDITUSERS) {
			header("location:index.php");
		}

		// edit existing user
		elseif ((isset($_GET['edituser'])) && ($_GET['edituser'] == "true")) {
			if (!preg_match("/^[0-9]+$/", $_GET['newlifetime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['newlifetime'])."&quot;");
			elseif (!preg_match("/^[0-9]+$/", $_GET['newbantime']))
				$msg = getWarningReport($wikilanguage->get("REPORT_NUM_INVALID")." &quot;".strip_tags($_GET['newbantime'])."&quot;");
			else {
				if ($_GET['newisbanned'] == "on") {
					$isbanned = time();
					$bantime = $_GET['newbantime'];
				}
				else {
					$isbanned = 0;
					$bantime = 0;
				}
				if ($wikiusers->addUser($id, $wikiusers->getPassword($id), $wikistringcleaner->cleanThatString(htmlentities($_GET['newfullname']), false), $_GET['newgroup'], $_GET['newlifetime'], $isbanned, $bantime, $wikiusers->getWikiStyle($id), true)) {
					$wikiusers->loadWikiUsers();
					$msg = getOkReport($wikilanguage->get("REPORT_USER_CHANGED")." &quot;".$wikiusers->getFullName($id)."&quot;");
				}
				else
					$msg = getWarningReport($wikilanguage->get("REPORT_USER_NOT_CHANGED")." &quot;".$id."&quot;");
				}
		}
		// delete existing user
		elseif ((isset($_GET['deleteuser'])) && ($_GET['deleteuser'] == "true")) {
			$fullname = $wikiusers->getFullName($_GET['id']);
			if ($fullname == "") 
				$fullname = $_GET['id'];
			if ($_GET['confirm'] == "true") {
				// also delete userpage, if given
				$pagedelmsg = "";
				if ($_GET['deleteuserpage'] == "true") {
					if (file_exists($DIR_PAGES.$fullname.$DEFAULT_PAGESEXT)) {
						if (copy($DIR_PAGES.$fullname.$DEFAULT_PAGESEXT, $DIR_TRASHPAGES.$fullname.$DEFAULT_PAGESEXT) && unlink($DIR_PAGES.$fullname.$DEFAULT_PAGESEXT)) {
							$wikistats->setLastPageChanger($deletefile, $CURRENTUSER);
							$pagedelmsg = "<br />".$wikilanguage->get("REPORT_PAGE_DEL_SUCCESS")." &quot;".$fullname."&quot;";
						}
						else
							$pagedelmsg = "<br />".$wikilanguage->get("REPORT_PAGE_DEL_ERROR")." &quot;".$fullname."&quot;";
					}
					else
						$pagedelmsg = "<br />".$wikilanguage->get("REPORT_PAGE_NOT_EXISTS")." &quot;".$fullname."&quot;";
				}
				if ($wikiusers->deleteUser($id)) {
					$wikiusers->loadWikiUsers();
					$msg = getOkReport($wikilanguage->get("REPORT_USER_DELETED")." &quot;".$fullname."&quot;$pagedelmsg");
				}
				else
					$msg = getWarningReport($wikilanguage->get("REPORT_USER_NOT_DELETED")." &quot;".$fullname."&quot;$pagedelmsg");
			}
			else
				$msg = getWarningReport("&quot;".$fullname."&quot; ".$wikilanguage->get("CONFIRM_DELETEUSER")." <a href=\"index.php?action=edituser&amp;deleteuser=true&amp;id=$id&amp;confirm=true\">".$wikilanguage->get("LANG_YES")."</a> - <a href=\"index.php?action=edituser&amp;deleteuser=true&amp;id=$id&amp;deleteuserpage=true&amp;confirm=true\">".$wikilanguage->get("LANG_YESWITHPAGE")."</a> - <a href=\"index.php?action=edituser&amp;id=$id\">".$wikilanguage->get("LANG_NO")."</a>");
		}
		// kick user
		elseif ((isset($_GET['kickuser'])) && ($_GET['kickuser'] == "true")) {
			$wikiusers->setIsOnline($id, "false");
			$msg = getOkReport($wikilanguage->get("REPORT_USER_KICKED")." &quot;".$wikiusers->getFullName($id)."&quot;");
		}
		// reset user's pw
		elseif ((isset($_GET['resetuser'])) && ($_GET['resetuser'] == "true") && ($wikiusers->userExists($id))) {
			if ($_GET['confirm'] == "true") {
				$wikiusers->setPassword($id, "");
				$wikiusers->setHasInitialPw($id, "true");
				$wikiusers->loadWikiUsers();
				$msg = getOkReport($wikilanguage->get("REPORT_USER_RESETTED")." &quot;".$wikiusers->getFullName($id)."&quot;");
			}
			else
				$msg = getWarningReport("&quot;".$wikiusers->getFullName($id)."&quot; ".$wikilanguage->get("CONFIRM_RESETUSER")." <a href=\"index.php?action=edituser&amp;resetuser=true&amp;id=$id&amp;confirm=true\">".$wikilanguage->get("LANG_YES")."</a> - <a href=\"index.php?action=edituser&amp;id=$id\">".$wikilanguage->get("LANG_NO")."</a>");
		}

		// prepare
		$currentlyonline = "";
		$lastaction = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		$firstaction = getNoActionString($wikilanguage->get("LANG_NOTIMEFOUND"));
		if ($wikiusers->isOnline($id))
			$currentlyonline = $wikilanguage->get("LANG_YES");
		if ($wikiusers->getLastAction($id) <> "")
			$lastaction = strftime($mainsettings->getTimeFormat(), $wikiusers->getLastAction($id));
		if ($wikiusers->getFirstAction($id) <> "")
			$firstaction = strftime($mainsettings->getTimeFormat(), $wikiusers->getFirstAction($id));

		$table = "<form method=\"get\" action=\"index.php\" name=\"edituser\"><input type=\"hidden\" name=\"action\" value=\"edituser\" /><input type=\"hidden\" name=\"id\" value=\"$id\" />"
		// table head
		. "<div class=\"myWiki\"><div class=\"myWiki-header\">".$wikilanguage->get("LANG_EDITUSER")." $id (".$wikiusers->getFullName($id).")</div>"
		// row "id"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_USERID")."</div>"
		. "<div><b>$id</b></div></div>"
		// row "fullname"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_USERNAME")."</div>"
		. "<div><input type=\"text\" name=\"newfullname\" value=\"".$wikiusers->getFullName($id)."\" /></div></div>"
		// row "firstonline"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_FIRSTONLINE")."</div>"
		. "<div>$firstaction</div></div>"
		// row "lastonline"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_LASTONLINE")."</div>"
		. "<div>$lastaction</div></div>"
		// row "currentlyonline"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_CURRENTLYONLINE")."</div>"
		. "<div>$currentlyonline</div></div>"
		// row "logins"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_LOGINS")."</div>"
		. "<div>".$wikiusers->getLoginCount($id)."</div></div>"
		// row "group"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_GROUP")."</div>"
		. "<div>";
		if (($id == "admin") || ($id == $CURRENTUSER))
			$table .= "<input type=\"hidden\" name=\"newgroup\" value=\"".$wikiusers->getUserGroup($CURRENTUSER)."\" />".$wikigroups->getName($wikiusers->getUserGroup($id));
		else {
			$table .= "<select name=\"newgroup\" class=\"content\">";
			for ($i=1;$i<=$wikigroups->getGroupCount();$i++) {
				$selected = "";
				if ($wikiusers->getUserGroup($id) == $i)
					$selected = " selected";
				$table .= "<option$selected value=\"$i\">".$wikigroups->getName($i)."</option>";
			}
			$table .= "</select>";
		}
		$table .= "</div></div>"
		// row "lifetime"
		. "<div class=".returnTdClass(1)."><div>".$wikilanguage->get("LANG_USERLIFETIME")."</div>"
		. "<div>";
		if (($id == "admin") || ($id == $CURRENTUSER))
			$table .= getNoActionString($wikilanguage->get("LANG_NOACTION"))."<input type=\"hidden\" name=\"newlifetime\" value=\"0\" />";
		else {
			if (($wikiusers->getLifeTime($id) == "") || ($wikiusers->getLifeTime($id) == 0))
				$table .= "<input type=\"text\" name=\"newlifetime\" value=\"0\" class=\"narrow\" /> ".$wikilanguage->get("LANG_DAYS")." (".$wikilanguage->get("LANG_INFINITE").")";
			else {
				$table .= "<input type=\"text\" name=\"newlifetime\" value=\"".$wikiusers->getLifeTime($id)."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_DAYS");
				if ($wikiusers->getFirstAction($id) <> "")
					$table .= " (".$wikilanguage->get("LANG_TILL")." ".strftime($mainsettings->getTimeFormat(), ($wikiusers->getLifeTime($id)*24*60*60+$wikiusers->getFirstAction($id))).")";
				else
					$table .= " (".$wikilanguage->get("LANG_FROMFIRSTLOGIN").")";
			}
		}
		$table .= "</div></div>"
		// row "ban time"
		. "<div class=".returnTdClass(0)."><div>".$wikilanguage->get("LANG_USERBANTIME")."</div>"
		. "<div>";
		if (($id == "admin") || ($id == $CURRENTUSER))
			$table .= getNoActionString($wikilanguage->get("LANG_NOACTION"))."<input type=\"hidden\" name=\"newbantime\" value=\"0\" />";
		else {
			$checked = "";
			$banfinish = "";
			if ($wikiusers->getIsBanned($id) > 0) {
				$checked = " checked";
				if ($wikiusers->getBanTime($id) > 0)
					$banfinish = " (".$wikilanguage->get("LANG_TILL")." ".strftime($mainsettings->getTimeFormat(), ($wikiusers->getIsBanned($id)+$wikiusers->getBanTime($id)*60)).")";
				else
					$banfinish = " (".$wikilanguage->get("LANG_INFINITE").")";
			}
			$table .= "<input type=\"checkbox\" name=\"newisbanned\"".$checked." /><input type=\"text\" name=\"newbantime\" value=\"".$wikiusers->getBanTime($id)."\" class=\"narrow\" /> ".$wikilanguage->get("LANG_MINUTES")."$banfinish";
		}
		$table .= "</div></div>"

		// summary row
		. "<div class=\"summary\">"
		// link "all users"
		. "<span><a href=\"index.php?action=allusers\">".$wikilanguage->get("LANG_ALLUSERS")."</a></span>"
		// button "apply"
		. "<span>"
		. "<input type=\"image\" name=\"edituser\" value=\"true\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_USERCHANGESAPPLY")." &quot;".$wikiusers->getFullName($id)."&quot;\" title=\"".$wikilanguage->get("LANG_USERCHANGESAPPLY")." &quot;".$wikiusers->getFullName($id)."&quot;\" />";
		// button "kick/delete"
		if (($id <> "admin") && ($id <> $CURRENTUSER)) {
			// if user is online: kick
			if ($wikiusers->isOnline($id))
				$table .= "<a href=\"index.php?action=edituser&amp;kickuser=true&amp;id=$id\" title=\"".$wikilanguage->get("LANG_USERKICK")." &quot;".$wikiusers->getFullName($id)."&quot;\"><img src=\"pic/kickusericon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_USERKICK")." &quot;".$wikiusers->getFullName($id)."&quot;\" /></a>";
			// if user is offline: delete
			else
				$table .= "<a href=\"index.php?action=edituser&amp;deleteuser=true&amp;id=$id\" title=\"".$wikilanguage->get("LANG_USERDELETE")." &quot;".$wikiusers->getFullName($id)."&quot;\"><img src=\"pic/deleteusericon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_USERDELETE")." &quot;".$wikiusers->getFullName($id)."&quot;\" /></a>";
		}
		// button "reset"
		if (($id <> "admin") && ($id <> $CURRENTUSER) && !$wikiusers->isOnline($id))
			$table .= "<a href=\"index.php?action=edituser&amp;resetuser=true&amp;id=$id\" title=\"".$wikilanguage->get("LANG_RESETPW")." &quot;".$wikiusers->getFullName($id)."&quot;\"><img src=\"pic/reseticon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_RESETPW")." &quot;".$wikiusers->getFullName($id)."&quot;\" /></a>";
		$table .= "</span>"
		. "</div>"
		
		// table end
		. "</div></form>";

		// no or invalid id given
		if (($id == "") || (!$wikiusers->userExists($id))) {
			$table = "";
			$msg = getWarningReport($wikilanguage->get("LANG_NOSUCHUSER")." $id");
		}
		// user selection
		$table .= "<h6>".$wikilanguage->get("LANG_CHOOSEUSER")."</h6>"
		. "<form method=\"get\" action=\"index.php\" name=\"selectuser\"><input type=\"hidden\" name=\"action\" value=\"edituser\" />"
		. "<select name=\"id\" class=\"content\">";
		foreach ($wikiusers->getAllUsers("") as $user=>$userdata) {
			$selected = "";
			if ($user == $id)
				$selected = " selected";
			$table .= "<option$selected value=\"$user\">$user (".$wikiusers->getFullName($user).")</option>";
		}
		$table .= "</select>"
		. "<input type=\"image\" name=\"submit\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_CHOOSEUSER")."\" title=\"".$wikilanguage->get("LANG_CHOOSEUSER")."\" />"
		. "</form>";

		// return the whole thing
		return $msg.$table;
	}

// edit a single user
	function editPattern() {
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGEPATTERNS;
		global $GRP_EDITUSERS;
		global $mainsettings;
		global $wikilanguage;
		global $wikistringcleaner;
		
		// this function works for admins only
		if (!$GRP_EDITUSERS) {
			header("location:index.php");
		}
		
		if (isset($_GET['pattern']))
			$pattern = strip_tags(htmlentities($_GET['pattern']));
		else
			$pattern = $_POST['pattern'];
		$table = "";
		$msg = "";
		
		// save pattern
		if ($_GET['savepattern'] == "true") {
			if (!$file = @fopen($DIR_PAGEPATTERNS.$pattern.$DEFAULT_PAGESEXT, "w")) 
				die("Could not write page ".$pattern.$DEFAULT_PAGESEXT."!");
			if (get_magic_quotes_gpc())
				if (fputs($file, trim(stripslashes($_GET["content"]))))
					$msg = getOkReport($wikilanguage->get("REPORT_PATTERN_SAVE_SUCCESS")." &quot;".$pattern."&quot;");
				else
					$msg = getWarningReport($wikilanguage->get("REPORT_PATTERN_SAVE_ERROR")." &quot;".$pattern."&quot;");
			else
				if (fputs($file, trim($_GET["content"])))
					$msg = getOkReport($wikilanguage->get("REPORT_PATTERN_SAVE_SUCCESS")." &quot;".$pattern."&quot;");
				else
					$msg = getWarningReport($wikilanguage->get("REPORT_PATTERN_SAVE_ERROR")." &quot;".$pattern."&quot;");
			fclose($file);
			$msg = getOkReport($wikilanguage->get("REPORT_PATTERN_SAVE_SUCCESS")." &quot;".$pattern."&quot;");
			$pattern = "";
		}
		// delete pattern
		elseif ($_GET['deletepattern'] == "true") {
			if ($_GET['confirm'] == "true") {
				if (file_exists($DIR_PAGEPATTERNS.$pattern.$DEFAULT_PAGESEXT)) {
					if (unlink($DIR_PAGEPATTERNS.$pattern.$DEFAULT_PAGESEXT)) {
						$msg = getOkReport($wikilanguage->get("REPORT_PATTERN_DELETED")." &quot;".$pattern."&quot;");
					}
					else
						$msg = getWarningReport($wikilanguage->get("REPORT_PATTERN_DEL_ERROR")." &quot;".$pattern."&quot;");
				}
				else
						$msg = getWarningReport($wikilanguage->get("REPORT_PATTERN_NOT_EXISTS")." &quot;".$pattern."&quot;");

			}
			else
				$msg = getWarningReport("&quot;".$pattern."&quot; ".$wikilanguage->get("CONFIRM_DELETEPATTERN")." <a href=\"index.php?action=editpattern&deletepattern=true&pattern=$pattern&amp;confirm=true\">".$wikilanguage->get("LANG_YES")."</a> - <a href=\"index.php?action=editpattern\">".$wikilanguage->get("LANG_NO")."</a>");
			$pattern = "";
		}
			
		// show list of all patterns
		if ($pattern == "") {
			// table "new pattern"
			$table .= "<h3>".$wikilanguage->get("LANG_PATTERNNEW")."</h3>"
			. "<form method=\"get\" action=\"index.php\" name=\"newpattern\"><input type=\"hidden\" name=\"action\" value=\"editpattern\" />"
			. "<div class=\"myWiki\"><div class=\"myWiki-header\">".$wikilanguage->get("LANG_PATTERN")."</div>"
			// column "pattern name"
			. "<div class=".returnTdClass(0)."><input type=\"text\" name=\"pattern\" /></div>"
			// summary row
			. "<div class=\"summary\"><input type=\"image\" name=\"submit\" value=\"true\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_PATTERNCREATE")."\" title=\"".$wikilanguage->get("LANG_PATTERNCREATE")."\" /></div>"
			. "</div></form>"
			// table "existent patterns"
			. "<h3>".$wikilanguage->get("LANG_PATTERNSEXISTENT")."</h3>";
			$patternstable .= "<table><tr><th>".$wikilanguage->get("LANG_PATTERN")."</th><th>".$wikilanguage->get("LANG_LASTCHANGED")."</th><th>&nbsp;</th><th>&nbsp;</th></tr>";
			$patternsdir = opendir(getcwd() . "/$DIR_PAGEPATTERNS");
			$i = 0;
			while ($file = readdir($patternsdir)) {
				if (($file <> ".") && ($file <> "..")) {
					$file = substr($file, 0, strlen($file)-strlen($DEFAULT_PAGESEXT));
					// pattern name
					$patternstable .= "<tr><td class=".returnTdClass($i).">$file</td>"
					// last change
					. "<td class=".returnTdClass($i).">".strftime($mainsettings->getTimeFormat(), filemtime($DIR_PAGEPATTERNS.$file.$DEFAULT_PAGESEXT))."</td>"
					// edit
					. "<td class=".returnTdClass($i)."><a href=\"index.php?action=editpattern&amp;pattern=$file\" title=\"".$wikilanguage->get("LANG_EDITPATTERN")." &quot;$file&quot;\"><img src=\"pic/editpatternicon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_EDITPATTERN")." &quot;$file&quot;\" /></a></td>"
					// delete
					. "<td class=".returnTdClass($i)."><a href=\"index.php?action=editpattern&amp;deletepattern=true&amp;pattern=$file\" title=\"".$wikilanguage->get("LANG_DELETE")." &quot;$file&quot;\"><img src=\"pic/deletepatternicon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_DELETE")." &quot;$file&quot;\" /></a></td></tr>";
					$i++;
				}
			}
			// summary and table end
			$patternstable .= "<tr><td class=\"summary\" colspan=\"4\">".$wikilanguage->get("LANG_PATTERNS").": $i</td></tr></table>";
			if ($i == 0)
				$patternstable = $wikilanguage->get("LANG_NOPATTERNFOUND");
			$table .= $patternstable;
		}
		// show pattern edit form
		else {
			$pattern = $wikistringcleaner->cleanThatString($pattern, false);
			if (file_exists($DIR_PAGEPATTERNS.$pattern.$DEFAULT_PAGESEXT) && (filesize($DIR_PAGEPATTERNS.$pattern.$DEFAULT_PAGESEXT) > 0)) {
				if (!$file = @fopen($DIR_PAGEPATTERNS.$pattern.$DEFAULT_PAGESEXT, "r"))
					die("Could not write ".$DIR_PAGEPATTERNS.$pattern.$DEFAULT_PAGESEXT."!");
				$patterncontent = fread($file, filesize($DIR_PAGEPATTERNS.$pattern.$DEFAULT_PAGESEXT));
				fclose($file);
			}
			else
				$patterncontent = "";
			$table = html_entity_decode($wikilanguage->get("INFO_PATTERNS"))
			. "<h3>$pattern</h3>"
			. "<form method=\"get\" action=\"index.php\" name=\"form\">"
			. "<textarea name=\"content\">$patterncontent</textarea>"
			. "<input type=\"hidden\" name=\"action\" value=\"editpattern\" />"
			. "<input type=\"hidden\" name=\"pattern\" value=\"$pattern\" />"
			. "<input type=\"hidden\" name=\"savepattern\" value=\"true\" />"
			. "<input type=\"submit\" value=\"".$wikilanguage->get("BUTTON_SAVE")."\" accesskey=\"s\" />"
			. "</form>"
			. "&nbsp;"
			. "<form method=\"get\" action=\"index.php\">"
			. "<input type=\"hidden\" name=\"action\" value=\"editpattern\" />"
			. "<input type=\"submit\" value=\"".$wikilanguage->get("BUTTON_CANCEL")."\" accesskey=\"a\" />"
			. "</form>";
		}

		// return the whole thing
		return $msg.$table;
	}
	
// show information about the given file
	function showFileInfo() {
		// this function doesn't work for anonymous users
		global $GRP_ANONYMOUS;
		if ($GRP_ANONYMOUS) {
			header("location:index.php");
		}
		global $wikilanguage;
		global $mainsettings;
		global $wikistats;
		global $wikiusers;
		global $CURRENTUSER;
		global $DIR_FILES;
		global $DIR_PAGES;
		global $DIR_TRASHFILES;
		global $GRP_EDITUSERS;
		
		if ($_GET['trashfile'] == "true") {
			$trashfile = " (".$wikilanguage->get("LANG_TRASHFILES").")";
			$dirtocheck = $DIR_TRASHFILES;
			$downloadparam = "&amp;trashfile=true";
		}
		else {
			$trashfile = "";
			$dirtocheck = $DIR_FILES;
			$downloadparam = "";
		}
		
		$file = strip_tags(htmlentities($_GET['file']));
		if (!file_exists($dirtocheck.$file) || ($file == "")) {
			$content = getWarningReport($wikilanguage->get("LANG_NOSUCHFILE") . " " . $file);
		}
		else {
			$content = "";
			// save changed comment (admins only)
			if ($GRP_EDITUSERS && isset($_GET['comment'])) {
				$wikistats->setFileComment($file, $_GET['comment']);
				$wikistats->setFileCommentChanger($file, $CURRENTUSER);
				$content .= getOkReport($wikilanguage->get("REPORT_COMMENTCHANGED"));
			}
			// collect data
			// comment
			$comment = htmlentities($wikistats->getFileComment($file));
			// last changer
			$lastchanger = $wikistats->getLastFileChanger($file);
			if ($wikiusers->userExists($lastchanger))
				$lastchanger = getLink("page", $DIR_PAGES, $wikiusers->getFullName($lastchanger), false, false).onlineStatusIcon($lastchanger);
			elseif ($lastchanger == "")
				$lastchanger = $wikilanguage->get("LANG_NOUSERFOUND");
			// last comment changer
			$commentchanger = $wikistats->getFileCommentChanger($file);
			if ($wikiusers->userExists($commentchanger))
				$commentchanger = getLink("page", $DIR_PAGES, $wikiusers->getFullName($commentchanger), false, false).onlineStatusIcon($commentchanger);
			elseif ($commentchanger == "")
				$commentchanger = $wikilanguage->get("LANG_NOUSERFOUND");
			// change date
			$changedate = strftime($mainsettings->getTimeFormat(), filemtime($dirtocheck.$file));
			// linking entries
			$linkingentriesarray = $wikistats->getFileLinkers($file);
			$linkingentries = "";
			if (count($linkingentriesarray) > 0) {
				foreach ($linkingentriesarray as $linkingentry)
					if ($linkingentry != "")
						$linkingentries .= getLink("page", $DIR_PAGES, $linkingentry, false, false)."<br />";
				$linkingentries = substr($linkingentries, 0, strlen($linkingentries)-6);
			}
			else
				$linkingentries = "";
			// file size
			$filesize = convertFileSizeUnit(filesize($dirtocheck.$file));
			// download count
			$downloadcount = $wikistats->getFileDownloadCount($file);
			
			// build info table
			$content .= "<h3>".$wikilanguage->get("LANG_FILEINFO")."</h3><table>"
				."<tr><th colspan=\"2\"><a href=\"download.php?file=$file$downloadparam\" title=\"".$wikilanguage->get("LANG_SHOWFILE")." &quot;$file&quot;\">$file</a>$trashfile</th>"
				."<tr><td class=".returnTdClass(0).">".$wikilanguage->get("LANG_UPLOADDATE")."</td><td class=".returnTdClass(0).">$changedate</td></tr>"
				."<tr><td class=".returnTdClass(1).">".$wikilanguage->get("LANG_UPLOADEDBY")."</td><td class=".returnTdClass(1).">$lastchanger</td></tr>";
			// admins may edit the comment...
			if ($GRP_EDITUSERS)
				$content .= "<tr><td class=".returnTdClass(0).">".$wikilanguage->get("LANG_COMMENT")."</td><td class=".returnTdClass(0)."><form method=\"get\" action=\"index.php\"><input type=\"hidden\" name=\"action\" value=\"fileinfo\" /><input type=\"hidden\" name=\"file\" value=\"$file\" /><input type=\"hidden\" name=\"trashfile\" value=\"".$_GET['trashfile']."\" /><input type=\"text\" name=\"comment\" value=\"$comment\" />&nbsp;<input type=\"image\" name=\"submit\" value=\"true\" src=\"pic/okicon.gif\" alt=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" title=\"".$wikilanguage->get("LANG_SAVECHANGES")."\" /></form></td></tr>";
			// ...users don't.
			else
				$content .= "<tr><td class=".returnTdClass(0).">".$wikilanguage->get("LANG_COMMENT")."</td><td class=".returnTdClass(0).">$comment</td></tr>";
			$content .= "<tr><td class=".returnTdClass(1).">".$wikilanguage->get("LANG_COMMENTCHANGEDBY")."</td><td class=".returnTdClass(1).">$commentchanger</td></tr>"
				."<tr><td class=".returnTdClass(0).">".$wikilanguage->get("LANG_LINKINGENTRIES")."</td><td class=".returnTdClass(0).">$linkingentries</td></tr>"
				."<tr><td class=".returnTdClass(1).">".$wikilanguage->get("LANG_FILESIZE")."</td><td class=".returnTdClass(1).">$filesize</td></tr>"
				."<tr><td class=".returnTdClass(0).">".$wikilanguage->get("LANG_DOWNLOADCOUNT")."</td><td class=".returnTdClass(0).">$downloadcount</td></tr>"
				."</table>";
		}
		
		return $content;
	}
	
// show wiki statistics
	function showWikiStatistics() {
		// this function doesn't work for anonymous users
		global $GRP_ANONYMOUS;
		if ($GRP_ANONYMOUS) {
			header("location:index.php");
		}
		global $mainsettings;
		global $wikibacklinks;
		global $wikilanguage;
		global $wikipageversions;
		global $wikistats;
		global $wikiusers;
		global $DEFAULT_PAGESEXT;
		global $DIR_FILES;
		global $DIR_PAGES;
	// collect pages data
		$allpages = returnQuery($DIR_PAGES, "", false, false, false, 0);
		// pages count
		$pagescount = count($allpages);
		// last changed page
		$latestpage = returnQuery($DIR_PAGES, "", false, true, true, 1);
		if ($latestpage == null)
			$pageslastchanged .= $wikilanguage->get("LANG_DIRISEMPTY");
		else {
			foreach ($latestpage as $filename => $timestamp) {
				$filename = substr($filename, 0, strlen($filename) - strlen($DEFAULT_PAGESEXT));
				$filetime = strftime($mainsettings->getTimeFormat(), $timestamp); 
			}
			$pageslastchanged = getLink("page", $DIR_PAGES, $filename, false, false)." (".$filetime.")";
		}
		// most active page
		$mostactivepages = $wikipageversions->getMostChanges();
		if ($mostactivepages == null)
			$pagesmostactive = $wikilanguage->get("LANG_DIRISEMPTY");
		else {
			foreach ($wikipageversions->getMostChanges() as $page => $changes)
				$pagesmostactive .= getLink("page", $DIR_PAGES, $page, false, false)." ($changes)<br />";
			$pagesmostactive = substr($pagesmostactive, 0, strlen($pagesmostactive)-6);
		}
		// most backlinks
		$pagesmostbacklinks = "";
		$mostbacklinksarray = $wikibacklinks->getMostBacklinksArray($allpages);
		if ($mostbacklinksarray == null)
			$pagesmostbacklinks .= $wikilanguage->get("LANG_DIRISEMPTY");
		else {
			foreach($mostbacklinksarray as $page => $backlinkcount)
				$pagesmostbacklinks .= getLink("page", $DIR_PAGES, $page, false, false)." ($backlinkcount)<br />";
		}
		// shortest and longest page
		$shortestandlongestpages = $wikistats->getShortestAndLongestFile($DIR_PAGES);
		if($shortestandlongestpages == null) {
			$pagesshortest = $wikilanguage->get("LANG_DIRISEMPTY");
			$pageslongest = $wikilanguage->get("LANG_DIRISEMPTY");
		}
		else {
			$pagesshortest = getLink("page", $DIR_PAGES, substr($shortestandlongestpages[0], 0, strlen($shortestandlongestpages[0])-strlen($DEFAULT_PAGESEXT)), false, false). " (".filesize($DIR_PAGES.$shortestandlongestpages[0])." ".$wikilanguage->get("LANG_CHARACTERS").")";
			$pageslongest = getLink("page", $DIR_PAGES, substr($shortestandlongestpages[1], 0, strlen($shortestandlongestpages[1])-strlen($DEFAULT_PAGESEXT)), false, false). " (".filesize($DIR_PAGES.$shortestandlongestpages[1])." ".$wikilanguage->get("LANG_CHARACTERS").")";
		}
	// collect files data
		$allfiles = returnQuery($DIR_FILES, "", false, false, false, 0);
		// files count
		$filescount = count($allfiles);
		// last changed file
		$latestfile = returnQuery($DIR_FILES, "", false, true, true, 1);
		if ($latestfile == null)
			$fileslastchanged = $wikilanguage->get("LANG_DIRISEMPTY");
		else {
			foreach ($latestfile as $filename => $timestamp)
				$filetime = strftime($mainsettings->getTimeFormat(), $timestamp); 
			$fileslastchanged = getLink("file", $DIR_FILES, $filename, false, true)." (".$filetime.")";
		}
		// most downloaded file
		if ($allfiles == null)
			$filesmostactive = $wikilanguage->get("LANG_DIRISEMPTY");
		else {
			$filesmostactive = "";
			$filedownloads = array();
			$mostfiledownloads = array();
			foreach ($allfiles as $file => $timestamp) {
				$filedownloads[$file] = $wikistats->getFileDownloadCount($file);
			}
			arsort($filedownloads);
			$highestcount = 0;
			// go through array and add most backlinked pages (can be more than one at the same time)
			foreach ($filedownloads as $file => $count) {
				if ($count >= $highestcount) {
					$mostfiledownloads[$file] = $count;
					$highestcount = $count;
				}
			}
			// sort and return
			uksort($mostfiledownloads, 'strnatcasecmp');
			foreach($mostfiledownloads as $file => $downloads)
				$filesmostactive .= getLink("file", $DIR_FILES, $file, false, true)." ($downloads)<br />";
			$filesmostactive = substr($filesmostactive, 0, strlen($filesmostactive)-6);
		}
		// most linked file
		if ($allfiles == null)
			$filesmostlinkers = $wikilanguage->get("LANG_DIRISEMPTY");
		else {
			$mostlinkersarray = $wikistats->getMostLinkersArray($allfiles);
			foreach($mostlinkersarray as $file => $linkerscount)
				$filesmostlinkers .= getLink("file", $DIR_FILES, $file, false, true)." ($linkerscount)<br />";
			$filesmostlinkers = substr($filesmostlinkers, 0, strlen($filesmostlinkers)-6);
		}
		// shortest and longest file
		$shortestandlongestfiles = $wikistats->getShortestAndLongestFile($DIR_FILES);
		if($shortestandlongestfiles == null) {
			$filesshortest = $wikilanguage->get("LANG_DIRISEMPTY");
			$fileslongest = $wikilanguage->get("LANG_DIRISEMPTY");
		}
		else {
			$filesshortest = getLink("file", $DIR_FILES, $shortestandlongestfiles[0], false, true). " (".convertFileSizeUnit(filesize($DIR_FILES.$shortestandlongestfiles[0])).")";
			$fileslongest = getLink("file", $DIR_FILES, $shortestandlongestfiles[1], false, true). " (".convertFileSizeUnit(filesize($DIR_FILES.$shortestandlongestfiles[1])).")";
		}

	// collect user data
		// users count
		$userscount = $wikiusers->getUserCount();
		// latest registered user
		$latestuser = $wikiusers->getLatestUser();
		$userslatest = getLink("page", $DIR_PAGES, $wikiusers->getFullName($latestuser), false, false).onlineStatusIcon($latestuser)." (".strftime($mainsettings->getTimeFormat(), $wikiusers->getFirstAction($latestuser)).")";
		// most logged in users
		$mostloggedinusers = $wikiusers->getMostLoginsUser();
		foreach($mostloggedinusers as $user => $logins)
			$usersmostactive .= getLink("page", $DIR_PAGES, $wikiusers->getFullName($user), false, false).onlineStatusIcon($user)." ($logins)<br />";
	// build content
		$content .= "<h3>".$wikilanguage->get("LANG_STATISTICS")."</h3><div class=\"myWiki\">"
			."<div class=\"myWiki-header\">".$wikilanguage->get("LANG_PAGES")."</div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("STAT_PAGESCOUNT")."</div><div>$pagescount</div></div>"
			."<div class=".returnTdClass(1)."><div>".$wikilanguage->get("STAT_PAGESLASTCHANGE")."</div><div>$pageslastchanged</div></div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("STAT_PAGESMOSTACTIVITY")."</div><div>$pagesmostactive</div></div>"
			."<div class=".returnTdClass(1)."><div>".$wikilanguage->get("STAT_PAGESMOSTBACKLINKS")."</div><div>$pagesmostbacklinks</div></div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("STAT_PAGESSHORTEST")."</div><div>$pagesshortest</div></div>"
			."<div class=".returnTdClass(1)."><div>".$wikilanguage->get("STAT_PAGESLONGEST")."</div><div>$pageslongest</div></div>"
			."<div class=\"myWiki-header\">".$wikilanguage->get("LANG_FILES")."</div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("STAT_FILESCOUNT")."</div><div>$filescount</div></div>"
			."<div class=".returnTdClass(1)."><div>".$wikilanguage->get("STAT_FILESLASTCHANGE")."</div><div>$fileslastchanged</div></div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("STAT_FILESMOSTDOWNLOADS")."</div><div>$filesmostactive</div></div>"
			."<div class=".returnTdClass(1)."><div>".$wikilanguage->get("STAT_FILESMOSTLINKERS")."</div><div>$filesmostlinkers</div></div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("STAT_FILESSHORTEST")."</div><div>$filesshortest</div></div>"
			."<div class=".returnTdClass(1)."><div>".$wikilanguage->get("STAT_FILESLONGEST")."</div><div>$fileslongest</div></div>"
			."<div class=\"myWiki-header\">".$wikilanguage->get("LANG_USERS")."</div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("STAT_USERSCOUNT")."</div><div>$userscount</div></div>"
			."<div class=".returnTdClass(1)."><div>".$wikilanguage->get("STAT_USERSLATEST")."</div><div>$userslatest</div></div>"
			."<div class=".returnTdClass(0)."><div>".$wikilanguage->get("STAT_USERSMOSTACTIVITY")."</div><div>$usersmostactive</div></div>"
			."</div>";
		return $content;
	}

// save a backup of the given page
	function savePageBackup($page, $version) {
		global $CURRENTUSER;
		global $DEFAULT_PAGESEXT;
		global $DIR_BACKUP;
		global $wikistats;
		global $wikistringcleaner;
		$page = $wikistringcleaner->cleanThatString($page, false); 
		// write backup of old version to file
		$time = time();
		if (!$file = @fopen($DIR_BACKUP.$page."_".$time.$DEFAULT_PAGESEXT, "w"))
	  	die("Could not write backup of page!");
	  // check if content comes from POST or out of a file
	  if ($version <> "") {
	  	// from file
			if (!$versionfile = @fopen($DIR_BACKUP.$page."_".$version.$DEFAULT_PAGESEXT, "r"))
				die("Could not read ".$DIR_BACKUP.$page."_".$version.$DEFAULT_PAGESEXT."!");
			$pagecontent = fread($versionfile, filesize($DIR_BACKUP.$page."_".$version.$DEFAULT_PAGESEXT));
			fclose($versionfile);
	  }
	  else {
	  	// from POST
	  	$pagecontent = $_POST['content'];
	  }
	  	
		if (get_magic_quotes_gpc())
				fputs($file, trim(stripslashes($pagecontent)));
		else
			fputs($file, trim($pagecontent));
	  fclose($file);
	  $wikistats->setLastPageChanger($page, $CURRENTUSER);
	}
	
// return a selectable list of page patterns
	function getPagePatternListAsSelect() {
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGEPATTERNS;
		global $mainsettings;
		global $wikilanguage;

		$list = "<br /><select name=\"pattern\" class=\"menu\">";
		$i = 0;
		$patternsdir = opendir(getcwd() . "/$DIR_PAGEPATTERNS");
		while ($file = readdir($patternsdir)) {
			if (($file <> ".") && ($file <> "..")) {
				$selected = "";
				$file = substr($file, 0, strlen($file)-strlen($DEFAULT_PAGESEXT));
				if ($file == $mainsettings->getDefaultPagePattern())
					$selected = " selected";
				$list .= "<option$selected value=\"$file\">$file</option>";
				$i++;
			}
		}
		$list .= "</select>";
		if ($i == 0)
			return "";
		return $list;
	}
	
// return JavaScript toolbar
	function getFormatToolbar() {
		global $CURRENTUSER;
		global $mainsettings;
		global $wikilanguage;
		global $wikismileys;
		global $wikiusers;
		$toolbar = "<p class=\"toolbar\">"
		// show user information if javascript inactive
		. "<noscript><p class=\"reportwarning\">".$wikilanguage->get("LANG_NOSCRIPT_TOOLBAR")."</p></noscript>"
		// syntax elements
		. "<img class=\"js\" title=\"[link| ... ]\" alt=\"Link\" src=\"pic/jsToolbar/link.png\" onClick=\"insert('[link|', ']')\" /> "
		. "<img class=\"js\" alt=\"E-Mail\" title=\"[mail| ... ]\" src=\"pic/jsToolbar/mail.png\" onClick=\"insert('[mail|', ']')\" /> "
		. "<img class=\"js\" alt=\"Eintrag\"	title=\"[eintrag| ... ]\" src=\"pic/jsToolbar/eintrag.png\" onClick=\"insert('[eintrag|', ']')\" /> "
		. "<img class=\"js\" alt=\"Datei\" title=\"[datei| ... ]\" src=\"pic/jsToolbar/datei.png\" onClick=\"insert('[datei|', ']')\" /> "
		. "<img class=\"js\" alt=\"Bild\" title=\"[bild| ... ]\" src=\"pic/jsToolbar/bild.png\" onClick=\"insert('[bild|', ']')\" /> "
		. "<img class=\"js\" alt=\"Bildlinks\"	title=\"[bildlinks| ... ]\" src=\"pic/jsToolbar/bildlinks.png\" onClick=\"insert('[bildlinks|', ']')\" /> "
		. "<img class=\"js\" alt=\"Bildrechts\" title=\"[bildrechts| ... ]\" src=\"pic/jsToolbar/bildrechts.png\" onClick=\"insert('[bildrechts|', ']')\" /> "
		. "<img class=\"js\" alt=\"Überschrift1\" title=\"[ueber1| ... ]\" src=\"pic/jsToolbar/ueber1.png\" onClick=\"insert('[ueber1|', ']')\" /> "
		. "<img class=\"js\" alt=\"Überschrift2\" title=\"[ueber2| ... ]\" src=\"pic/jsToolbar/ueber2.png\" onClick=\"insert('[ueber2|', ']')\" /> "
		. "<img class=\"js\" alt=\"Überschrift3\" title=\"[ueber3| ... ]\" src=\"pic/jsToolbar/ueber3.png\" onClick=\"insert('[ueber3|', ']')\" /> "
		. "<img class=\"js\" alt=\"Absatz\"	title=\"[absatz| ... ]\" src=\"pic/jsToolbar/absatz.png\" onClick=\"insert('[absatz|', ']')\" /> "
		. "<img class=\"js\" alt=\"Liste1\" title=\"[liste1| ... ]\" src=\"pic/jsToolbar/liste1.png\" onClick=\"insert('[liste1|', ']')\" /> "
		. "<img class=\"js\" alt=\"Liste2\" title=\"[liste2| ... ]\" src=\"pic/jsToolbar/liste2.png\" onClick=\"insert('[liste2|', ']')\" /> "
		. "<img class=\"js\" alt=\"Liste3\" title=\"[liste3| ... ]\" src=\"pic/jsToolbar/liste3.png\" onClick=\"insert('[liste3|', ']')\" /> ";
		if ($mainsettings->getUseHtmlTag() == "true")
			$toolbar .= "<img class=\"js\" alt=\"HTML\" title=\"[html| ... ]\" src=\"pic/jsToolbar/html.png\" onClick=\"insert('[html|', ']')\" />";
		$toolbar .= "<img class=\"js\" alt=\"Code\"	title=\"[code| ... ]\" src=\"pic/jsToolbar/code.png\" onClick=\"insert('[code|', ']')\" /> "
		."<img class=\"js\" alt=\"Horizontale Linie\" title=\"[----]\" src=\"pic/jsToolbar/linie.png\" onClick=\"insert('[----]', '')\" />"
		. "<br />"
		// text formatting
		. "<img class=\"js\" alt=\"Linksbündig\" title=\"[links| ... ]\" src=\"pic/jsToolbar/links.png\" onClick=\"insert('[links|', ']')\" /> "
		. "<img class=\"js\" alt=\"Zentriert\" title=\"[zentriert| ... ]\" src=\"pic/jsToolbar/zentriert.png\" onClick=\"insert('[zentriert|', ']')\" /> "
		. "<img class=\"js\" alt=\"Blocksatz\" title=\"[block| ... ]\" src=\"pic/jsToolbar/block.png\" onClick=\"insert('[block|', ']')\" /> "
		. "<img class=\"js\" alt=\"Rechtsbündig\" title=\"[rechts| ... ]\" src=\"pic/jsToolbar/rechts.png\" onClick=\"insert('[rechts|', ']')\" /> "
		. "<img class=\"js\" alt=\"Fett\" title=\"[fett| ... ]\" src=\"pic/jsToolbar/fett.png\" onClick=\"insert('[fett|', ']')\" /> "
		. "<img class=\"js\" alt=\"Kursiv\" title=\"[kursiv| ... ]\" src=\"pic/jsToolbar/kursiv.png\" onClick=\"insert('[kursiv|', ']')\" /> "
		. "<img class=\"js\" alt=\"Fettkursiv\" title=\"[fettkursiv| ... ]\" src=\"pic/jsToolbar/fettkursiv.png\" onClick=\"insert('[fettkursiv|', ']')\" /> "
		. "<img class=\"js\" alt=\"Unterstrichen\" title=\"[unter| ... ]\" src=\"pic/jsToolbar/unter.png\" onClick=\"insert('[unter|', ']')\" /> "
		. "<img class=\"js\" alt=\"Farbig\" title=\"[farbe=RRGGBB| ... ]\" src=\"pic/jsToolbar/farbe.png\" onClick=\"insert('[farbe=AA0000|', ']')\" /> "
		. "</p>";
		// build smiley menu
		if ($wikiusers->getShowSmileys($CURRENTUSER)) {
			$toolbar .= "<a href=\"#\" onclick=\"toggle('smileybar', this, '".$wikilanguage->get("LANG_HIDESMILEYBAR")."', '".$wikilanguage->get("LANG_SHOWSMILEYBAR")."');\">".$wikilanguage->get("LANG_SHOWSMILEYBAR")."</a><br />"
			. "<p class=\"toolbar\" id=\"smileybar\" style=\"display:none;\">";
			foreach($wikismileys->getSmileysArray() as $icon => $emoticon)
				$toolbar .= "<img class=\"jss\" title=\":$icon:\" alt=\"$emoticon\" src=\"pic/smileys/$icon.gif\" onClick=\"insert(' :$icon: ', '')\" />";
			$toolbar .= "</p>";
		}
		return $toolbar;
	}

// return backlinks of current page as array
	function getBacklinks($page) {
		global $wikibacklinks;
		return $wikibacklinks->getBacklinkList($page);
	}
	
// return backlinks of current page as content
	function showBacklinks($page) {
		global $ACTION;
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGES;
		global $GRP_EDITCONTENT;
		global $PAGE_TITLE;
		global $mainsettings;
		global $wikilanguage;
		global $wikistringcleaner;
		
		$backlinkstext = "<div class=\"submenu\"><div class=\"submenudescription\">".str_replace('_', ' ', $wikistringcleaner->shortenName($PAGE_TITLE))." - ".$wikilanguage->get("LANG_BACKLINKS")."</div>"
		. "<div class=\"submenucontentscroll\">";
		$backlinksarray = getBacklinks($page);
		if (count($backlinksarray) == 0)
			$backlinkstext .= "<div class=\"menuitem\">".$wikilanguage->get("LANG_NOBACKLINKS")."</div>";
		else {
			foreach ($backlinksarray as $backlink) {
				$timestamp = filemtime($DIR_PAGES.$backlink.$DEFAULT_PAGESEXT);
				$editedfilename = $wikistringcleaner->shortenName($backlink);
				if ($ACTION == "edit")
					$backlinkstext .= "<div class=\"menuitem\"><em class=\"inactivemenupoint\">".str_replace('_', ' ', $editedfilename)."</em></div>";
				else
					$backlinkstext .= "<div class=\"menuitem\">".getLastChangeMarker($timestamp)."<a href=\"$backlink\" title=\"".strftime($mainsettings->getTimeFormat(), $timestamp)." - ".str_replace('_', ' ', $backlink)."\">".str_replace('_', ' ', $editedfilename)."</a></div>";
			}
		}
		$backlinkstext .= "</div></div>";
		return $backlinkstext;
	}
	
// return a menu listung all users currently logged in
	function getUsersOnlineList() {
		global $ACTION;
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGES;
		global $GRP_ANONYMOUS;
		global $GRP_EDITCONTENT;
		global $PAGE_TITLE;
		global $mainsettings;
		global $wikilanguage;
		global $wikiusers;
		// this menu item isn't shown to anonymous visitors or if "show users online list" is globally disabled
		if ($GRP_ANONYMOUS || ($mainsettings->getShowUsersOnlineList() <> "true")) {
			return "";
		}
		$usersarray = $wikiusers->getAllUsers("");
		$userslist = "<div class=\"submenu\"><div class=\"submenudescription\">".$wikilanguage->get("LANG_USERSONLINE")."</div>"
		. "<div class=\"submenucontentscroll\">";
		foreach ($usersarray as $user => $userdata) {
			if ($wikiusers->isOnline($user)) {
				if ($ACTION == "edit")
					$userslist .= "<div class=\"menuitem\"><em class=\"inactivemenupoint\">".$userdata[1]."</em></div>";
				elseif (!file_exists($DIR_PAGES.$userdata[1].$DEFAULT_PAGESEXT)) {
					if ($GRP_EDITCONTENT)
						$userslist .= "<div class=\"menuitem\"><a class=\"pending\" href=\"".$userdata[1]."\">".$userdata[1]."</a></div>";
					else
						$userslist .= "<div class=\"menuitem\"><a class=\"pending\">".$userdata[1]."</a></div>";
				}
				elseif ($PAGE_TITLE == $userdata[1])
					$userslist .= "<div class=\"menuitem\"><a href=\"".$userdata[1]."\" class=\"activemenupoint\">".$userdata[1]."</a></div>";
				else
					$userslist .= "<div class=\"menuitem\"><a href=\"".$userdata[1]."\">".$userdata[1]."</a></div>";
			}
		}
		$userslist .= "</div></div>";
		return $userslist;
	}
	
// return user's favourite pages
	function getUserFavourites() {
		global $ACTION;
		global $CURRENTUSER;
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGES;
		global $GRP_ANONYMOUS;
		global $PAGE_TITLE;
		global $mainsettings;
		global $wikilanguage;
		global $wikiusers;
		global $wikistringcleaner;

		// this menu item isn't shown to anonymous visitors
		if ($GRP_ANONYMOUS) {
			return "";
		}
		$favourites = "<div class=\"submenu\"><div class=\"submenudescription\">".$wikilanguage->get("LANG_FAVOURITES")."</div>"
		. "<div class=\"submenucontentscroll\">";
		$favsarray = $wikiusers->getFavouritesArray($CURRENTUSER);
		// read user's favourites, delete non-existent pages
		foreach ($favsarray as $current) {
			if (!file_exists($DIR_PAGES.$current.$DEFAULT_PAGESEXT))
				$wikiusers->deleteFavourite($CURRENTUSER, $current);
		}
		// re-read user's favourites
		$favsarray = $wikiusers->getFavouritesArray($CURRENTUSER);
		// show phrase if no favourite saved
		if (count($favsarray) == 0)
			$favourites .= "<div class=\"menuitem\">".$wikilanguage->get("LANG_NOFAVOURITESFOUND")."</div>";
		// else: show all favourites
		else {
			foreach ($favsarray as $current) {
				$timestamp = filemtime($DIR_PAGES.$current.$DEFAULT_PAGESEXT);
				$editedfilename = $wikistringcleaner->shortenName($current);
				if ($ACTION == "edit")
					$favourites .= "<div class=\"menuitem\"><em class=\"inactivemenupoint\">$editedfilename</em></div>";
				elseif ($PAGE_TITLE == $current)
					$favourites .= "<div class=\"menuitem\">".getLastChangeMarker($timestamp)."<a href=\"$current\" class=\"activemenupoint\" title=\"".strftime($mainsettings->getTimeFormat(), $timestamp)." - $current\">$editedfilename</a></div>";
				else
					$favourites .= "<div class=\"menuitem\">".getLastChangeMarker($timestamp)."<a href=\"$current\" title=\"".strftime($mainsettings->getTimeFormat(), $timestamp)." - $current\">$editedfilename</a></div>";
			}
		}
		$favourites .= "</div></div>";
		return $favourites;
	}
	
// add page to favourites
	function addFav($add) {
		global $ACTION;
		global $CURRENTUSER;
		global $PAGE_TITLE;
		global $wikiusers;
		if ($add)
			$wikiusers->addFavourite($CURRENTUSER, $PAGE_TITLE);
		else
			$wikiusers->deleteFavourite($CURRENTUSER, $PAGE_TITLE);
		if ($ACTION <> "")
			$page = "";
		else
			$page = $PAGE_TITLE;
		header("location:index.php?action=$ACTION&page=$page");
	}

// return "add" or "delete" favourite link
	function getFavLink($page) {
		global $ACTION;
		global $CURRENTUSER;
		global $DEFAULT_PAGESEXT;
		global $DIR_PAGES;
		global $GRP_ANONYMOUS;
		global $PAGE_TITLE;
		global $wikilanguage;
		global $wikiusers;
		
		if ($GRP_ANONYMOUS || ($ACTION == "edit") || (!file_exists($DIR_PAGES.$page.$DEFAULT_PAGESEXT)))
			return getNoActionString($wikilanguage->get("LANG_NOACTION"));

		$isfav = false;
		foreach ($wikiusers->getFavouritesArray($CURRENTUSER) as $singlefav) {
			if ($page == $singlefav) {
				$isfav = true;
				continue;
			}
		}
		if ($isfav)
			return "<a href=\"index.php?action=$ACTION&amp;page=$page&amp;delfav=true\" title=\"".$wikilanguage->get("LANG_DELFAV").": &quot;$page&quot;\" accesskey=\"f\"><img src=\"pic/delfavicon.gif\" class=\"noborder\" alt=\"".$wikilanguage->get("LANG_DELFAV")."\" /></a>";
		else
			return "<a href=\"index.php?action=$ACTION&amp;page=$page&amp;addfav=true\" title=\"".$wikilanguage->get("LANG_ADDFAV").": &quot;$page&quot;\" accesskey=\"f\"><img src=\"pic/addfavicon.gif\" alt=\"".$wikilanguage->get("LANG_ADDFAV")."\" /></a>";
	}
	
// return login form
	function getLoginForm() {
		global $ACTION;
		global $wikilanguage;
		$loginform = "<div class=\"submenu\">"
		. "<div class=\"submenudescription\">".$wikilanguage->get("LANG_LOGIN")."</div>"
		. "<div class=\"submenucontent\">";
		// login failed
		if ($_GET['login'] && !$_SESSION['login_okay'])
			$loginform .= "<div class=\"menuitem\">".getWarningReport($wikilanguage->get("LANG_LOGINFAILED"))."</div>";
		$loginform .= "<form name=\"loginform\" action=\"login.php\" method=\"post\">"
		. "<input type=\"hidden\" name=\"page\" value=\"".strip_tags(htmlentities($_GET['page']))."\" />"
		. "<input type=\"hidden\" name=\"action\" value=\"".$ACTION."\" />"
		. "<div class=\"menuitem\"><input type=\"text\" name=\"username\" value=\"".$wikilanguage->get("LANG_USERNAME")."\" class=\"menutext\" accesskey=\"l\" onFocus=\"document.loginform.username.focus();document.loginform.username.select();\" /></div>"
		. "<div class=\"menuitem\"><input type=\"password\" name=\"password\" value=\"".$wikilanguage->get("LANG_PASSWORD")."\" class=\"menutext\" onFocus=\"document.loginform.password.focus();document.loginform.password.select();\" /></div>"
		. "<div class=\"menuitem\"><input type=\"submit\" name=\"login\" value=\"".$wikilanguage->get("LANG_LOGIN")."\" class=\"menubutton\" /></div>"
		. "</form>"
		. "</div>"
		. "</div>";
		return $loginform;
	}
	
// check if file extension is allowed for upload
	function allowedToUpload($filename) {
		global $mainsettings;
		$extensions = explode(",", $mainsettings->getUploadExtensions());
		// allowed extensions
		if ($mainsettings->getUploadExtensionsAllowed()) {
			foreach ($extensions as $ext) {
				if (strtolower(substr($filename, strlen($filename)-(strlen($ext)+1), strlen($ext)+1)) == ".".strtolower($ext))
					return true;
			}
			return false;
		}
		// forbidden extensions
		else {
			foreach ($extensions as $ext) {
				if (strtolower(substr($filename, strlen($filename)-(strlen($ext)+1), strlen($ext)+1)) == ".".strtolower($ext))
					return false;
			}
			return true;
		}
	}

// check if uploaded file's size is within upload limit
	function filesizeWithinLimit($filesize) {
		global $mainsettings;
		$limit = $mainsettings->getFilesMaxUploadSize();
		// unlimited upload?
		if ($limit == 0)
			return true;
		// check filesize
		elseif ($filesize > $limit)
			return false;
		else
			return true;
	}

// return "user is on-/offline" icon
	function onlineStatusIcon($id) {
		global $GRP_EDITCONTENT;
		global $wikilanguage;
		global $wikiusers;
		if (!$GRP_EDITCONTENT)
			return "";
		if (($id == "") || (!$wikiusers->userExists($id)))
			return "";
		if ($wikiusers->isOnline($id))
			return "&nbsp;<img src=\"pic/useronlineicon.gif\" alt=\"(Online)\" title=\"".$wikilanguage->get("LANG_USERISONLINE")." &quot;".$wikiusers->getFullName($id)."&quot;\" />";
		else
			return "&nbsp;<img src=\"pic/userofflineicon.gif\" alt=\"(Offline)\" title=\"".$wikilanguage->get("LANG_USERISOFFLINE")." &quot;".$wikiusers->getFullName($id)."&quot;\" />";
	}
	
// convert given file size (in byte) to string with fitting unit (B/KB/MB)
	function convertFileSizeUnit($filesize){
		if ($filesize < 1024)
			return $filesize . " B";
		elseif ($filesize < 1048576)
			return round(($filesize/1024) , 2) . " KB";
		else
			return round(($filesize/1024/1024) , 2) . " MB";
	}
	
// return information about this wiki
	function wikiInfo() {
		global $mainsettings;
		global $wikilanguage;
		return "| <a href=\"http://wiki.mozilo.de/\" target=\"_blank\" class=\"wikiinfolink\" title=\"wiki.mozilo.de\">moziloWiki 1.1 RC-1</a> | &copy; 2009 - <script> document.write(new Date().getFullYear()); </script> by <a href=\"https://www.mozilo.de/\" target=\"_blank\" class=\"wikiinfolink\" title=\"mozilo.de\">mozilo</a> | ".$wikilanguage->get("LANG_SERVERTIME")." ".strftime($mainsettings->getTimeFormat(), time())." |";
	}
?>