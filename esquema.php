<?php
header('Content-Type: text/plain; charset=utf-8');

include "database.php";

$tables = DBQ("SHOW TABLES");

if (empty($tables)) {
    echo "-- Nenhuma tabela encontrada no banco de dados.";
    exit();
}

echo "-- Estrutura do Banco de Dados: " . DB_DATABASE . "\n";
echo "-- Gerado em: " . date("d/m/Y H:i:s") . "\n\n";

foreach ($tables as $row) {
    $tableName = current($row);
    
    $createResult = DBQ("SHOW CREATE TABLE `$tableName`");
    if (!empty($createResult)) {
        $createSql = $createResult[0]['Create Table'] ?? $createResult[0]['Create View'] ?? null;
        if ($createSql) {
            echo "DROP TABLE IF EXISTS `$tableName`;\n";
            echo $createSql . ";\n\n";
        }
    }
}
?>
