<?
require_once(dirname(__FILE__).'/dbup.php');
require_once(dirname(__FILE__).'/config.php');

dbup($db, $versions);
