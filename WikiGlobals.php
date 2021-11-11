<?php

/*

		moziloWiki - WikiGlobals.php
		
		Language and path constants for moziloWiki. Feel free to
		change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

// ===========================================================
//
// In this file, you can set most of the variables to your values. 
// Consider using valid HTML entities for special characters!
//

// CONFIGURATION
// Wiki styles
$MAINDIR_STYLES		= "styles/";
$DIR_PRINTSTYLE		= "printstyle/";
$TEMPLATE_FILE		= "template.html";
$CSS_FILE					= "style.css";
// Directory paths
$MAINDIR_STORAGE	= "storage/";
$MAINDIR_WIKIDATA	= "wikidata/";
$DIR_BACKLINKS		=	$MAINDIR_WIKIDATA."backlinks/";
$DIR_BACKUP				=	$MAINDIR_WIKIDATA."pageshistory/";
$DIR_LOCKS				=	$MAINDIR_WIKIDATA."locks/";
$DIR_FILES				=	$MAINDIR_STORAGE."files/";
$DIR_PAGES				= $MAINDIR_STORAGE."pages/";
$DIR_TRASHFILES		=	$MAINDIR_STORAGE."trashfiles/";
$DIR_TRASHPAGES		=	$MAINDIR_STORAGE."trashpages/";
$DIR_SMILEYS			= $MAINDIR_WIKIDATA."smileys/";
$DIR_SPECIALCHARS	= $MAINDIR_WIKIDATA."specialchars/";
// language settings
$DIR_LANGSETTINGS	= $MAINDIR_WIKIDATA."language/";
// Page patterns
$DIR_PAGEPATTERNS = $MAINDIR_WIKIDATA."pagepatterns/";
// Backlinks path
$WIKIMGT_BACKLINKS 		= $DIR_BACKLINKS."backlinks.prop";
// Wiki settings path
$WIKIMGT_SETTINGS	= $MAINDIR_WIKIDATA."mainsettings.prop";
// User management paths
$DIR_WIKIDATA_USERS = $MAINDIR_WIKIDATA."users/";
$USRMGT_GROUPS		= $DIR_WIKIDATA_USERS."groups.csv";
$USRMGT_USERS			= $DIR_WIKIDATA_USERS."users.csv";
$USRMGT_GROUPDATA	= $DIR_WIKIDATA_USERS."groupdata.prop";
$USRMGT_USERDATA	= $DIR_WIKIDATA_USERS."userdata.prop";
// Statistics paths
$DIR_WIKIDATA_STATS = $MAINDIR_WIKIDATA."stats/";
$STATS_FILES			= $DIR_WIKIDATA_STATS."files.prop";
$STATS_PAGES			= $DIR_WIKIDATA_STATS."pages.prop";
$STATS_USERS			= $DIR_WIKIDATA_STATS."users.prop";
// Default page names
$DEFAULT_FUNCTIONSPAGE		=	"Wiki_Funktionen";
$DEFAULT_STARTPAGE				=	"Wiki_Startseite";
$DEFAULT_TESTPAGE					=	"Wiki_Testseite";
// Default file extensions
$DEFAULT_PAGESEXT					= ".txt";
$DEFAULT_LOCKSEXT					= ".lck";
// Default recent files count
$DEFAULT_RECENTCOUNT			= 10;
// default wiki style 
$DEFAULT_WIKI_STYLE	= "moziloWiki";
// path to special characters list
$SPECIALCHARS_LIST = $DIR_SPECIALCHARS."specialchars.prop";
// path to smileys list
$SMILEYS_LIST = $DIR_SMILEYS."smileys.prop";
?>
