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
function getChartData($sensor_id, $fosso, $nome, $start, $end, $ajuste, $debug = false, $shadow = false, $valor_referencia = null, $tipo = 'agua') {
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
    $ruidos = [];
    $anterior = false;
    $intervalo = 0;
	
	date_default_timezone_set('America/Sao_Paulo');

    foreach ($dados_para_grafico as $h) {
        if ($tipo === 'raw') {
            // Modo RAW: valor direto do banco somado ao ajuste, sem inversão de sinal nem filtro de poço
            $valor_plot = (double)$h['Valor'] + $ajuste;
        } else {
            // Modo ÁGUA (Padrão): soma ajuste, filtra ruídos de 2 a 220 cm e inverte o sinal
            $h['Valor'] += $ajuste;
            $isRuido = ($h['Valor'] > 220 || $h['Valor'] < 2);

            if ($isRuido && !$debug) {
                $timestamp_local = strtotime($h['timestamp'] . ' UTC');
                $timestamp_js = $timestamp_local * 1000;
                $ruidos[] = [
                    'id' => $h['id'],
                    'timestamp' => $h['timestamp'],
                    'timestamp_js' => $timestamp_js,
                    'valor' => $h['Valor'],
                    'valor_plot' => $h['Valor'] * -1
                ];
                continue;
            }
            $valor_plot = $h['Valor'] * -1;
        }

        if ($anterior) {
            $intervalo = strtotime($h['timestamp']) - $anterior;
        }
        $anterior = strtotime($h['timestamp']);

        // Converte timestamp para milissegundos para o JavaScript (Date.UTC espera isso)
		$timestamp_local = strtotime($h['timestamp'] . ' UTC');
        $timestamp_js = $timestamp_local * 1000; 

        if ($intervalo > 1200) { // Insere ponto nulo para criar um buraco no gráfico se o intervalo for maior que 20 minutos (1200 segundos)
            $seriesData[] = [$timestamp_js, null];
        }
        $seriesData[] = [$timestamp_js, $valor_plot];
    }

    // Pega a última leitura para o subtítulo usando o índice idx_sensor_timestamp
    $sql_ultimo = "SELECT `timestamp` FROM h2o.leituras WHERE sensor = ".$sensor_id." ORDER BY `timestamp` DESC LIMIT 1";
    $ultimo = DBQ($sql_ultimo);
    $ult_att = $ultimo ? date("d/m/Y H:i:s", strtotime($ultimo[0]['timestamp'])) : 'N/A';
	
    $seriesList = [
        [
            'name' => $nome,
            'data' => $seriesData,
            'color' => "rgb(067, 067, 072)"
        ]
    ];

    $yAxisOptions = [
        'title' => ['text' => ($tipo === 'raw' ? 'Valor' : 'centimetros')]
    ];

    if ($tipo === 'agua') {
        // ===================================================================
        // LÓGICA ESPECÍFICA DE RESERVATÓRIO DE ÁGUA
        // ===================================================================
        $now_string = !empty($_GET['end']) ? $_GET['end'] : 'now';
        $now_timestamp_js = strtotime($now_string. ' UTC') * 1000;
        $yesterday_timestamp_js = strtotime($now_string . ' -24 hours'. ' UTC') * 1000;

        // Obtém o valor de comparação para definir as faixas de nível (Ponto de Virada)
        if ($valor_referencia !== null) {
            $valor_plot_comp = $valor_referencia * -1;
        } else {
            $valor_comparacao = get_sensor_comparison_value($sensor_id);
            $valor_plot_comp = ($valor_comparacao !== null) ? ($valor_comparacao + $ajuste) * -1 : -35;
        }

        $plotBands = [
            [
                'from' => $valor_plot_comp, 
                'to' => 0, 
                'color' => 'rgba(68, 170, 213, 0.1)', // Azul - Zona de Operação Normal (Suave)
                'label' => ['text' => 'Normal', 'style' => ['color' => '#44AAD5']]
            ],
            [
                'from' => -100, 
                'to' => $valor_plot_comp, 
                'color' => 'rgba(255, 165, 0, 0.1)', // Laranja - Alerta/Baixando
                'label' => ['text' => 'Atenção', 'style' => ['color' => '#FFA500']]
            ],
            [
                'from' => -240, 
                'to' => -100, 
                'color' => 'rgba(227, 22, 22, 0.1)', // Vermelho - Crítico (Vazio)
                'label' => ['text' => 'Crítico', 'style' => ['color' => '#E31616']]
            ]
        ];

        // A série de dados para o ponto de referência do fundo do reservatório
        $nowSeries = [
            'name' => 'FundoDoReservatorio',
            'data' => [
                [$yesterday_timestamp_js, -240, '24h antes'],
                [$now_timestamp_js, -240, 'Agora']
            ],
            'marker' => [
                'enabled' => true,
                'symbol' => 'square',
                'radius' => 1,
                'fillColor' => '#000000'
            ],
            'lineWidth' => 0,
            'enableMouseTracking' => true,
            'tooltip' => [
                'pointFormat' => '<span style="color:{point.color}"></span> {series.name}: <b>{point.y}</b><br/>',
                'headerFormat' => '<span style="font-size: 10px">{point.key:%d/%m/%Y %H:%M}</span><br/>'
            ]
        ];

        $seriesList[] = $nowSeries;
        $yAxisOptions['plotBands'] = $plotBands;

        if ($shadow) {
            $shadowData = [];
            foreach ($ruidos as $r) {
                $shadowData[] = [$r['timestamp_js'], $r['valor_plot']];
            }
            $seriesList[] = [
                'name' => 'Ruídos (Sombra)',
                'data' => $shadowData,
                'color' => 'rgba(255, 99, 71, 0.6)',
                'dashStyle' => 'ShortDot',
                'lineWidth' => 1,
                'marker' => [
                    'enabled' => true,
                    'radius' => 3,
                    'symbol' => 'circle'
                ],
                'tooltip' => [
                    'pointFormat' => '<span style="color:{point.color}">●</span> {series.name}: <b>{point.y}</b><br/>'
                ]
            ];
        }
    }

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
        'yAxis' => $yAxisOptions,
        'legend' => ['enabled' => false],
        'credits' => ['enabled' => false],
        'tooltip' => ['shared' => true],
        'exporting' => ['enabled' => true],
        'series' => $seriesList
    ];

    return ['success' => true, 'chartOptions' => $chartOptions, 'ruidos' => $ruidos];
}

// --- PONTO DE ENTRADA DO SCRIPT ---

$sensor_id = filter_input(INPUT_GET, 'sensor', FILTER_VALIDATE_INT);
if (!$sensor_id) {
    echo json_encode(['success' => false, 'message' => 'ID do sensor inválido.']);
    exit();
}

$debug = (filter_input(INPUT_GET, 'debug') === 'true');
$shadow = (filter_input(INPUT_GET, 'shadow') === 'true');

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

// Busca os detalhes do reservatório para ter o nome, fosso, tipo, etc.
$sql_caixa = "SELECT * FROM h2o.reservatorio WHERE sensor = '$sensor_id' AND ativo = true";
$caixa = DBQ($sql_caixa);

if (empty($caixa)) {
    echo json_encode(['success' => false, 'message' => 'Sensor não encontrado ou inativo.']);
    exit();
}
$caixa = $caixa[0];

// Extrai o tipo do sensor (padrao: 'agua')
$tipo = isset($caixa['tipo']) && $caixa['tipo'] === 'raw' ? 'raw' : 'agua';

// Extrai o valor_referencia (já com ajuste de alturaSonda somado) ou NULL para calcular automaticamente
$valor_referencia = isset($caixa['valor_referencia']) && $caixa['valor_referencia'] !== null && $caixa['valor_referencia'] !== '' ? (double)$caixa['valor_referencia'] : null;

// Chama a função para obter os dados e imprime o resultado em JSON
$result = getChartData($sensor_id, $caixa['fosso'], $caixa['nome'], $start, $end, $caixa['alturaSonda'], $debug, $shadow, $valor_referencia, $tipo);
echo json_encode($result);
?>