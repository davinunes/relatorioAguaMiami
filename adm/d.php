<?php

include "../database.php";
error_reporting(E_ERROR | E_PARSE);

$start = str_replace("T"," ",$_POST[start]);
$end = str_replace("T"," ",$_POST[end]);

$sql  = " delete";
$sql .= " from h2o.leituras";
$sql .= " where";
$sql .= " sensor = ".$_POST[sensor];
$sql .= " AND date(`timestamp`) BETWEEN date('".$start."') and date('".$end."')";

// echo $sql;

if(DBExecute($sql)){
	echo "Feito!";
}else{
	echo "Falhou";
}

?>