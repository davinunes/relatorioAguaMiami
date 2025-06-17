<?php
// login.php (adaptado para suas funções mysqli)
session_start();
// Seu arquivo de banco de dados com as funções
require 'database.php'; 

// Se o usuário já estiver logado, redireciona para o index.
if (isset($_SESSION['user_id'])) {
    header('Location: index2.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (empty($login) || empty($senha)) {
        $error_message = 'Por favor, preencha o login e a senha.';
    } else {
        // 1. Proteger a entrada do usuário com sua função
        $login_escaped = DBEscape($login);

        $sql = "SELECT id, nome, senha, role, grupo_id FROM usuarios WHERE login = '$login_escaped'";
		$user = DBQ($sql);

        // Verifica se o usuário existe e se a senha está correta
        // A função password_verify não muda, pois é do PHP
        if ($user && password_verify($senha, $user[0]['senha'])) {
			// Armazena dados na sessão
			$_SESSION['user_id'] = $user[0]['id'];
			$_SESSION['user_name'] = $user[0]['nome'];
			$_SESSION['user_role'] = $user[0]['role'];         // <-- NOVO
			$_SESSION['user_grupo_id'] = $user[0]['grupo_id']; // <-- NOVO
			
			// Redireciona para a página principal
			header('Location: index2.php');
			exit();
		} else {
			$error_message = 'Login ou senha inválidos.';
		}
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - H2O Monitor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; background-color: #f5f5f5; }
        .login-card { width: 400px; }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="card-content">
            <span class="card-title center-align">Acessar Sistema</span>
				<form method="POST" action="login.php">
					<div class="input-field">
						<input id="login" type="text" name="login" required>
						<label for="login" class="active">Usuário</label>
					</div>
					<div class="input-field">
						<input id="senha" type="password" name="senha" required>
						<label for="senha" class="active">Senha</label>
					</div>
					<?php if ($error_message): ?>
						<p class="red-text center-align"><?php echo htmlspecialchars($error_message); ?></p>
					<?php endif; ?>
					<div class="center-align">
						<button class="btn waves-effect waves-light" type="submit">Entrar</button>
					</div>
				</form>
        </div>
    </div>
</body>
</html>