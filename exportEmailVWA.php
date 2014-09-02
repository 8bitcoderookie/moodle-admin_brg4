<?php

/* *****************************************************

exportEmailVWA.php

     author: Michael Rundel
       date: 13.12.2013
description: exportes email for 7 and 8 Graders

updates:

****************************************************** */


// imports
require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/authlib.php');
require('databaseconnect.php');

// ================================

// ensure admin context
admin_externalpage_setup('brg4emailexport'); // has to be defined in ../settings/users.php

$htmlTitle = "Export Email Adressen für 7. und 8. Klassen";
$htmlBody = "";
$classCohortIDs = array();

$submit = htmlspecialchars($_REQUEST["submit"]);
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

function getForm($CSVdata = '') {
	$out = "";
	$out .= '<style type="text/css">p {margin: 0.5em 0; text-indent: -25px; padding-left: 25px;}</style>';
	$out .= '<form accept-charset="UTF-8" method="post" action="'.$_SERVER['SCRIPT_NAME'].'">';
	$out .= '<table>';
	$out .= '<tr><td><label for="prefix">Class Cohort Year Prefix:</label></td><td><select name="prefix"><option>'.date('Y').'-</option><option>'.(date('Y')-1).'-</option><option>'.(date('Y')-2).'-</option><option>'.(date('Y')-3).'-</option> </select></td></tr>';
	$out .= '</table>';
	$out .= '<textarea name="userdata" rows="20" cols="80">'.$CSVdata.'</textarea>';
	$out .= '<p><input type="submit" name="submit" value="Submit"></p>';
	$out .= '</form>';
	return $out;
}

function processData($data) {
	global $classCohortPrefix, $db ;
	$CSVdata = '';
	$query = <<< EOT
SELECT RIGHT(mc.name, 2) as klasse, mu.idnumber, mu.lastname, mu.firstname, mu.email
FROM mdl_user as mu, mdl_cohort as mc, mdl_cohort_members as mcm
WHERE mcm.cohortid = mc.id
AND mcm.userid = mu.id
AND mc.name IN ("{$classCohortPrefix}7A","{$classCohortPrefix}7B","{$classCohortPrefix}8A","{$classCohortPrefix}8B")
ORDER BY klasse, mu.lastname, mu.firstname ASC;
EOT;
	$result = mysql_query($query, $db);
	if (!$result) {
		$CSVdata = ' <strong>Could not query mdl_user for cohortPrefix "'.$classCohortPrefix.'": '.mysql_error().".</strong>";
	}
	else {
		$num_rows = mysql_num_rows($result);
		if ($num_rows > 0) {
			$CSVdata .= 'Klasse;SchülerID;Nachname;Vorname;EMail'.PHP_EOL;
			while ($row = mysql_fetch_array($result)) {
				$CSVdata .= $row["klasse"].';'.$row["idnumber"].';'.$row["lastname"].';'.$row["firstname"].';'.$row["email"].PHP_EOL;
			}
		}
		else {
			$CSVdata = ' <strong style="color:red">No user with cohortPrefix "'.$classCohortPrefix.'" found!</strong> ';
		}
	}

	return $CSVdata;
}

if (empty($submit)) {
	$htmlBody = "<h2>".$htmlTitle."</h2>".getForm();
}
else {
	$htmlBody = "<h2>".$htmlTitle."</h2>".getForm(processData($userdata));
}

outputHTML($htmlTitle,$htmlBody);

?>

	

  

