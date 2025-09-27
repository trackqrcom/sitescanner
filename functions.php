<?php

function randString($length)
{

$charset='ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz';


    $str = '';
    $count = strlen($charset);
    while ($length--) {
	if($length==($orgl-1) || $length==0) $str .= $charset[mt_rand(1, $count-1)];
        else $str .= $charset[mt_rand(0, $count-1)];
    }
    return $str;
}

?>
