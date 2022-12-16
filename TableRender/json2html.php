<?php
include "Converter.php";

if (isset($_GET['pmcid'])) {
	$pmcid=$_GET['pmcid'];
	$filename="JSON/V3/".$pmcid."_tables.json";
} 
else {
    $filename="JSON/V3/PMC3634361_tables.json";
}


echo "
<!DOCTYPE html>
<html>
	<head>
		<link rel='stylesheet' type='text/css' href='../css/style.css' />
	</head>
	<body class='blank_canvas'>
		<div class='document_background'>";


$paper = new Paper($filename);
echo $paper->outputTables();
$documentRelations = $paper->relations;
$documentAnnotations = $paper->annotations;

// side panel
echo "
		<div class='side-panel'>
			<div class='side-panel-inner-content'>
				<h4>Relationships</h4>
				<ul id='document-relations'>
				</ul>
			</div>
			<div class='menu-btn' onClick='clearLock()'>Reset</div>
		</div>
	</div>
";

// store document relations client-side
$documentRelations = json_encode($documentRelations);
$documentAnnotations = json_encode($documentAnnotations);

echo "<script>var documentRelations = $documentRelations;</script>";
echo "<script>var documentAnnotations = $documentAnnotations;</script>";

$relationScript = "
<script src=\"https://code.jquery.com/jquery-3.6.1.slim.min.js\" integrity=\"sha256-w8CvhFs7iHNVUtnSP0YKEg00p9Ih13rlL9zGqvLdePA=\" crossorigin=\"anonymous\"></script>
<script src='jquery.connections.js'></script>
<script src='tablerender.js'></script>
";

echo "</div>$relationScript</body></html>";

?>
