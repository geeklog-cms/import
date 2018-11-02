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

require_once '../../../lib-common.php';

if (!SEC_inGroup('Root')) {
    die('Only Root Users can run this utility');
}

if ( function_exists('set_error_handler') ) {
    $defaultErrorHandler = set_error_handler('IMP_handleError', error_reporting());
}

$dbprefix = SESS_getVar('dbprefix');

$action = '';
$expected = array('startimport','checkdb','mode');
foreach($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
    } elseif (isset($_GET[$provided])) {
	    $action = $provided;
    }
}

if ( isset($_POST['cancelbutton'])) echo COM_refresh($_CONF['site_admin_url']);

switch ($action) {
    case 'startimport' :
        $page = getDBInfo();
        break;
    case 'checkdb' :
        $page = checkDB();
        break;

    case 'mode' :
        $mode = COM_applyFilter($_POST['mode']);
        switch ( $mode ) {
            case 'import_init' :
                IMP_importInit();
                break;
            case 'import_content' :
                IMP_importContent();
                break;
            case 'import_complete' :
                IMP_importComplete();
                break;
        }
        break;
    default :
        $page = welcomePage();
        break;
}

$display = COM_siteHeader(false,'Import');
$display .= $page;
$display .= COM_siteFooter();

echo $display;

// displays the initial welcome page and instructions
function welcomePage()
{
    global $_CONF, $_TABLES, $LANG_IMP;

    $retval = '';

    $T = new Template ($_CONF['path'] . 'plugins/import/templates');
    $T->set_file (array (
        'page' => 'welcome.thtml',
    ));

    $secToken = SEC_createToken();

    $T->set_var(array(
        'lang_title'        => $LANG_IMP['admin_title'],
        'lang_instructions' => $LANG_IMP['import_instructions'],
        'lang_warning'      => $LANG_IMP['import_warning'],
        'lang_warning_alert' => $LANG_IMP['import_warning_alert'],
        'lang_continue'     => $LANG_IMP['continue'],
        'lang_cancel'       => $LANG_IMP['cancel'],
        'security_token'    => $secToken,
        'security_token_name' =>  CSRF_TOKEN,
        'action'            => 'startimport',
    ));

    $T->parse('output', 'page');
    $retval = $T->finish($T->get_var('output'));

    return $retval;
}

// displays input form to get the database information

function getDBInfo($dbname = '', $dbuser = '', $dbpasswd = '', $dbprefix = '', $errors = array() )
{
    global $_CONF, $_TABLES, $LANG_IMP;

    $retval = '';

    $T = new Template ($_CONF['path'] . 'plugins/import/templates');
    $T->set_file (array (
        'page' => 'startimport.thtml',
    ));

    $secToken = SEC_createToken();

    $T->set_var(array(
        'lang_title'        => $LANG_IMP['admin_title'],
        'lang_instructions' => $LANG_IMP['start_instructions'],

        'lang_dbuser'       => $LANG_IMP['dbuser'],
        'lang_dbname'       => $LANG_IMP['dbname'],
        'lang_dbpasswd'     => $LANG_IMP['dbpasswd'],
        'lang_dbprefix'     => $LANG_IMP['dbprefix'],
        'dbname'            => $dbname,
        'dbuser'            => $dbuser,
        'dbpasswd'          => $dbpasswd,
        'dbprefix'          => $dbprefix,
        'lang_continue'     => $LANG_IMP['continue'],
        'lang_cancel'       => $LANG_IMP['cancel'],
        'security_token'    => $secToken,
        'security_token_name' =>  CSRF_TOKEN,
        'action'            => 'checkdb',
    ));

    if (count($errors) > 0 ) {
        $T->set_var('errors',true);
        $T->set_block('page','errors','ec');
        foreach ($errors AS $error ) {
            $T->set_var('error_message',$error);
            $T->parse('ec','errors',true);
        }
    }

    $T->parse('output', 'page');
    $retval = $T->finish($T->get_var('output'));

    return $retval;
}

// validate the database settings
// ensure we can connect to the database
// ensure we can see the tables
// ensure we can query data from the users table and data exists
function checkDB()
{
    global $_CONF, $_TABLES, $LANG_IMP, $dbprefix, $_DB_host;

    $retval = '';
    $errorMessages = array();
    $errorCount = 0;
    $dbname = '';
    $dbpasswd = '';
    $dbuser = '';
    $dbprefix = '';

    if ( !isset($_POST['dbname']) ) {
        $errorMessages[] = $LANG_IMP['error_dbname_empty'];
        $errorCount++;
    } else {
        $dbname = COM_applyFilter($_POST['dbname']);
    }
    if ( !isset($_POST['dbuser']) || $_POST['dbuser'] == '' ) {
        $errorMessages[] = $LANG_IMP['error_dbuser_empty'];
        $errorCount++;
    } else {
        $dbuser = COM_applyFilter($_POST['dbuser']);
    }
    if ( !isset($_POST['dbpasswd']) || $_POST['dbpasswd'] == '' ) {
        $errorMessages[] = $LANG_IMP['error_dbpass_empty'];
        $errorCount++;
    } else {
        $dbpasswd = $_POST['dbpasswd'];
    }
    if ( isset($_POST['dbprefix']) ) {
        $dbprefix = $_POST['dbprefix'];
    }

    if ( $errorCount > 0 ) {
        return getDBInfo($dbname, $dbuser, $dbpasswd, $dbprefix, $errorMessages );
    }

    // everything has been provided - set session vars
    SESS_setVar('dbname',$dbname);
    SESS_setVar('dbpasswd',$dbpasswd);
    SESS_setVar('dbuser',$dbuser);
    SESS_setVar('dbprefix',$dbprefix);
    SESS_setVar('dbhost',$_DB_host);

    $glDB = new Import\database();

    if ( $glDB->getDbErrorNo() != 0 ) {
        $errorMessages[] = $glDB->getDbErrorString();
        return getDBInfo($dbname, $dbuser, $dbpasswd, $dbprefix, $errorMessages );
    }

    if ( ! $glDB->tableExists('users') ) {
        $errorMessages[] = $LANG_IMP['error_no_user_table'];
        return getDBInfo($dbname, $dbuser, $dbpasswd, $dbprefix, $errorMessages );
    }

    $result = $glDB->query("SELECT COUNT(*) AS total_users FROM ". $dbprefix."users");
    if ( $result == NULL ) {
        $errorMessages[] = $LANG_IMP['error_no_user_table'];
        return getDBInfo($dbname, $dbuser, $dbpasswd, $dbprefix, $errorMessages );
    }

    $row = $glDB->fetchArray($result);
    if ( $row === NULL ) {
        $errorMessages[] = $LANG_IMP['error_no_user_data'];
        return getDBInfo($dbname, $dbuser, $dbpasswd, $dbprefix, $errorMessages );
   }

    return getImportItems();
}


// the final 'processing' screen the user sees before starting import.
// this is the screen that stays up while the ajax processing is going on.
function getImportItems()
{
    global $_CONF, $_TABLES, $LANG_IMP, $glDB, $_PLUGINS;

    $glDB = new Import\database();
    $dbprefix = $glDB->getDbPrefix();

    $retval = '';

    $T = new Template ($_CONF['path'] . 'plugins/import/templates');
    $T->set_file (array (
        'page' => 'importitems.thtml',
    ));

    $secToken = SEC_createToken();

    $T->set_var(array(
        'lang_importing'        => $LANG_IMP['importing'],
        'lang_continue'         => $LANG_IMP['continue'],
        'lang_success'          => $LANG_IMP['import_success'],
        'lang_ok'               => $LANG_IMP['ok'],
        'lang_cancel'           => $LANG_IMP['cancel'],
        'lang_title'            => $LANG_IMP['admin_title'],
        'lang_instructions'     => $LANG_IMP['final_instructions'],
        'lang_import_list'      => $LANG_IMP['import_list'],
        'lang_warning'          => $LANG_IMP['final_warning'],
        'security_token'        => $secToken,
        'security_token_name'   =>  CSRF_TOKEN,
        'action'                => 'checkdb',
    ));

    // build a list of gl plugins
    $_glPlugins = array();
    $res = $glDB->query("SELECT * FROM ".$dbprefix."plugins WHERE pi_enabled=1");
    while ( $A = $glDB->fetchArray($res) ) {
        $_glPlugins[] = $A['pi_name'];
    }

    $contentList = array();
    // here is what we will convert
    $contentList[] = 'Users';
    $contentList[] = 'Core Group Assignments';
    $contentList[] = 'Topics';
    $contentList[] = 'Articles (including Images)';
    $contentList[] = 'Comments';

    if ( $glDB->tableExists('commentedits') ) {
        $contentList[] = 'Comment Edits';
    }
    $contentList[] = 'Blocks';
    $contentList[] = 'Trackbacks';

    // plugins - need to validate they exist in the gl database
    if ( in_array('staticpages',$_PLUGINS) && in_array('staticpages',$_glPlugins) && $glDB->tableExists('staticpage') ) {
        $contentList[] = 'Static Pages';
    }
    if ( in_array('calendar',$_PLUGINS) && in_array('calendar',$_glPlugins) && $glDB->tableExists('events') ) {
        $contentList[] = 'Calendar Events';
    }
    if ( in_array('calendar',$_PLUGINS) && in_array('calendar',$_glPlugins) && $glDB->tableExists('personal_events') ) {
        $contentList[] = 'Personal Calendar Events';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_albums') ) {
        $contentList[] = 'Media Gallery';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_forums') ) {
        $contentList[] = 'Forum';
    }
    if ( in_array('polls',$_PLUGINS) && in_array('polls',$_glPlugins) && $glDB->tableExists('pollanswers') ) {
        $contentList[] = 'Polls';
    }
    if ( in_array('links',$_PLUGINS) && in_array('links',$_glPlugins) && $glDB->tableExists('linkcategories') ) {
        $contentList[] = 'Links';
    }

    $T->set_block('page','contentitems','ci');
    foreach ($contentList AS $description ) {
        $T->set_var('content_item',$description);
        $T->parse('ci','contentitems',true);
    }

    $T->parse('output', 'page');
    $retval = $T->finish($T->get_var('output'));

    return $retval;
}


function connectToDatabase()
{
    $dbConn = new Import\database();

    if ( $dbConn->getDbErrorNo() != 0 ) {
        $errorMessages[] = $glDB->getDbErrorString();
        return getDBInfo($dbname, $dbuser, $dbpasswd, $dbprefix, $errorMessages );
    }
    return $dbConn;
}

// remap the error handler so we handle errors in the code
function IMP_handleError($errno, $errstr, $errfile='', $errline=0, $errcontext='')
{
    global $_CONF, $_USER, $_SYSTEM;

    return;
}

// AJAX routine
// Initialize the import
// return the content types we support
// truncate the tables to prepare for new content
function IMP_importInit()
{
    global $_CONF, $_TABLES, $LANG_IMP, $glDB, $_PLUGINS;

    if ( !COM_isAjax()) die();

    $glDB = new Import\database();
    $dbprefix = $glDB->getDbPrefix();

    // build a list of gl plugins
    $_glPlugins = array();
    $res = $glDB->query("SELECT * FROM ".$dbprefix."plugins WHERE pi_enabled=1");
    while ( $A = $glDB->fetchArray($res) ) {
        $_glPlugins[] = $A['pi_name'];
    }

    $retval = array();
    $errorCode = 0;

    $totalUsers = 0;
    $totalTopics = 0;
    $totalStories = 0;

    $result = $glDB->query("SELECT COUNT(*) AS total_users FROM ". $dbprefix."users");
    if ( $result != NULL ) {
        $row = $glDB->fetchArray($result);
        if ( $row !== NULL ) {
            $totalUsers = $row['total_users'];
        }
    }
    $result = $glDB->query("SELECT COUNT(*) AS total_topics FROM ". $dbprefix."topics");
    if ( $result != NULL ) {
        $row = $glDB->fetchArray($result);
        if ( $row !== NULL ) {
            $totalTopics = $row['total_topics'];
        }
    }
    $result = $glDB->query("SELECT COUNT(*) AS total_stories FROM ". $dbprefix."stories");
    if ( $result != NULL ) {
        $row = $glDB->fetchArray($result);
        if ( $row !== NULL ) {
            $totalStories = $row['total_stories'];
        }
    }

    $contentList = array();

    $rowCount = $totalStories + $totalUsers + $totalTopics;

    $contentList[] = 'user';
    $contentList[] = 'group_assignments';
    $contentList[] = 'topic';
    $contentList[] = 'article';
    $contentList[] = 'article_images';
    $contentList[] = 'comment';
    $contentList[] = 'commentedits';
    $contentList[] = 'block';
    $contentList[] = 'trackback';

    // plugins - need to validate they exist in the gl database
    if ( in_array('staticpages',$_PLUGINS) && in_array('staticpages',$_glPlugins) && $glDB->tableExists('staticpage') ) {
        $contentList[] = 'staticpage';
    }
    if ( in_array('calendar',$_PLUGINS) && in_array('calendar',$_glPlugins) && $glDB->tableExists('events') ) {
        $contentList[] = 'events';
    }
    if ( in_array('calendar',$_PLUGINS) && in_array('calendar',$_glPlugins) && $glDB->tableExists('personal_events') ) {
        $contentList[] = 'personal_events';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_albums') ) {
        $contentList[] = 'mg_albums';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_media') ) {
        $contentList[] = 'mg_media';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_media_albums') ) {
        $contentList[] = 'mg_media_albums';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_userprefs') ) {
        $contentList[] = 'mg_userprefs';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_watermarks') ) {
        $contentList[] = 'mg_watermarks';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_category') ) {
        $contentList[] = 'mg_category';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_rating') ) {
        $contentList[] = 'mg_rating';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_exif_tags') ) {
        $contentList[] = 'mg_exif_tags';
    }
    if ( in_array('mediagallery',$_PLUGINS) && in_array('mediagallery',$_glPlugins) && $glDB->tableExists('mg_playback_options') ) {
        $contentList[] = 'mg_playback_options';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_forums') ) {
        $contentList[] = 'forum_forums';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_topic') ) {
        $contentList[] = 'forum_topic';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_categories') ) {
        $contentList[] = 'forum_categories';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_moderators') ) {
        $contentList[] = 'forum_moderators';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_userprefs') ) {
        $contentList[] = 'forum_userprefs';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_userinfo') ) {
        $contentList[] = 'forum_userinfo';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_log') ) {
        $contentList[] = 'forum_log';
    }
    if ( in_array('forum',$_PLUGINS) && in_array('forum',$_glPlugins) && $glDB->tableExists('forum_watch') ) {
        $contentList[] = 'forum_watch';
    }
    if ( in_array('polls',$_PLUGINS) && in_array('polls',$_glPlugins) && $glDB->tableExists('pollanswers') ) {
        $contentList[] = 'pollanswers';
    }
    if ( in_array('polls',$_PLUGINS) && in_array('polls',$_glPlugins) && $glDB->tableExists('pollquestions') ) {
        $contentList[] = 'pollquestions';
    }
    if ( in_array('polls',$_PLUGINS) && in_array('polls',$_glPlugins) && $glDB->tableExists('polltopics') ) {
        $contentList[] = 'polltopics';
    }
    if ( in_array('polls',$_PLUGINS) && in_array('polls',$_glPlugins) && $glDB->tableExists('pollvoters') ) {
        $contentList[] = 'pollvoters';
    }
    if ( in_array('links',$_PLUGINS) && in_array('links',$_glPlugins) && $glDB->tableExists('linkcategories') ) {
        $contentList[] = 'linkcategories';
    }
    if ( in_array('links',$_PLUGINS) && in_array('links',$_glPlugins) && $glDB->tableExists('links') ) {
        $contentList[] = 'links';
    }

    // truncate data from glFusion tables we are importing

    DB_query("DELETE FROM {$_TABLES['users']} WHERE uid > 2",1);
    DB_query("DELETE FROM {$_TABLES['usercomment']} WHERE uid > 2",1);
    DB_query("DELETE FROM {$_TABLES['userindex']} WHERE uid > 2",1);
    DB_query("DELETE FROM {$_TABLES['userinfo']} WHERE uid > 2",1);
    DB_query("DELETE FROM {$_TABLES['userprefs']} WHERE uid > 2",1);
    DB_query("DELETE FROM {$_TABLES['group_assignments']} WHERE ug_uid != NULL AND ug_uid > 2",1);
    DB_query("TRUNCATE {$_TABLES['topics']}",1);
    DB_query("TRUNCATE {$_TABLES['stories']}",1);
    DB_query("TRUNCATE {$_TABLES['article_images']}",1);
    DB_query("TRUNCATE {$_TABLES['comments']}",1);
    DB_query("TRUNCATE {$_TABLES['commentedits']}",1);
    DB_query("TRUNCATE {$_TABLES['blocks']}",1);
    DB_query("TRUNCATE {$_TABLES['trackback']}",1);

    if (in_array('staticpage', $contentList)) {
        DB_query("TRUNCATE {$_TABLES['staticpage']}",1);
    }
    if ( in_array('events', $contentList ) ) {
        DB_query("TRUNCATE {$_TABLES['events']}",1);
        DB_query("TRUNCATE {$_TABLES['personal_events']}",1);
    }
    if ( in_array('mg_albums', $contentList ) ) {
        DB_query("TRUNCATE {$_TABLES['mg_albums']}",1);
        DB_query("TRUNCATE {$_TABLES['mg_media']}",1);
        DB_query("TRUNCATE {$_TABLES['mg_media_albums']}",1);
        DB_query("TRUNCATE {$_TABLES['mg_userprefs']}",1);
        DB_query("TRUNCATE {$_TABLES['mg_watermarks']}",1);
        DB_query("TRUNCATE {$_TABLES['mg_category']}",1);
        DB_query("TRUNCATE {$_TABLES['mg_rating']}",1);
        DB_query("TRUNCATE {$_TABLES['mg_exif_tags']}",1);
        DB_query("TRUNCATE {$_TABLES['mg_playback_options']}",1);
    }
    if ( in_array('forum_forums', $contentList ) ) {
        DB_query("TRUNCATE {$_TABLES['ff_forums']}",1);
        DB_query("TRUNCATE {$_TABLES['ff_topic']}",1);
        DB_query("TRUNCATE {$_TABLES['ff_categories']}",1);
        DB_query("TRUNCATE {$_TABLES['ff_moderators']}",1);
        DB_query("TRUNCATE {$_TABLES['ff_log']}",1);
        DB_query("TRUNCATE {$_TABLES['ff_userprefs']}",1);
        DB_query("TRUNCATE {$_TABLES['ff_userinfo']}",1);
        DB_query("DELETE FROM {$_TABLES['subscriptions']} WHERE type='forum'",1);
    }
    if ( in_array('pollanswers',$contentList ) ) {
        DB_query("TRUNCATE {$_TABLES['pollanswers']}",1);
        DB_query("TRUNCATE {$_TABLES['pollquestions']}",1);
        DB_query("TRUNCATE {$_TABLES['polltopics']}",1);
        DB_query("TRUNCATE {$_TABLES['pollvoters']}",1);
    }
    if ( in_array('linkcategories',$contentList ) ) {
        DB_query("TRUNCATE {$_TABLES['linkcategories']}",1);
        DB_query("TRUNCATE {$_TABLES['links']}",1);
    }

    $retval['errorCode'] = $errorCode;
    $retval['contentlist'] = $contentList;
    $retval['totalrows'] = $rowCount;
    $retval['statusMessage'] = 'Initialization Successful';

    $return["js"] = json_encode($retval);

    echo json_encode($return);
    exit;
}

//
// Master controller
//
function IMP_importContent()
{
    global $_CONF, $_TABLES, $LANG_IMP, $glDB;

    if ( !COM_isAjax()) die();

    if ( isset($_POST['start'] ) ) {
        $start = COM_applyFilter($_POST['start'],true);
    } else {
        $start = 0;
    }

    switch ($_POST['type'] ) {
        case 'user' :
            $rc = importContent_user('user', $start);
            break;
        case 'group_assignments' :
            $rc = importContent_group_assignments('group_assignments', $start);
            break;

        case 'topic' :
            $rc = importContent_topic('topic', $start);
            break;

        case 'article' :
            $rc = importContent_article('article', $start);
            break;

        case 'article_images' :
            $rc = importContent_article_images('article_images', $start);
            break;

        case 'comment' :
            $rc = importContent_comment('comment', $start);
            break;

        case 'commentedits' :
            $rc = importContent_commentedits('comment', $start);
            break;

        case 'block' :
            $rc = importContent_blocks('block', $start);
            break;

        case 'trackback' :
            $rc = importContent_trackback('block', $start);
            break;

        case 'staticpage' :
            $rc = importContent_staticpages('staticpage', $start);
            break;

        case 'events':
            $rc = importContent_events('events',$start);
            break;

        case 'personal_events' :
            $rc = importContent_personal_events('personal_events',$start);
            break;

        case 'mg_albums' :
            $rc = importContent_mg_albums('mg_albums',$start);
            break;

        case 'mg_media' :
            $rc = importContent_mg_media('mg_media',$start);
            break;

        case 'mg_media_albums' :
            $rc = importContent_mg_media_albums('mg_media_albums',$start);
            break;

        case 'mg_userprefs' :
            $rc = importContent_mg_userprefs('mg_userprefs',$start);
            break;

        case 'mg_watermarks' :
            $rc = importContent_mg_watermarks('mg_watermarks',$start);
            break;

        case 'mg_category' :
            $rc = importContent_mg_category('mg_category',$start);
            break;

        case 'mg_rating' :
            $rc = importContent_mg_rating('mg_rating',$start);
            break;

        case 'mg_exif_tags' :
            $rc = importContent_mg_exif_tags('mg_exif_tags',$start);
            break;

        case 'mg_playback_options' :
            $rc = importContent_mg_playback_options('mg_playback_options',$start);
            break;

        case 'forum_forums' :
            $rc = importContent_forum_forums('forum_forums',$start);
            break;

        case 'forum_topic' :
            $rc = importContent_forum_topic('forum_topic',$start);
            break;

        case 'forum_categories' :
            $rc = importContent_forum_categories('forum_categories',$start);
            break;

        case 'forum_moderators' :
            $rc = importContent_forum_moderators('forum_moderators',$start);
            break;

        case 'forum_userprefs' :
            $rc = importContent_forum_userprefs('forum_userprefs',$start);
            break;

        case 'forum_userinfo' :
            $rc = importContent_forum_userinfo('forum_userinfo',$start);
            break;

        case 'forum_log' :
            $rc = importContent_forum_log('forum_log',$start);
            break;

        case 'forum_watch' :
            $rc = importContent_forum_watch('forum_watch',$start);
            break;

        case 'pollanswers' :
            $rc = importContent_pollanswers('pollanswers',$start);
            break;

        case 'pollquestions' :
            $rc = importContent_pollquestions('pollquestions',$start);
            break;

        case 'polltopics' :
            $rc = importContent_polltopics('polltopics',$start);
            break;

        case 'pollvoters' :
            $rc = importContent_pollvoters('pollvoters',$start);
            break;

        case 'linkcategories' :
            $rc = importContent_linkcategories('linkcategories',$start);
            break;

        case 'links' :
            $rc = importContent_links('links',$start);
            break;

        default :
            $rc = array(-1,0,0);
            break;
    }

    $retval['errorCode']    = $rc[0];
    $retval['startrecord']  = $rc[2];
    $retval['processed']    = $rc[1];
    $retval['statusMessage'] = 'Import Cycle Successful';
    $return["js"] = json_encode($retval);
    echo json_encode($return);
    exit;
}

function IMP_importComplete()
{
    global $LANG_IMP;

    CTL_clearCache();

    $retval['errorCode']    = 0;
    $retval['statusMessage'] = $LANG_IMP['final_status'];
    $return["js"] = json_encode($retval);
    echo json_encode($return);
    exit;

}

// specific import for users
function importContent_user($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all users
    $result = $glDB->query("SELECT COUNT(*) AS total_users FROM " . $dbprefix."users WHERE uid > 2");
    $row = $glDB->fetchArray($result);
    $userRows = $row['total_users'];

    if ( $userRows > $max_rows ) {
        $userRows =  (int) $max_rows + 100;
    }

    $normal_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Logged-in Users'");
    $all_grp        = DB_getItem ($_TABLES['groups'], 'grp_id',"grp_name='All Users'");
    $remote_grp     = DB_getItem ($_TABLES['groups'], 'grp_id',"grp_name='Remote Users'");

    // users and userinfo
    $sql = "SELECT * FROM ".$dbprefix."users AS u LEFT JOIN ".$dbprefix."userinfo AS ui ON u.uid=ui.uid LEFT JOIN ".$dbprefix."userprefs AS up ON u.uid=up.uid WHERE u.uid > 2 ORDER BY u.uid ASC LIMIT " . $userRows . " OFFSET " . $start;
    $res = $glDB->query($sql);
    while ($A = $glDB->fetchArray($res)) {

        $uid = $A['uid'];
        if ( (int) $uid === 0 ) {
            $sessionCounter++;
            $recordCounter++;
            continue;
        }

        $user = array();
        $user['uid']                = DB_escapeString($A['uid']);
        $user['username']           = DB_escapeString($A['username']);
        $user['remoteusername']     = DB_escapeString($A['remoteusername']);
        $user['remoteservice']      = DB_escapeString($A['remoteservice']);
        $user['fullname']           = DB_escapeString($A['fullname']);
        $user['password']           = DB_escapeString('');
        $user['email']              = DB_escapeString($A['email']);
        $user['homepage']           = DB_escapeString($A['homepage']);
        $user['sig']                = DB_escapeString($A['sig']);
        $user['regdate']            = DB_escapeString($A['regdate']);
        $user['photo']              = DB_escapeString($A['photo']);
        $user['cookietimeout']      = DB_escapeString($A['cookietimeout']);
        $user['theme']              = DB_escapeString($_CONF['theme']);
        $user['language']           = DB_escapeString($A['language']);
        $user['status']             = DB_escapeString($A['status']);
        $user['account_type']       = ($A['remoteservice'] == '' ? 1 : 2);

        // user info table data
        $userinfo = array();
        $userinfo['uid']            = $uid;
        $userinfo['about']          = DB_escapeString($A['about']);
        $userinfo['location']       = DB_escapeString($A['location']);
        $userinfo['pgpkey']         = DB_escapeString($A['pgpkey']);
        $userinfo['userspace']      = DB_escapeString($A['userspace']);
        $userinfo['tokens']         = DB_escapeString($A['tokens']);
        $userinfo['totalcomments']  = DB_escapeString($A['totalcomments']);
        $userinfo['lastgranted']    = DB_escapeString($A['lastgranted']);
        $userinfo['lastlogin']      = DB_escapeString($A['lastlogin']);

        // user pref table data

        $userprefs = array();
        $userprefs['uid']               = $uid;
        $userprefs['noicons']           = $A['noicons'];
        $userprefs['willing']           = $A['willing'];
        $userprefs['dfid']              = $A['dfid'];
        $userprefs['tzid']              = ($A['tzid'] == '') ? $_CONF['timezone'] : DB_escapeString($A['tzid']);
        $userprefs['emailstories']      = $A['emailstories'];
        $userprefs['emailfromadmin']    = $A['emailfromadmin'];
        $userprefs['emailfromuser']     = $A['emailfromuser'];
        $userprefs['showonline']        = $A['showonline'];

        $user_values = implode("','",$user);
        $userinfo_values = implode("','",$userinfo);
        $userprefs_values = implode("','",$userprefs);

        $sql = "INSERT INTO {$_TABLES['users']} (uid,username,remoteusername,remoteservice,fullname,passwd,email,homepage,sig,regdate,photo,cookietimeout,theme,language,status,account_type) VALUES ('".$user_values."')";
        DB_query($sql,1);
        // set up standard group assignments
        DB_query ("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id,ug_uid) VALUES ($normal_grp, $uid)");
        DB_query ("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id,ug_uid) VALUES ($all_grp, $uid)");
        if ( $A['remoteservice'] != '' && $A['remoteservice'] != NULL ) {
            DB_query ("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id,ug_uid) VALUES ($remote_grp, $uid)");
        }

        // any default groups?
        $result = DB_query("SELECT grp_id FROM {$_TABLES['groups']} WHERE grp_default = 1");
        $num_groups = DB_numRows($result);
        for ($i = 0; $i < $num_groups; $i++) {
            list($def_grp) = DB_fetchArray($result);
            DB_query("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id, ug_uid) VALUES ($def_grp, $uid)");
        }

// user prefs

        DB_query ("INSERT INTO {$_TABLES['userprefs']} (uid,noicons,willing,dfid,tzid,emailstories,emailfromadmin,emailfromuser,showonline) VALUES ('".$userprefs_values."')");

// userindex
        if ($_CONF['emailstoriesperdefault'] == 1) {
            DB_query ("INSERT INTO {$_TABLES['userindex']} (uid,etids) VALUES ($uid,'')");
        } else {
            DB_query ("INSERT INTO {$_TABLES['userindex']} (uid,etids) VALUES ($uid, '-')");
        }

// usercomment
        DB_query ("INSERT INTO {$_TABLES['usercomment']} (uid,commentmode,commentlimit) VALUES ($uid,'{$_CONF['comment_mode']}','{$_CONF['comment_limit']}')");

// user info
        $sql = "INSERT INTO {$_TABLES['userinfo']} (uid,about,location,pgpkey,userspace,tokens,totalcomments,lastgranted,lastlogin) VALUES ('".$userinfo_values."')";
        DB_query ($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}


// specific import for users
function importContent_group_assignments($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;


    // get members for the following groups:
    // build a mapping array

    $grpNameMapping = array(
                    'Root'              => 'Root',
                    'Story Admin'       => 'Story Admin',
                    'Block Admin'       => 'Block Admin',
                    'Syndication Admin' => 'Syndication Admin',
                    'Topic Admin'       => 'Topic Admin',
                    'Webservices Users' => 'Webservices Users',
                    'User Admin'        => 'User Admin',
                    'Plugin Admin'      => 'Plugin Admin',
                    'Group Admin'       => 'Group Admin',
                    'Mail Admin'        => 'Mail Admin',
                    'Comment Admin'     => 'Comment Admin',
                    'staticpages Admin' => 'Static Page Admin',
                    'calendar Admin'    => 'Calendar Admin',
                    'forum Admin'       => 'Forum Admin',
                    'links admin'       => 'Links Admin',
                    'mediagallery Admin' => 'mediagallery Admin',
                    'polls admin'       => 'Polls Admin'
    );

    foreach ( $grpNameMapping AS $glFusion => $geeklog ) {
        $glGrpID = $glDB->get_item($dbprefix."groups",'grp_id',"grp_name='".$geeklog."'");
        $glFusionGrpID = DB_getItem($_TABLES['groups'],'grp_id',"grp_name='".$glFusion."'");
        if ( $glGrpID != NULL && $glFusionGrpID != NULL ) {
            $res = $glDB->query("SELECT ug_uid FROM ".$dbprefix."group_assignments WHERE ug_main_grp_id=".$glGrpID." AND ug_uid > 2");
            while ( $row = $glDB->fetchArray($res) ) {
                DB_query("INSERT INTO {$_TABLES['group_assignments']} (ug_main_grp_id,ug_uid) VALUES ({$glFusionGrpID},{$row['ug_uid']})",1);
            }
        }
    }
    return array(-1,$sessionCounter, $recordCounter);
}


// import topics
function importContent_topic($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;


    // get count of all topics
    $result = $glDB->query("SELECT COUNT(*) AS total_topics FROM " . $dbprefix."topics");
    $row = $glDB->fetchArray($result);
    $topicRows = $row['total_topics'];

    if ( $topicRows > $max_rows ) {
        $topicRows =  (int) $max_rows + 100;
    }

    // users and userinfo
    $sql = "SELECT * FROM ".$dbprefix."topics ORDER BY sortnum ASC LIMIT " . $topicRows . " OFFSET " . $start;

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // topic table data
        $topic = array();
        $topic['tid']               = DB_escapeString($A['tid']);
        $topic['topic']             = DB_escapeString($A['topic']);
        $topic['description']       = isset($A['meta_description']) ? DB_escapeString($A['meta_description']) : '';
        $topic['imageurl']          = DB_escapeString($A['imageurl']);
        $topic['sortnum']           = $A['sortnum'];
        $topic['limitnews']         = $A['limitnews'];
        $topic['is_default']        = $A['is_default'];
        $topic['archive_flag']      = $A['archive_flag'];
        $topic['owner_id']          = $A['owner_id'];
        $topic['group_id']          = $A['group_id'];
        $topic['perm_owner']        = $A['perm_owner'];
        $topic['perm_group']        = $A['perm_group'];
        $topic['perm_members']      = $A['perm_members'];
        $topic['perm_anon']         = $A['perm_anon'];

        $topic_values = implode("','",$topic);

        $sql = "INSERT INTO {$_TABLES['topics']} (tid,topic,description,imageurl,sortnum,limitnews,is_default,archive_flag,owner_id,group_id,perm_owner,perm_group,perm_members,perm_anon) VALUES ('".$topic_values."')";
        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import topics
function importContent_article($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_stories FROM " . $dbprefix."stories");
    $row = $glDB->fetchArray($result);
    $storyRows = $row['total_stories'];

    if ( $storyRows > $max_rows ) {
        $storyRows =  (int) $max_rows + 100;
    }

    $topicAssignmentTable = false;
    // see if the topic assignments table exists and has data
    $result = $glDB->query("SELECT COUNT(*) AS count FROM ". $dbprefix."topic_assignments");
    if ( $result == NULL ) {
        $result = $glDB->query("SELECT * FROM ". $dbprefix."stories LIMIT 1");
        if ( $result == NULL ) {
            return array(-1,$sessionCounter, $recordCounter);
        }
        $row = $glDB->fetchArray($result);
        if ( $row === NULL ) {
            return array(-1,$sessionCounter, $recordCounter);
        }
        if ( isset($row['tid'])) {
            $topicAssignmentTable = false;
        }
    } else {
        $topicAssignmentTable = true;
    }

    if ( $topicAssignmentTable == true ) {
        $sql = "SELECT * FROM ".$dbprefix."stories AS s LEFT JOIN ".$dbprefix."topic_assignments AS t ON s.sid=t.id WHERE t.tdefault=1 ORDER BY s.date ASC LIMIT " . $storyRows . " OFFSET " . $start;
    } else {
        $sql = "SELECT * FROM ".$dbprefix."stories ORDER BY date ASC LIMIT " . $storyRows . " OFFSET " . $start;
    }

    $validFields=array('sid','uid','draft_flag','tid','date','title','introtext','bodytext','hits','numemails','comments','comment_expire','trackbacks','related','featured','show_topic_icon','commentcode','trackbackcode','statuscode','expire','postmode','advanced_editor_mode','frontpage','owner_id','group_id','perm_owner','perm_group','perm_members','perm_anon');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // story table data
        $story = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $story[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $story_values   = implode("','",$story);

        $sql = "INSERT INTO {$_TABLES['stories']} ( ".$fieldset.") VALUES ('".$story_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}


// import artcile images
function importContent_article_images($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;


    // get count of all article images
    $result = $glDB->query("SELECT COUNT(*) AS total_images FROM " . $dbprefix."article_images");
    $row = $glDB->fetchArray($result);
    $imageRows = $row['total_images'];

    if ( $imageRows > $max_rows ) {
        $imageRows =  (int) $max_rows + 100;
    }

    // users and userinfo
    $sql = "SELECT * FROM ".$dbprefix."article_images LIMIT " . $imageRows . " OFFSET " . $start;

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // image table data
        $image = array();

        $image['ai_sid']    = DB_escapeString($A['ai_sid']);
        $image['ai_img_num'] = $A['ai_img_num'];
        $image['ai_filename'] = DB_escapeString($A['ai_filename']);

        $image_values = implode("','",$image);

        $sql = "INSERT INTO {$_TABLES['article_images']} (ai_sid,ai_img_num,ai_filename) VALUES ('".$image_values."')";
        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import comments
function importContent_comment($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;


    // get count of all article images
    $result = $glDB->query("SELECT COUNT(*) AS total_comments FROM " . $dbprefix."comments");
    $row = $glDB->fetchArray($result);
    $commentRows = $row['total_comments'];

    if ( $commentRows > $max_rows ) {
        $commentRows =  (int) $max_rows + 100;
    }

    // users and userinfo
    $sql = "SELECT * FROM ".$dbprefix."comments LIMIT " . $commentRows . " OFFSET " . $start;

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // comment table data
        $comment = array();

        $comment['cid']         = $A['cid'];
        $comment['type']        = $A['type'];
        $comment['sid']         = DB_escapeString($A['sid']);
        $comment['date']        = $A['date'];
        $comment['title']       = DB_escapeString($A['title']);
        $comment['comment']     = DB_escapeString($A['comment']);
        $comment['pid']         = DB_escapeString($A['pid']);
        $comment['lft']         = $A['lft'];
        $comment['rht']         = $A['rht'];
        $comment['indent']      = $A['indent'];
        $comment['name']        = isset($A['name']) ? DB_escapeString($A['name']) : '';
        $comment['uid']         = $A['uid'];
        $comment['ipaddress']   = DB_escapeString($A['ipaddress']);

        $comment_values = implode("','",$comment);

        $sql = "INSERT INTO {$_TABLES['comments']} (cid,type,sid,date,title,comment,pid,lft,rht,indent,name,uid,ipaddress) VALUES ('".$comment_values."')";
        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
// check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import comments
function importContent_commentedits($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;


    // get count of all article images
    $result = $glDB->query("SELECT COUNT(*) AS total_commentedits FROM " . $dbprefix."commentedits");
    $row = $glDB->fetchArray($result);
    $commentRows = $row['total_commentedits'];

    if ( $commentRows > $max_rows ) {
        $commentRows =  (int) $max_rows + 100;
    }

    // users and userinfo
    $sql = "SELECT * FROM ".$dbprefix."commentedits LIMIT " . $commentRows . " OFFSET " . $start;

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // comment table data
        $comment = array();

        $comment['cid']         = $A['cid'];
        $comment['uid']         = $A['uid'];
        $comment['time']        = $A['time'];

        $comment_values = implode("','",$comment);

        $sql = "INSERT INTO {$_TABLES['commentedits']} (cid,uid,time) VALUES ('".$comment_values."')";
        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}


// import topics
function importContent_blocks($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_blocks FROM " . $dbprefix."blocks");
    $row = $glDB->fetchArray($result);
    $blockRows = $row['total_blocks'];

    if ( $blockRows > $max_rows ) {
        $blockRows =  (int) $max_rows + 100;
    }

    $topicAssignmentTable = false;
    // see if the topic assignments table exists and has data
    $result = $glDB->query("SELECT COUNT(*) AS count FROM ". $dbprefix."topic_assignments");
    if ( $result == NULL ) {
        $result = $glDB->query("SELECT * FROM ". $dbprefix."stories LIMIT 1");
        if ( $result == NULL ) {
            return array(-1,$sessionCounter, $recordCounter);
        }
        $row = $glDB->fetchArray($result);
        if ( $row === NULL ) {
            return array(-1,$sessionCounter, $recordCounter);
        }
        if ( isset($row['tid'])) {
            $topicAssignmentTable = false;
        }
    } else {
        $topicAssignmentTable = true;
    }

    if ( $topicAssignmentTable == true ) {
        $sql = "SELECT *,b.type AS block_type FROM ".$dbprefix."blocks AS b LEFT JOIN ".$dbprefix."topic_assignments AS t ON b.bid=t.id  LIMIT " . $blockRows . " OFFSET " . $start;
    } else {
        $sql = "SELECT * FROM ".$dbprefix."blocks LIMIT " . $blockRows . " OFFSET " . $start;
    }

    $validFields=array('bid','is_enabled','name','block_type','title','tid','blockorder','content','allow_autotags','rdfurl','rdfupdated','rdf_last_modified','rdf_etag','rdflimit','onleft','phpblockfn','help','owner_id','group_id','perm_owner','perm_group','perm_members','perm_anon');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // block table data
        $block = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                if ($item == 'block_type' ) $item = 'type';
                $fields[] = $item;
                $block[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $block_values   = implode("','",$block);

        $sql = "INSERT INTO {$_TABLES['blocks']} ( ".$fieldset.") VALUES ('".$block_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import trackback
function importContent_trackback($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_trackbacks FROM " . $dbprefix."trackback");
    $row = $glDB->fetchArray($result);
    $trackbackRows = $row['total_trackbacks'];

    if ( $trackbackRows > $max_rows ) {
        $trackbackRows =  (int) $max_rows + 100;
    }

    $sql = "SELECT * FROM ".$dbprefix."trackback LIMIT " . $trackbackRows . " OFFSET " . $start;

    $validFields=array('cid','sid','url','title','blog','excerpt','date','type','ipaddress');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // trackback table data
        $trackback = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $trackback[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $block_values   = implode("','",$trackback);

        $sql = "INSERT INTO {$_TABLES['trackback']} ( ".$fieldset.") VALUES ('".$block_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import staticpages
function importContent_staticpages($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_pages FROM " . $dbprefix."staticpage");
    $row = $glDB->fetchArray($result);
    $pageRows = $row['total_pages'];

    if ( $pageRows > $max_rows ) {
        $pageRows =  (int) $max_rows + 100;
    }

// we need to pull the staticpages admin group and force it as the group owner
    $spa_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Staticpages Admin'");


    $topicAssignmentTable = false;
    // see if the topic assignments table exists and has data
    $result = $glDB->query("SELECT COUNT(*) AS count FROM ". $dbprefix."topic_assignments");
    if ( $result == NULL ) {
        $result = $glDB->query("SELECT * FROM ". $dbprefix."stories LIMIT 1");
        if ( $result == NULL ) {
            return array(-1,$sessionCounter, $recordCounter);
        }
        $row = $glDB->fetchArray($result);
        if ( $row === NULL ) {
            return array(-1,$sessionCounter, $recordCounter);
        }
        if ( isset($row['tid'])) {
            $topicAssignmentTable = false;
        }
    } else {
        $topicAssignmentTable = true;
    }

    if ( $topicAssignmentTable == true ) {
        $sql = "SELECT * FROM ".$dbprefix."staticpage AS s LEFT JOIN ".$dbprefix."topic_assignments AS t ON s.sp_id=t.id LIMIT " . $pageRows . " OFFSET " . $start;
    } else {
        $sql = "SELECT * FROM ".$dbprefix."staticpage LIMIT " . $pageRows . " OFFSET " . $start;
    }

//    $sql = "SELECT * FROM ".$dbprefix."staticpage LIMIT " . $pageRows . " OFFSET " . $start;

    $validFields=array('sp_id','sp_title','sp_content','sp_hits','sp_format','sp_onmenu','sp_label','commentcode','owner_id','group_id','perm_owner','perm_group','perm_members','perm_anon','sp_centerblock','sp_help','sp_tid','sp_where','sp_php','sp_nf','sp_inblock','postmode');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // page table data
        $page = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $page[$item] = DB_escapeString($value);
                if ( $item == 'owner_id') {
                    $fields[] = 'sp_uid';
                    $page['sp_uid'] = $value;
                }
                if ( $item == 'group_id' ) $page['group_id'] = $spa_grp;

            } else {
                if ( $item == 'created') {
                    $fields[] = 'sp_date';
                    $page['sp_date'] = $value;
                }
                if ( $item == 'tid' ) {
                    $fields[] = 'sp_tid';
                    if ( $value == 'homeonly' ) $value = 'none';
                    $page['sp_tid'] = DB_escapeString($value);
                }
            }
        }
        $fieldset       = implode(",",$fields);
        $page_values   = implode("','",$page);

        $sql = "INSERT INTO {$_TABLES['staticpage']} ( ".$fieldset.") VALUES ('".$page_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import staticpages
function importContent_events($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_events FROM " . $dbprefix."events");
    $row = $glDB->fetchArray($result);
    $eventRows = $row['total_events'];

    if ( $eventRows > $max_rows ) {
        $eventRows =  (int) $max_rows + 100;
    }

// we need to pull the staticpages admin group and force it as the group owner
    $event_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Calendar Admin'");

    $sql = "SELECT * FROM ".$dbprefix."events LIMIT " . $eventRows . " OFFSET " . $start;

    $validFields=array('eid','title','description','postmode','datestart','dateend','url','hits','owner_id','group_id','perm_owner','perm_group','perm_members','perm_anon','address1','address2','city','state','zipcode','allday','event_type','location','timestart','timeend');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // page table data
        $event = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $event[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $page['group_id'] = $event_grp;
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$event);

        $sql = "INSERT INTO {$_TABLES['events']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import staticpages
function importContent_personal_events($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_events FROM " . $dbprefix."personal_events");
    $row = $glDB->fetchArray($result);
    $eventRows = $row['total_events'];

    if ( $eventRows > $max_rows ) {
        $eventRows =  (int) $max_rows + 100;
    }

// we need to pull the staticpages admin group and force it as the group owner
    $event_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Calendar Admin'");

    $sql = "SELECT * FROM ".$dbprefix."personal_events LIMIT " . $eventRows . " OFFSET " . $start;

    $validFields=array('eid','title','description','postmode','datestart','dateend','url','hits','owner_id','group_id','perm_owner','perm_group','perm_members','perm_anon','uid','address1','address2','city','state','zipcode','allday','event_type','location','timestart','timeend');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // page table data
        $event = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $event[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $event['group_id'] = $event_grp;
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$event);

        $sql = "INSERT INTO {$_TABLES['personal_events']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}


// import mediagallery
function importContent_mg_albums($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_albums");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_albums LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('album_id','album_title','album_desc','album_parent','album_order','skin','hidden','podcast','mp3ribbon','album_cover','album_cover_filename','media_count','album_disk_usage','last_update','album_views','enable_album_views','album_view_type','image_skin','album_skin','display_skin','enable_comments','exif_display','enable_rating','va_playback','playback_type','usealternate','tn_attached','tnheight','tnwidth','enable_slideshow','enable_random','enable_shutterfly','enable_views','enable_keywords','enable_html','display_album_desc','enable_sort','enable_rss','enable_postcard','albums_first','allow_download','full_display','tn_size','max_image_height','max_image_width','max_filesize','display_image_size','display_rows','display_columns','valid_formats','filename_title','shopping_cart','rsschildren','wm_auto','wm_id','opacity','wm_location','album_sort_order','member_uploads','moderate','email_mod','featured','cbposition','cbpage','owner_id','group_id','mod_group_id','perm_owner','perm_group','perm_members','perm_anon');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $mg['group_id'] = $mg_grp;
                if ( $item == 'mod_group_id') $mg['mod_group_id'] = $mg_grp;
                if ( $item == 'image_skin') $mg['image_skin'] = 'mgShadow';
                if ( $item == 'album_skin') $mg['album_skin'] = 'mgAlbum';
                if ( $item == 'display_skin') $mg['display_skin'] = 'mgShadow';
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_albums']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import mediagallery
function importContent_mg_media($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_media");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_media LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('media_id','media_filename','media_original_filename','media_mime_ext','media_exif','mime_type','media_title','media_desc','media_keywords','media_time','media_views','media_comments','media_votes','media_rating','media_resolution_x','media_resolution_y','remote_media','remote_url','media_tn_attached','media_tn_image','include_ss','media_user_id','media_user_ip','media_approval','media_type','media_upload_time','media_category','media_watermarked','artist','album','genre','v100','maint');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $mg['group_id'] = $mg_grp;
                if ( $item == 'mod_group_id') $mg['mod_group_id'] = $mg_grp;
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_media']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import mediagallery
function importContent_mg_media_albums($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_media_albums");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_media_albums LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('album_id','media_id','media_order');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $mg['group_id'] = $mg_grp;
                if ( $item == 'mod_group_id') $mg['mod_group_id'] = $mg_grp;
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_media_albums']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}
// import mediagallery
function importContent_mg_userprefs($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_userprefs");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_userprefs LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('uid','active','display_rows','display_columns','mp3_player','playback_mode','tn_size','quota','member_gallery');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_userprefs']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import mediagallery
function importContent_mg_watermarks($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_watermarks");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_watermarks LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('wm_id','owner_id','filename','description');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_watermarks']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import mediagallery
function importContent_mg_category($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_category");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_category LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('cat_id','cat_name','cat_description','cat_order');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_category']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import mediagallery
function importContent_mg_rating($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_rating");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_rating LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('id','ip_address','uid','media_id','ratingdate','owner_id');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_rating']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import mediagallery
function importContent_mg_exif_tags($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_exif_tags");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_exif_tags LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('name','selected');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_exif_tags']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import mediagallery
function importContent_mg_playback_options($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."mg_playback_options");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $mg_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Mediagallery Admin'");

    $sql = "SELECT * FROM ".$dbprefix."mg_playback_options LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('media_id','option_name','option_value');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // mg table data
        $mg = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $mg[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$mg);

        $sql = "INSERT INTO {$_TABLES['mg_playback_options']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}


// import forum
function importContent_forum_forums($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."forum_forums");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the MG admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Forum Admin'");

    $sql = "SELECT * FROM ".$dbprefix."forum_forums LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('forum_order','forum_name','forum_dscp','forum_id','forum_cat','grp_id','is_hidden','no_newposts','topic_count','post_count','last_post_rec');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['ff_forums']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import forum
function importContent_forum_topic($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."forum_topic");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Forum Admin'");

    $sql = "SELECT * FROM ".$dbprefix."forum_topic LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('id','forum','pid','uid','name','date','lastupdated','last_reply_rec','email','website','subject','comment','postmode','replies','views','ip','mood','sticky','moved','locked','status');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['ff_topic']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import forum
function importContent_forum_categories($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."forum_categories");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Forum Admin'");

    $sql = "SELECT * FROM ".$dbprefix."forum_categories LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('cat_order','cat_name','cat_dscp','id');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['ff_categories']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import forum
function importContent_forum_moderators($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 10000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."forum_moderators");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Forum Admin'");

    $sql = "SELECT * FROM ".$dbprefix."forum_moderators LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('mod_id','mod_uid','mod_groupid','mod_username','mod_forum','mod_delete','mod_ban','mod_edit','mod_move','mod_stick');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
            if ( $item == 'mod_groupid' && $value != 0 ) $tableData['mod_groupid'] = $adm_grp;
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['ff_moderators']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import forum
function importContent_forum_log($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."forum_log");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Forum Admin'");

    $sql = "SELECT * FROM ".$dbprefix."forum_log LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('uid','forum','topic','time');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $event_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['ff_log']} ( ".$fieldset.") VALUES ('".$event_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import forum
function importContent_forum_userprefs($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."forum_userprefs");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Forum Admin'");

    $sql = "SELECT * FROM ".$dbprefix."forum_userprefs LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('uid','topicsperpage','postsperpage','popularlimit','messagesperpage','searchlines','viewanonposts','enablenotify','alwaysnotify','membersperpage','showiframe','notify_once','topic_order','use_wysiwyg_editor');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['ff_userprefs']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import forum
function importContent_forum_userinfo($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."forum_userinfo");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Forum Admin'");

    $sql = "SELECT * FROM ".$dbprefix."forum_userinfo LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('uid','rating','location','aim','icq','yim','msn','interests','occupation','signature');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['ff_userinfo']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// import forum
function importContent_forum_watch($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name, $LANG_GF02;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 10000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
//    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."forum_watch");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

    $fName = array();
    $tName = array();

    $dt = new Date('now',$_USER['tzid']);

    $processed = array();
    $forumAll = array();

    $sql = "SELECT id FROM {$_TABLES['ff_topic']} WHERE pid=0";
    $result = DB_query($sql);
    while ( ( $T = DB_fetchArray($result) ) != NULL ) {
        $pids[] = $T['id'];
    }

    $sql = "SELECT * FROM ".$dbprefix."forum_watch ORDER BY uid,topic_id ASC LIMIT " . $mgRows . " OFFSET " . $start;
    $res = $glDB->query($sql);

// we want to cycle through the user records

    $previousUID = -1;
    while ( $W = $glDB->fetchArray($res)) {
//        if ( $W['uid'] == 0 ) {
//            $recordCounter++;
//            $sessionCounter++;
//            continue;
//        }
        if ( $W['uid'] != $previousUID ) {
            $recordCounter++;
            $checkTimer = time();
            $elapsedTime = $checkTimer - $timerStart;
            // check timer or if we hit our maximum rows
            if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
                return array(2,$sessionCounter, $recordCounter);
            }
            $processed = array();
            $forumAll  = array();
            $previousUID = $W['uid'];
        }

        if ( !isset($fName[$W['forum_id']]) ) {
           $forum_name = _forum_watch_getForumName( (int)$W['forum_id'] );
           $fName[$W['forum_id']] = $forum_name;
        } else {
            $forum_name = $fName[$W['forum_id']];
        }

        if ( $W['topic_id'] < 0 ) {
            $searchID = abs($W['topic_id']);
        } else {
            $searchID = $W['topic_id'];
        }

        if ( $W['topic_id'] == 0 ) {
            if ( !isset($forumAll[$W['forum_id']]) ) {
                $topic_name = $LANG_GF02['msg138'];
                $sql="INSERT INTO {$_TABLES['subscriptions']} ".
                     "(type,uid,category,id,date_added,category_desc,id_desc) VALUES " .
                     "('forum',".
                     (int)$W['uid'].",'".
                     DB_escapeString($W['forum_id'])."','".
                     DB_escapeString($W['topic_id'])."','".
                     $W['date_added']."','".
                     DB_escapeString($forum_name)."','".
                     DB_escapeString($topic_name)."')";
                DB_query($sql,1);
                $processed[$W['topic_id']] = 1;
                $forumAll[$W['forum_id']] = 1;
            }
        } else if ( in_array($searchID,$pids) && !isset($processed[$W['topic_id']]) && !isset($forumAll[$W['forum_id']]) ) {
            if ( !isset($tName[$searchID]) ) {
                $topic_name = _forum_watch_getTopicSubject((int)$searchID);
                $tName[$searchID] = $topic_name;
            } else {
                $topic_name = $tName[$searchID];
            }

            $sql="INSERT INTO {$_TABLES['subscriptions']} ".
                 "(type,uid,category,id,date_added,category_desc,id_desc) VALUES " .
                 "('forum',".
                 (int)$W['uid'].",'".
                 DB_escapeString($W['forum_id'])."','".
                 DB_escapeString($W['topic_id'])."','".
                 $W['date_added']."','".
                 DB_escapeString($forum_name)."','".
                 DB_escapeString($topic_name)."')";
            DB_query($sql,1);
            $processed[$W['topic_id']] = 1;
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

function _forum_watch_getForumName( $id )
{
    global $_TABLES;

    static $forumName  = array();

    if ( isset($forumName[$id] ) ) return $forumName[$id];

    $forum_name = DB_getItem($_TABLES['ff_forums'],'forum_name','forum_id='.(int)$id);
    $forumName[$id] = $forum_name;
    return $forum_name;
}

function _forum_watch_getTopicSubject($id)
{
    global $_TABLES;

    static $topicSubject  = array();

    if ( isset($topicSubject[$id] ) ) return $topicSubject[$id];

    $topic_name = DB_getItem($_TABLES['ff_topic'],'subject','id='.(int)$id);
    $topicSubject[$id] = $topic_name;
    return $topic_name;
}


// import polls
function importContent_pollanswers($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."pollanswers");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Polls Admin'");

    $sql = "SELECT * FROM ".$dbprefix."pollanswers LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('pid','qid','aid','answer','votes','remark');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['pollanswers']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}
function importContent_pollquestions($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."pollquestions");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Polls Admin'");

    $sql = "SELECT * FROM ".$dbprefix."pollquestions LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('qid','pid','question');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['pollquestions']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}
function importContent_polltopics($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."polltopics");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Polls Admin'");

    $sql = "SELECT * FROM ".$dbprefix."polltopics LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('pid','topic','description','voters','questions','date','display','is_open','login_required','hideresults','commentcode','statuscode','owner_id','group_id','perm_owner','perm_group','perm_members','perm_anon');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $tableData['group_id'] = $adm_grp;
            } elseif ( $item == 'created' ) {
                $fields[] = 'date';
                $tableData['date'] = $value;
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['polltopics']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}
function importContent_pollvoters($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."pollvoters");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Polls Admin'");

    $sql = "SELECT * FROM ".$dbprefix."pollvoters LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('id','pid','ipaddress','uid','date');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $tableData['group_id'] = $adm_grp;
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['pollvoters']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}

// links
function importContent_linkcategories($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."linkcategories");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Links Admin'");

    $sql = "SELECT * FROM ".$dbprefix."linkcategories LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('cid','pid','category','description','tid','created','modified','owner_id','group_id','perm_owner','perm_group','perm_members','perm_anon');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $tableData['group_id'] = $adm_grp;
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['linkcategories']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}
function importContent_links($type, $start = 0)
{
    global $_CONF, $_TABLES, $_SYSTEM, $_DB_name;

    $glDB = connectToDatabase();
    $dbprefix = SESS_getVar('dbprefix');

    $recordCounter = $start;
    $timerStart    = time();
    $sessionCounter = 0;
    $timeout        = 30;

    $max_rows = 1000;

    $maxExecutionTime = @ini_get("max_execution_time");
    $timeout = min($maxExecutionTime,30);
    $timeout -= -10; // buffer
    if ( $timeout < 0 ) {
        $timeout = min($maxExecutionTime,30);
    }
    if ( $timeout > 10 ) $timeout = 10;

    // get count of all stories
    $result = $glDB->query("SELECT COUNT(*) AS total_items FROM " . $dbprefix."links");
    $row = $glDB->fetchArray($result);
    $mgRows = $row['total_items'];

    if ( $mgRows > $max_rows ) {
        $mgRows =  (int) $max_rows + 100;
    }

// we need to pull the admin group and force it as the group owner
    $adm_grp     = DB_getItem ($_TABLES['groups'], 'grp_id', "grp_name='Links Admin'");

    $sql = "SELECT * FROM ".$dbprefix."links LIMIT " . $mgRows . " OFFSET " . $start;

    $validFields=array('lid','cid','url','description','title','hits','date','owner_id');

    $res = $glDB->query($sql);

    while ($A = $glDB->fetchArray($res)) {
        // table data
        $tableData = array();
        $fields = array();
        foreach ( $A AS $item => $value ) {
            if ( in_array( $item,$validFields ) ) {
                $fields[] = $item;
                $tableData[$item] = DB_escapeString($value);
                if ( $item == 'group_id' ) $tableData['group_id'] = $adm_grp;
            }
        }
        $fieldset       = implode(",",$fields);
        $table_values   = implode("','",$tableData);

        $sql = "INSERT INTO {$_TABLES['links']} ( ".$fieldset.") VALUES ('".$table_values."')";

        DB_query($sql,1);

        $recordCounter++;

        $checkTimer = time();
        $elapsedTime = $checkTimer - $timerStart;
        // check timer or if we hit our maximum rows
        if ( $elapsedTime > $timeout || $sessionCounter > $max_rows ) {
            return array(2,$sessionCounter, $recordCounter);
        }
        $sessionCounter++;
    }
    return array(-1,$sessionCounter, $recordCounter);
}
?>