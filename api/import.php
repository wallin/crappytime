<?php

$fh = fopen("tk2.txt", "r");
$cnt = 0;
$uid = $_REQUEST['uid'];
while($timeentry = fgets($fh)) {
   $timeentry = trim($timeentry);
   $matches = null;
   if(preg_match("/(.{10})([A-Z ]) (.{5})  (.{5})  (.{5})  (.{5})/", $timeentry, $matches)) {
   }
   else if(preg_match("/(.{10})([A-Z])/", $timeentry, $matches)) {
   }
   if($matches) {
      print "0,$uid,".$matches[1].",".$matches[2].",".$matches[3].",".$matches[4]."\n";
   }
}

?>
