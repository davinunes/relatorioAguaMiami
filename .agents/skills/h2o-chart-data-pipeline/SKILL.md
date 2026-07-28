---
name: h2o-chart-data-pipeline
description: Entende a arquitetura de filtragem de ruído, geração de gráficos Highcharts, modos debug/shadow e atualização em tempo real do sistema h2o.
---

# H2O Chart & Data Pipeline Architecture

Esta skill descreve o funcionamento da camada de dados e renderização de gráficos do sistema de relatórios de água.

## Regras de Processamento de Leituras

1. **Ajuste da Sonda**:
   - Cada leitura bruta sofre ajuste somando a `alturaSonda` cadastrada no reservatório:
     `$valor_final = $h['Valor'] + $caixa['alturaSonda']`
   - O valor plotado no Highcharts é invertido para efeito visual de profundidade (`$valor_plot = $valor_final * -1`).

2. **Filtro de Ruído**:
   - Valores considerados inválidos/ruído: `$valor_final > 220 || $valor_final < 2`.
   - Em modo padrão (`debug=false`), estas leituras são descartadas da série principal e armazenadas no array `ruidos`.

3. **Tempo Limite de Descontinuidade (Lacunas)**:
   - Se o intervalo entre duas leituras sucessivas ultrapassar **20 minutos (1200 segundos)**, insere-se um ponto nulo `[timestamp_js, null]` para que a linha do gráfico seja interrompida (não gerando falsa continuidade).

## Modos de Operação via URL

- **`debug=true`**:
  - Desativa o limitador de ruídos. Todos os valores brutos lidos são enviados para a série principal do gráfico.
- **`shadow=true`**:
  - Transforma os valores descartados pelo filtro de ruído em uma série secundária intitulada **"Ruídos (Sombra)"** com marcadores visíveis e cor translúcida (`rgba(255, 99, 71, 0.6)`).

## Endpoints do Pipeline

- **[get_chart_data.php](file:///e:/DEV/relatorioAguaMiami/get_chart_data.php)**: Retorna o JSON completo com opções do Highcharts para o carregamento inicial.
- **[get_new_readings.php](file:///e:/DEV/relatorioAguaMiami/get_new_readings.php)**: Retorna novas leituras dinâmicas a partir de um timestamp `since` (em segundos).
- **[index.php](file:///e:/DEV/relatorioAguaMiami/index.php)**: Renderizador frontend em JS/jQuery que gerencia o ciclo de vida dos gráficos `chartInstances[sensorId]`.
