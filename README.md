BRG4 Waltergasse - Moodle Administration Extensions

Install

Go to your Moodle directory in the admin directory and do a git clone:

`git clone https://github.com/8bitcoderookie/Moodle-Admin_BRG4.git brg4`

To integrate all the extensions in your admin tree add the following lines 
to file [/moodle/admin/settings/users.php](../settings/users.php)
right below `// stuff under the "accounts" subcategory` (line 23)

	$ADMIN->add('accounts', new admin_externalpage('brg4userbulk', new lang_string('BRG4-CreateAccounts', 'admin'), "$securewwwroot/admin/brg4/customBulkUserUpload.php", array('moodle/cohort:manage', 'moodle/cohort:view')));
	$ADMIN->add('accounts', new admin_externalpage('brg4cohortbulkgen', new lang_string('BRG4-CreateCohorts', 'admin'), "$securewwwroot/admin/brg4/klassenCohortenGen.php", array('moodle/cohort:manage', 'moodle/cohort:view')));
	$ADMIN->add('accounts', new admin_externalpage('brg4cohortbulkdel', new lang_string('BRG4-DeleteCohorts', 'admin'), "$securewwwroot/admin/brg4/klassenCohortenDel.php", array('moodle/cohort:manage', 'moodle/cohort:view')));
	$ADMIN->add('accounts', new admin_externalpage('brg4emailexport', new lang_string('BRG4-ExportEmail', 'admin'), "$securewwwroot/admin/brg4/exportEmailVWA.php", array('moodle/cohort:manage', 'moodle/cohort:view')));
    
Warning:

Don't expect beautiful code. All my programs get a certain job done. That's it.