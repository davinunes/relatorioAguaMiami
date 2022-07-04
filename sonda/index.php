<?php

include "../database.php";

$sql .= "INSERT INTO h2o.leituras (sensor, Valor) VALUES($_GET[sensor], $_GET[valor])";
// select * from h2o.leituras l WHERE l.sensor = 1 ORDER by l.`id` DESC limit 1
if(DBExecute($sql)){
	echo "ok";
}else{
	echo "erro";
}



?>