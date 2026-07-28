<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include "database.php";

// --- PONTO DE ENTRADA DO SCRIPT ---

// 1. Valida o ID do sensor recebido via GET
$sensor_id = filter_input(INPUT_GET, 'sensor', FILTER_VALIDATE_INT);
$hours = filter_input(INPUT_GET, 'hours', FILTER_VALIDATE_INT);
if (!$hours || $hours <= 0) {
    $hours = 3; // Janela padrão de 3 horas para diagnóstico de parada cardíaca e ruídos
}

if (!$sensor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do sensor inválido ou não fornecido.']);
    exit();
}

// 2. Busca os detalhes do reservatório
$sql_sensor = "SELECT nome, alturaSonda, fosso FROM h2o.reservatorio WHERE sensor = $sensor_id AND ativo = true LIMIT 1";
$info_sensor = DBQ($sql_sensor);

if (empty($info_sensor)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => "Sensor com ID $sensor_id não encontrado ou está inativo."]);
    exit();
}

$nome_sensor = $info_sensor[0]['nome'];
$ajuste = (double)$info_sensor[0]['alturaSonda'];

// 3. Define os períodos de consulta
$now_ts = time();
$inicio_ts = strtotime("-$hours hours");
$inicio_sql = date("Y-m-d H:i:s", $inicio_ts);
$inicio_br = date("d/m/Y H:i:s", $inicio_ts);

$uma_hora_atras_ts = strtotime('-1 hour');
$uma_hora_atras_sql = date("Y-m-d H:i:s", $uma_hora_atras_ts);

// 4. Consulta leituras na janela de diagnóstico
$sql_leituras = "SELECT * FROM h2o.leituras WHERE sensor = $sensor_id AND `timestamp` >= '$inicio_sql' ORDER BY `timestamp` ASC";
$leituras = DBQ($sql_leituras);

// 5. Busca os contatos de notificação vinculados a este sensor
$sql_contatos = "SELECT numero FROM h2o.contatos_notificacao WHERE sensor_id = $sensor_id";
$contatos_res = DBQ($sql_contatos);
$contatos = [];
if (!empty($contatos_res)) {
    foreach ($contatos_res as $c) {
        $contatos[] = $c['numero'];
    }
}

// 6. Algoritmo de Diagnóstico de Anomalias de Sonda
$total_itens = count($leituras);
$total_alerta_1h = 0;
$ruidos_count = 0;
$valores_validos = [];
$ultimo_timestamp_ts = null;

if (!empty($leituras)) {
    foreach ($leituras as $l) {
        $ts = strtotime($l['timestamp']);
        if ($ultimo_timestamp_ts === null || $ts > $ultimo_timestamp_ts) {
            $ultimo_timestamp_ts = $ts;
        }

        $val = (double)$l['Valor'] + $ajuste;

        // Filtro de ruído PRIMEIRO: ruídos não entram em alertas de nível nem em tendência
        $isRuido = ($val > 220 || $val < 2);

        if ($isRuido) {
            $ruidos_count++;
        } else {
            $valores_validos[] = $val;
            $leituras_validas[] = ['ts' => $ts, 'valor' => $val];

            // Contagem de alerta tradicional de nível alto na última hora (APENAS LEITURAS VÁLIDAS)
            if ($ts >= $uma_hora_atras_ts && $val > 75) {
                $total_alerta_1h++;
            }
        }
    }
}

$tempo_sem_comunicacao_min = $ultimo_timestamp_ts ? round(($now_ts - $ultimo_timestamp_ts) / 60) : 9999;
$total_validos = count($valores_validos);

$variacao_nivel = 0.0;
$stddev = 0.0;

if ($total_validos > 1) {
    $min_val = min($valores_validos);
    $max_val = max($valores_validos);
    $variacao_nivel = $max_val - $min_val;

    $media = array_sum($valores_validos) / $total_validos;
    $soma_quad = 0.0;
    foreach ($valores_validos as $v) {
        $soma_quad += pow($v - $media, 2);
    }
    $stddev = sqrt($soma_quad / $total_validos);
}

// Algoritmo de Tendência na Cauda da Curva
$tendencia = "DESCONHECIDO";
$descricao_tendencia = "Leituras recentes insuficientes para determinar a tendência.";

if ($total_validos >= 3) {
    $nivel_atual = $leituras_validas[$total_validos - 1]['valor'];
    $idx_anterior = max(0, $total_validos - 5);
    $nivel_anterior = $leituras_validas[$idx_anterior]['valor'];
    $delta_v = $nivel_atual - $nivel_anterior;

    if ($delta_v > 0.5) {
        $tendencia = "ENCHENDO";
        $descricao_tendencia = "Nível em elevação (+" . number_format($delta_v, 1) . " cm nas últimas leituras).";
    } elseif ($delta_v < -0.5) {
        $tendencia = "ESVAZIANDO";
        $descricao_tendencia = "Nível em queda (" . number_format($delta_v, 1) . " cm nas últimas leituras).";
    } else {
        $tendencia = "ESTAVEL";
        $descricao_tendencia = "Nível estável (variação de " . sprintf("%+.1f", $delta_v) . " cm nas últimas leituras).";
    }
}

$pct_ruido = $total_itens > 0 ? ($ruidos_count / $total_itens) * 100 : 0;

// Regras de Alerta e Sintomas
$alerta_sem_comunicacao = ($tempo_sem_comunicacao_min > 60 || $total_itens == 0);
$alerta_parada_cardiaca = ($total_validos >= 8 && !$alerta_sem_comunicacao && $variacao_nivel < 0.2);
$alerta_ruido_excessivo = ($pct_ruido > 15.0);
$alerta_nivel = ($total_alerta_1h > 0);

$detalhes = [];
if ($alerta_sem_comunicacao) {
    $detalhes[] = "Falta de comunicação: Nenhuma leitura recebida há {$tempo_sem_comunicacao_min} minutos.";
}
if ($alerta_parada_cardiaca) {
    $detalhes[] = "Sinal travado (Parada Cardíaca): O sensor enviou {$total_validos} leituras nas últimas {$hours}h sem variação de nível (variação de apenas " . number_format($variacao_nivel, 2) . " cm). Possível oxidação nos contatos da sonda.";
}
if ($alerta_ruido_excessivo) {
    $detalhes[] = "Excesso de ruídos: {$ruidos_count} das {$total_itens} leituras (" . number_format($pct_ruido, 1) . "%) apresentaram valores com ruído. Possível mau contato/oxidação na sonda.";
}
if ($alerta_nivel) {
    $detalhes[] = "Nível crítico: {$total_alerta_1h} leituras na última hora ultrapassaram o limite de 75cm.";
}

// Definição do Status de Saúde do Sensor
$status = "OK";
if ($alerta_nivel || $alerta_sem_comunicacao) {
    $status = "CRITICO";
} elseif ($alerta_parada_cardiaca || $alerta_ruido_excessivo) {
    $status = "ATENCAO";
}

// Gera a URL da imagem do gráfico das últimas 24h
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . $domainName . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/\\');
$image_url = $base_url . "/get_chart_image.php?sensor=" . $sensor_id . "&hours=24";

// Monta a resposta final mantendo retrocompatibilidade
$resposta = [
    'success' => true,
    'sensor_id' => $sensor_id,
    'nome_sensor' => $nome_sensor,
    'periodo_consultado' => $inicio_br,
    'total_itens_encontrados' => $total_itens,
    'alerta' => $total_alerta_1h,
    'chart_image_url' => $image_url,
    'contatos' => $contatos,
    'diagnostico' => [
        'status' => $status,
        'tendencia' => $tendencia,
        'descricao_tendencia' => $descricao_tendencia,
        'alerta_nivel' => $alerta_nivel,
        'alerta_parada_cardiaca' => $alerta_parada_cardiaca,
        'alerta_ruido_excessivo' => $alerta_ruido_excessivo,
        'alerta_sem_comunicacao' => $alerta_sem_comunicacao,
        'variacao_nivel_cm' => round($variacao_nivel, 2),
        'desvio_padrao' => round($stddev, 3),
        'porcentagem_ruido' => round($pct_ruido, 1),
        'tempo_sem_comunicacao_min' => $tempo_sem_comunicacao_min,
        'detalhes' => $detalhes
    ]
];

echo json_encode($resposta);
?>