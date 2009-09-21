<?
require_once('dbup.php');
require_once('config.php');

dbdown($db, $versions);
