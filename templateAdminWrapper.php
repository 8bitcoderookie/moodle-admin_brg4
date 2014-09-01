<?php
    require_once('../../config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/authlib.php');

	admin_externalpage_setup('brg4userbulk'); // has to be defined in ../settings/users.php

	$PAGE->set_title('Custom Bulk User Upload for BRG4');

	echo $OUTPUT->header();
	echo $OUTPUT->box_start();
	echo 'whatever';
	echo $OUTPUT->box_end();	
    echo $OUTPUT->footer();
?>