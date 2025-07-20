<?php
session_start();
header('Content-Type: application/json'); // Sempre retorne JSON!

// Proteção: só permite usuários logados
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit();
}

include "database.php"; // Sua conexão com o banco

// Suas funções de ajuda que `historico` precisa
// Copie as funções dump(), limpaRuido(), isRuidoContraVizinho() para cá.

// ... (cole as funções limpaRuido e isRuidoContraVizinho aqui) ...

/**
 * Função principal que busca e formata os dados para UM gráfico.
 * Agora ela retorna um array em vez de imprimir HTML/JS.
 */
function getChartData($sensor_id, $fosso, $nome, $start, $end, $ajuste) {
    global $cor; // Cores que você definiu

    // ... (o início da sua função historico original) ...
	$sql = "select * from h2o.leituras l WHERE l.sensor = ".$sensor_id." and l.`timestamp` BETWEEN $start AND $end ORDER by l.`id` asc";
    $historico = DBQ($sql);
    
    // ... (lógica de `limpaRuido` se desejar usá-la) ...
    // $dados_para_grafico = limpaRuido($historico, ...);
    $dados_para_grafico = $historico;

    if (empty($dados_para_grafico)) {
        return ['success' => false, 'message' => "Nenhum dado de leitura encontrado para '<strong>".htmlspecialchars($nome)."</strong>' no período selecionado."];
    }
    
    $seriesData = [];
    $anterior = false;
    $intervalo = 0;
	
	date_default_timezone_set('America/Sao_Paulo');

    foreach ($dados_para_grafico as $h) {
        $h['Valor'] += $ajuste;
        if ($h['Valor'] > 220 || $h['Valor'] < 2) continue;

        if ($anterior) {
            $intervalo = strtotime($h['timestamp']) - $anterior;
        }
        $anterior = strtotime($h['timestamp']);

        // Converte timestamp para milissegundos para o JavaScript (Date.UTC espera isso)
		$timestamp_local = strtotime($h['timestamp'] . ' UTC');
        $timestamp_js = $timestamp_local * 1000; 
        $valor_plot = $h['Valor'] * -1;

        if ($intervalo > 600) { // Insere ponto nulo para criar um buraco no gráfico
            $seriesData[] = [$timestamp_js, null];
        }
        $seriesData[] = [$timestamp_js, $valor_plot];
    }

    // Pega a última leitura para o subtítulo
    $sql_ultimo = "select `timestamp` from h2o.leituras WHERE sensor = ".$sensor_id." ORDER by id desc limit 1";
    $ultimo = DBQ($sql_ultimo);
    $ult_att = $ultimo ? date("d/m/Y H:i:s", strtotime($ultimo[0]['timestamp'])) : 'N/A';
	
	// ===================================================================
    // ADIÇÃO DA LÓGICA DO PONTO "NOW"
    // ===================================================================
    // Define o timestamp para o ponto de referência. Usa a data final do filtro se existir, senão usa agora.
    $now_string = !empty($_GET['end']) ? $_GET['end'] : 'now';
    $now_timestamp_js = strtotime($now_string. ' UTC') * 1000;

    // A série de dados para o ponto de referência
    $nowSeries = [
        'name' => 'now',
        'data' => [
            [$now_timestamp_js, -200] // Usando -200 como você mencionou. O original era -180. Ajuste conforme necessário.
        ],
        'marker' => [ // Opções para customizar o marcador
             'enabled' => true,
             'symbol' => 'circle',
             'radius' => 1,
             'fillColor' => '#FF0000' // Vermelho para destacar
        ],
        'lineWidth' => 0, // Sem linha, apenas o ponto
        'enableMouseTracking' => false // Para não mostrar tooltip
    ];
    // ===================================================================
    // FIM DA ADIÇÃO
    // ===================================================================

    // Monta o array de opções do Highcharts
    $chartOptions = [
        'chart' => [
            'type' => 'spline',
            'zoomType' => 'x',
            'panning' => ['enabled' => true, 'type' => 'x'],
            'panKey' => 'shift'
        ],
        'title' => ['text' => $nome],
        'subtitle' => ['text' => 'Ultima atualização: ' . $ult_att],
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
        'tooltip' => ['shared' => true],
        'exporting' => ['enabled' => true],
        'series' => [ // <--- O array de séries agora contém DOIS elementos
            [
                'name' => $nome,
                'data' => $seriesData,
                'color' => "rgb(067, 067, 072)"
            ],
            $nowSeries // Adicionando a segunda série aqui
		]
    ];

    return ['success' => true, 'chartOptions' => $chartOptions];
}

// --- PONTO DE ENTRADA DO SCRIPT ---

$sensor_id = filter_input(INPUT_GET, 'sensor', FILTER_VALIDATE_INT);
if (!$sensor_id) {
    echo json_encode(['success' => false, 'message' => 'ID do sensor inválido.']);
    exit();
}

// Lógica de datas (similar à original)
if (!empty($_GET['start'])) {
    $start = "'" . date("Y-m-d H:i:s", strtotime($_GET['start'])) . "'";
} else {
    $start = "'" . date("Y-m-d H:i:s", strtotime('-1 day')) . "'";
}

if (!empty($_GET['end'])) {
    $end = "'" . date("Y-m-d H:i:s", strtotime($_GET['end'])) . "'";
} else {
    $end = "'" . date("Y-m-d H:i:s") . "'";
}

// Busca os detalhes do reservatório para ter o nome, fosso, etc.
$sql_caixa = "SELECT * FROM h2o.reservatorio WHERE sensor = '$sensor_id' AND ativo = true";
$caixa = DBQ($sql_caixa);

if (empty($caixa)) {
    echo json_encode(['success' => false, 'message' => 'Sensor não encontrado ou inativo.']);
    exit();
}
$caixa = $caixa[0];

// Chama a função para obter os dados e imprime o resultado em JSON
$result = getChartData($sensor_id, $caixa['fosso'], $caixa['nome'], $start, $end, $caixa['alturaSonda']);
echo json_encode($result);
?>