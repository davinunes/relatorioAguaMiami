<?php
//require_once 'config.php'; // Onde estão seus DB_HOSTNAME, etc.
require_once '../database.php'; // Onde estão suas funções DBConnect, DBExecute, etc.

// 1. Limpeza e Decodificação do Log
$log_raw = $_GET['log_b64'] ?? '';
$log_clean = str_replace(['%5168', ' '], ['', '+'], $log_raw);
$log_clean = urldecode($log_clean);
$log_decoded = base64_decode($log_clean);

// 2. Preparação dos dados para o Banco
$uuid       = DBEscape($_GET['uuid'] ?? 'unknown');
$board      = DBEscape($_GET['board'] ?? 'N/A');
$site_esp   = DBEscape($_GET['site_esp'] ?? 'N/A');
$ssid       = DBEscape($_GET['ssid'] ?? 'N/A');
$sensorId   = (int)($_GET['sensorId'] ?? 0);
$version    = DBEscape($_GET['version'] ?? 'N/A');
$remote_ip  = DBEscape($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$log_final  = DBEscape($log_decoded !== false ? $log_decoded : "Erro decodificação");

// 3. Query de Inserção (Mantendo histórico)
$query = "INSERT INTO esp32_pings (
            uuid, board, site_esp, ssid, sensor_id, firmware_version, remote_ip, log_content
          ) VALUES (
            '$uuid', '$board', '$site_esp', '$ssid', $sensorId, '$version', '$remote_ip', '$log_final'
          )";

if(DBExecute($query)){
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'msg' => 'PONG']);
}