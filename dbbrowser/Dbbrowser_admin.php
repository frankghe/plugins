<?php 

/* 
 * Simple and quick access to Dbbrowser auto-generated back-office
 * Accessible through the edit link in Back-Office > Modules > Dbbrowser
 * 
 */ 

	require_once(realpath(dirname(__FILE__)) . "./Dbbrowser.class.php");

	$dbb = new Dbbrowser();
	// showDb() reads info directly from url parameters ($_REQUEST) 
	// so we dynamically (here) add an 'action' in case none is defined 
	// to manage the case where request is coming from Thelia BO in modules page
	// Which is in fact the entry point for accessing the BO 
	if (! isset($_REQUEST['action'])) $_REQUEST['action'] = 'dbbrowser_showtables';
	echo '<div id="contenu_int">'.$dbb->showDb().'</div>';


?>