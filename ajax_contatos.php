<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Segurança: verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit();
}

include "database.php";

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] === 'dev' || $_SESSION['user_role'] === 'admin');

// Determina a ação
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $sensor_id = filter_input(INPUT_GET, 'sensor_id', FILTER_VALIDATE_INT);
    if (!$sensor_id) {
        echo json_encode(['success' => false, 'message' => 'ID do sensor inválido.']);
        exit();
    }

    // Verifica permissão para usuários comuns (não-admins)
    if (!$is_admin) {
        $access = DBQ("SELECT 1 FROM h2o.usuario_sensores WHERE usuario_id = '".DBEscape($user_id)."' AND sensor_id = '$sensor_id'");
        if (empty($access)) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para este sensor.']);
            exit();
        }
    }

    $contatos = DBQ("SELECT * FROM h2o.contatos_notificacao WHERE sensor_id = '$sensor_id' ORDER BY id DESC");
    echo json_encode(['success' => true, 'contatos' => $contatos]);
    exit();
}

if ($action === 'add') {
    $sensor_id = filter_input(INPUT_POST, 'sensor_id', FILTER_VALIDATE_INT);
    $numero = filter_input(INPUT_POST, 'numero', FILTER_DEFAULT);
    
    if (!$sensor_id || empty($numero)) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit();
    }

    $numero = trim($numero);

    // Formata o número para garantir que tem o sufixo @s.whatsapp.net
    if (!strpos($numero, '@')) {
        $numero_digits = preg_replace('/\D/', '', $numero);
        if (empty($numero_digits)) {
            echo json_encode(['success' => false, 'message' => 'Número de WhatsApp inválido.']);
            exit();
        }
        $numero = $numero_digits . '@s.whatsapp.net';
    }

    // Remove o nono dígito para números brasileiros se tiver 13 dígitos numéricos
    // (55 + 2 dígitos DDD + 9 + 8 dígitos)
    $parts = explode('@', $numero);
    $num_part = $parts[0];
    if (substr($num_part, 0, 2) === '55' && strlen($num_part) === 13) {
        if ($num_part[4] === '9') {
            $num_part = substr($num_part, 0, 4) . substr($num_part, 5);
            $numero = $num_part . '@' . ($parts[1] ?? 's.whatsapp.net');
        }
    }

    // Verifica permissão
    if (!$is_admin) {
        $access = DBQ("SELECT 1 FROM h2o.usuario_sensores WHERE usuario_id = '".DBEscape($user_id)."' AND sensor_id = '$sensor_id'");
        if (empty($access)) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para este sensor.']);
            exit();
        }
    }

    $sensor_id_escaped = DBEscape($sensor_id);
    $numero_escaped = DBEscape($numero);

    // Verifica se já está cadastrado
    $dup = DBQ("SELECT 1 FROM h2o.contatos_notificacao WHERE sensor_id = '$sensor_id_escaped' AND numero = '$numero_escaped'");
    if (!empty($dup)) {
        echo json_encode(['success' => false, 'message' => 'Este número já está cadastrado para este sensor.']);
        exit();
    }

    $sql = "INSERT INTO h2o.contatos_notificacao (sensor_id, numero) VALUES ('$sensor_id_escaped', '$numero_escaped')";
    if (DBExecute($sql)) {
        echo json_encode(['success' => true, 'message' => 'Número adicionado com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco de dados.']);
    }
    exit();
}

if ($action === 'delete') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit();
    }

    // Busca o contato para ver a qual sensor pertence
    $contato = DBQ("SELECT * FROM h2o.contatos_notificacao WHERE id = '$id'");
    if (empty($contato)) {
        echo json_encode(['success' => false, 'message' => 'Contato não encontrado.']);
        exit();
    }
    $sensor_id = $contato[0]['sensor_id'];

    // Verifica permissão
    if (!$is_admin) {
        $access = DBQ("SELECT 1 FROM h2o.usuario_sensores WHERE usuario_id = '".DBEscape($user_id)."' AND sensor_id = '$sensor_id'");
        if (empty($access)) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para este sensor.']);
            exit();
        }
    }

    $id_escaped = DBEscape($id);
    $sql = "DELETE FROM h2o.contatos_notificacao WHERE id = '$id_escaped'";
    if (DBExecute($sql)) {
        echo json_encode(['success' => true, 'message' => 'Número removido com sucesso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao remover do banco de dados.']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
?>
