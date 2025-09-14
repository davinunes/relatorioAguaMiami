<?php

	if(isset($_GET['git']) and  $_GET['git'] == 1){
		//
		// exit;
		
		$mensagem = $_POST['msg'] ? $_POST['msg'] : "Atualização";
		// echo $mensagem."<br/>";
		$cmd = 'cd /var/www/relatorioAguaMiami && git pull && git add . && git commit -m "'.$mensagem.'" && git push';
		$comando = "/usr/bin/python3 /var/www/html/py/ssh.py '".$cmd."'";
		
		// var_dump($comando);

		$output = shell_exec($comando);
		echo "<pre>".$output."</pre>";
		exit;
	}
	
	$cmd = 'cd /var/www/relatorioAguaMiami;/usr/bin/git status 2>&1;/usr/bin/git config credential.helper cache 2>&1;'; 
	// $cmd = '/usr/bin/git config --global --add safe.directory /var/www/relatorioAguaMiami 2>&1'; 
	// $cmd = 'whoami';
	
	$comando = "/usr/bin/python3 /var/www/html/py/ssh.py '".$cmd."'";

	$output = shell_exec($comando);
	echo "<pre>".$output."</pre>";
?>
<form action="git.php?git=1" method="post">
        <label for="texto">Descrição do Commit:</label>
        <input type="text" id="msg" name="msg">
        <input type="submit" value="Enviar">
</form>