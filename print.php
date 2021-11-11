<?php
/*

		moziloWiki - print.php
		
		A print version display for moziloWiki. Feel 
		free to suit it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

	// finish script even if user connection broke
	ignore_user_abort(true);

	// Initial: Fehlerausgabe unterdrücken, um Path-Disclosure-Attacken ins Leere laufen zu lassen
	@ini_set("display_errors", 0);

	require_once("WikiGlobals.php");				// global wiki data
	require_once("WikiGroups.php");					// wiki group data
	require_once("WikiLanguage.php");				// wiki language
	require_once("WikiSettings.php");				// wiki main settings
	require_once("WikiStringCleaner.php"); 	// clean string from special characters functions
	require_once("WikiSyntax.php");					// wiki syntax conversion
	require_once("WikiUsers.php");					// wiki user data

	session_start();

// MAIN SETTINGS
	$mainsettings = new WikiSettings();

	// send anonymous users to login, if anonymous access not allowed
	if (($mainsettings->getAnonymousAccess() != "true") && ($_SESSION['login_okay'] != true))
		header("location:login.php?logout=true");

	// prepare language	
	$lang = $mainsettings->getDefaultWikiLanguage();
	$wikilanguage = new WikiLanguage($lang.".prop");
	$wikistringcleaner = new WikiStringCleaner($wikilanguage->get("LANG_SPECIALCHARS"), $wikilanguage->get("LANG_REPLACEMENT"));

	// prepare syntax
	$wikisyntax = new WikiSyntax($DEFAULT_PAGESEXT,$DIR_FILES,$DIR_PAGES,$GRP_ANONYMOUS,$GRP_EDITCONTENT,$wikilanguage,$wikistringcleaner, true);
	
	// page given?
	if (!$PAGE_TITLE = stripslashes(strip_tags(htmlentities($_GET['page'])))) {
		header("location:index.php");
	}
	// page existant?
	if  (!file_exists($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT))
		header("location:index.php");
		
	// Read page contents and time of last change
	if (($file = @fopen($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT, "r")) || $ACTION <> "") {
		$TIME = strftime($mainsettings->getTimeFormat(), @filemtime($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT));
		$CONTENT = "\n" . @fread($file, @filesize($DIR_PAGES.$PAGE_TITLE.$DEFAULT_PAGESEXT)) . "\n";
		@fclose($file);
	}

	// replace wiki syntaxwith HTML
	$CONTENT = $wikisyntax->convertWikiSyntax($PAGE_TITLE, $CONTENT, true);
	// cut first "<br />"
	$CONTENT = substr($CONTENT, 6, strlen($CONTENT) - 6);
	
 	// Read and parse template
	if (!$file = @fopen($DIR_PRINTSTYLE.$TEMPLATE_FILE, "r"))
		die("Could not read template file $TEMPLATE_FILE!");
	$template = fread($file, filesize($DIR_PRINTSTYLE.$TEMPLATE_FILE));
	fclose($file);
	$template = preg_replace("/{STYLE_PATH}/", $DIR_PRINTSTYLE.$CSS_FILE, $template);
	$html = preg_replace("/{CONTENT}/", $CONTENT, $template);
	$html = preg_replace('/{WIKI_TITLE}/', $mainsettings->getWikiName(), $html);
	$html = preg_replace('/{PAGE_TITLE}/', $PAGE_TITLE, $html);
	$html = preg_replace('/{LAST_CHANGE}/', $wikilanguage->get("LANG_LASTCHANGED"), $html);
	$html = preg_replace('/{AT}/', $wikilanguage->get("LANG_AT"), $html);
	$html = preg_replace('/{TIME}/', $TIME, $html);
	$html = preg_replace('/{PRINT_VIEW}/', $wikilanguage->get("LANG_PRINTVIEW"), $html);
	
	echo $html;
?>
