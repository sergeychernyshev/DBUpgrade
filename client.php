<?php
/*
 * This is a simple client code to allow easy web-based and command-live upgrades
 *
 * Included below $dbupgrade object (you can simply use dbupgrade.php and not worry about this script)
*/
header('Content-type: text/plain');

try {
	if (!empty($argc) && count($argv) == 2 && $argv[1] == 'down') {
		$dbupgrade->dbdown();
	} else {
		$dbupgrade->dbup();
	}
} catch (Exception $e) {
	echo '[ERR] Caught exception: ',  $e->getMessage(), "\n";
	exit(1);
}
