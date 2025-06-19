<?php
include "../database.php";
session_start(); // Inicia sessão para verificar permissão

// Apenas dev e admin podem deletar
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['dev', 'admin'])) {
    echo "Erro: Permissão negada.";
    exit();
}

// Protege as entradas
$sensor = DBEscape($_POST['sensor']);
// Substitui o 'T' que vem do input datetime-local por um espaço
$start = DBEscape(str_replace("T", " ", $_POST['start']));
$end = DBEscape(str_replace("T", " ", $_POST['end']));

// Query segura e mais eficiente
// Usar a coluna `timestamp` diretamente permite que o banco use índices,
// o que é muito mais rápido do que usar `date(timestamp)`.
$sql  = "DELETE FROM h2o.leituras";
$sql .= " WHERE sensor = '$sensor'";
$sql .= " AND timestamp BETWEEN '$start' AND '$end'";

if(DBExecute($sql)){
    echo "Feito! Leituras antigas foram limpas.";
} else {
    echo "Falhou ao tentar limpar as leituras.";
}
?>