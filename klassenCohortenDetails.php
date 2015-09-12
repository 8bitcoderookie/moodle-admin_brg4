<?php

/* *****************************************************
klassenCohortenDetails.php

     author: Michael Rundel
       date: 22.04.2011
description: Lists all courses where a cohort is enrolled

****************************************************** */


// imports
require('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/authlib.php');
require('databaseconnect.php');

// ================================
// ensure admin context
admin_externalpage_setup('brg4cohortbulkdel'); // has to be defined in ../settings/users.php

$cohortID = '';
if (isset($_REQUEST['cohortID'])) {
	$cohortID = mysql_real_escape_string(strval($_REQUEST['cohortID']));
}
else {
	exit("missing id");
}

$ajax = '';
if (isset($_REQUEST['ajax'])) {
	$ajax = mysql_real_escape_string(strval($_REQUEST['ajax']));
}

$htmlTitle = "Class Cohort Enrolment Details";

function getList() {
	global $db, $cohortID, $ajax;
	$list = '';
	if (empty($cohortID)) {
		$list .= "missing cohort id...";
	}
	else {
		$query = '';
		$query .= 'SELECT mdl_course.shortname, mdl_course.id ';
		$query .= 'FROM mdl_enrol, mdl_course ';
		$query .= 'WHERE mdl_enrol.customint1 = '.$cohortID.' ';
		$query .= 'AND mdl_enrol.courseid = mdl_course.id ';
		$query .= 'AND mdl_enrol.enrol LIKE "cohort"; ';
		$result = mysql_query($query,$db);
		if (!$result) {
			$list .= '<p><strong>Konnte mdl_cohort nicht abfragen:</strong> '.mysql_error().'</p>';
		}
		else {
			if (mysql_num_rows($result) > 0) {
				if (empty($ajax)) {
					$list .= "<p>This cohort (id=".$cohortID.") is enrolled in the following course(s)</p>\n";
					$list .= "<ul>\n";
				}
				$a = array();
				while ($row = mysql_fetch_array($result)) {
					$list .= '<li><a href="../course/view.php?id='.$row["id"].'" title="">'.$row["shortname"].'</a></li>'."\n";
				}
				if (empty($ajax)) {
					$list .= "<ul>\n";
				}
			}
			else {
				$list .= '<p>cohort is not enrolled in any course</p>';
			}
		}
	}
	return $list;
}

function outputHTML($title,$body) {
	global $PAGE, $OUTPUT;
	$PAGE->set_title($title);
	echo $OUTPUT->header();
	echo $body;
	echo $OUTPUT->footer();
}

if (empty($ajax)) {
	outputHTML($htmlTitle,"<h1>".$htmlTitle."</h1>".getList());
}
else {
	echo getList();
}

?>
