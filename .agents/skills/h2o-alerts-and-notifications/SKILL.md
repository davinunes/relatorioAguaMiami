---
name: h2o-alerts-and-notifications
description: Arquitetura de verificação de alertas de reservatório, detecção de anomalias físicas na sonda (parada cardíaca, ruídos e falta de sinal), tendência do reservatório (enchendo/esvaziando/estável), notificações via WhatsApp e geração remota de imagens de gráficos PNG para integração (ex. n8n).
---

# H2O Alerts & Notifications Architecture

Esta skill descreve o subsistema de checagem de alertas, diagnóstico de anomalias de hardware, tendência do reservatório, gestão de contatos de notificação e renderização remota de imagens de gráficos.

## Monitoramento, Diagnóstico e Tendência ([alert.php](file:///e:/DEV/relatorioAguaMiami/alert.php))

- Consulta as leituras recentes do `sensor` em uma janela de diagnóstico (padrão de 3h, ajustável via `hours`).
- Calcula a **Tendência Dinâmica** no final da curva (`cauda`):
  - **`ENCHENDO`**: Subida contínua do nível ($\Delta V > +0.5\text{ cm}$).
  - **`ESVAZIANDO`**: Queda contínua do nível ($\Delta V < -0.5\text{ cm}$).
  - **`ESTAVEL`**: Nível estável ($|\Delta V| \le 0.5\text{ cm}$).
  - **`DESCONHECIDO`**: Leituras recentes insuficientes.
- Retorna o objeto `diagnostico` com `status` (`OK`, `ATENCAO`, `CRITICO`) e flags de diagnóstico:
  - **`alerta_parada_cardiaca`**: Detecta sonda travada/oxidada (sinal plano sem flutuação de zigue-zague).
  - **`alerta_ruido_excessivo`**: Detecta falso-contato/oxidação parcial (mais de 15% de leituras com ruído).
  - **`alerta_sem_comunicacao`**: Detecta interrupção no envio de dados (> 60 min sem pings).
  - **`alerta_nivel`**: Detecta nível crítico (> 75cm).

## Gerador de Imagens de Gráficos ([get_chart_image.php](file:///e:/DEV/relatorioAguaMiami/get_chart_image.php))

- Monta a estrutura das opções do Highcharts para determinado período (`hours` ou datas `start`/`end`).
- Envia o JSON do gráfico via POST para o servidor oficial de exportação do Highcharts (`https://export.highcharts.com`).
- Retorna a imagem em formato PNG pronta para anexar em mensagens de alerta (WhatsApp / n8n).

## Gestão de Contatos WhatsApp ([ajax_contatos.php](file:///e:/DEV/relatorioAguaMiami/ajax_contatos.php))

- Tabela de destino: `h2o.contatos_notificacao`.
- Ações: `list`, `add`, `delete`.
- Formatação de número: Garante o sufixo `@s.whatsapp.net` para automações como n8n.
