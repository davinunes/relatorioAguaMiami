<?php
// adm/c.php - Script para Criar Sensores
include "../database.php";
session_start();

// Apenas dev e admin podem criar sensores
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['dev', 'admin'])) {
    echo "Erro: Permissão negada.";
    exit();
}

// Validação básica dos dados recebidos
if (empty($_POST['sensor_id']) || empty($_POST['nome_sensor'])) {
    echo "Erro: ID do Sensor e Nome são campos obrigatórios.";
    exit();
}

// Protege todas as entradas contra SQL Injection
$sensor_id = DBEscape($_POST['sensor_id']);
$nome = DBEscape($_POST['nome_sensor']);
// Usa o operador de coalescência nula para definir 0 se o valor for vazio
$fosso = DBEscape($_POST['fosso'] ?? 0); 
$alturaSonda = DBEscape($_POST['altura_sonda'] ?? 0);
$ativo = 1; // Sensor já começa ativo por padrão

// Constrói a query de forma segura
$sql  = "INSERT INTO h2o.reservatorio (sensor, nome, fosso, alturaSonda, ativo) ";
$sql .= "VALUES ('$sensor_id', '$nome', '$fosso', '$alturaSonda', '$ativo')";

if(DBExecute($sql)){
    echo "Sensor criado com sucesso!";
} else {
    // Tenta capturar um erro comum de ID duplicado
    if (mysqli_errno(DBConnect()) == 1062) {
        echo "Falhou: O ID do Sensor '$sensor_id' já existe.";
    } else {
        echo "Falhou ao tentar criar o sensor.";
    }
}
?>