<?php

define(TIME_TYPE_WEEK, "w");
define(TIME_TYPE_MONTH, "m");

/**
 *  A simple class for querying MySQL
 */
class DataAccess {
   /**
    * Private
    * $db stores a database resource
    */
   var $db;

   //! A constructor.
   /**
   * Constucts a new DataAccess object
   * @param $host string hostname for dbserver
   * @param $user string dbserver user
   * @param $pass string dbserver user password
   * @param $db string database name
   */
   function DataAccess ($host,$user,$pass,$db) {
      $this->db=mysql_connect($host,$user,$pass);
      mysql_select_db($db,$this->db);
   }

   //! An accessor
   /**
   * Fetches a query resources and stores it in a local member
   * @param $sql string the database query to run
   * @return object DataAccessResult
   */
   function & fetch($sql) {
      return new DataAccessResult($this,mysql_query($sql,$this->db));
   }

   //! An accessor
   /**
   * Returns any MySQL errors
   * @return string a MySQL error
   */
   function isError () {
      return mysql_error($this->db);
   }
}

/**
 *  Fetches MySQL database rows as objects
 */
class DataAccessResult {
   /**
    * Private
    * $da stores data access object
    */
   var $da;
   /**
    * Private
    * $query stores a query resource
    */
   var $query;

   function DataAccessResult(& $da,$query) {
      $this->da=& $da;
      $this->query=$query;
   }

   //! An accessor
   /**
   * Returns an array from query row or false if no more rows
   * @return mixed
   */
   function getRow () {
      if ( $row=mysql_fetch_array($this->query,MYSQL_ASSOC) )
      return $row;
      else
      return false;
   }

   //! An accessor
   /**
   * Returns the number of rows affected
   * @return int
   */
   function rowCount () {
      return mysql_num_rows($this->query);
   }

   //! An accessor
   /**
   * Returns false if no errors or returns a MySQL error message
   * @return mixed
   */
   function isError () {
      $error=$this->da->isError();
      if (!empty($error))
      return $error;
      else
      return false;
   }
}

/**
 *  Base class for data access objects
 */
class Dao {
   /**
    * Private
    * $da stores data access object
    */
   var $da;

   //! A constructor
   /**
   * Constructs the Dao
   * @param $da instance of the DataAccess class
   */
   function Dao ( & $da ) {
      $this->da=$da;
   }

   //! An accessor
   /**
   * For SELECT queries
   * @param $sql the query string
   * @return mixed either false if error or object DataAccessResult
   */
   function & retrieve ($sql) {
      $result=& $this->da->fetch($sql);
      if ($error=$result->isError()) {
         trigger_error($error);
         return false;
      } else {
         return $result;
      }
   }

   //! An accessor
   /**
   * For INSERT, UPDATE and DELETE queries
   * @param $sql the query string
   * @return boolean true if success
   */
   function update ($sql) {
      $result=$this->da->fetch($sql);
      if ($error=$result->isError()) {
         trigger_error($error);
         return false;
      } else {
         return true;
      }
   }
}

class UserDao extends Dao {
	function UserDao(&$da) {
		Dao::Dao($da);
	}
	
	function validate($user, $pass, $time) {
		$sql = "SELECT MD5(CONCAT(pass, '$time')) AS tmp, id FROM time_users WHERE MD5(CONCAT(pass, '$time')) = '$pass' LIMIT 1";
		return $this->retrieve($sql);
	}
	function get($id) {
      $sql = "SELECT *  FROM time_users WHERE id=$id";
      return $this->retrieve($sql);
   }
}

class UserModel
{
   var $dao;
   var $result;
   /**
   * Constructs the TimeModel
   * @param $da instance of the DataAccess class
   */
   function UserModel ( & $dao ) {
      $this->dao=& $dao;
   }

   function validate($user, $pass, $time) {
      $this->result=& $this->dao->validate($user, $pass, $time);
      $res = $this->result->getRow();
      if($res) {
         return $res['id'];
      }
      return false;
   }
   function getInfo() {
      $this->result=& $this->dao->get($_SESSION['uid']);
      $res = $this->result->getRow();
      if($res) {
         unset($res['pass']);
         return $res;
      } else {
         return false;
      }
   }
}

class SessionDao extends Dao {
	function SessionDao(&$da) {
		Dao::Dao($da);
	}
	
	function validate($SID, $IP) {
		$sql = "SELECT UNIX_TIMESTAMP(expdate) AS expdate, uid FROM time_sessions WHERE id = '$SID' AND ip='$IP'";
		return $this->retrieve($sql);
	}
   function create($SID, $UID, $IP) {
      /* First clean out expired sessions*/
      $this->update("DELETE FROM time_sessions WHERE uid = '$UID' AND expdate < NOW()");
      /* Se if user already have a session with the same IP */
      $sql = "SELECT id FROM time_sessions WHERE uid = '$UID' AND ip='$IP'";
		$res = $this->retrieve($sql);
		$res = $res->getRow();
      if(!empty($res['id'])) {
         $this->delete($res['id']);
      }
      $sql = "INSERT INTO time_sessions (`uid`, `expdate`, `ip`, `id`, `cdate`) VALUES ('$UID', TIMESTAMPADD(DAY,1, NOW()), '$IP', '$SID', NOW())";
      return $this->update($sql);
   }
	function delete($SID) {
		$sql = "DELETE FROM time_sessions WHERE id = '$SID'";
		return $this->update($sql);
	}
}

class SessionModel
{
   var $dao;
   var $result;
   /**
   * @param $da instance of the DataAccess class
   */
   function SessionModel ( & $dao ) {
      $this->dao=& $dao;
   }

   function validate($SID) {
      $this->result=& $this->dao->validate($SID, $_SERVER['REMOTE_ADDR']);
      $res = $this->result->getRow();
      if($res) {
         if($res['expdate'] > time()) {
            $_SESSION['uid'] = $res['uid'];
            return $res['uid'];
         }
         $this->dao->delete($SID);
      }
      unset($_SESSION['uid']);
      return false;
   }
   function create($uid) {
      $IP = $_SERVER['REMOTE_ADDR'];
      $sid = md5(time().$IP);
      if($this->dao->create($sid, $uid, $IP)) {
         $_SESSION['uid'] = $uid;
         return $sid;
      }
      unset($_SESSION['uid']);
      return false;
   }
}


/**
 *  Data Access Object for Log Table
 */
class TimeDao extends Dao {
   var $year = false;
   var $month = false;
   var $type = TIME_TYPE_WEEK;
   var $flex = "TIME_TO_SEC(TIMEDIFF(time_out ,time_in)) as sum";
   var $uid = false;
   //! A constructor
   /**
   * Constructs the LogDao
   * @param $da instance of the DataAccess class
   */
   function TimeDao ( & $da ) {
      $this->uid = $_SESSION['uid'];
      Dao::Dao($da);
   }

   //! An accessor
   /**
   * Gets time entries
   * @return object a result object
   */
   function & searchAll ($start=false) {
      $ext[] = "uid = ".$_SESSION['uid'];
      if ( $start ) {
         $ext[] = $this->type == TIME_TYPE_WEEK ?
            "WEEK(date, 3) = $start" :
            "MONTH(date) = $start";
      }
      if($this->year) {
         $ext[] ="YEAR(date) = ".$this->year;
      }
      $ext = "WHERE ".implode(" AND ", $ext);
      $sql="SELECT *,$this->flex FROM time_log $ext ORDER BY date ASC";
      return $this->retrieve($sql);
   }

   function setYear($year) {
      $this->year = $year;
   }

   function minMax() {
      $sql = "SELECT MIN(date) AS min, MAX(date) AS max FROM time_log WHERE uid = '".$this->uid."'";
      return $this->retrieve($sql);
   }

   function latestEntry() {
      $sql = "SELECT date, MAX(time_in) AS time_in, time_out  FROM time_log WHERE uid = '".$this->uid."' AND date = (SELECT MAX(date) FROM time_log WHERE uid = '".$this->uid."' AND date <= DATE(NOW()))";
      return $this->retrieve($sql);
   }
   
   function set($date, $intime, $outtime, $type, $lunch = "00:00") {
      $sql = "SELECT id FROM time_log WHERE date = '$date' AND uid = $this->uid";
      $r = $this->retrieve($sql);
      $data = $r->getRow();
      if(empty($data['id'])) {
         $sql = "INSERT INTO time_log (`date`, `time_in`, `time_out`, `lunch`, `type`, `uid`) VALUES ('$date', '$intime', '$outtime', '$lunch', '$type', $this->uid)";
      }
      else {
         $fields = Array();
         if(!empty($intime))  { $fields[] = "`time_in`='$intime'"; }
         if(!empty($outtime)) { $fields[] = "`time_out`='$outtime'"; }
         if(!empty($lunch))   { $fields[] = "`lunch`='$lunch'"; }
         if(!empty($type))    { $fields[] = "`type`='$type'"; }
         $args = implode($fields, ',');
         if(!empty($args)) {
            $sql = "UPDATE time_log SET $args WHERE id=$data[id]";
         }
      }
      return $this->update($sql);
   }

   function & totalRows () {
      $sql="SELECT count(*) as count FROM time_log WHERE uid = ".$this->uid;
      return $this->retrieve($sql);
   }
}

/**
 *  Modelling time data
 */
class TimeModel {
   /**
    * Private
    * $dao stores data access object
    */
   var $dao;

   /**
    * Private
    * $result stores result object
    */
   var $result;

   /**
    * Private
    * $rowCount stores number of rows returned
    */
   var $numRows;

   //! A constructor
   /**
   * Constructs the TimeModel
   * @param $da instance of the DataAccess class
   */
   function TimeModel ( & $dao ) {
      $this->dao=& $dao;
   }

   function setYear($year) {
      $this->dao->setYear($year);
   }

   function setType($type) {
      $type = strtolower($type);
      switch($type) {
         case TIME_TYPE_WEEK:
         case TIME_TYPE_MONTH:
            break;
         default:
            $type = TIME_TYPE_WEEK;
            break;
      }
      $this->dao->type = $type;
   }

   function getYear() {
      return $this->dao->year;
   }

   //! An accessor
   /**
   * Gets a paged result set
   * @param $page the page to view from result set
   * @return void
   */
   function listWeek ($week=0) {
      $this->result=& $this->dao->searchAll($week);
      $numRowsRes=$this->dao->totalRows();
      $numRow=$numRowsRes->getRow();
      $this->numRows=$numRow['count'];
   }

   function listSpan() {
      $this->result=& $this->dao->searchAll();
      $numRowsRes=$this->dao->totalRows();
      $numRow=$numRowsRes->getRow();
      $this->numRows=$numRow['count'];
   }

   function minMax() {
      $this->result = &$this->dao->minMax();
      return $this->result->getRow();

   }

   function getLatest() {
      $this->result = &$this->dao->latestEntry();
      return $this->result->getRow();      
   }
   
   function update($date, $intime, $outtime, $type, $lunch) {
      /* Check if date is valid */
      if(!strtotime($date))      { throw new Exception('Invalid date'); }
      if(!empty($intime) && !strchr($intime, ':'))   { throw new Exception('Invalid in time'); }
      if(!empty($outtime) && !strchr($outtime, ':')) { throw new Exception('Invalid out time'); }
      if(!empty($lunch) && !strchr($lunch, ':'))     { throw new Exception('Invalid lunch time'); }
      switch($type) {
         case "H":
			break;
         case "V":
            $intime  = 0;
            $outtime = 0;
    			  break;
         case "S":
		      	break;
         case "A":
            break;
         default:
            $type = false;
      }
      if($this->dao->set($date, $intime, $outtime, $type, $lunch)) {
         return true;
      } else {
         throw new Exception('Update failed!');
      }
   }
   //! An accessor
   /**
   * Returns the number of pages in result set
   * @return int
   */
   function getNumRows () {
      return $this->numRows;
   }

   //! An accessor
   /**
   * Gets a single log row by it's id
   * @param $id of the log row
   * @return void
   */
   function listLog ($id) {
      $this->result=& $this->dao->searchByID($id);
   }

   //! An accessor
   /**
   * Gets the data from a single row
   * @return array a single log row
   */
   function getLog() {
      return $this->result->getRow();
   }
}


?>