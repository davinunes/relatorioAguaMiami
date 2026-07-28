---
name: h2o-alerts-and-notifications
description: Arquitetura de verificação de alertas de reservatório, detecção de anomalias físicas na sonda (parada cardíaca, ruídos e falta de sinal), notificações via WhatsApp e geração remota de imagens de gráficos PNG para integração (ex. n8n).
---

# H2O Alerts & Notifications Architecture

Esta skill descreve o subsistema de checagem de alertas, diagnóstico de anomalias de hardware, gestão de contatos de notificação e renderização remota de imagens de gráficos.

## Monitoramento e Diagnóstico de Alertas ([alert.php](file:///e:/DEV/relatorioAguaMiami/alert.php))

- Consulta as leituras recentes do `sensor` em uma janela de diagnóstico (padrão de 3h, ajustável via `hours`).
- Calcula métricas estatísticas: variação de nível (`max - min`), desvio padrão (`stddev`), percentual de ruídos e minutos sem comunicação.
- Retorna o objeto `diagnostico` com o status de saúde do sensor (`OK`, `ATENCAO`, `CRITICO`):
  - **`alerta_parada_cardiaca`**: Detecta sonda travada/oxidada (sinal plano sem flutuação de zigue-zague nas últimas 3h).
  - **`alerta_ruido_excessivo`**: Detecta falso-contato/oxidação parcial (mais de 15% de leituras com ruído).
  - **`alerta_sem_comunicacao`**: Detecta interrupção no envio de dados (> 60 min sem pings).
  - **`alerta_nivel`**: Detecta nível crítico (> 75cm).
- Retorna a lista de contatos vinculados e a URL da imagem do gráfico (`chart_image_url`).

## Gerador de Imagens de Gráficos ([get_chart_image.php](file:///e:/DEV/relatorioAguaMiami/get_chart_image.php))

- Monta a estrutura das opções do Highcharts para determinado período (`hours` ou datas `start`/`end`).
- Envia o JSON do gráfico via POST para o servidor oficial de exportação do Highcharts (`https://export.highcharts.com`).
- Retorna a imagem em formato PNG pronta para anexar em mensagens de alerta (WhatsApp / n8n).

## Gestão de Contatos WhatsApp ([ajax_contatos.php](file:///e:/DEV/relatorioAguaMiami/ajax_contatos.php))

- Tabela de destino: `h2o.contatos_notificacao`.
- Ações: `list`, `add`, `delete`.
- Formatação de número: Garante o sufixo `@s.whatsapp.net` caso não esteja presente, garantindo compatibilidade com motores de automação como n8n.
