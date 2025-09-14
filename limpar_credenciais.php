<?php
// limpar_credenciais.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Saindo do sistema...</title>
    <script>
        // Remover as credenciais do localStorage
        localStorage.removeItem('h2o_login');
        localStorage.removeItem('h2o_senha');
        
        // Redirecionar para a página de login após a limpeza
        window.location.href = 'login.php';
    </script>
</head>
<body>
    <p>Saindo do sistema... Aguarde um momento.</p>
</body>
</html>