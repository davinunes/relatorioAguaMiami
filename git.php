<?php
/**
 * Gerenciador de Atualizações Git & Migrações de Banco de Dados (Interface AJAX)
 * Projeto: relatorioAguaMiami
 * Path: /var/www/relatorioAguaMiami
 * Host: localhost
 */

$projectPath = '/var/www/relatorioAguaMiami';
$sshHost     = 'localhost';
$sshScript   = '/usr/bin/python3 /var/www/html/py/ssh.py';
$pemSaveDir  = '/var/www/html/py';
$pemSavePath = $pemSaveDir . '/chave.pem';

// Função auxiliar para executar comando via ssh.py (com fallback local se houver erro de permissão da chave)
function executarComando($cmd, $sshScript, $projectPath) {
    $cmdFull = "cd {$projectPath} && {$cmd} 2>&1";
    
    if (file_exists('/var/www/html/py/ssh.py')) {
        $comandoSSH = "/usr/bin/python3 {$sshScript} " . escapeshellarg($cmdFull) . " 2>&1";
        $res = shell_exec($comandoSSH);
        
        // Se der erro de permissão na chave ou no arquivo, realiza o fallback direto e avisa o usuário
        if ($res && (strpos($res, 'Permission denied') !== false || strpos($res, 'PermissionError') !== false || strpos($res, 'permiss') !== false)) {
            $resDirect = shell_exec($cmdFull);
            $diagMsg = "[AVISO DE SISTEMA: O script ssh.py retornou Erro de Permissão. Executado via Fallback Direto]\n";
            $diagMsg .= "[DICA: Execute no servidor: 'chown -R www-data:www-data /var/www/html/py && chmod 600 /var/www/html/py/mykeyopenssh.pem']\n\n";
            return $diagMsg . trim($resDirect);
        }
        return $res ? trim($res) : "Sem retorno do script ssh.py.";
    } 
    
    $res = shell_exec($cmdFull);
    return $res ? trim($res) : "Sem retorno ou execução vazia.";
}

// Processador de Requisições AJAX (Retorna JSON)
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_REQUEST['action'] ?? 'status';
    $response = [
        'success' => true,
        'action' => '',
        'output' => '',
        'message' => ''
    ];

    if ($action === 'upload_pem') {
        if (isset($_FILES['pem_file']) && $_FILES['pem_file']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($pemSaveDir)) {
                @mkdir($pemSaveDir, 0755, true);
            }
            if (move_uploaded_file($_FILES['pem_file']['tmp_name'], $pemSavePath)) {
                chmod($pemSavePath, 0600);
                @chown($pemSavePath, 'www-data');
                $response['action'] = 'Upload Chave PEM';
                $response['message'] = "Chave PEM enviada com sucesso para <code>{$pemSavePath}</code> (permissão 0600 instalada).";
                $response['output'] = "[OK] Chave PEM salva em: {$pemSavePath}\nPermissão alterada para 0600 com sucesso.";
            } else {
                $response['success'] = false;
                $response['action'] = 'Upload Chave PEM';
                $response['message'] = "Erro ao mover o arquivo da chave PEM para <code>{$pemSavePath}</code>. Verifique permissões da pasta.";
                $response['output'] = "[ERRO] Permissão negada ao salvar arquivo tmp em {$pemSavePath}";
            }
        } else {
            $response['success'] = false;
            $response['action'] = 'Upload Chave PEM';
            $response['message'] = "Nenhum arquivo enviado ou falha no upload HTTP.";
            $response['output'] = "[ERRO] Nenhum arquivo .pem válido recebido.";
        }
        echo json_encode($response);
        exit;
    }

    if ($action === 'status') {
        $response['action'] = "Git Status";
        $response['output'] = executarComando("git status", $sshScript, $projectPath);
    } elseif ($action === 'pull') {
        $response['action'] = "Git Pull";
        $response['output'] = executarComando("git pull", $sshScript, $projectPath);
    } elseif ($action === 'commit_push') {
        $msg = !empty($_POST['msg']) ? $_POST['msg'] : "Atualização automática via Web " . date("d/m/Y H:i:s");
        $msgEscaped = escapeshellarg($msg);
        $response['action'] = "Git Commit & Push";
        $response['output'] = executarComando("git add . && git commit -m {$msgEscaped} && git push", $sshScript, $projectPath);
    } elseif ($action === 'migrate') {
        $response['action'] = "Execução de Migrações";
        $response['output'] = executarComando("php migrates.php", $sshScript, $projectPath);
    } elseif ($action === 'full_update') {
        $response['action'] = "Atualização Completa (Pull + Migrate)";
        $response['output'] = executarComando("git pull && php migrates.php", $sshScript, $projectPath);
    } else {
        $response['success'] = false;
        $response['action'] = "Ação Desconhecida";
        $response['output'] = "Ação não reconhecida.";
    }

    echo json_encode($response);
    exit;
}

$terminalCommand = "cd {$projectPath} && git pull && php migrates.php";
$sshCommand = "ssh -i {$pemSavePath} ubuntu@{$sshHost} \"cd {$projectPath} && git pull && php migrates.php\"";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Atualizações & Migrações (AJAX)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0e14; color: #abb2bf; font-family: system-ui, -apple-system, sans-serif; }
        .card-custom { background-color: #1c2128; border: 1px solid #30363d; border-radius: 8px; }
        .console-box { background-color: #05070a; border: 1px solid #30363d; border-radius: 6px; color: #50fa7b; font-family: monospace; min-height: 220px; max-height: 480px; overflow-y: auto; }
        .badge-path { background-color: #21262d; color: #58a6ff; border: 1px solid #30363d; }
        .btn-action { min-width: 145px; }
    </style>
</head>
<body>
<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h2 class="text-white m-0"><i class="fas fa-code-branch text-primary me-2"></i> Painel de Atualizações & Migrações</h2>
            <small class="text-muted">Sincronização Git e Migrações via AJAX sem recarga de página</small>
        </div>
        <div>
            <span class="badge badge-path p-2 fs-6 me-2"><i class="fas fa-server me-1"></i> Host: <?php echo htmlspecialchars($sshHost); ?></span>
            <span class="badge badge-path p-2 fs-6"><i class="fas fa-folder me-1"></i> <?php echo htmlspecialchars($projectPath); ?></span>
        </div>
    </div>

    <div id="alert-area"></div>

    <div class="row g-4">
        <!-- Ações Principais -->
        <div class="col-lg-8">
            <div class="card card-custom p-3 mb-4">
                <h5 class="text-white mb-3"><i class="fas fa-rocket me-2 text-warning"></i> Ações Rápidas</h5>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-info btn-action action-btn" data-action="status"><i class="fas fa-info-circle me-1"></i> Git Status</button>
                    <button type="button" class="btn btn-primary btn-action action-btn" data-action="pull"><i class="fas fa-cloud-download-alt me-1"></i> Git Pull</button>
                    <button type="button" class="btn btn-warning text-dark btn-action action-btn" data-action="migrate"><i class="fas fa-database me-1"></i> Rodar Migrações</button>
                    <button type="button" class="btn btn-success btn-action action-btn" data-action="full_update"><i class="fas fa-sync-alt me-1"></i> Pull + Migrate</button>
                </div>

                <hr class="border-secondary my-3">

                <!-- Form Commit & Push AJAX -->
                <form id="form-commit" class="row g-2 align-items-center">
                    <div class="col-sm-8">
                        <input type="text" id="commit-msg" name="msg" class="form-control bg-dark text-white border-secondary" placeholder="Descrição do Commit (ex: Ajustes de layout)">
                    </div>
                    <div class="col-sm-4">
                        <button type="submit" id="btn-commit" class="btn btn-outline-success w-100"><i class="fas fa-paper-plane me-1"></i> Commit & Push</button>
                    </div>
                </form>
            </div>

            <!-- Console Output -->
            <div class="card card-custom p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="text-white m-0"><i class="fas fa-terminal me-2 text-info"></i> Saída do Console</h5>
                    <span id="current-action-badge" class="badge bg-secondary">Aguardando comando...</span>
                </div>
                <div class="console-box p-3">
                    <pre id="console-output" class="m-0 text-wrap">Iniciando console...</pre>
                </div>
            </div>
        </div>

        <!-- Painel Lateral: Upload Pem e Dica Terminal -->
        <div class="col-lg-4">
            
            <!-- Upload da Chave PEM AJAX -->
            <div class="card card-custom p-3 mb-4">
                <h5 class="text-white mb-2"><i class="fas fa-key me-2 text-warning"></i> Upload de Chave SSH (.pem)</h5>
                <p class="small text-muted mb-3">Envie a chave <code>chave.pem</code> para autenticação automática no servidor SSH.</p>
                <form id="form-pem" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" id="pem-file-input" name="pem_file" accept=".pem" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <button type="submit" id="btn-upload-pem" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-upload me-1"></i> Enviar Chave PEM</button>
                </form>
            </div>

            <!-- Dica do Comando Linux -->
            <div class="card card-custom p-3">
                <h5 class="text-white mb-2"><i class="fas fa-lightbulb me-2 text-info"></i> Execução no Terminal Linux</h5>
                <p class="small text-muted mb-2">Comando direto no servidor Linux:</p>
                <div class="bg-dark p-2 rounded border border-secondary mb-3">
                    <code class="text-warning small d-block text-break"><?php echo htmlspecialchars($terminalCommand); ?></code>
                </div>

                <p class="small text-muted mb-2">Comando via SSH com chave pem:</p>
                <div class="bg-dark p-2 rounded border border-secondary">
                    <code class="text-info small d-block text-break"><?php echo htmlspecialchars($sshCommand); ?></code>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const consoleOutput = document.getElementById('console-output');
    const currentActionBadge = document.getElementById('current-action-badge');
    const alertArea = document.getElementById('alert-area');
    const actionBtns = document.querySelectorAll('.action-btn');

    function showAlert(msg, isSuccess = true) {
        alertArea.innerHTML = `
            <div class="alert alert-${isSuccess ? 'success' : 'danger'} alert-dismissible fade show" role="alert">
                <i class="fas fa-${isSuccess ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
    }

    function setExecutingState(actionName) {
        currentActionBadge.className = 'badge bg-warning text-dark';
        currentActionBadge.textContent = `Executando: ${actionName}...`;
        consoleOutput.innerHTML = `<span class="text-warning">⏳ Executando comando (${actionName})... por favor aguarde...</span>`;
        actionBtns.forEach(btn => btn.disabled = true);
    }

    function resetExecutingState() {
        actionBtns.forEach(btn => btn.disabled = false);
    }

    async function runAction(action, formData = null) {
        const actionLabels = {
            'status': 'Git Status',
            'pull': 'Git Pull',
            'migrate': 'Executando Migrações',
            'full_update': 'Atualização Completa',
            'commit_push': 'Git Commit & Push',
            'upload_pem': 'Upload Chave PEM'
        };

        setExecutingState(actionLabels[action] || action);

        try {
            const options = {
                method: 'POST'
            };

            if (formData) {
                options.body = formData;
            } else {
                const fd = new FormData();
                fd.append('action', action);
                options.body = fd;
            }

            const response = await fetch('git.php?ajax=1', options);
            const data = await response.json();

            currentActionBadge.className = 'badge bg-info text-dark';
            currentActionBadge.textContent = data.action || 'Concluído';
            consoleOutput.textContent = data.output || 'Nenhum resultado retornado.';

            if (data.message) {
                showAlert(data.message, data.success !== false);
            }
        } catch (err) {
            currentActionBadge.className = 'badge bg-danger';
            currentActionBadge.textContent = 'Erro de Execução';
            consoleOutput.textContent = `Erro na requisição AJAX: ${err.message}`;
            showAlert(`Falha ao conectar com o servidor: ${err.message}`, false);
        } finally {
            resetExecutingState();
        }
    }

    // Eventos dos botões de ação rápida
    actionBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const action = btn.getAttribute('data-action');
            runAction(action);
        });
    });

    // Evento Form Commit & Push
    document.getElementById('form-commit').addEventListener('submit', (e) => {
        e.preventDefault();
        const msg = document.getElementById('commit-msg').value;
        const fd = new FormData();
        fd.append('action', 'commit_push');
        fd.append('msg', msg);
        runAction('commit_push', fd);
    });

    // Evento Form Upload PEM
    document.getElementById('form-pem').addEventListener('submit', (e) => {
        e.preventDefault();
        const fileInput = document.getElementById('pem-file-input');
        if (!fileInput.files.length) return;

        const fd = new FormData();
        fd.append('action', 'upload_pem');
        fd.append('pem_file', fileInput.files[0]);
        runAction('upload_pem', fd);
    });

    // Carrega o Git Status inicial via AJAX sem dar reload na página
    runAction('status');
});
</script>
</body>
</html>