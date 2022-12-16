<?php

echo "<!DOCTYPE html>
<html>
<head>
<link rel='stylesheet' type='text/css' href='../css/style.css' />
</head>
<body>";
#$dir    = 'json';
#$dir    = 'jsonSpecific';
$dir    = 'JSON/V3';
$files1 = scandir($dir);

// remove dot files
$files2 = array_diff(scandir($dir), array('..', '.'));
echo "<div class='file-menu-container'>
<div class='file-menu'>";
  foreach ($files2 as $key => $value) {


  		$pmc=substr($value, 0, -12);
  		echo "<div class='file-menu-item' onclick=\"window.location.href='json2html.php?pmcid={$pmc}'\">{$pmc}</div>";


  }



//print_r($files2);
echo "</div></div></body>
</html>";
?>
