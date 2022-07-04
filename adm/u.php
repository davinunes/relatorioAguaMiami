<?php

include "../database.php";

$sql  = " update h2o.reservatorio ";
$sql .= " set  ";
$sql .= " alturaSonda = ".$_POST[alturaSonda];
$sql .= " ,fosso = ".$_POST[fosso];
$sql .= " ,nome =  '".$_POST[nome]."'";
$sql .= " where  ";
$sql .= " id =  ".$_POST[id];


// echo $sql;

if(DBExecute($sql)){
	echo "Feito!";
}else{
	echo "Falhou";
}

?>