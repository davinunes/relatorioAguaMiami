<?php
// adm/index.php (versão final com modais de criação)
session_start();
require '../database.php';

// ===================================================================
// ===== 1. VERIFICAÇÕES INICIAIS E DE PERMISSÃO =====================
// ===================================================================
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
if ($_SESSION['user_role'] === 'monitor') {
    header('Location: ../index2.php');
    exit();
}

// ===================================================================
// ===== 2. PROCESSADOR DE REQUISIÇÕES AJAX (POSIÇÃO CORRIGIDA) ======
// ===================================================================
if (isset($_GET['action']) && $_GET['action'] == 'get_sensores_usuario' && isset($_GET['usuario_id'])) {
    $usuario_id = DBEscape($_GET['usuario_id']);
    $sql = "SELECT sensor_id FROM usuario_sensores WHERE usuario_id = '$usuario_id'";
    $sensores_associados = DBQ($sql);
    $sensor_ids = [];
    if ($sensores_associados) { foreach ($sensores_associados as $assoc) { $sensor_ids[] = $assoc['sensor_id']; } }
    
    header('Content-Type: application/json');
    echo json_encode(['sensor_ids' => $sensor_ids]);
    exit();
}

// ===================================================================
// ===== 3. PROCESSAMENTO DE FORMULÁRIOS (POST) ======================
// ===================================================================
// A lógica PHP abaixo não muda, pois os formulários nos modais enviam os mesmos dados.

// Bloco para criar novo GRUPO (Apenas DEV)
if ($_SESSION['user_role'] === 'dev' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_grupo'])) {
    $nome_grupo = DBEscape($_POST['nome_grupo']);
    if (!empty($nome_grupo)) {
        DBExecute("INSERT INTO grupos (nome) VALUES ('$nome_grupo')");
        header('Location: index.php');
        exit();
    }
}

// Bloco para criar novo usuário (Apenas DEV)
if ($_SESSION['user_role'] === 'dev' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_usuario'])) {
    $nome = DBEscape($_POST['nome']);
    $login = DBEscape($_POST['login']);
    $senha = $_POST['senha'];

    if (!empty($nome) && !empty($login) && !empty($senha)) {
        $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
        $hash_senha_escaped = DBEscape($hash_senha);
        $sql = "INSERT INTO usuarios (nome, login, senha) VALUES ('$nome', '$login', '$hash_senha_escaped')";
        DBExecute($sql);
        header('Location: index.php');
        exit();
    }
}
// ===================================================================
// ===== 3. PROCESSAMENTO DE FORMULÁRIOS (POST) ======================
// ===================================================================

// Bloco para criar novo GRUPO (Apenas DEV)
if ($_SESSION['user_role'] === 'dev' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_grupo'])) {
    $nome_grupo = DBEscape($_POST['nome_grupo']);
    if (!empty($nome_grupo)) {
        DBExecute("INSERT INTO grupos (nome) VALUES ('$nome_grupo')");
        header('Location: index.php');
        exit();
    }
}

// FUNCIONALIDADE RESTAURADA: Bloco para criar novo usuário (Apenas DEV)
if ($_SESSION['user_role'] === 'dev' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_usuario'])) {
    $nome = DBEscape($_POST['nome']);
    $login = DBEscape($_POST['login']);
    $senha = $_POST['senha'];

    if (!empty($nome) && !empty($login) && !empty($senha)) {
        $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
        $hash_senha_escaped = DBEscape($hash_senha);
        // O usuário é criado com o role padrão 'monitor' e sem grupo (NULL)
        $sql = "INSERT INTO usuarios (nome, login, senha) VALUES ('$nome', '$login', '$hash_senha_escaped')";
        DBExecute($sql);
        header('Location: index.php');
        exit();
    }
}

// Bloco para GERENCIAR USUÁRIO (mudar role/grupo) (Apenas DEV)
if ($_SESSION['user_role'] === 'dev' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerenciar_usuario'])) {
    $usuario_id = DBEscape($_POST['gerenciar_usuario']);
    $role = DBEscape($_POST['role'][$usuario_id]);
    $grupo_id = !empty($_POST['grupo_id'][$usuario_id]) ? DBEscape($_POST['grupo_id'][$usuario_id]) : 'NULL';

    DBExecute("UPDATE usuarios SET role = '$role', grupo_id = $grupo_id WHERE id = '$usuario_id'");
    header('Location: index.php');
    exit();
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
    header('Location: index.php');
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
    <h3 class="center-align">Gerenciamento do Sistema  (<?= strtoupper(htmlspecialchars($_SESSION['user_role'])) ?>)</h3>

    <?php if ($_SESSION['user_role'] === 'dev'): ?>
    <div class="row">
        <div class="col s12">
            <a href="#modal-criar-usuario" class="btn waves-effect waves-light modal-trigger">
                <i class="material-icons left">person_add</i>Criar Novo Usuário
            </a>
            <a href="#modal-criar-grupo" class="btn waves-effect waves-light modal-trigger">
                <i class="material-icons left">group_add</i>Criar Novo Grupo
            </a>
             <a href="sensores.php" class="btn waves-effect waves-light indigo right">
                <i class="material-icons left">sensors</i>Gerenciar Sensores
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-content">
            <span class="card-title">Gerenciar Usuários</span>
            <form method="POST">
            <table class="striped highlight responsive-table">
                <thead><tr><th>Nome</th><th>Login</th><th>Grupo</th><th>Perfil</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php if ($usuarios_gerenciaveis) foreach ($usuarios_gerenciaveis as $usuario): ?>
                    <tr>
                        <td><?= htmlspecialchars($usuario['nome']) ?></td>
                        <td><?= htmlspecialchars($usuario['login']) ?></td>
                        <td>
                            <div class="input-field"><select name="grupo_id[<?= $usuario['id'] ?>]">
                                <option value="">Nenhum</option>
                                <?php if ($grupos) foreach ($grupos as $grupo): ?>
                                    <option value="<?= $grupo['id'] ?>" <?= ($usuario['grupo_nome'] == $grupo['nome']) ? 'selected' : '' ?>><?= htmlspecialchars($grupo['nome']) ?></option>
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

    <div class="center-align" style="margin-top: 20px;">
        <a href="../index2.php" class="btn-large blue waves-effect waves-light"><i class="material-icons left">dashboard</i>Voltar ao Painel</a>
        <a href="../logout.php" class="btn-large red waves-effect waves-light"><i class="material-icons left">exit_to_app</i>Sair</a>
    </div>
</div>

<div id="modal-criar-usuario" class="modal modal-fixed-footer">
    <form method="POST">
        <div class="modal-content">
            <h4>Criar Novo Usuário</h4>
            <input type="hidden" name="criar_usuario" value="1">
            <div class="row">
                <div class="input-field col s12">
                    <input id="criar-nome" type="text" name="nome" required>
                    <label for="criar-nome">Nome Completo</label>
                </div>
                <div class="input-field col s12">
                    <input id="criar-login" type="text" name="login" required>
                    <label for="criar-login">Login</label>
                </div>
                <div class="input-field col s12">
                    <input id="criar-senha" type="password" name="senha" required>
                    <label for="criar-senha">Senha</label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-grey btn-flat">Cancelar</a>
            <button type="submit" class="btn waves-effect waves-green">Criar Usuário</button>
        </div>
    </form>
</div>

<div id="modal-criar-grupo" class="modal">
    <form method="POST">
        <div class="modal-content">
            <h4>Criar Novo Grupo</h4>
            <input type="hidden" name="criar_grupo" value="1">
            <div class="row">
                <div class="input-field col s12">
                    <input id="criar-nome-grupo" type="text" name="nome_grupo" required>
                    <label for="criar-nome-grupo">Nome do Grupo</label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-grey btn-flat">Cancelar</a>
            <button class="btn waves-effect waves-green" type="submit">Criar Grupo</button>
        </div>
    </form>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Inicializa TODOS os modais da página (criar usuário, criar grupo)
    var modals = document.querySelectorAll('.modal');
    M.Modal.init(modals);

    // 2. Inicializa TODOS os selects da página (o da tabela e o de associar sensor)
    var selects = document.querySelectorAll('select');
    M.FormSelect.init(selects);

    // 3. Lógica AJAX para o select de "Associar Sensores"
    const selectUsuario = document.getElementById('select-usuario');

    // Adicionamos uma verificação para garantir que o elemento existe antes de adicionar o 'listener'
    // Isso torna o script mais robusto, caso um 'admin' sem grupo acesse a página.
    if (selectUsuario) {
        selectUsuario.addEventListener('change', function() {
            const usuarioId = this.value;
            const checkboxes = document.querySelectorAll('#lista-sensores-checkboxes input[type="checkbox"]');
            
            // Limpa todos os checkboxes antes de uma nova seleção
            checkboxes.forEach(checkbox => checkbox.checked = false);

            // Se um usuário válido foi selecionado, busca os sensores associados
            if (usuarioId) {
                fetch(`index.php?action=get_sensores_usuario&usuario_id=${usuarioId}`)
                    .then(response => {
                        if (!response.ok) { throw new Error('A resposta da rede não foi OK'); }
                        return response.json();
                    })
                    .then(data => {
                        if (data.sensor_ids) {
                            checkboxes.forEach(checkbox => {
                                // A função includes() é moderna e eficiente para verificar se um item existe no array
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
    }
});
</script>
</body>
</html>