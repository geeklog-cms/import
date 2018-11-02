<?php
/**
* glFusion CMS
*
* geeklog Import
*
* @license GNU General Public License version 2 or later
*     http://www.opensource.org/licenses/gpl-license.php
*
*  Copyright (C) 2016-2017 by the following authors:
*   Mark R. Evans   mark AT glfusion DOT org
*
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
* Language file Include
*/
$langfile = $_CONF['path'] . 'plugins/import/language/' . $_CONF['language'] . '.php';

if (file_exists ($langfile)) {
    include_once $langfile;
} else {
    include_once $_CONF['path'] . 'plugins/import/language/english_utf-8.php';
}

/*
* Include import config file
*/
require_once $_CONF['path'] . 'plugins/import/import.php';

/**
* This will put an option for the plugin in the command and control block on moderation.php
*
* Add the plugin name, icon and link to the command and control block in moderation.php
*
* @return   array   Array containing (plugin name, admin url, url of plugin icon)
*
*/
function plugin_cclabel_import()
{
    global $_CONF, $LANG_IMP;

    return array(
        $LANG_IMP['plugin_name'],
        $_CONF['site_admin_url'] . '/plugins/import/index.php',
        $_CONF['site_admin_url'] . '/plugins/import/images/import.png'
    );
}

/**
* Returns the administrative option for this plugin
*
* Adds the plugin to the Admin menu
*
* Universal Plugin:  Modify/Uncomment use it
*
* @return   array   Array containing (plugin name, plugin admin url, # of items in plugin or '')
*
*/
function plugin_getadminoption_import()
{
    global $_CONF, $_TABLES, $LANG_IMP;

    if (SEC_inGroup('Root')) {
        return array(
            $LANG_IMP['plugin_name'],
            $_CONF['site_admin_url'] . '/plugins/import/index.php',
            0
        );
    }
}

function plugin_chkVersion_import() {
    global $_IMP_CONF;

    return $_IMP_CONF['pi_version'] ;
}


/* Auto loader */

spl_autoload_register('import_autoloader');

function import_autoloader($class) {
    global $_CONF;
    if (strpos($class, 'Import\\') === 0) {
        $class = str_replace('Import\\', '', $class);
        $path = $_CONF['path'].'plugins/import/classes/' . $class . '.class.php';
        if (file_exists($path)) {
            include $path;
        }
    }
}

?>