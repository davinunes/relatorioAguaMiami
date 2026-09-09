<?php
/**
 * Gerenciador de Atualizações Git & Migrações de Banco de Dados
 * Projeto: relatorioAguaMiami
 * Path: /var/www/relatorioAguaMiami
 * Host: localhost
 */

$projectPath = '/var/www/relatorioAguaMiami';
$sshHost     = 'localhost';
$sshScript   = '/usr/bin/python3 /var/www/html/py/ssh.py';
$pemSaveDir  = '/var/www/html/py';
$pemSavePath = $pemSaveDir . '/chave.pem';

$output = '';
$actionPerformed = '';
$uploadAlert = '';

// Função auxiliar para executar comando via ssh.py (com fallback local caso necessário)
function executarComando($cmd, $sshScript, $projectPath) {
    $cmdFull = "cd {$projectPath} && {$cmd} 2>&1";
    if (file_exists('/var/www/html/py/ssh.py')) {
        $comandoSSH = "/usr/bin/python3 {$sshScript} " . escapeshellarg($cmdFull);
        $res = shell_exec($comandoSSH);
    } else {
        $res = shell_exec($cmdFull);
    }
    return $res ? trim($res) : "Sem retorno ou execução vazia.";
}

// 1. Upload da Chave PEM
if (isset($_FILES['pem_file']) && $_FILES['pem_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['pem_file']['tmp_name'];
    
    if (!is_dir($pemSaveDir)) {
        @mkdir($pemSaveDir, 0755, true);
    }
    
    if (move_uploaded_file($fileTmpPath, $pemSavePath)) {
        chmod($pemSavePath, 0600);
        $uploadAlert = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <i class='fas fa-check-circle me-2'></i>Chave PEM enviada com sucesso para <code>{$pemSavePath}</code> com permissão <code>0600</code>.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $uploadAlert = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <i class='fas fa-exclamation-triangle me-2'></i>Falha ao salvar a chave PEM em <code>{$pemSavePath}</code>. Verifique as permissões da pasta.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// 2. Ações de Execução
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'status') {
    $actionPerformed = "Git Status";
    $output = executarComando("git status", $sshScript, $projectPath);
} elseif ($action === 'pull') {
    $actionPerformed = "Git Pull";
    $output = executarComando("git pull", $sshScript, $projectPath);
} elseif ($action === 'commit_push') {
    $msg = !empty($_POST['msg']) ? $_POST['msg'] : "Atualização automática via Web " . date("d/m/Y H:i:s");
    $msgEscaped = escapeshellarg($msg);
    $actionPerformed = "Git Commit & Push";
    $output = executarComando("git add . && git commit -m {$msgEscaped} && git push", $sshScript, $projectPath);
} elseif ($action === 'migrate') {
    $actionPerformed = "Execução de Migrações (php migrates.php)";
    $output = executarComando("php migrates.php", $sshScript, $projectPath);
} elseif ($action === 'full_update') {
    $actionPerformed = "Atualização Completa (Git Pull + Migrações)";
    $output = executarComando("git pull && php migrates.php", $sshScript, $projectPath);
} else {
    $actionPerformed = "Git Status (Inicial)";
    $output = executarComando("git status", $sshScript, $projectPath);
}

// Comando pronto para copiar para o terminal Linux
$terminalCommand = "cd {$projectPath} && git pull && php migrates.php";
$sshCommand = "ssh -i {$pemSavePath} ubuntu@{$sshHost} \"cd {$projectPath} && git pull && php migrates.php\"";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Atualizações & Migrações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0e14; color: #abb2bf; font-family: system-ui, -apple-system, sans-serif; }
        .card-custom { background-color: #1c2128; border: 1px solid #30363d; border-radius: 8px; }
        .console-box { background-color: #05070a; border: 1px solid #30363d; border-radius: 6px; color: #50fa7b; font-family: monospace; min-height: 200px; max-height: 450px; overflow-y: auto; }
        .badge-path { background-color: #21262d; color: #58a6ff; border: 1px solid #30363d; }
        .btn-action { min-width: 140px; }
    </style>
</head>
<body>
<div class="container py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
        <div>
            <h2 class="text-white m-0"><i class="fas fa-code-branch text-primary me-2"></i> Painel de Atualizações & Migrações</h2>
            <small class="text-muted">Gerenciador Git e Migrações de Banco de Dados</small>
        </div>
        <div>
            <span class="badge badge-path p-2 fs-6 me-2"><i class="fas fa-server me-1"></i> Host: <?php echo htmlspecialchars($sshHost); ?></span>
            <span class="badge badge-path p-2 fs-6"><i class="fas fa-folder me-1"></i> <?php echo htmlspecialchars($projectPath); ?></span>
        </div>
    </div>

    <?php if ($uploadAlert) echo $uploadAlert; ?>

    <div class="row g-4">
        <!-- Ações Principais -->
        <div class="col-lg-8">
            <div class="card card-custom p-3 mb-4">
                <h5 class="text-white mb-3"><i class="fas fa-rocket me-2 text-warning"></i> Ações Rápidas</h5>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="git.php?action=status" class="btn btn-outline-info btn-action"><i class="fas fa-info-circle me-1"></i> Git Status</a>
                    <a href="git.php?action=pull" class="btn btn-primary btn-action"><i class="fas fa-cloud-download-alt me-1"></i> Git Pull</a>
                    <a href="git.php?action=migrate" class="btn btn-warning text-dark btn-action"><i class="fas fa-database me-1"></i> Rodar Migrações</a>
                    <a href="git.php?action=full_update" class="btn btn-success btn-action"><i class="fas fa-sync-alt me-1"></i> Pull + Migrate</a>
                </div>

                <hr class="border-secondary my-3">

                <!-- Commit & Push Form -->
                <form action="git.php" method="post" class="row g-2 align-items-center">
                    <input type="hidden" name="action" value="commit_push">
                    <div class="col-sm-8">
                        <input type="text" name="msg" class="form-control bg-dark text-white border-secondary" placeholder="Descrição do Commit (ex: Ajustes de layout)">
                    </div>
                    <div class="col-sm-4">
                        <button type="submit" class="btn btn-outline-success w-100"><i class="fas fa-paper-plane me-1"></i> Commit & Push</button>
                    </div>
                </form>
            </div>

            <!-- Console Output -->
            <div class="card card-custom p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="text-white m-0"><i class="fas fa-terminal me-2 text-info"></i> Saída do Console</h5>
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($actionPerformed); ?></span>
                </div>
                <div class="console-box p-3">
                    <pre class="m-0 text-wrap"><?php echo htmlspecialchars($output); ?></pre>
                </div>
            </div>
        </div>

        <!-- Painel Lateral: Upload Pem e Dica Terminal -->
        <div class="col-lg-4">
            
            <!-- Upload da Chave PEM -->
            <div class="card card-custom p-3 mb-4">
                <h5 class="text-white mb-2"><i class="fas fa-key me-2 text-warning"></i> Upload de Chave SSH (.pem)</h5>
                <p class="small text-muted mb-3">Envie a chave <code>chave.pem</code> para autenticação automática no servidor SSH.</p>
                <form action="git.php" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" name="pem_file" accept=".pem" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-warning w-100"><i class="fas fa-upload me-1"></i> Enviar Chave PEM</button>
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
</body>
</html>