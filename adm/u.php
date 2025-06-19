<?php
include "../database.php";
session_start(); // Inicia sessão para verificar permissão

// Apenas dev e admin podem fazer updates
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['dev', 'admin'])) {
    echo "Erro: Permissão negada.";
    exit();
}

// Protege todas as entradas contra SQL Injection usando sua função
$id = DBEscape($_POST['id']);
$alturaSonda = DBEscape($_POST['alturaSonda']);
$fosso = DBEscape($_POST['fosso']);
$nome = DBEscape($_POST['nome']);
$ativo = DBEscape($_POST['ativo']);

// Constrói a query de forma segura
$sql  = "UPDATE h2o.reservatorio SET ";
$sql .= " alturaSonda = '$alturaSonda',";
$sql .= " fosso = '$fosso',";
$sql .= " nome = '$nome',";
$sql .= " ativo = '$ativo'";
$sql .= " WHERE id = '$id'";

if(DBExecute($sql)){
    echo "Feito! Sensor atualizado com sucesso.";
} else {
    echo "Falhou ao tentar atualizar o sensor.";
}
?>