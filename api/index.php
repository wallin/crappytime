<?php
// SCHEMA:
// --
// --  `time_log`
// --
//
// CREATE TABLE IF NOT EXISTS `time_log` (
//   `id` int(10) unsigned NOT NULL auto_increment,
//   `uid` int(10) unsigned NOT NULL,
//   `date` date NOT NULL,
//   `type` tinytext NOT NULL,
//   `time_in` time NOT NULL,
//   `time_out` time NOT NULL,
//   `lunch` time default NULL,
//   `comment` mediumtext NOT NULL,
//   PRIMARY KEY  (`id`)
// ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1962 ;
//
// -- --------------------------------------------------------
//
// --
// --  `time_sessions`
// --
//
// CREATE TABLE IF NOT EXISTS `time_sessions` (
//   `uid` int(10) unsigned NOT NULL,
//   `expdate` datetime NOT NULL,
//   `ip` varchar(32) NOT NULL,
//   `id` varchar(32) NOT NULL,
//   `cdate` datetime NOT NULL,
//   PRIMARY KEY  (`id`)
// ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
//
// -- --------------------------------------------------------
//
// --
// --  `time_users`
// --
//
// CREATE TABLE IF NOT EXISTS `time_users` (
//   `id` int(10) unsigned NOT NULL auto_increment,
//   `user` tinytext NOT NULL,
//   `pass` varchar(32) NOT NULL,
//   `name` tinytext NOT NULL,
//   `lunch` time NOT NULL,
//   `target` time NOT NULL default '08:00:00',
//   PRIMARY KEY  (`id`)
// ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;
//

session_start();
include 'classes.php';

ob_start();
// DataAccess($host,$user,$pass,$db)
$da = new DataAccess("", "", "", "");

$sdao = new SessionDao($da);
$smod = new SessionModel($sdao);


class Commands {
   var $query   = false;
   var $sid     = false;
   /* Login */
   var $user    = false;
   var $pass    = false;
   var $tstamp  = false;
   /* Time */
   var $year    = false;
   var $period  = false;
   var $ptype   = false;
   /* Update */
   var $date    = false;
   var $intime  = false;
   var $outtime = false;
   var $type    = false;
   function Commands() {
      $this->query   = $_REQUEST['q'];
      $this->sid     = $_REQUEST['sid'];
      $this->user    = $_REQUEST['lu'];
      $this->pass    = $_REQUEST['lp'];
      $this->tstamp  = $_REQUEST['ts'];
      $this->year    = $_REQUEST['y'];
      $this->period  = $_REQUEST['p'];
      $this->ptype   = $_REQUEST['pt'];
      $this->date    = $_REQUEST['ud'];
      $this->intime  = $_REQUEST['ui'];
      $this->outtime = $_REQUEST['uo'];
      $this->lunch   = $_REQUEST['ul'];
      $this->type    = $_REQUEST['ut'];
   }
}

$c = new Commands();


if(empty($c->query)) {
   include('api.html');
   die();
}

$result = Array();
$error = false;
$message = "";

/* Actions that do not require login */
switch($c->query) {
   case "login":
      $user = $c->user;
      $pass = $c->pass;
      $time = $c->tstamp;
      if(empty($user) || empty($pass) || empty($time)) {
         $error = true;
         $message = "Invalid arguments";
         break;
      }
      $udao = new UserDao($da);
      $umod = new UserModel($udao);
      $uid = $umod->validate($user, $pass, $time);
      if(!$uid) {
         $error = false;
         $message = "Invalid user or pass";
      }
      else {
         $result = Array("sid" => $smod->create($uid));
         $message = "Login OK";
      }
      send_response($result, $error, $message);
      break;
   case "validate":
      if($smod->validate($c->sid)) {
         $error   = false;
         $result = Array("sid" => $c->sid);
      }
      else {
         $error   = true;
         $message = "Please login again";
      }
      send_response($result, $error, $message);
      break;
}

/* Actions that require login */
if($smod->validate($c->sid)) {
   $dao = new TimeDao($da);
   $time = new TimeModel($dao);

   if($c->year) {
      $time->setYear($c->year);
   }
   if($c->ptype) {
      $time->setType($c->ptype);
   }
   switch($c->query) {
      case "summary":
         handle_summary($result, $time, $c->ptype);
         if(empty($result)) { $error = true; $message = "No data found"; }
         break;
      case "period":
         $time->listWeek(intval($c->period));
         $data = Array();
         while($entry = $time->getLog()) {
            $data[] = $entry;
         }
         $w = new period($c->ptype, $c->period, $c->year, $data);
         $w->fullWeek($result);
         break;
      case "info":
         $result = $time->minMax();
         break;
      case "update":
         try {
            $time->update($c->date, $c->intime, $c->outtime, $c->type, $c->lunch);
         } catch(Exception $e) {
            $error = true;
            $message = $e->getMessage();
         }
         break;
      case "settings":
         $udao = new UserDao($da);
         $umod = new UserModel($udao);
         $ltime = $time->getLatest();
         $result['today']['int'] = $ltime['time_in'] ? ts2min($ltime['time_in']) : 0;
         $result['today']['out'] = $ltime['time_out'] ? ts2min($ltime['time_out']) : 0;
         if($res = $umod->getInfo()) {
            $result['name'] = $res['name'];
            $result['lunch'] = ts2min($res['lunch']);
            $result['target'] = ts2min($res['target']);
         }
         break;
      default:
         $error = true;
         $message = "Nothing to do";
   }
} else {
   $error = true;
   $message = "action requires login";
}

send_response($result, $error, $message);


/*****************************************************************************/

function ts2min($string) {
   $p = explode(":", $string);
   return intval($p[0])*60 + intval($p[1]);
}

function send_response($result, $error, $message) {
   $response = Array("data" => $result, "error" => $error, "message" => $message);
   if($_REQUEST['callback']) {
      print $_REQUEST['callback']."(".json_encode($response).")";
   } else {
      print json_encode($response);
   }
   die();
}

/*
 * Translate period type to PHP date compatible string
 */
function check_type($type) {
   $type = strtolower($type);
   switch(strtolower($type)) {
      case "m":
         break;
      case "w":
         $type = "W";
         break;
      default:
         $type = "W";
   }
   return $type;
}

/*
 * Week Summary
 */

function handle_summary(&$result, &$time, $type) {
   global $c;
   $time->listSpan();
   $weekdata = false;
   $type = check_type($type);
   $currentweek = 0;
   while($entry = $time->getLog()) {
      $week = date($type,strtotime($entry['date']));
      if($currentweek != $week) {
         $w = false;
         if($weekdata) {
            $w = new period($c->ptype, $currentweek, $time->getYear(), $weekdata);
            $cw = Array(
               "p" => $currentweek,
               "s" => $w->startDay(),
               "e" => $w->endDay(),
               "h" => $w->worktime(),
               "f" => $w->flextime(),
               "v" => $w->vacation(),
               "l" => $w->sickdays()
               );
            $result[] = $cw;
         }
         $weekdata = Array();
         $currentweek = $week;
      }
      $weekdata[] = $entry;
   }
   $w = new period($c->ptype, $currentweek, $c->year, $weekdata);
   $cw = Array(
     "p" => $currentweek,
     "s" => $w->startDay(),
     "e" => $w->endDay(),
     "h" => $w->worktime(),
     "f" => $w->flextime(),
     "v" => $w->vacation(),
     "l" => $w->sickdays()
     );
   $result[] = $cw;
}


/* Class for describing period data (week or month)
 * Takes data from database and applies to period time
 */
class period {
   var $data = false;
   var $vac = 0;
   var $time = 0;
   var $workdays = 0;
   var $sickdays = 0;
   var $holidays = 0;
   function period($type, $number, $year,&$data) {

      /* Initialize a new period */
      $format = $type == TIME_TYPE_MONTH ? $year."-".$number."-" : $year."W".$number."-";
      $end = $type == TIME_TYPE_MONTH ? cal_days_in_month(CAL_GREGORIAN, $number, $year) : 7;
      for($day=1; $day<=$end; $day++)
      {
         $ts = strtotime($format.$day);
         $date = date('Y-m-d', $ts);
         if(substr($date, 0, 4) != $year) { continue;  }
         $this->data[$date]['time_in'] = "00:00";
         $this->data[$date]['time_out'] = "00:00";
         $this->data[$date]['sum'] = 0;
         $this->data[$date]['date'] = $date;
         if(date("N", $ts) > 5) {
            $this->data[$date]['type'] = 'H';
         } else {
            $this->data[$date]['type'] = '';
            /* Dont count future as workdays */
            if($ts < time()) {
               $this->workdays++;
            }
         }
      }
      /* Fit given data into period */
      foreach($data as $entry) {
         if(empty($this->data[$entry['date']])) { continue; }
         $entry['sum'] = $entry['sum']/60;
         switch($entry['type']) {
            case "V":
               $this->vac++;
               $this->workdays--;
               break;
            case "S":
               $this->sickdays++;
               $this->workdays--;
               break;
            case "H":
               $this->holidays++;
               $this->workdays--;
               break;
         }
         if(intval(substr($entry['time_out'], 0, 2)) > 12) {
            $entry['sum']-=35;
         }
         if($entry['sum'] < 0) { $entry['sum'] = 0; }
         if($this->workdays < 0) { $this->workdays = 0; }
         $this->time += $entry['sum'];
         $this->data[$entry['date']] = $entry;
      }
   }
   function fullWeek(&$rv) {
      foreach($this->data as $entry) {
         $rv[] = Array(
            "d" => $entry["date"],
            "t" => $entry["type"],
            "i" => $entry["time_in"],
            "o" => $entry["time_out"],
            "s" => $entry["sum"]
         );
      }
      return true; //array_values($this->data);
   }
   function worktime() {
      return $this->time;
   }
   function flextime() {
      $ftime = ($this->time) - ($this->workdays * (8*60));
      return $ftime;
   }
   function startDay() {
      //rewind($this->data);
      $tmp = current($this->data);
      return date("j M", strtotime($tmp['date']));
   }

   function endDay() {
      end($this->data);
      $tmp = current($this->data);
      //rewind($this->data);
      return date("j M", strtotime($tmp['date']));
   }
   function vacation() {
      return $this->vac;
   }
   function sickdays() {
      return $this->sickdays;
   }
}
?>
