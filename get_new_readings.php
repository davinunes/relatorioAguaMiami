<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Proteção: só permite usuários logados
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit();
}

include "database.php";

$sensor_id = filter_input(INPUT_GET, 'sensor', FILTER_VALIDATE_INT);
$since_seconds = filter_input(INPUT_GET, 'since', FILTER_VALIDATE_INT);

if (!$sensor_id || !$since_seconds) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit();
}

// Converte o timestamp Unix (segundos) de volta para o formato do banco (usando UTC/GMT
// já que no get_chart_data.php a data do banco foi interpretada com sufixo UTC).
$since_sql = gmdate("Y-m-d H:i:s", $since_seconds);

// Busca os detalhes do reservatório para ter a alturaSonda/ajuste
$sql_caixa = "SELECT * FROM h2o.reservatorio WHERE sensor = '$sensor_id' AND ativo = true";
$caixa = DBQ($sql_caixa);

if (empty($caixa)) {
    echo json_encode(['success' => false, 'message' => 'Sensor não encontrado ou inativo.']);
    exit();
}
$caixa = $caixa[0];
$ajuste = (double)$caixa['alturaSonda'];

// Busca apenas leituras estritamente maiores que o timestamp fornecido
$sql = "SELECT * FROM h2o.leituras WHERE sensor = $sensor_id AND `timestamp` > '$since_sql' ORDER BY id ASC";
$leituras = DBQ($sql);

$newPoints = [];
date_default_timezone_set('America/Sao_Paulo');

foreach ($leituras as $h) {
    $h['Valor'] += $ajuste;
    if ($h['Valor'] > 220 || $h['Valor'] < 2) continue;

    $timestamp_local = strtotime($h['timestamp'] . ' UTC');
    $timestamp_js = $timestamp_local * 1000;
    $valor_plot = $h['Valor'] * -1;

    $newPoints[] = [
        'timestamp_js' => $timestamp_js,
        'valor_plot' => $valor_plot
    ];
}

// Busca a última atualização global do sensor para subtítulo do gráfico
$sql_ultimo = "SELECT `timestamp` FROM h2o.leituras WHERE sensor = $sensor_id ORDER BY id DESC LIMIT 1";
$ultimo = DBQ($sql_ultimo);
$ult_att = $ultimo ? date("d/m/Y H:i:s", strtotime($ultimo[0]['timestamp'])) : 'N/A';

echo json_encode([
    'success' => true,
    'newPoints' => $newPoints,
    'ult_att' => $ult_att
]);
?>
