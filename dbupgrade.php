<?php
/*
 * Copy this script to the folder above and populate $versions array with your migrations
 * For more info see: http://www.dbupgrade.org/Main_Page#Migrations_($versions_array)
 *
 * Note: this script should be versioned in your code repository so it always reflects current code's
 *       requirements for the database structure.
*/
require_once(dirname(__FILE__).'/dbupgrade/lib.php');

$versions = array();
// Add new migrations on top, right below this line.

/* -------------------------------------------------------------------------------------------------------
 * VERSION 2
 * ... put some description of version 2 here ...
*/
$versions[2] = array(
	'up' => '',
	'down' => '',
);

/* -------------------------------------------------------------------------------------------------------
 * VERSION 1
 * ... put some description of version 1 here ...
*/
$versions[1] = array(
	'up' => '',
	'down' => '',
);

// creating DBUpgrade object with your database credentials and $versions defined above
$dbupgrade = new DBUpgrade(
	new mysqli( 'localhost', '...user...', '...pass...', '...dbname...'), // must create MySQLi db object
	$versions,
	'myapp' // optional namespace if your database has tables from multiple projects that use DBUpgrade
);

require_once(dirname(__FILE__).'/dbupgrade/client.php');
