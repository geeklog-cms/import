<?php
/**
* glFusion CMS
*
* geeklog Import
*
* Auto Installer
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

global $_DB_dbms;

require_once $_CONF['path'].'plugins/import/functions.inc';
require_once $_CONF['path'].'plugins/import/import.php';

// +--------------------------------------------------------------------------+
// | Plugin installation options                                              |
// +--------------------------------------------------------------------------+

$INSTALL_plugin['import'] = array(

    'installer' => array('type' => 'installer', 'version' => '1', 'mode' => 'install'),

    'plugin' => array('type' => 'plugin', 'name' => $_IMP_CONF['pi_name'],
        'ver' => $_IMP_CONF['pi_version'], 'gl_ver' => $_IMP_CONF['gl_version'],
        'url' => $_IMP_CONF['pi_url'], 'display' => $_IMP_CONF['pi_display_name']),
);


/**
* Puts the datastructures for this plugin into the glFusion database
*
* Note: Corresponding uninstall routine is in functions.inc
*
* @return   boolean True if successful False otherwise
*
*/
function plugin_install_import()
{
    global $INSTALL_plugin, $_IMP_CONF;

    $pi_name            = $_IMP_CONF['pi_name'];
    $pi_display_name    = $_IMP_CONF['pi_display_name'];
    $pi_version         = $_IMP_CONF['pi_version'];

    COM_errorLog("Attempting to install the $pi_display_name plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$pi_name]);
    if ($ret > 0) {
        return false;
    }

    return true;
}

/**
* Automatic uninstall function for plugins
*
* @return   array
*
* This code is automatically uninstalling the plugin.
* It passes an array to the core code function that removes
* tables, groups, features and php blocks from the tables.
* Additionally, this code can perform special actions that cannot be
* foreseen by the core code (interactions with other plugins for example)
*
*/
function plugin_autouninstall_import ()
{
    $out = array (
        /* give the name of the tables, without $_TABLES[] */
        'tables' => array(),
        /* give the full name of the group, as in the db */
        'groups' => array(),
        /* give the full name of the feature, as in the db */
        'features' => array(),
        /* give the full name of the block, including 'phpblock_', etc */
        'php_blocks' => array(),
        /* give all vars with their name */
        'vars'=> array()
    );
    return $out;
}
?>