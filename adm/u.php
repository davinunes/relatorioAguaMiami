<?php

include "../database.php";
error_reporting(E_ERROR | E_PARSE);

$sql  = " update h2o.reservatorio ";
$sql .= " set  ";
$sql .= " alturaSonda = ".$_POST[alturaSonda];
$sql .= " ,fosso = ".$_POST[fosso];
$sql .= " ,nome =  '".$_POST[nome]."'";
$sql .= " ,ativo =  '".$_POST[ativo]."'";
$sql .= " where  ";
$sql .= " id =  ".$_POST[id];


// echo $sql;

if(DBExecute($sql)){
	echo "Feito!";
}else{
	echo "Falhou";
}

?>