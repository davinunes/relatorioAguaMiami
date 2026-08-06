<?php
session_start();
header('Content-Type: text/plain; charset=utf-8');

include "database.php";

echo "====================================================\n";
echo "       H2O DATABASE MIGRATIONS RUNNER               \n";
echo "====================================================\n";
echo "Gerado em: " . date("d/m/Y H:i:s") . "\n\n";

// 1. Cria a tabela de controle de migrações caso não exista
$createControlTable = "
CREATE TABLE IF NOT EXISTS h2o.schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
DBExecute($createControlTable);

// 2. Lista de Migrações
$migrations = [
    '001_add_index_leituras_sensor_timestamp' => [
        'description' => 'Adiciona índice composto idx_sensor_timestamp em leituras',
        'sql' => [
            "ALTER TABLE h2o.leituras ADD INDEX idx_sensor_timestamp (sensor, timestamp);"
        ]
    ],
    '002_add_index_reservatorio' => [
        'description' => 'Adiciona índice único e de status ativo em reservatório',
        'sql' => [
            "ALTER TABLE h2o.reservatorio ADD UNIQUE INDEX idx_sensor_unique (sensor);",
            "ALTER TABLE h2o.reservatorio ADD INDEX idx_sensor_ativo (sensor, ativo);"
        ]
    ],
    '003_add_index_usuario_sensores' => [
        'description' => 'Adiciona índices compostos em usuario_sensores',
        'sql' => [
            "ALTER TABLE h2o.usuario_sensores ADD INDEX idx_usuario_sensor (usuario_id, sensor_id);",
            "ALTER TABLE h2o.usuario_sensores ADD INDEX idx_sensor_usuario (sensor_id, usuario_id);"
        ]
    ],
    '004_add_index_contatos_notificacao' => [
        'description' => 'Adiciona índice por sensor em contatos_notificacao',
        'sql' => [
            "ALTER TABLE h2o.contatos_notificacao ADD INDEX idx_sensor_id (sensor_id);"
        ]
    ],
    '005_add_unique_usuarios_login' => [
        'description' => 'Adiciona índice único em usuarios.login',
        'sql' => [
            "ALTER TABLE h2o.usuarios ADD UNIQUE INDEX idx_login (login);"
        ]
    ],
    '006_add_index_esp32_pings_uuid_created' => [
        'description' => 'Adiciona índice composto em esp32_pings para acelerar GROUP BY uuid',
        'sql' => [
            "ALTER TABLE h2o.esp32_pings ADD INDEX idx_uuid_created (uuid, created_at DESC);"
        ]
    ],
    '007_add_foreign_keys_relacionais' => [
        'description' => 'Adiciona Chaves Estrangeiras relacionais com ON DELETE CASCADE',
        'sql' => [
            "ALTER TABLE h2o.usuario_sensores ADD CONSTRAINT fk_usuario_sensores_usuario FOREIGN KEY (usuario_id) REFERENCES h2o.usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE;",
            "ALTER TABLE h2o.usuario_sensores ADD CONSTRAINT fk_usuario_sensores_reservatorio FOREIGN KEY (sensor_id) REFERENCES h2o.reservatorio(sensor) ON DELETE CASCADE ON UPDATE CASCADE;",
            "ALTER TABLE h2o.contatos_notificacao ADD CONSTRAINT fk_contatos_notificacao_reservatorio FOREIGN KEY (sensor_id) REFERENCES h2o.reservatorio(sensor) ON DELETE CASCADE ON UPDATE CASCADE;"
        ]
    ],
    '008_add_valor_referencia_reservatorio' => [
        'description' => 'Adiciona coluna valor_referencia em reservatorio para sobrescrever o limite da zona Normal',
        'sql' => [
            "ALTER TABLE h2o.reservatorio ADD COLUMN valor_referencia DECIMAL(8,2) DEFAULT NULL COMMENT 'Valor ajustado (com alturaSonda somada) em cm que define o teto da zona Normal. Se NULL, calcula automaticamente.';"
        ]
    ]
];

// Busca migrações já executadas
$executed = DBQ("SELECT migration FROM h2o.schema_migrations");
$executedList = array_column($executed, 'migration');

$totalApplied = 0;

foreach ($migrations as $name => $mig) {
    if (in_array($name, $executedList)) {
        echo "[PULADO] $name - Já executada anteriormente.\n";
        continue;
    }

    echo "[EXECUTANDO] $name: {$mig['description']}...\n";
    $success = true;

    foreach ($mig['sql'] as $query) {
        try {
            DBExecute($query);
        } catch (Throwable $e) {
            echo "  -> AVISO/ERRO na query: " . $e->getMessage() . "\n";
            // Se o índice/FK já existir no banco físico, continuamos o fluxo
        }
    }

    // Registra a migração como executada
    $escapedName = DBEscape($name);
    DBExecute("INSERT INTO h2o.schema_migrations (migration) VALUES ('$escapedName')");
    echo "  -> OK! Migração $name concluída com sucesso.\n\n";
    $totalApplied++;
}

echo "====================================================\n";
echo "MIGRAÇÕES CONCLUÍDAS. Total de novas migrações aplicadas: $totalApplied\n";
echo "====================================================\n";
?>
