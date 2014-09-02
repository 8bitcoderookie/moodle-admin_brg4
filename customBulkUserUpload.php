<?php

/* *****************************************************
customBulkUserUpload.php

     author: Michael Rundel
       date: 15.04.2011
description: Writes a semicolon seperated list of format
             username; lastname; firstname; [idnumber]
			 to mdl_user. Additionally adds the user to 
			 cohort "LehrerInnen" (if idnumber is missing or id begins with "L")
			 or cohort "SchuelerInnen" (if idnumber is given)

updates:
v1.1 (20.07.2011):	sets idnumber if user exists an field idnumber is empty.
v1.2 (03.10.2011):	fixed: description "SchülerIn" for teachers.
                    fixed: mnethostid.
                    added: teacher id begins with "L".
					fixed: timelastmodified, timecreated.
                    added: ajax.
v1.3 (18.07.2013):	fixed: utf-8 bug.

IMPORTANT:
Every User has an value mnethostid set, which is used for moodle to moodle communication only. So if you are running a single instance of moodle this field value is never used. The program just ensures that the value points to a valid key in mdl_mnet_host for db consistency.

****************************************************** */

// imports
// =======

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/authlib.php');
require_once('databaseconnect.php');

// ensure admin context
admin_externalpage_setup('brg4userbulk'); // has to be defined in ../settings/users.php

$htmlTitle = "Custom Bulk User Upload for BRG4";
$htmlBody = "";

$CohortTeacherId = -1;
$CohortStudentId = -1;
$mnethostid = -1;

function outputHTML($title,$body) {
	global $PAGE, $OUTPUT;
	$PAGE->set_title($title);
	echo $OUTPUT->header();
	echo $body;
	echo $OUTPUT->footer();
}

function getForm() {
	global $db, $mnethostid;
	$out = "";
	$out .= '<form accept-charset="UTF-8" method="post" action="'.$_SERVER['SCRIPT_NAME'].'">';
	$out .= '<p>expected data format:</p><ul>
		<li>one record per line</li>
		<li>fields seperated by semicolon</li>
		<li>idnumber is missing or beginns with "L"=> cohort "teacher"</li>
		<li>else => cohort "student"</li>
	</ul>';
	$out .= '<p><code><strong>username; lastname; firstname; email; [idnumber]</strong></code></p>';
	$out .= '<textarea name="userdata" rows="20" cols="80"></textarea>';
	$out .= '<p><input type="submit" name="submit" value="Submit"></p>';
	$out .= '</form>';
	return $out;
}

function getMnethostid() {
	global $mnethostid, $db;
	$query = "select * from mdl_mnet_host;";
	$result = mysql_query($query, $db);
	if (!$result) {
		return " <strong>Could not query mdl_mnet_host: ".mysql_error()."!</strong><br>";
	}
	else {
		if (mysql_num_rows($result) > 0) {
			while ($row = mysql_fetch_array($result)) {
				if ($mnethostid == -1) { 
					if (strpos($row["wwwroot"],'localhost') !== false) { // look for localhost
						$mnethostid = $row["id"];
						return 'mnethostid set to '.$mnethostid.' ('.$row["name"].' '.$row["wwwroot"].' '.$row["ip_address"].')<br>';
					}
				}
			}
		}
		else {
			return " <strong>Could table mdl_mnet_host contains no entries!</strong><br>";
		}
	}
}

function processCVSdata($data) {
	$report = "";
	$data = preg_replace('/\r\n|\r|\n+/', "\n", $data); // Windows uses \r\n newlines; *nix uses \n; Mac uses \r.
	$data = trim(preg_replace('/\n+/', "\n", $data)); // get rid of emtpy lines
	$lines = preg_split("/\n/", $data);
	foreach ($lines as $line) {
		$username = "";
		$lastname = "";
		$firstname = "";
		$email = "";
		$idnumber = "";
		$rowData = preg_split("/;/", $line);
		$rowDataCount = count($rowData);
		if ($rowDataCount > 0) {
			$username = trim($rowData[0]);
		}
		if ($rowDataCount > 1) {
			$lastname = trim($rowData[1]);
		}
		if ($rowDataCount > 2) {
			$firstname = trim($rowData[2]);
		}
		if ($rowDataCount > 3) {
			$email = trim($rowData[3]);
		}
		if ($rowDataCount > 3) {
			$idnumber = trim($rowData[3]);
		}
		$report .= "<br>processing: [".$line."]: ".validateDataAndInsertIntoDB($username, $lastname, $firstname, $email, $idnumber)."\n";
	}
	return $report;
}

function validateDataAndInsertIntoDB($username, $lastname, $firstname, $email, $idnumber) {
	if (empty($username)) {
		return ("<strong>username missing - row omitted</strong>");
	}
	if (empty($lastname)) {
		return ("<strong>lastname missing - row omitted</strong>");
	}
	if (empty($firstname)) {
		return ("<strong>firstname missing - row omitted</strong>");
	}
	if (empty($email)) {
		return ("<strong>email missing - row omitted</strong>");
	}
	$idOwnerIDnumber = getIsForThisIDnumber($idnumber);
	$idOwnerUsername = getIsForThisUsername($username);
	if ($idOwnerUsername > -1) {
		if ($idOwnerIDnumber > -1) {
			if ($idOwnerUsername != $idOwnerIDnumber) {
				return ("<strong>WARNING: owner of username is different to owner of IDNumber!!! - row omitted</strong>");
			}
			else {
				$status .= " <strong>User <code>".$username."</code> already exists.</strong>".addUserToTeacherOrStudentCohort($idOwnerUsername, $idnumber);
			}
		}
		else {
			$status = "<strong>username <code>".$username."</code> is already in use!</strong>";
			$status .= assignIDnumberIfNotAlreadySet($idOwnerUsername, $idnumber);
			$status .= addUserToTeacherOrStudentCohort($idOwnerUsername, $idnumber);
		}
	}
	else {
		$status = insertIntoDB($username, $lastname, $firstname, $email, $idnumber);
	}
	return $status;
}

function assignIDnumberIfNotAlreadySet($userid, $idnumber) {
	global $db;
	if (empty($idnumber)) {
		return " IDnumber is empty!";
	}
	else {
		// test if idnumber is empty...
		$query = "SELECT id, idnumber FROM mdl_user WHERE id = ".$userid.";";
		$result = mysql_query($query, $db);
		if (!$result) {
			return " <strong>Could not query idnumber from mdl_user for user (id: ".$userid."): ".mysql_error()."!</strong>";
		}
		else {
			if (mysql_num_rows($result) > 0) {
				$row = mysql_fetch_array($result);
				if (!empty($row["idnumber"])) {
					return " <strong>A idnumber has already been assigned. Cancel update!</strong>";
				}
				else {
					$query = "UPDATE mdl_user SET idnumber = '".$idnumber."' WHERE id = ".$userid." Limit 1; ";
					$result = mysql_query($query, $db);
					if (!$result) {
						return " <strong>Could not update user (id: ".$userid.") to idnumber '".$idnumber."': ".mysql_error()."!</strong>";
					}
					else {
						if (mysql_affected_rows($db) > 0) {
							return " <span style='color:blue'>IDnumber = '".$idnumber."' set.</span>";
						}
						else {
							return " <strong>Could not update user (id: ".$userid.") to idnumber '".$idnumber."'!</strong>";
						}
					}
				}
			}
			else {
				return " <strong>Could not query idnumber from mdl_user for user (id: ".$userid."). Maybe user does not exist at all!</strong>";
			}
		}
	}	
}

function getIsForThisIDnumber($idnumber) {
	global $db;
	if (empty($idnumber)) {
		return -1;
	}
	else {
		$query = 'SELECT id FROM mdl_user WHERE idnumber LIKE "'.$idnumber.'";';
		$result = mysql_query($query, $db);
		if (!$result) {
			echo ' <strong>Konnte idnumber nicht abfragen:</strong> '.mysql_error();
			return -1;
		}
		else {
			$num_rows = mysql_num_rows($result);
			if ($num_rows > 0) {
				$row = mysql_fetch_array($result);
				return $row["id"];
			}
			else {
				return -1;
			}
		}
	}
}

function getIsForThisUsername($username) {
	global $db;
	$query = 'SELECT id FROM mdl_user WHERE username LIKE "'.$username.'";';
	$result = mysql_query($query, $db);
	if (!$result) {
		echo ' <strong>Konnte Benutzername nicht abfragen:</strong> '.mysql_error();
		return -1;
	}
	else {
		$num_rows = mysql_num_rows($result);
		if ($num_rows > 0) {
			$row = mysql_fetch_array($result);
			return $row["id"];
		}
		else {
			return -1;
		}
	}
}

function insertIntoDB($username, $lastname, $firstname, $email, $idnumber) {
	global $db, $mnethostid;
	$query = "";
	$query .= 'INSERT INTO mdl_user (';
	$query .= 'lastname,';
	$query .= 'firstname,';
	$query .= 'username,';
	$query .= 'idnumber,';
	$query .= 'password,';
	$query .= 'auth,';
	$query .= 'email,';
	$query .= 'confirmed,';
	$query .= 'mnethostid,';
	$query .= 'emailstop,';
	$query .= 'city,';
	$query .= 'country,';
	$query .= 'lang,';
	$query .= 'timecreated,';
	$query .= 'timemodified,';
	$query .= 'firstaccess,';
	$query .= 'lastaccess,';
	$query .= 'description,';
	$query .= 'descriptionformat,';
//	$query .= 'ajax,';					// no more in moodle 2.5
	$query .= 'maildisplay, ';
	$query .= 'autosubscribe) ';
	$query .= ' VALUES(';
	$query .= '"'.$lastname.'",';
	$query .= '"'.$firstname.'",';
	$query .= '"'.$username.'",';
	$query .= '"'.$idnumber.'",';
	$query .= '"198bb50085a9db4e335b851e74dd6366",';
	$query .= '"ldap",';
	$query .= '"'.$email.'",';
	$query .= '1,';
	$query .= $mnethostid.',';
	$query .= '0,';
	$query .= '"Wien",';
	$query .= '"AT",';
	$query .= '"de",';
	$query .= 'UNIX_TIMESTAMP(),';
	$query .= 'UNIX_TIMESTAMP(),';
	$query .= '0,';
	$query .= '0,';
	if (isTeacherIdNumber($idnumber)) {
		$query .= '"<p>LehrerIn am BRG4</p>",';
	}
	else {
		$query .= '"<p>Sch&uuml;lerIn des BRG4</p>",';
	}
	$query .= '1,';
//	$query .= '1,';					// 'ajax' no more in moodle 2.5
	$query .= '0,';
	$query .= '0);';

	// return 'OK';
	// echo("<p>".$query."</p>");

	$result = mysql_query($query, $db);
	if (!$result) {
		return ' <strong>Konnte Benutzer nicht eintragen:</strong> '.mysql_error();
	}
	else {
		$userid = mysql_insert_id($db);
		// echo("<p>userid = ".$userid."</p>");
		return 'OK'.addUserToTeacherOrStudentCohort($userid, $idnumber);
		// return 'OK.';
	}
}

function isTeacherIdNumber($userid) {
	if (empty($userid)) return true;
	if (StartsWith(strtoupper($userid), 'L')) return true;
	return false;
}

function StartsWith($Haystack, $Needle){
    // Recommended version, using strpos
    return strpos($Haystack, $Needle) === 0;
}

function addUserToTeacherOrStudentCohort($userid,$idnumber) {
	// return " addUserToTeacherOrStudentCohort() disabled.";
	if (isTeacherIdNumber($idnumber)) {
		return addToCohort($userid, getIDCohortTeacher(), ' (=> Kohorte Lehrer)');
	}
	else {
		return addToCohort($userid, getIDCohortStudent(), ' (=> Kohorte Schüler)');
	}
}

function getIDCohortTeacher() {
	global $db,$CohortTeacherId;
	$cohortName = "LehrerInnen";
	if ($CohortTeacherId == -1) {
		$query = 'SELECT id FROM mdl_cohort WHERE name LIKE "'.$cohortName.'";';
		$result = mysql_query($query, $db);
		if (!$result) {
			return ' Kohorte Lehrer Ungültige Abfrage: '.mysql_error();
		}
		else {
			$num_rows = mysql_num_rows($result);
			if ($num_rows > 0) {
				$row = mysql_fetch_array($result);
				$CohortTeacherId = $row["id"];
			}
			else { // create cohort
				$cohortDescription = "In dieser Cohorte sind alle Leher und Lehrerinnen zusammengefasst.";
				$cohortContextid = 1;
				$query = "INSERT INTO mdl_cohort VALUES (NULL, ".$cohortContextid.", '".$cohortName."', NULL, '".$cohortDescription."',1,'', ".time().", ".time().");";
				$result = mysql_query($query, $db);
				if (!$result) {
					return ' Konnte Kohorte Lehrer nicht anlegen: '.mysql_error();
				}
				else {
					$CohortTeacherId = mysql_insert_id($db);
				}
			}
		}
	}
	return $CohortTeacherId;
}

function getIDCohortStudent() {
	global $db,$CohortStudentId;
	$cohortName = "SchuelerInnen";
	if ($CohortStudentId == -1) {
		$query = 'SELECT id FROM mdl_cohort WHERE name LIKE "'.$cohortName.'";';
		$result = mysql_query($query, $db);
		if (!$result) {
			return ' <strong>Kohorte Schüler Ungültige Abfrage:</strong> '.mysql_error();
		}
		else {
			$num_rows = mysql_num_rows($result);
			if ($num_rows > 0) {
				$row = mysql_fetch_array($result);
				$CohortStudentId = $row["id"];
			}
			else { // create cohort
				$cohortDescription = "In dieser Cohorte sind alle Sch&uuml;ler und Sch&uuml;lerinnen zusammengefasst.";
				$cohortContextid = 1;
				$query = "INSERT INTO mdl_cohort VALUES (NULL, ".$cohortContextid.", '".$cohortName."', NULL, '".$cohortDescription."',1,'', ".time().", ".time().");";
				$result = mysql_query($query, $db);
				if (!$result) {
					return ' <strong>Konnte Kohorte Schüler nicht anlegen:</strong> '.mysql_error();
				}
				else {
					$CohortStudentId = mysql_insert_id($db);
				}
			}
		}
	}
	return $CohortStudentId;
}


function addToCohort($userid, $cohortid, $infoSuccess) {
	global $db;
	// test if user already in cohort enlisted...
	$query = "SELECT id FROM mdl_cohort_members WHERE cohortid=".$cohortid." AND userid=".$userid."; ";
	$result = mysql_query($query);
	if (!$result) {
		return ' <strong>Could not query mdl_cohort_members:</strong> '.mysql_error();
	}
	else {
		if (mysql_num_rows($result) > 0) {
			return ' <strong>User already is in cohort (id='.$cohortid.'):</strong> '.mysql_error();
		}
		else {
			$query = "INSERT INTO mdl_cohort_members VALUES (NULL, ".$cohortid.", ".$userid.", ".time().");";
			$result = mysql_query($query);
			if (!$result) {
				return ' <strong>Konnte Benuzter in Kohorte nicht eintragen:</strong> '.mysql_error();
			}
			else {
				return $infoSuccess;
			}
		}
	}
}

$submit = htmlspecialchars($_REQUEST["submit"]);
$userdata = htmlspecialchars($_REQUEST["userdata"]);

if (empty($submit)) {
	$htmlBody = '<h2>'.$htmlTitle.'</h2>'.getForm();
}
else {
	$htmlBody = '<h2>'.$htmlTitle.' - Report</h2><p>'.getMnethostid().processCVSdata($userdata).'</p>';
}

outputHTML($htmlTitle,$htmlBody);

?>
