<?php
/**
* glFusion CMS
*
* GL Import
*
* @license GNU General Public License version 2 or later
*     http://www.opensource.org/licenses/gpl-license.php
*
* Source from glFusion's MySQLi driver and lib-database.php
*
*
*/

namespace Import;

/**
 * Class database
 *
 * @package glFuion
 */
class database
{
    private $conn;
    private $_host;
    private $_name;
    private $_pass;
    private $_user;
    private $_prefix;
    private $dbError = 0;
    private $serverVersion;

    public function __construct()
    {
        // initialize a DB connection
        $this->_host   = SESS_getVar('dbhost');
        $this->_name   = SESS_getVar('dbname');
        $this->_pass   = SESS_getVar('dbpasswd');
        $this->_user   = SESS_getVar('dbuser');
        $this->_prefix = SESS_getVar('dbprefix');

        if (!is_callable('mysqli_connect')) {
            $this->dbErrorMsg = 'mysqli is not supported';
            $this->dbError = -1;
        }
        $this->conn = new \mysqli($this->_host,$this->_user, $this->_pass, $this->_name);

        if ($this->conn->connect_error) {
            $this->dbErrorMsg = $this->conn->connect_error;
            $this->dbError = $this->conn->connect_errno;
        }

        $this->serverVersion = $this->conn->server_version;
    }

    public function getDbPrefix()
    {
        return $this->_prefix;
    }

    public function getDbhost()
    {
        return $this->_host;
    }

    public function getDbName()
    {
        return $this->_name;
    }

    public function tableExists( $tbl_name )
    {
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = '".$this->_name."' AND table_name = '".$this->_prefix.$tbl_name."';";
        $res = $this->query($sql);
        return $this->numrows($res);
    }

    public function get_item($table,$what,$selection='')
    {
        if (!empty($selection)) {
            $sql = "SELECT $what FROM $table WHERE $selection";
            $result = $this->query($sql);
        } else {
            $sql = "SELECT $what FROM $table";
            $result = $this->query($sql);
        }
    	if ($result === NULL || $this->dbError ) {
    		return NULL;
    	} else if ($this->numrows($result) == 0) {
    		return NULL;
    	} else {
    		$ITEM = $this->fetchArray($result);
    		return $ITEM[$what];
    	}
    }

    public function getDbErrorNo( )
    {
        return $this->dbError;
    }
    public function getDbErrorString( )
    {
        return $this->dbErrorMsg;
    }

    public function close()
    {
        $this->conn->close();
        $this->conn = null;
    }

    public function query($sql)
    {
        $result = $this->conn->query($sql);

        return $result;
    }

    public function fetchArray($recordSet, $both = false)
    {
        $result_type = $both ? MYSQLI_BOTH : MYSQLI_ASSOC;

        $result = $recordSet->fetch_array($result_type);

        return ($result === null) ? false : $result;
    }

    public function numrows($recordSet)
    {
        return $recordSet->num_rows;
    }

    public function error()
    {
        return $this->conn->error;
    }

    public function getVersion()
    {
        return $this->serverVersion;
    }
}
?>