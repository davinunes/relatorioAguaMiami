<?php
/**
 * Gerenciador de Atualizações Git & Migrações de Banco de Dados (Interface AJAX via SSH)
 * Projeto: relatorioAguaMiami
 * Path: /var/www/relatorioAguaMiami
 * Host: localhost
 */

$projectPath = '/var/www/relatorioAguaMiami';
$sshHost     = 'localhost';
$sshScript   = '/var/www/html/py/ssh.py';
$pemSaveDir  = '/var/www/html/py';
$pemSavePath = $pemSaveDir . '/chave.pem';

// Função auxiliar para verificar a existência e detalhes da chave PEM instalada
function getPemKeyStatus($pemSavePath) {
    $altPath = '/var/www/html/py/mykeyopenssh.pem';
    $targetPath = file_exists($pemSavePath) ? $pemSavePath : (file_exists($altPath) ? $altPath : null);

    if ($targetPath) {
        $perms = substr(sprintf('%o', fileperms($targetPath)), -4);
        return [
            'exists' => true,
            'path' => $targetPath,
            'perms' => $perms,
            'label' => 'Instalada (' . $perms . ')'
        ];
    }
    return [
        'exists' => false,
        'path' => null,
        'perms' => null,
        'label' => 'Não Instalada'
    ];
}

// Função auxiliar para executar comandos via ponte SSH (/var/www/html/py/ssh.py)
function executarComando($cmd, $sshScript, $projectPath) {
    $cmdFull = "cd {$projectPath} && {$cmd}";
    
    // Invocação padronizada do ssh.py (idêntica ao recMan / boss_v2)
    $comandoSSH = "/usr/bin/python3 {$sshScript} '" . str_replace("'", "'\\''", $cmdFull) . "'";
    
    $res = shell_exec($comandoSSH);
    
    // Fallback local se o SSH não retornar resultado
    if ($res === null || trim($res) === '') {
        $res = shell_exec("cd {$projectPath} && {$cmd} 2>&1");
    }
    
    return $res ? trim($res) : "Sem retorno da execução.";
}

// Processador de Requisições AJAX (Retorna JSON)
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_REQUEST['action'] ?? 'status';
    $response = [
        'success' => true,
        'action' => '',
        'output' => '',
        'message' => '',
        'keyStatus' => getPemKeyStatus($pemSavePath)
    ];

    if ($action === 'upload_pem') {
        if (isset($_FILES['pem_file']) && $_FILES['pem_file']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($pemSaveDir)) {
                @mkdir($pemSaveDir, 0755, true);
            }
            if (move_uploaded_file($_FILES['pem_file']['tmp_name'], $pemSavePath)) {
                chmod($pemSavePath, 0600);
                $response['action'] = 'Upload Chave PEM';
                $response['message'] = "Chave PEM enviada com sucesso para <code>{$pemSavePath}</code> (permissão 0600 instalada).";
                $response['output'] = "[OK] Chave PEM salva em: {$pemSavePath}\nPermissão alterada para 0600 com sucesso.";
                $response['keyStatus'] = getPemKeyStatus($pemSavePath);
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

    if ($action === 'save_pem_text') {
        $pemText = trim($_POST['pem_text'] ?? '');
        if (!empty($pemText)) {
            if (!is_dir($pemSaveDir)) {
                @mkdir($pemSaveDir, 0755, true);
            }
            if (file_put_contents($pemSavePath, $pemText) !== false) {
                chmod($pemSavePath, 0600);
                $response['action'] = 'Salvar Chave PEM';
                $response['message'] = "Conteúdo da Chave PEM salvo com sucesso em <code>{$pemSavePath}</code> (permissão 0600 instalada).";
                $response['output'] = "[OK] Conteúdo da chave PEM gravado em: {$pemSavePath}\nPermissão setada para 0600.";
                $response['keyStatus'] = getPemKeyStatus($pemSavePath);
            } else {
                $response['success'] = false;
                $response['action'] = 'Salvar Chave PEM';
                $response['message'] = "Erro ao gravar a chave PEM em <code>{$pemSavePath}</code>. Verifique permissões da pasta.";
                $response['output'] = "[ERRO] Falha ao escrever conteúdo em {$pemSavePath}";
            }
        } else {
            $response['success'] = false;
            $response['action'] = 'Salvar Chave PEM';
            $response['message'] = "O texto da chave PEM não pode estar vazio.";
            $response['output'] = "[ERRO] Nenhum texto fornecido.";
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

$keyStatus = getPemKeyStatus($pemSavePath);
$terminalCommand = "cd {$projectPath} && git pull && php migrates.php";
$sshCommand = "ssh -i {$pemSavePath} root@{$sshHost} \"cd {$projectPath} && git pull && php migrates.php\"";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Atualizações & Migrações (SSH / AJAX)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0e14; color: #abb2bf; font-family: system-ui, -apple-system, sans-serif; }
        .card-custom { background-color: #1c2128; border: 1px solid #30363d; border-radius: 8px; }
        .console-box { background-color: #05070a; border: 1px solid #30363d; border-radius: 6px; color: #50fa7b; font-family: monospace; min-height: 220px; max-height: 480px; overflow-y: auto; }
        .badge-path { background-color: #21262d; color: #58a6ff; border: 1px solid #30363d; }
        .btn-action { min-width: 145px; }
        .nav-tabs .nav-link { color: #8b949e; border-color: transparent; }
        .nav-tabs .nav-link.active { color: #fff; background-color: #1c2128; border-color: #30363d #30363d #1c2128; }
    </style>
</head>
<body>
<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h2 class="text-white m-0"><i class="fas fa-code-branch text-primary me-2"></i> Painel de Atualizações & Migrações</h2>
            <small class="text-muted">Sincronização Git e Migrações via SSH (Ponte <code>ssh.py</code>) + AJAX</small>
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

        <!-- Painel Lateral: Status PEM, Upload Pem e Texto Pem -->
        <div class="col-lg-4">
            
            <!-- Card de Status da Chave PEM -->
            <div class="card card-custom p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="text-white m-0"><i class="fas fa-key me-2 text-warning"></i> Chave SSH (.pem)</h5>
                    <span id="pem-status-badge" class="badge <?php echo $keyStatus['exists'] ? 'bg-success' : 'bg-danger'; ?>">
                        <i class="fas <?php echo $keyStatus['exists'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                        <?php echo htmlspecialchars($keyStatus['label']); ?>
                    </span>
                </div>
                <p class="small text-muted mb-0" id="pem-status-detail">
                    <?php if ($keyStatus['exists']): ?>
                        Arquivo: <code><?php echo htmlspecialchars($keyStatus['path']); ?></code>
                    <?php else: ?>
                        Nenhuma chave `.pem` encontrada no diretório do servidor.
                    <?php endif; ?>
                </p>
            </div>

            <!-- Abas para Upload de Arquivo ou Colar Texto -->
            <div class="card card-custom p-3 mb-4">
                <ul class="nav nav-tabs border-secondary mb-3" id="pemTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active small" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-pane" type="button" role="tab">
                            <i class="fas fa-upload me-1"></i> Enviar Arquivo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small" id="paste-tab" data-bs-toggle="tab" data-bs-target="#paste-pane" type="button" role="tab">
                            <i class="fas fa-paste me-1"></i> Colar Texto
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pemTabContent">
                    <!-- Aba 1: Upload Arquivo -->
                    <div class="tab-pane fade show active" id="upload-pane" role="tabpanel">
                        <form id="form-pem-file" enctype="multipart/form-data">
                            <div class="mb-3">
                                <input type="file" id="pem-file-input" name="pem_file" accept=".pem" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <button type="submit" id="btn-upload-pem" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-upload me-1"></i> Enviar Arquivo PEM</button>
                        </form>
                    </div>

                    <!-- Aba 2: Colar Texto -->
                    <div class="tab-pane fade" id="paste-pane" role="tabpanel">
                        <form id="form-pem-text">
                            <div class="mb-3">
                                <textarea id="pem-text-input" name="pem_text" rows="5" class="form-control bg-dark text-white border-secondary font-monospace small" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----" required></textarea>
                            </div>
                            <button type="submit" id="btn-save-pem-text" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-save me-1"></i> Salvar Texto da Chave</button>
                        </form>
                    </div>
                </div>
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
    const pemStatusBadge = document.getElementById('pem-status-badge');
    const pemStatusDetail = document.getElementById('pem-status-detail');

    function showAlert(msg, isSuccess = true) {
        alertArea.innerHTML = `
            <div class="alert alert-${isSuccess ? 'success' : 'danger'} alert-dismissible fade show" role="alert">
                <i class="fas fa-${isSuccess ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
    }

    function updatePemKeyUI(keyStatus) {
        if (!keyStatus) return;
        if (keyStatus.exists) {
            pemStatusBadge.className = 'badge bg-success';
            pemStatusBadge.innerHTML = `<i class="fas fa-check-circle me-1"></i>${keyStatus.label}`;
            pemStatusDetail.innerHTML = `Arquivo: <code>${keyStatus.path}</code>`;
        } else {
            pemStatusBadge.className = 'badge bg-danger';
            pemStatusBadge.innerHTML = `<i class="fas fa-times-circle me-1"></i>Não Instalada`;
            pemStatusDetail.innerHTML = `Nenhuma chave <code>.pem</code> encontrada no servidor.`;
        }
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
            'upload_pem': 'Upload Chave PEM',
            'save_pem_text': 'Salvar Texto PEM'
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

            if (data.keyStatus) {
                updatePemKeyUI(data.keyStatus);
            }

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

    // Evento Form Upload PEM (Arquivo)
    document.getElementById('form-pem-file').addEventListener('submit', (e) => {
        e.preventDefault();
        const fileInput = document.getElementById('pem-file-input');
        if (!fileInput.files.length) return;

        const fd = new FormData();
        fd.append('action', 'upload_pem');
        fd.append('pem_file', fileInput.files[0]);
        runAction('upload_pem', fd);
    });

    // Evento Form Colar Texto PEM
    document.getElementById('form-pem-text').addEventListener('submit', (e) => {
        e.preventDefault();
        const pemText = document.getElementById('pem-text-input').value;
        if (!pemText.trim()) return;

        const fd = new FormData();
        fd.append('action', 'save_pem_text');
        fd.append('pem_text', pemText);
        runAction('save_pem_text', fd);
    });

    // Carrega o Git Status inicial via AJAX sem dar reload na página
    runAction('status');
});
</script>
</body>
</html>