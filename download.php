<?php
/*

		moziloWiki - download.php
		
		A download script for moziloWiki. 
		Feel free to change it to your personal purposes.
		
		Arvid Zimmermann 2006 <moziloWiki@azett.com>
		
*/

	// finish script even if user connection broke
	ignore_user_abort(true);

	// session and user management
	session_start();
	require_once("WikiGlobals.php");
	require_once("WikiStatistics.php");
	
	// Initial: Fehlerausgabe unterdrücken, um Path-Disclosure-Attacken ins Leere laufen zu lassen
	@ini_set("display_errors", 0);

	$wikistats = new WikiStatistics();

	$TRASHFILE 	= preg_replace('/(\/|\\\)/', "", $_REQUEST['trashfile']);
	$IMAGEFILE 	= preg_replace('/(\/|\\\)/', "", $_REQUEST['imagefile']);
	$FILE 			= preg_replace('/(\/|\\\)/', "", $_REQUEST['file']);
	
	// download from trash or file directory?
	if ($TRASHFILE == "true")
		$dir = $DIR_TRASHFILES;
	else
		$dir = $DIR_FILES;
	
	// not logged in correctly? images will be shown in any case.
	if (!$_SESSION['login_okay'] && (!fileHasExtension($FILE, array("jpg","png","gif","jpeg","bmp","tif","tiff","svg","ico"))))
		header("location:index.php");
	
	// does the requested file exist?
	elseif (!file_exists($dir.$FILE))
		header("location:index.php");
	
	// everything okay, send file
	else {
		// icrease download counter (not for image-tags)
		if ($IMAGEFILE != "true")
			$wikistats->increaseFileDownloadCount($FILE);
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"".$FILE."\"");
		readfile($dir.$FILE);
	}
	
	// check uploaded file for given extensions
	function fileHasExtension($filename, $extensions) {
		foreach ($extensions as $ext) {
			if (strtolower(substr($filename, strlen($filename)-(strlen($ext)+1), strlen($ext)+1)) == ".".strtolower($ext))
				return true;
		}
		return false;
	}

?>
