<?php
/**
* glFusion CMS
*
* import - Import Plugin for glFusion
*
* English Language
*
* @license GNU General Public License version 2 or later
*     http://www.opensource.org/licenses/gpl-license.php
*
*  Copyright (C) 2016-2017 by the following authors:
*   Mark R. Evans   mark AT glfusion DOT org
*
*/

$LANG_IMP = array (
    'plugin'            => 'import',
    'plugin_name'       => 'Import',
    'admin_title'       => 'GL Import Utility',
    'import_instructions' => 'This utility will import the data from a GL database into glFusion.',
    'import_warning'    => 'Caution! This utility will overwrite all content, user data, block data and other data. You must run this utility on a new installation of glFusion.',
    'import_warning_alert' => 'This utility will delete all existing content on this site prior to importing',
    'final_instructions' => 'You are now ready to begin the import of your content into glFusion. Depending on the size of your old site, this could take awhile. Sit back, relax while the migration utility works. You are just a few minutes away from glFusion Awesomeness!',
    'final_warning'     => 'This will remove the content from your glFusion site before importing. Are you sure you want to continue?',
    'continue'          => 'Continue',
    'cancel'            => 'Cancel',
    'ok'                => 'OK',
    'start_instructions' => 'Enter the database information for the <b>GL</b> database that you will import from.',
    'dbname'            => 'Database Name',
    'dbuser'            => 'Database Username',
    'dbpasswd'          => 'Database Password',
    'dbprefix'          => 'Database Prefix',
    'import_success'    => 'Congradulations! You have made the move to glFusion!',
    'import_list'       => 'The content listed below will now be imported.',
    'importing'         => 'Importing',
    'final_status'      => 'glFusion Awesomeness is Ready!',

    'error_dbname_empty'    => 'Database name cannot be blank',
    'error_dbuser_empty'    => 'Database user cannot be blank',
    'error_dbpass_empty'    => 'Database password cannot be blank',
    'error_no_user_table'   => 'Unable to find the users table - please ensure the database prefix is correct.',
    'error_no_user_data'    => 'No data was returned from the user table.',
);
?>