<?php

// connecting to db
$db = mysql_connect($CFG->dbhost,$CFG->dbuser,$CFG->dbpass);
if (!$db) {
	print "Error: Could not connect to Database!";
	exit;
}
mysql_select_db($CFG->dbname, $db) or die("Error: Unable to Select Database");
mysql_query("SET NAMES 'utf8'",$db);
mysql_query("SET CHARACTER SET 'utf8'",$db);

?>
