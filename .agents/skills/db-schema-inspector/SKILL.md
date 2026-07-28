---
name: db-schema-inspector
description: Consulta e entende a estrutura das tabelas do banco de dados h2o via URL remota (https://app.digitalinovation.com.br/esquema.php) ou arquivo local de esquema.
---

# DB Schema Inspector (Sistema h2o)

Esta skill define as diretrizes para consultar, inspecionar e compreender a estrutura de dados do projeto de monitoramento de água.

## Fonte da Verdade do Esquema

Sempre que for necessário compreender, alterar ou criar novas funcionalidades que dependam da estrutura do banco de dados:

- **URL Remota de Produção**: `https://app.digitalinovation.com.br/esquema.php`
  - Retorna o código SQL de criação de tabelas em texto puro (`text/plain`).
- **Endpoint Local**: [esquema.php](file:///e:/DEV/relatorioAguaMiami/esquema.php)
- **Script de Conexão**: [database.php](file:///e:/DEV/relatorioAguaMiami/database.php)

## Tabelas Principais do Sistema

1. **`leituras`**:
   - `id` (bigint unsigned, auto_increment): Identificador da leitura.
   - `sensor` (int unsigned): ID do sensor transmissor.
   - `Valor` (double): Nível lido pela sonda (em centímetros).
   - `timestamp` (timestamp): Data e hora do registro da leitura.

2. **`reservatorio`**:
   - `id` (bigint unsigned): PK.
   - `sensor` (int unsigned): ID do sensor associado.
   - `nome` (varchar 100): Nome descritivo da caixa d'água / reservatório.
   - `fosso` (int unsigned): Profundidade total da caixa.
   - `alturaSonda` (int unsigned): Offset / ajuste em centímetros da altura da sonda.
   - `ativo` (tinyint 1): Indicador se o reservatório está ativo.

3. **`usuario_sensores`**:
   - Vincula `usuario_id` aos `sensor_id` para controle de acesso por usuário.

4. **`contatos_notificacao`**:
   - `id` (int/bigint): PK.
   - `sensor_id` (int): ID do sensor monitorado.
   - `numero` (varchar): Número no formato WhatsApp (`55...` ou `...@s.whatsapp.net`).

## Padrões de Consulta SQL
Utilize sempre as funções auxiliares em `database.php`:
- `DBQ($sql)`: Retorna array associativo com os resultados.
- `DBEscape($valor)`: Sanitiza strings contra SQL Injection.
- `DBExecute($sql)`: Executa comandos INSERT/UPDATE/DELETE.
