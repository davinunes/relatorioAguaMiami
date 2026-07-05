<?php
header('Access-Control-Allow-Origin: *');

include "database.php";

$sensor_id = filter_input(INPUT_GET, 'sensor', FILTER_VALIDATE_INT);
if (!$sensor_id) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ID do sensor inválido ou não fornecido.";
    exit();
}

$hours = filter_input(INPUT_GET, 'hours', FILTER_VALIDATE_INT);
if (!$hours || $hours <= 0) {
    $hours = 24; // Default para 24 horas
}

// Filtro de data
$start_param = filter_input(INPUT_GET, 'start', FILTER_DEFAULT);
$end_param = filter_input(INPUT_GET, 'end', FILTER_DEFAULT);

if ($start_param) {
    $start = "'" . date("Y-m-d H:i:s", strtotime($start_param)) . "'";
} else {
    $start = "'" . date("Y-m-d H:i:s", strtotime("-$hours hours")) . "'";
}

if ($end_param) {
    $end = "'" . date("Y-m-d H:i:s", strtotime($end_param)) . "'";
} else {
    $end = "'" . date("Y-m-d H:i:s") . "'";
}

// Busca os detalhes do reservatório
$sql_caixa = "SELECT * FROM h2o.reservatorio WHERE sensor = '$sensor_id' AND ativo = true";
$caixa = DBQ($sql_caixa);

if (empty($caixa)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Sensor com ID $sensor_id não encontrado ou inativo.";
    exit();
}
$caixa = $caixa[0];
$nome = $caixa['nome'];
$ajuste = (double)$caixa['alturaSonda'];

// Busca as leituras correspondentes
$sql = "SELECT * FROM h2o.leituras l WHERE l.sensor = ".$sensor_id." AND l.`timestamp` BETWEEN $start AND $end ORDER BY l.`id` ASC";
$historico = DBQ($sql);

$seriesData = [];
$anterior = false;
$intervalo = 0;

date_default_timezone_set('America/Sao_Paulo');

foreach ($historico as $h) {
    $h['Valor'] += $ajuste;
    if ($h['Valor'] > 220 || $h['Valor'] < 2) continue;

    if ($anterior) {
        $intervalo = strtotime($h['timestamp']) - $anterior;
    }
    $anterior = strtotime($h['timestamp']);

    // Converte timestamp para milissegundos para o JavaScript (UTC)
    $timestamp_local = strtotime($h['timestamp'] . ' UTC');
    $timestamp_js = $timestamp_local * 1000; 
    $valor_plot = $h['Valor'] * -1;

    if ($intervalo > 600) { // Insere ponto nulo para criar um buraco no gráfico
        $seriesData[] = [$timestamp_js, null];
    }
    $seriesData[] = [$timestamp_js, $valor_plot];
}

// Busca a última atualização global do sensor
$sql_ultimo = "SELECT `timestamp` FROM h2o.leituras WHERE sensor = ".$sensor_id." ORDER BY id DESC LIMIT 1";
$ultimo = DBQ($sql_ultimo);
$ult_att = $ultimo ? date("d/m/Y H:i:s", strtotime($ultimo[0]['timestamp'])) : 'N/A';

// Série do ponto de referência "Now/Agora"
$now_string = $end_param ? $end_param : 'now';
$now_timestamp_js = strtotime($now_string. ' UTC') * 1000;
$yesterday_timestamp_js = strtotime($now_string . ' -24 hours'. ' UTC') * 1000;

$nowSeries = [
    'name' => 'FundoDoReservatorio',
    'data' => [
        [$yesterday_timestamp_js, -240], // Ponto de 24 horas atrás
        [$now_timestamp_js, -240]        // Ponto atual
    ],
    'marker' => [
        'enabled' => true,
        'symbol' => 'square',
        'radius' => 1,
        'fillColor' => '#000000'
    ],
    'lineWidth' => 0,
    'enableMouseTracking' => false
];

// Monta o array de opções do Highcharts
$chartOptions = [
    'chart' => [
        'type' => 'spline',
    ],
    'title' => ['text' => $nome],
    'subtitle' => ['text' => 'Ultima atualizacao: ' . $ult_att],
    'xAxis' => [
        'type' => 'datetime',
        'title' => ['text' => 'Data/Hora'],
    ],
    'yAxis' => [
        'title' => ['text' => 'centimetros'],
        'plotBands' => [
            ['from' => 0, 'to' => -30, 'color' => 'rgba(227, 22, 22, 0.1)', 'label' => ['text' => '...']],
            ['from' => -31, 'to' => -100, 'color' => 'rgba(29, 27, 22, 0.1)', 'label' => ['text' => '...']],
            ['from' => -100, 'to' => -240, 'color' => 'rgba(68, 170, 213, 0.1)', 'label' => ['text' => '...']]
        ]
    ],
    'legend' => ['enabled' => false],
    'credits' => ['enabled' => false],
    'time' => [
        'timezoneOffset' => 180 // GMT-3 (Recife/Sao Paulo)
    ],
    'series' => [
        [
            'name' => $nome,
            'data' => $seriesData,
            'color' => "rgb(067, 067, 072)"
        ],
        $nowSeries
    ]
];

// Payload para o servidor de exportação Highcharts
$post_data = json_encode([
    'infile' => $chartOptions,
    'type' => 'png',
    'width' => 800
]);

$ch = curl_init('https://export.highcharts.com/');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($post_data)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita falhas locais de certificado SSL

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
}
curl_close($ch);

if ($http_code === 200 && !empty($result)) {
    header('Content-Type: image/png');
    echo $result;
    exit();
} else {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Erro ao gerar imagem do gráfico. HTTP Code: $http_code. Curl Error: " . ($error_msg ?? 'Nenhum');
    exit();
}
?>
