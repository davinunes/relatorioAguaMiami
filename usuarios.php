<?php
// usuarios.php (versão final corrigida)
session_start();
require 'database.php';

// Bloco para GERENCIAR USUÁRIO (mudar role/grupo) (Apenas DEV)
if ($_SESSION['user_role'] === 'dev' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerenciar_usuario'])) {
    // O ID do usuário agora vem do VALOR do botão que foi clicado
    $usuario_id = DBEscape($_POST['gerenciar_usuario']);

    // Acessamos o role e grupo usando o ID do usuário como chave no array
    $role = DBEscape($_POST['role'][$usuario_id]);
    $grupo_id = !empty($_POST['grupo_id'][$usuario_id]) ? DBEscape($_POST['grupo_id'][$usuario_id]) : 'NULL';

    // A query de update permanece a mesma
    DBExecute("UPDATE usuarios SET role = '$role', grupo_id = $grupo_id WHERE id = '$usuario_id'");
    header('Location: usuarios.php');
    exit();
}

// ===================================================================
// ===== 1. VERIFICAÇÕES INICIAIS E DE PERMISSÃO =====================
// ===================================================================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if ($_SESSION['user_role'] === 'monitor') {
    header('Location: index.php');
    exit();
}

// ===================================================================
// ===== 2. PROCESSADOR DE REQUISIÇÕES AJAX (POSIÇÃO CORRIGIDA) ======
// ===================================================================
// Este bloco agora vem antes de qualquer outra lógica de página.
if (isset($_GET['action']) && $_GET['action'] == 'get_sensores_usuario' && isset($_GET['usuario_id'])) {
    $usuario_id = DBEscape($_GET['usuario_id']);
    $sql = "SELECT sensor_id FROM usuario_sensores WHERE usuario_id = '$usuario_id'";
    $sensores_associados = DBQ($sql);
    $sensor_ids = [];
    if ($sensores_associados) { foreach ($sensores_associados as $assoc) { $sensor_ids[] = $assoc['sensor_id']; } }
    
    header('Content-Type: application/json');
    echo json_encode(['sensor_ids' => $sensor_ids]);
    exit(); // Encerra o script aqui, retornando apenas o JSON.
}


// ===================================================================
// ===== 3. PROCESSAMENTO DE FORMULÁRIOS (POST) ======================
// ===================================================================

// Bloco para criar novo GRUPO (Apenas DEV)
if ($_SESSION['user_role'] === 'dev' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_grupo'])) {
    $nome_grupo = DBEscape($_POST['nome_grupo']);
    if (!empty($nome_grupo)) {
        DBExecute("INSERT INTO grupos (nome) VALUES ('$nome_grupo')");
        header('Location: usuarios.php');
        exit();
    }
}

// Bloco para GERENCIAR USUÁRIO (mudar role/grupo) (Apenas DEV)
if ($_SESSION['user_role'] === 'dev' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerenciar_usuario'])) {
    $usuario_id = DBEscape($_POST['usuario_id']);
    $role = DBEscape($_POST['role']);
    $grupo_id = $_POST['grupo_id'] ? DBEscape($_POST['grupo_id']) : 'NULL';

    DBExecute("UPDATE usuarios SET role = '$role', grupo_id = $grupo_id WHERE id = '$usuario_id'");
    header('Location: usuarios.php');
    exit();
}

// FUNCIONALIDADE RESTAURADA: Bloco para criar novo sensor (DEV e ADMIN)
if (in_array($_SESSION['user_role'], ['dev', 'admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_sensor'])) {
    $sensor_id = DBEscape($_POST['sensor_id']);
    $nome_sensor = DBEscape($_POST['nome_sensor']);
    $fosso = DBEscape($_POST['fosso']);
    $altura_sonda = DBEscape($_POST['altura_sonda']);

    if (!empty($sensor_id) && !empty($nome_sensor)) {
        $sql = "INSERT INTO reservatorio (sensor, nome, fosso, alturaSonda, ativo) VALUES ('$sensor_id', '$nome_sensor', '$fosso', '$altura_sonda', 1)";
        DBExecute($sql);
        header('Location: usuarios.php');
        exit();
    }
}

// Bloco para ASSOCIAR SENSORES (DEV e ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['associar_sensores'])) {
    $usuario_id = DBEscape($_POST['usuario_id']);
    $sensores_associados = $_POST['sensores'] ?? [];
    
    $pode_alterar = false;
    if ($_SESSION['user_role'] === 'dev') {
        $pode_alterar = true;
    } elseif ($_SESSION['user_role'] === 'admin') {
        $usuario_alvo = DBQ("SELECT grupo_id FROM usuarios WHERE id = '$usuario_id'");
        if ($usuario_alvo && !empty($_SESSION['user_grupo_id']) && $usuario_alvo[0]['grupo_id'] == $_SESSION['user_grupo_id']) {
            $pode_alterar = true;
        }
    }

    if ($pode_alterar) {
        DBExecute("DELETE FROM usuario_sensores WHERE usuario_id = '$usuario_id'");
        if (!empty($sensores_associados)) {
            foreach ($sensores_associados as $sensor_id) {
                $sensor_id_escaped = DBEscape($sensor_id);
                DBExecute("INSERT INTO usuario_sensores (usuario_id, sensor_id) VALUES ('$usuario_id', '$sensor_id_escaped')");
            }
        }
    }
    header('Location: usuarios.php');
    exit();
}


// ===================================================================
// ===== 4. BUSCA DE DADOS PARA RENDERIZAR A PÁGINA ==================
// ===================================================================
$grupos = DBQ("SELECT * FROM grupos ORDER BY nome ASC");

if ($_SESSION['user_role'] === 'admin') {
    $grupo_do_admin = $_SESSION['user_grupo_id'];
    $usuarios_gerenciaveis = empty($grupo_do_admin) ? [] : DBQ("SELECT u.id, u.nome, u.login, u.role, g.nome as grupo_nome FROM usuarios u LEFT JOIN grupos g ON u.grupo_id = g.id WHERE u.grupo_id = '".DBEscape($grupo_do_admin)."' ORDER BY u.nome ASC");
} else { // dev
    $usuarios_gerenciaveis = DBQ("SELECT u.id, u.nome, u.login, u.role, g.nome as grupo_nome FROM usuarios u LEFT JOIN grupos g ON u.grupo_id = g.id ORDER BY u.nome ASC");
}

$sensores = DBQ("SELECT sensor, nome FROM reservatorio ORDER BY nome ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento do Sistema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        td .input-field { margin-top: 0; margin-bottom: 0; }
    </style>
</head>
<body>
<div class="container">
    <h3 class="center-align">Gerenciamento do Sistema (Perfil: <?= strtoupper(htmlspecialchars($_SESSION['user_role'])) ?>)</h3>

    <?php if ($_SESSION['user_role'] === 'dev'): ?>
    <div class="row">
        <div class="col s12 m6">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Criar Novo Grupo</span>
                    <form method="POST">
                        <input type="hidden" name="criar_grupo" value="1">
                        <div class="input-field"><input type="text" name="nome_grupo" required><label>Nome do Grupo</label></div>
                        <button class="btn" type="submit"><i class="material-icons left">group_add</i>Criar Grupo</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col s12 m6">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Adicionar Novo Sensor</span>
                    <form method="POST">
                        <input type="hidden" name="criar_sensor" value="1">
                        <div class="input-field"><input type="number" name="sensor_id" required><label>ID do Sensor</label></div>
                        <div class="input-field"><input type="text" name="nome_sensor" required><label>Nome do Sensor</label></div>
                        <div class="input-field"><input type="number" name="fosso"><label>Profundidade Fosso (cm)</label></div>
                        <div class="input-field"><input type="number" name="altura_sonda"><label>Ajuste Altura Sonda (cm)</label></div>
                        <button class="btn" type="submit"><i class="material-icons left">add_circle</i>Criar Sensor</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
    <div class="card-content">
        <span class="card-title">Gerenciar Usuários</span>

        <form method="POST">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr><th>Nome</th><th>Login</th><th>Grupo</th><th>Perfil</th><th>Ações</th></tr>
                </thead>
                <tbody>
                    <?php if ($usuarios_gerenciaveis) foreach ($usuarios_gerenciaveis as $usuario): ?>
                    <tr>
                        <td><?= htmlspecialchars($usuario['nome']) ?></td>
                        <td><?= htmlspecialchars($usuario['login']) ?></td>
                        <td>
                            <div class="input-field"><select name="grupo_id[<?= $usuario['id'] ?>]">
                                <option value="">Nenhum</option>
                                <?php if ($grupos) foreach ($grupos as $grupo): ?>
                                    <option value="<?= $grupo['id'] ?>" <?= ($usuario['grupo_nome'] == $grupo['nome']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($grupo['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select></div>
                        </td>
                        <td>
                            <div class="input-field"><select name="role[<?= $usuario['id'] ?>]">
                                <option value="dev" <?= ($usuario['role'] == 'dev') ? 'selected' : '' ?>>dev</option>
                                <option value="admin" <?= ($usuario['role'] == 'admin') ? 'selected' : '' ?>>admin</option>
                                <option value="monitor" <?= ($usuario['role'] == 'monitor') ? 'selected' : '' ?>>monitor</option>
                            </select></div>
                        </td>
                        <td>
                            <button type="submit" class="btn-small waves-effect waves-light" name="gerenciar_usuario" value="<?= $usuario['id'] ?>">
                                <i class="material-icons">save</i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
</div>
    <?php endif; // Fim do IF de DEV ?>

    <div class="card">
        <div class="card-content">
            <span class="card-title">Associar Sensores a Usuários
                <?php if ($_SESSION['user_role'] === 'admin'): ?> (do seu grupo)<?php endif; ?>
            </span>
            <form method="POST" id="form-associar">
                <input type="hidden" name="associar_sensores" value="1">
                <div class="input-field">
                    <select id="select-usuario" name="usuario_id" required>
                        <option value="" disabled selected>Selecione um usuário para gerenciar</option>
                        <?php if ($usuarios_gerenciaveis) foreach ($usuarios_gerenciaveis as $usuario): ?>
                            <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Usuário</label>
                </div>
                
                <div id="lista-sensores-checkboxes">
                    <p><strong>Sensores Disponíveis:</strong></p>
                    <?php if ($sensores) foreach ($sensores as $sensor): ?>
                        <p><label><input type="checkbox" name="sensores[]" value="<?= $sensor['sensor'] ?>" /><span><?= htmlspecialchars($sensor['nome']) ?> (ID: <?= $sensor['sensor'] ?>)</span></label></p>
                    <?php endforeach; ?>
                </div>
                <br>
                <button class="btn" type="submit"><i class="material-icons left">save</i>Salvar Associações de Sensores</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var elems = document.querySelectorAll('select');
    M.FormSelect.init(elems);

    const selectUsuario = document.getElementById('select-usuario');
    
    selectUsuario.addEventListener('change', function() {
        const usuarioId = this.value;
        const checkboxes = document.querySelectorAll('#lista-sensores-checkboxes input[type="checkbox"]');
        checkboxes.forEach(checkbox => checkbox.checked = false);

        if (usuarioId) {
            fetch(`usuarios.php?action=get_sensores_usuario&usuario_id=${usuarioId}`)
                .then(response => {
                    if (!response.ok) { throw new Error('A resposta da rede não foi OK'); }
                    return response.json();
                })
                .then(data => {
                    if (data.sensor_ids) {
                        checkboxes.forEach(checkbox => {
                            if (data.sensor_ids.includes(checkbox.value)) {
                                checkbox.checked = true;
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar sensores do usuário:', error);
                    M.toast({html: 'Não foi possível carregar as associações de sensores.'});
                });
        }
    });
});
</script>
</body>
</html>