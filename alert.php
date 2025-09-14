<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    // http_response_code(401); 
    // echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    // exit();
}

include "database.php";

// --- PONTO DE ENTRADA DO SCRIPT ---

// 1. Valida o ID do sensor recebido via GET
$sensor_id = filter_input(INPUT_GET, 'sensor', FILTER_VALIDATE_INT);

if (!$sensor_id) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'ID do sensor inválido ou não fornecido.']);
    exit();
}

// ===================================================================
// 2. BUSCA O NOME DO SENSOR - NOVA SEÇÃO
// ===================================================================
// Consulta a tabela de reservatórios para obter o nome do sensor
$sql_sensor = "SELECT nome FROM h2o.reservatorio WHERE sensor = $sensor_id AND ativo = true LIMIT 1";
$info_sensor = DBQ($sql_sensor);

// Se não encontrarmos o sensor ou ele estiver inativo, retorna um erro.
if (empty($info_sensor)) {
    http_response_code(404); // Not Found (Não encontrado)
    echo json_encode(['success' => false, 'message' => "Sensor com ID $sensor_id não encontrado ou está inativo."]);
    exit();
}
// Armazena o nome do sensor para usar na resposta final
$nome_sensor = $info_sensor[0]['nome'];
// ===================================================================


// 3. Define o período de tempo (última hora)
$timestamp_uma_hora_atras = strtotime('-1 hour');
$uma_hora_atras_sql = date("Y-m-d H:i:s", $timestamp_uma_hora_atras);
$uma_hora_atras_br = date("d/m/Y H:i:s", $timestamp_uma_hora_atras);


// 4. Monta e executa a consulta SQL das leituras
$sql_leituras = "
    SELECT
        COUNT(*) AS total_itens_encontrados,
        SUM(CASE WHEN Valor > 100 THEN 1 ELSE 0 END) AS total_alerta
    FROM
        h2o.leituras
    WHERE
        sensor = $sensor_id AND `timestamp` >= '$uma_hora_atras_sql'
";
$resultado = DBQ($sql_leituras);


// 5. Processa o resultado e monta a resposta JSON
if ($resultado && isset($resultado[0])) {
    $dados = $resultado[0];
    
    // Monta o array de resposta final
    $resposta = [
        'success' => true,
        'sensor_id' => $sensor_id,
        'nome_sensor' => $nome_sensor, // <-- NOME DO SENSOR INCLUÍDO AQUI
        'periodo_consultado' => "" . $uma_hora_atras_br,
        'total_itens_encontrados' => (int) $dados['total_itens_encontrados'],
        'alerta' => (int) $dados['total_alerta']
    ];

} else {
    http_response_code(500);
    $resposta = [
        'success' => false,
        'message' => 'Erro ao consultar o banco de dados para as leituras.',
        'total_itens_encontrados' => 0,
        'alerta' => 0
    ];
}

// 6. Retorna o JSON final
echo json_encode($resposta);

?>