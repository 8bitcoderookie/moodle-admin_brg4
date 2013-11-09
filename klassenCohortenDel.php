<?php

/* *****************************************************
klassenCohortenDel.php

     author: Michael Rundel
       date: 18.04.2011
description: Supports deletion of class cohorts

todo:

+-----------------+---------------------+------+-----+---------+----------------+
| Field           | Type                | Null | Key | Default | Extra          |
+-----------------+---------------------+------+-----+---------+----------------+
| id              | bigint(10) unsigned | NO   | PRI | NULL    | auto_increment |
| enrol           | varchar(20)         | NO   | MUL |         |                |
| status          | bigint(10) unsigned | NO   |     | 0       |                |
| courseid        | bigint(10) unsigned | NO   | MUL | NULL    |                |
| sortorder       | bigint(10) unsigned | NO   |     | 0       |                |
| name            | varchar(255)        | YES  |     | NULL    |                |
| enrolperiod     | bigint(10) unsigned | YES  |     | 0       |                |
| enrolstartdate  | bigint(10) unsigned | YES  |     | 0       |                |
| enrolenddate    | bigint(10) unsigned | YES  |     | 0       |                |
| expirynotify    | tinyint(1) unsigned | YES  |     | 0       |                |
| expirythreshold | bigint(10) unsigned | YES  |     | 0       |                |
| notifyall       | tinyint(1) unsigned | YES  |     | 0       |                |
| password        | varchar(50)         | YES  |     | NULL    |                |
| cost            | varchar(20)         | YES  |     | NULL    |                |
| currency        | varchar(3)          | YES  |     | NULL    |                |
| roleid          | bigint(10) unsigned | YES  |     | 0       |                |
| customint1      | bigint(10)          | YES  |     | NULL    |                |
| customint2      | bigint(10)          | YES  |     | NULL    |                |
| customint3      | bigint(10)          | YES  |     | NULL    |                |
| customint4      | bigint(10)          | YES  |     | NULL    |                |
| customchar1     | varchar(255)        | YES  |     | NULL    |                |
| customchar2     | varchar(255)        | YES  |     | NULL    |                |
| customdec1      | decimal(12,7)       | YES  |     | NULL    |                |
| customdec2      | decimal(12,7)       | YES  |     | NULL    |                |
| customtext1     | longtext            | YES  |     | NULL    |                |
| customtext2     | longtext            | YES  |     | NULL    |                |
| timecreated     | bigint(10) unsigned | NO   |     | 0       |                |
| timemodified    | bigint(10) unsigned | NO   |     | 0       |                |
+-----------------+---------------------+------+-----+---------+----------------+

id              41,
enrol           'cohort',
status          0,
courseid        13,
sortorder       3,
name            NULL,
enrolperiod     0,
enrolstartdate  0,
enrolenddate    0,
expirynotify    0,
expirythreshold 0,
notifyall       0,
password        NULL,
cost            NULL,
currency        NULL,
roleid          1,
customint1      29,
customint2      NULL,
customint3      NULL,
customint4      NULL,
customchar1     NULL,
customchar2     NULL,
customdec1      NULL,
customdec2      NULL,
customtext1     NULL,
customtext2     NULL,
timecreated     1303288348,
timemodified    1303288348


Zählt die Anzahl der Enrollments pro cohorte, die die form 2zzz-zb (z...Zahl, b...Buchstabe):

SELECT mdl_cohort.name, mdl_cohort.id, COUNT(temp_table.customint1) as anzahl 
FROM mdl_cohort  
LEFT OUTER JOIN (SELECT * FROM mdl_enrol WHERE mdl_enrol.enrol LIKE "cohort") as temp_table
ON mdl_cohort.id=temp_table.customint1 
WHERE mdl_cohort.name  REGEXP "2[[:digit:]][[:digit:]][[:digit:]]-[[:digit:]][[:alpha:]]" 
GROUP BY mdl_cohort.id 
ORDER BY name ASC; 

=== obsolte ====
SELECT mdl_cohort.name, mdl_cohort.id, COUNT(mdl_enrol.customint1) as anzahl 
FROM mdl_cohort  
LEFT OUTER JOIN mdl_enrol 
ON mdl_cohort.id=mdl_enrol.customint1 
WHERE mdl_cohort.name  REGEXP "2[[:digit:]][[:digit:]][[:digit:]]-[[:digit:]][[:alpha:]]" 
GROUP BY mdl_cohort.id 
ORDER BY name ASC; 
================


****************************************************** */


// imports
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/authlib.php');
require_once('databaseconnect.php');

// ensure admin context
admin_externalpage_setup('brg4cohortbulkdel'); // has to be defined in ../settings/users.php

// ================================


$htmlTitle = "Class Cohort Deletion for BRG4";
$htmlBody = "";

$classCohortIDs = $_REQUEST["cohortList"];
$submit = htmlspecialchars($_REQUEST["submit"]);


function outputHTML($title,$body) {
	global $PAGE, $OUTPUT;
	$PAGE->set_title($title);
	$style = '<style type="text/css">ul {list-style-type:none}</style>';
	$javascripts = '<script src="http://code.jquery.com/jquery-1.4.2.min.js"></script>';
	// $javascripts = '<script src="http://code.jquery.com/jquery-2.0.3.min.js"></script>';
	$javascripts .= '<script src="klassenCohortenDel.js"></script>';
	echo $OUTPUT->header();
	echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html;charset=utf-8" ><title>'.$title.'</title>'.$style.'</head><body>'.$body.$javascripts.'</body></html>';
	echo $OUTPUT->footer();
}

function getForm() {
	global $db;
	$list = "";
	$query = '';
	$query .= 'SELECT mdl_cohort.name, mdl_cohort.id, COUNT(temp_table.customint1) as anzahl  ';
	$query .= 'FROM mdl_cohort   ';
	$query .= 'LEFT OUTER JOIN (SELECT * FROM mdl_enrol WHERE mdl_enrol.enrol LIKE "cohort") as temp_table ';
	$query .= 'ON mdl_cohort.id=temp_table.customint1  ';
	$query .= 'WHERE mdl_cohort.name  REGEXP "2[[:digit:]][[:digit:]][[:digit:]]-[[:digit:]][[:alpha:]]"  ';
	$query .= 'GROUP BY mdl_cohort.id  ';
	$query .= 'ORDER BY name ASC;  ';
	$result = mysql_query($query,$db);
	if (!$result) {
		$list .= ' <p><strong>Konnte mdl_cohort nicht abfragen:</strong> '.mysql_error().'</p>';
	}
	else {
		$num_rows = mysql_num_rows($result); 
		if ($num_rows > 0) {
			$currentYear = "";
			$list .= "<ul>\n";
			while ($row = mysql_fetch_array($result)) {
				$year = substr($row["name"],0,4);
				if ($year != $currentYear) {
					if ($currentYear != "") {
						$list .= '</ul>'."\n";
					}
					$list .= "<li class='folder'>".$year."</li>\n<ul>\n";
					$currentYear = $year;
				}
				if ($row["anzahl"] > 0) {
					$list .= '<li><input type="checkbox" disabled="disabled" name="" value="">&nbsp;'.$row["name"];
					$list .= ' <span class="notice">- This cohort is enrolled in '.$row["anzahl"].' course(s) (<a href="klassenCohortenDetails.php?cohortID='.$row["id"].'" title="">show Details</a>).</span>';
					$list .= '</li>'."\n";
				}
				else {
					$list .= '<li><input type="checkbox" name="cohortList[]" value="'.$row["id"].'">&nbsp;'.$row["name"].'</li>'."\n";
				}
			}
			$list .= "</ul>\n</ul>\n";
		}
		else {
			$list .= ' <p><strong>Keine Klassen-Cohorten in DB gefunden:</strong></p> ';
		}
	}
	$out = "";
	$out .= '<p>Note: This Form only shows class related cohorts! If you want to see all cohorts, please use <a href="../cohort/index.php" >moodle standard cohort management interface</a>.</p>';
	$out .= '<form accept-charset="UTF-8" method="post" action="'.$_SERVER['SCRIPT_NAME'].'">';
	$out .= $list;
	$out .= '<p><input type="submit" name="submit" value="Delete all selected cohorts"></p>';
	$out .= '<p>Choose wisely - there is no simple undo for this operation!!!</p>';
	$out .= '</form>';
	return $out;
}


function processCVSdata($classCohortIDs) {
	$out = "";
	if (count($classCohortIDs)>0) {
		$out .= '<p>below you find a recovery CSV List of all deleted cohort-members. You can use this list in the <a href="klassenCohortenGen.php">Class Cohort Generation Form</a></p>';
		$out .= '<textarea name="userdata" rows="20" cols="80">'.getRecoveryCSV($classCohortIDs).'</textarea>';
		$out .= deleteCohorts($classCohortIDs).$out;
	}
	else {
		$out .= '<p>no cohorts selected - nothing to do...</p>';
	}
	return $out;
}

function deleteCohorts($classCohortIDs) {
	global $db;
	$out = "";
	$out .= deleteCohortMembers($classCohortIDs);
	$query = '';
	$query .= 'DELETE ';
	$query .= 'FROM mdl_cohort ';
	$query .= 'WHERE id IN ('.implode(",",$classCohortIDs).');  ';
	$result = mysql_query($query,$db);
	if (!$result) {
		$out .= ' <p><strong>Konnte Einträge in mdl_cohort nicht löschen:</strong> '.mysql_error().'</p>';
	}
	else {
		$out .= ' <p>Es wurden '.mysql_affected_rows($db).' Datensätze in mdl_cohort gelöscht.</p>';
	}
	return $out;
}

function deleteCohortMembers($classCohortIDs) {
	global $db;
	$out = "";
	$query = '';
	$query .= 'DELETE ';
	$query .= 'FROM mdl_cohort_members ';
	$query .= 'WHERE cohortid IN ('.implode(",",$classCohortIDs).');  ';
	$result = mysql_query($query,$db);
	if (!$result) {
		$out .= ' <p><strong>Konnte Einträge in mdl_cohort_members nicht löschen:</strong> '.mysql_error().'</p>';
	}
	else {
		$out .= ' <p>Es wurden '.mysql_affected_rows($db).' Datensätze in mdl_cohort_members gelöscht.</p>';
	}
	return $out;
}


function getRecoveryCSV($classCohortIDs) {
	global $db;
	$out = "";

	$query = '';
	$query .= 'SELECT c.name, u.username  ';
	$query .= 'FROM mdl_cohort_members as m, mdl_user as u, mdl_cohort as c  ';
	$query .= 'WHERE m.cohortid IN ('.implode(",",$classCohortIDs).')  ';
	$query .= 'AND u.id = m.userid  ';
	$query .= 'AND c.id = m.cohortid  ';
	$query .= 'ORDER BY c.name ASC;  ';
	
	$result = mysql_query($query,$db);
	if (!$result) {
		$out .= ' <p><strong>Konnte mdl_cohort_members nicht abfragen:</strong> '.mysql_error().'</p>';
	}
	else {
		$num_rows = mysql_num_rows($result); 
		if ($num_rows > 0) {
			while ($row = mysql_fetch_array($result)) {
				$out .= $row["name"].';'.$row["username"]."\n";
			}
		}
	}
	return $out;
}



if (empty($submit)) {
	$htmlBody = "<h2>".$htmlTitle."</h2>".getForm();
}
else {
	$htmlBody = "<h2>".$htmlTitle." - Report</h2><p>".processCVSdata($classCohortIDs)."</p>";
}

outputHTML($htmlTitle,$htmlBody);


?>
