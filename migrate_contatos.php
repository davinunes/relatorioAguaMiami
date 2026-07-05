<?php
require 'database.php';

// SQL para criar a tabela de contatos de notificação
$sql = "CREATE TABLE IF NOT EXISTS h2o.contatos_notificacao (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sensor_id int(10) unsigned DEFAULT NULL,
  grupo_id bigint(20) unsigned DEFAULT NULL,
  numero varchar(100) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

if (DBExecute($sql)) {
    echo "<h1>Migração Concluída com Sucesso!</h1>";
    echo "<p>A tabela <code>contatos_notificacao</code> foi criada ou já existia no banco de dados.</p>";
} else {
    echo "<h1>Erro na Migração</h1>";
    echo "<p>Não foi possível criar a tabela <code>contatos_notificacao</code>.</p>";
}
?>
