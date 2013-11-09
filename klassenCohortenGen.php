<?php

/* *****************************************************

klassenCohortenGen.php

     author: Michael Rundel
       date: 18.04.2011
description: Writes a semicolon seperated list of format
             class; idnumber 
			 or 
             class; username 
			 creates cohort for each class (mdl_cohort) and assings students 
			 with idnumber or username to the class cohort (mdl_cohort_members). 
			 WARNING: this script assumes that idnumber is a valid number 
			 (moodle also accepts strings in this field!)
			 Detail: The script checks if a student is already listed in another 
			 class of the same year. In this case the student is moved to the new class.

updates:
v1.1 (19.07.2011):	support vor subclasses (BI/ACG, BE/ME,...)

****************************************************** */


// imports
require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/authlib.php');
require('databaseconnect.php');

// ================================

// ensure admin context
admin_externalpage_setup('brg4cohortbulkgen'); // has to be defined in ../settings/users.php

$htmlTitle = "Class Cohort Generation for BRG4";
$htmlBody = "";
$classCohortIDs = array();

$useprefix = htmlspecialchars($_REQUEST["useprefix"]);
$submit = htmlspecialchars($_REQUEST["submit"]);
$userdata = htmlspecialchars($_REQUEST["userdata"]);
$classCohortPrefix = htmlspecialchars($_REQUEST['prefix']);


function outputHTML($title,$body) {
	global $PAGE, $OUTPUT;
	$PAGE->set_title($title);
	echo $OUTPUT->header();
	// echo $OUTPUT->box_start();
	echo $body;
	// echo $OUTPUT->box_end();	
	echo $OUTPUT->footer();
}

function getForm() {
	$out = "";
	$out .= '<style type="text/css">p {margin: 0.5em 0; text-indent: -25px; padding-left: 25px;}</style>';
	$out .= '<form accept-charset="UTF-8" method="post" action="'.$_SERVER['SCRIPT_NAME'].'">';
	$out .= '<table>';
	$out .= '<tr><td><input type="checkbox" name="useprefix" value="useprefix" checked="checked"><label for="prefix">Class Cohort Year Prefix:</label></td><td><select name="prefix"><option>'.date('Y').'-</option><option>'.(date('Y')-1).'-</option><option>'.(date('Y')-2).'-</option><option>'.(date('Y')-3).'-</option> </select></td></tr>';
	$out .= '</table>';
	$out .= '<p>expected CSV data format (one record per line; you may use usernames instead of idnumbers):</p>';
	$out .= '<p><code><strong>idnumberOrUsername ; class [; subclass[; subclass[; subclass[; subclass[;...]]]]</strong></code></p>';
	$out .= '<p>Examples:</p>';
	$out .= '<table>';
	$out .= '<tr><td><code><strong>4323; 3a</strong></code> </td><td>(registers student with id 4323 in the cohort <strong>'.date('Y').'-3A</strong>)</td></tr>';
	$out .= '<tr><td><code><strong>4323; 7a; acg; be; sp</strong></code> </td><td>(registers student with id 4323 in the cohort <strong>'.date('Y').'-6b</strong>, <strong>'.date('Y').'-7A (ACG)</strong>, <strong>'.date('Y').'-7A (BE)</strong> and <strong>'.date('Y').'-7A (SP)</strong>)</td></tr>';
	$out .= '</table>';
	$out .= '<textarea name="userdata" rows="20" cols="80"></textarea>';
	$out .= '<p><input type="submit" name="submit" value="Submit"></p>';
	$out .= '</form>';
	return $out;
}

function processCVSdata($data) {
	global $classCohortPrefix;
	$report = "";
	$data = preg_replace('/\r\n|\r|\n+/', "\n", $data); // Windows uses \r\n newlines; *nix uses \n; Mac uses \r.
	$data = trim(preg_replace('/\n+/', "\n", $data)); // get rid of emtpy lines
	$lines = preg_split("/\n/", $data);
	foreach ($lines as $line) {
		$rowData = preg_split("/;/", $line);
		$idnumberOrUsername = trim($rowData[0]);
		$class = trim($rowData[1]);
		$report .= "<p>processing: [".$line."]: ".validateDataAndInsertIntoDB($class, $idnumberOrUsername, array_slice($rowData, 2))."</p>\n";
	}
	return $report;
}

function validateDataAndInsertIntoDB($class, $idnumberOrUsername,$subClassArray) {
	global $useprefix;
	if (empty($class)) {
		return ("<strong style='color:red'>class missing - row omitted!</strong>");
	}
	if (!empty($useprefix)) {
		if (!preg_match("/[12345678][abcdefgh]/i", $class)) {
			return ("<strong style='color:red'>".$class." seems to be no valid class name - row omitted!</strong>");
		}	
	}
	if (empty($idnumberOrUsername)) {
		return (' <strong style="color:red">Idnumber od username missing - row omitted!</strong>');
	}
	if (preg_match("/\d+/i", $idnumberOrUsername)) { // we have an idnumber...
		$retObj = getUserIDbyIdnumber($idnumberOrUsername);
	}
	else { // we assume we have got a username...
		$retObj = getUserIDbyUsername($idnumberOrUsername);
	}
	if (empty($retObj->id)) {
		return $retObj->report;
	}
	// at this stage we have a valid userid
	// return $retObj->report.insertIntoDB($class, $retObj->id, $retObj->firstname, $retObj->lastname,$subClassArray);
	return $retObj->report.insertIntoDB($class, $retObj->id, $subClassArray);
}

function getUserIDbyIdnumber($idnumber) {
	global $db;
	$retObj = new stdClass;
	$query = 'SELECT id,firstname,lastname FROM mdl_user WHERE idnumber LIKE "'.$idnumber.'";';
	$result = mysql_query($query, $db);
	if (!$result) {
		$retObj->report = ' <strong>Could not query mdl_user for idnumber "'.$idnumber.'": '.mysql_error().".</strong>";
		return $retObj;
	}
	else {
		$num_rows = mysql_num_rows($result);
		if ($num_rows > 0) {
			$row = mysql_fetch_array($result);
			$retObj->firstname = $row["firstname"];
			$retObj->lastname = $row["lastname"];
			$retObj->id = $row["id"];
			$retObj->report = ' user ('.$row["firstname"].' '.$row["lastname"].') found. ';
			return $retObj;
		}
		else {
			$retObj->report = ' <strong style="color:red">No user with idnumber "'.$idnumber.'" found!</strong> ';
			return $retObj;
		}
	}
}

function getUserIDbyUsername($username) {
	global $db;
	$retObj = new stdClass;
	$query = 'SELECT id,firstname,lastname FROM mdl_user WHERE username LIKE "'.$username.'";';
	$result = mysql_query($query, $db);
	if (!$result) {
		$retObj->report = ' <strong>Could not query mdl_user for username "'.$username.'": '.mysql_error().".</strong>";
	}
	else {
		$num_rows = mysql_num_rows($result);
		if ($num_rows > 0) {
			$row = mysql_fetch_array($result);
			$retObj->firstname = $row["firstname"];
			$retObj->lastname = $row["lastname"];
			$retObj->id = $row["id"];
			$retObj->report = ' user ('.$row["firstname"].' '.$row["lastname"].') found. ';
		}
		else {
			$retObj->report = ' <strong style="color:red">No user with username "'.$username.'" found!</strong> ';
		}
	}
	return $retObj;
}

function createCohortIfNotExists($cohortName, $cohortDescription, $cohortContextid) {		// returns cohortID
	global $db, $classCohortIDs;
	$retObj = new stdClass;
	if ($classCohortIDs[$cohortName]) { // allready listed in local array
		$retObj->id = $classCohortIDs[$cohortName];
	}
	else {
		// look if class cohort already exists in mdl_cohort
		$query = 'SELECT id FROM mdl_cohort WHERE name LIKE "'.$cohortName.'";';
		$result = mysql_query($query, $db);
		if (!$result) {
			$retObj->report = ' <strong>Could not query mdl_cohort for "'.$cohortName.'": '.mysql_error().".</strong>";
		}
		else {
			$num_rows = mysql_num_rows($result);
			if ($num_rows > 0) {
				$row = mysql_fetch_array($result);
				$classCohortIDs[$cohortName] = $row["id"];
				$retObj->id = $classCohortIDs[$cohortName];
				$retObj->report = '';
			}
			else { // class cohort does not exist, create one
				$query = "INSERT INTO mdl_cohort VALUES (NULL, ".$cohortContextid.", '".$cohortName."', NULL, '".$cohortDescription."',1,'', ".time().", ".time().");";
				$result = mysql_query($query);
				if (!$result) {
					$retObj->report = ' <strong>Could not create cohort "'.$cohortName.'": '.mysql_error().".</strong>";
				}
				else {
					$classCohortIDs[$cohortName] = mysql_insert_id($db);
					$retObj->id = $classCohortIDs[$cohortName];
					$retObj->report = " <span style='color:green'>Created cohort ".$cohortName.".</span>";
				}
			}
		}
	}
	return $retObj;
}

function insertIntoDB($class, $userid, $subClassArray) {
	global $db, $classCohortPrefix, $classCohortIDs, $useprefix;
	$report = "";
	$cohortContextid = 1;
	if (empty($useprefix)) {
		$cohortName = strtoupper($class);
	}
	else {
		$cohortName = $classCohortPrefix.strtoupper($class);
	}
	$cohortDescription = "Klasse ".strtoupper($class)." (Schuljahr ".$classCohortPrefix."/".substr(($classCohortPrefix+1), -2).")";
	$report .= deleteFromCohortSameYearIfAnyDB($cohortName,$userid);
	$retObj = createCohortIfNotExists($cohortName, $cohortDescription, $cohortContextid);
	$report .= $retObj->report;
	if ($retObj->id > 0) {
		// now we can be sure that class cohort exists!!!
		$report .= insertIntoDBCohortUserId($cohortName, $classCohortIDs[$cohortName], $userid);
	}
	for ($i = 0; $i < sizeof($subClassArray); $i++) {
		$subClass = strtoupper($subClassArray[$i]);
		if (!empty($subClass)) {
			$cohortNameSubClass = $cohortName." (".$subClass.")";
			$cohortDescriptionSubClass = $cohortDescription."; Zweig: ".$subClass;
			$retObj = createCohortIfNotExists($cohortNameSubClass, $cohortDescriptionSubClass, $cohortContextid);
			$report .= $retObj->report;
			if ($retObj->id > 0) {
				$report .= insertIntoDBCohortUserId($cohortNameSubClass, $classCohortIDs[$cohortNameSubClass], $userid);
			}
		}
	}
	return $report;
}

	

function insertIntoDBCohortUserId($cohortName, $cohortid, $userid) {
	global $db;
	$report = "";
	$query = 'SELECT id FROM mdl_cohort_members ';
	$query .= 'WHERE cohortid = '.$cohortid.' ';
	$query .= 'AND userid = '.$userid.'; ';
	$result = mysql_query($query);
	if (!$result) {
		return $report.' <strong>Could not query mdl_cohort_members for cohortid='.$cohortid.', userid='.$userid.': '.mysql_error().".</strong>";
	}
	else {
		$num_rows = mysql_num_rows($result);
		if ($num_rows > 0) {
			return $report." Already listed in cohort '".$cohortName."'.";
		}
		else {
			$query = 'INSERT INTO mdl_cohort_members (cohortid,userid,timeadded) VALUES('.$cohortid.','.$userid.','.time().');';
			$result = mysql_query($query);
			if (!$result) {
				return $report.' <strong>Could not insert cohortid='.$cohortid.', userid='.$userid.' into mdl_cohort_members:</strong> '.mysql_error();
			}
			else {
				return $report." Added to '".$cohortName."'.";
			}
		}
	}
}

function deleteFromCohortSameYearIfAnyDB($cohortName,$userid) {
	global $db, $classCohortIDs;
	$report = "";
	$query = 'SELECT c.name, m.id ';
	$query .= 'FROM mdl_cohort as c, mdl_cohort_members as m ';
	$query .= 'WHERE c.name LIKE "'.substr($cohortName,0,4).'%" ';
	$query .= 'AND m.cohortid = c.id ';
	$query .= 'AND m.userid = '.$userid.'; ';
	$result = mysql_query($query, $db);
	if (!$result) {
		$report .= ' <strong>Could not query mdl_cohort_members find user id='.$userid.' in cohort "'.$cohortName.'": '.mysql_error().".</strong>";
		return $report;
	}
	else {
		$num_rows = mysql_num_rows($result); 
		if ($num_rows > 0) { // user is already in a class cohorts of the same year => remove from all old class cohort
			$report .= " <span style='color:blue'>User is already listed in ".$num_rows." class cohort(s) in the same year.</span>";
			while ($row = mysql_fetch_array($result)) {
				$oldCohortName = $row["name"];
				$query = 'DELETE FROM mdl_cohort_members WHERE id = '.$row["id"].' LIMIT 1; ';
				$result2 = mysql_query($query, $db);
				if (!$result2) {
					$report .= " <strong>Could not remove user (id: ".$userid.") from cohorte '".$oldCohortName."': ".mysql_error().".</strong>";
				}
				else {
					if (mysql_affected_rows($db) > 0) {
						$report .= " <span style='color:blue'>Removed from '".$oldCohortName."'.</span>";
					}
					else {
						$report .= " <strong>Could not remove user (id: ".$userid.") from cohorte '".$oldCohortName."'.</strong>";
					}
				}
			}
		}
	}
	return $report;
}


if (empty($submit)) {
	$htmlBody = "<h2>".$htmlTitle."</h2>".getForm();
}
else {
	$htmlBody = "<h2>".$htmlTitle." - Report</h2><p>".processCVSdata($userdata)."</p>";
}

outputHTML($htmlTitle,$htmlBody);

?>

	

  

