<?php
// gerar_senha.php

// IMPORTANTE: Coloque a senha que você quer usar para o admin aqui dentro
$senha_texto_plano = 'salsemsal'; 

// Gera o hash seguro
$hash = password_hash($senha_texto_plano, PASSWORD_DEFAULT);

// Exibe o hash na tela
echo "Criptografia da senha gerada com sucesso!<br><br>";
echo "Copie a linha abaixo e cole no seu comando SQL:<br><br>";
echo "<strong>" . htmlspecialchars($hash) . "</strong>";

?>