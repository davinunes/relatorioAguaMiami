<?php
// logout.php
session_start();
session_unset();
session_destroy();

// Redirecionar para uma página intermediária que limpa o localStorage
header('Location: limpar_credenciais.php');
exit();
?>