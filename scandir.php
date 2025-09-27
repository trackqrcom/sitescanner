<?php
mysqli_report(MYSQLI_REPORT_OFF);

/*-------------------- Version 1.00 ------------------------------------//
 A php script to scan your filesystem for changes. Can be useful to discover if a website has been hacked, or searching for viruses. 
https://sitescanner.t-qr.com/

GNU General Public License v3.0

Add &nolog to url for skip writing to logfile.
Add &nomtime to skip check modified time, just filesize will be checked.

The first time this script runs it will generate a token to access the script, save it. If you need a new token simply remove token.php from the scandir folder. 

The second time the script runs it will create a new table and populate it with the list of all your files in the same directory as this script. It will also create an empty logfile in the scandir folder. 

The third time you will only see the changes in the filesystem and it will be written to the logfile. 

No logrotation yet
//-------------------------------------------------------------------------*/


//Set you database credentials  
$db = @new mysqli('localhost', 'user', 'password', 'database');

//file extentions to skip, leaave two dummies here if you dont need them. filenames.dummy1 and filenames.dummy2 will be ignored 
$extention_skip = array('dummy1','dummy2'); 

//directories to skip, leaave two dummies here if you dont need them.
// directory without '/' after will be skipped but subfolders will be scanned. e.g 'logs' 
//logs will not be scanned but it's subdirs
// directories with '/' after and it's subdirs will be ignored from scan e.g. 'administrator/cache/'     
$dir_skip = array('logs','administrator/cache/'); //  'folder' not recursive, 'folder/' recursive  

//use if you want your local time
date_default_timezone_set('Europe/Stockholm');

//A new table will be created with the name 'scandir-yourrootfoldername'
$TableBaseName = "scandir-";

//subfolders name for the script 
$scandirdir = "scandir";
//----------------------------------------------------------//


if($db->connect_errno > 0){
    die('Unable to connect to database [' . $db->connect_error . ']');}


ob_start();

$tokenfile = "token.php";

$dir_to_scan = $_SERVER['DOCUMENT_ROOT']; // /var/www/siteroothgfd; 

$pieces = explode("/", $dir_to_scan);
$lastpiece = sizeof($pieces)-1;

$table_name = $TableBaseName.$pieces[$lastpiece];  //table name scandir-[your root dir name]

$logfile = "$scandirdir/scandir-logs.php";

include "$scandirdir/functions.php";

if(isset($_GET['nomtime'])) $chktime = 0;
else $chktime = 1;

if(isset($_GET['token'])) $pwd = $_GET['token'];
else $pwd = "";


if(!is_file("$scandirdir/$tokenfile")) 
{

$passwd = randString(15);

$hash = hash('sha256',$passwd);

file_put_contents("$scandirdir/$tokenfile", "<?php $"."hash = \"$hash\"; ?>"); 

if(!is_file("$scandirdir/$tokenfile")) exit("unable to create $scandirdir/$tokenfile, please check your permissions");

echo "<a href='scandir.php?token=$passwd'>scandir.php?token=$passwd</a><br><br>";

exit ('Your token has been set, this will only show once, save the link');

}
else 
{
include "$scandirdir/$tokenfile";

$hash1 = hash('sha256',$pwd);

if($hash1 != $hash) exit('access error'); 

}


$noofdirs = sizeof($dir_skip);

$rekursive[0]=0;

for($a = 0; $a < $noofdirs ; $a++)
{
$dir_skips = "$dir_to_scan/".$dir_skip[$a];

$trimmed = rtrim($dir_skips,"/");

if(strlen($dir_skips) != strlen($trimmed)) $rekursive[$a]=1; else $rekursive[$a]=0;

$dir_skip[$a]=$trimmed;

//echo "$trimmed ".strlen($trimmed)." ".strlen($dir_skips);

}

$sql = "SHOW TABLES LIKE '$table_name';";

if($res = $db->query($sql)->fetch_assoc()) $first = 0;
		else $first = 1;


$sql = "CREATE TABLE `$table_name` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` char(255) NOT NULL,
  `typ` tinyint(11) NOT NULL,
  `time` int(11) NOT NULL,
  `fsize` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;";

if($first == 1)
{
if ($db->query($sql) === TRUE) {
  echo "Table $table_name created successfully<br>";
} else {
  echo "Error creating table: " . $conn->error;
  exit();
}

}
	

$count=0;

$sql2 = "SELECT `path`,`typ` FROM `$table_name`";

$result = $db->query($sql2);

while($row = $result->fetch_assoc())
{

$filetodel = $row['path'];
$exists=0;

if($row ['typ']==0) if(is_file($filetodel)) $exists=1;
if($row ['typ']==1) if(is_dir($filetodel)) $exists=1;

if(!$exists) {$sql = "DELETE FROM `$table_name` WHERE `$table_name`.`path` = '$filetodel' LIMIT 1";
		$db->query($sql);
		 echo "(deleted) $filetodel \n"; 
		}

//echo $row ['path']."<br>";

}



function listFolderFiles($dir){
global $db,$table_name, $first,$logfile,$dir_to_scan, $extention_skip,$dir_skip,$rekursive,$chktime;
    $ffs = scandir($dir);

    unset($ffs[array_search('.', $ffs, true)]);
    unset($ffs[array_search('..', $ffs, true)]);

    // prevent empty ordered elements
    if (count($ffs) < 1)
        return;
        
        $sea = array_search($dir,$dir_skip);
        			
        			if(is_numeric($sea)) 
        				{
        				$skip = !$rekursive[$sea];
        				}
        				else $skip = 0;
        
        

    
    foreach($ffs as $ff){
    	$filename= "$dir/$ff";
    	$ext = pathinfo($filename, PATHINFO_EXTENSION);
       	
    	if(!is_numeric(array_search($ext,$extention_skip)) AND $skip==0 AND $filename!="$dir_to_scan/$logfile") 
    		{ 
    		if($first==0)
    		{
    		$sql2 = "SELECT * FROM `$table_name` WHERE `path` = '$filename'";
		if($revent = $db->query($sql2)->fetch_assoc()) $found = 1;
		else $found = 0;
    		}
    		else $found = 0; //sök inte vid första körningen 
    		
    		if(is_file($filename)) 
    		{
    		
    		 $fsize = filesize($filename);	//echo $GLOBALS['count']."($found) $filename $fsize<br>"; 
    		 $ftime = filemtime($filename);
    		
    		if($found) {
    		//filen har ändrats 
    		$id = $revent['id'];
    		if(($revent['fsize'] != $fsize) OR (($revent['time']!=$ftime) AND $chktime))  //$chktime
    			{$sql = "UPDATE `$table_name` SET `fsize` = $fsize, `time` = $ftime WHERE `$table_name`.`id` = $id;";
    			 $db->query($sql);
    			 echo "(changed) $filename ".date("Y-m-d H:i:s", $ftime)."\n"; 
    			 }
    		    		
    			}
    		else //not found 
    			{
    			$sql = "INSERT INTO `$table_name` (`id`, `path`, `typ`, `time`, `fsize`) VALUES (NULL, '$filename', '0', '$ftime', '$fsize');";
    			$db->query($sql);
    			echo "(new) $filename ".date("Y-m-d H:i:s", $ftime)."\n"; 
    			}
    		
    		}
		//dirs     		
    		else { //echo $GLOBALS['count']." <b>$filename</b><br>"; 
    			if(!$found) 
    			{
    			$dtime = filectime($filename);
    			 $sql = "INSERT INTO `$table_name` (`id`, `path`, `typ`, `time`, `fsize`) VALUES (NULL, '$filename', '1', '$dtime', '0');";
    			 $db->query($sql);
    			 echo "(new dir) $filename ".date("Y-m-d H:i:s", $dtime)."\n"; 
    			}
    		
    			}
    		
    		$GLOBALS['count']++;
    		
    		
    		
    		}
        
        // $dir_skip  if(!is_numeric(array_search($filename,$dir_skip)))
        
        
        if(is_dir($filename)) {
        			$sea = array_search($filename,$dir_skip);
        			
        			if(is_numeric($sea)) 
        				{
        				$skip = $rekursive[$sea];
        				}
        				else $skip = 0;
        			
        			if(!$skip) listFolderFiles($filename); 
        
        			}
        
        
    }
    
}

listFolderFiles($dir_to_scan);


$out2 = ob_get_contents();

ob_end_clean();

echo nl2br($out2);


if(!is_file($logfile)) file_put_contents($logfile, "<?php die('Forbidden.'); ?>"); 

if(!$first AND !isset($_GET['nolog']))
{

$now = date("Y-m-d H:i:s",time());

$myfile = fopen($logfile, "a") or die("Unable to open file!");
fwrite($myfile, "\n $now (table_name: $table_name) new scan:\n". $out2);
fclose($myfile);
}


	
	


?>
