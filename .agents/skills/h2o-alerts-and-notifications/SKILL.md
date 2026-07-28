---
name: h2o-alerts-and-notifications
description: Arquitetura de verificação de alertas de reservatório, notificações via WhatsApp e geração remota de imagens de gráficos PNG para integração (ex. n8n).
---

# H2O Alerts & Notifications Architecture

Esta skill descreve o subsistema de checagem de alertas, gestão de contatos de notificação e renderização remota de imagens de gráficos.

## Monitoramento de Alertas ([alert.php](file:///e:/DEV/relatorioAguaMiami/alert.php))

- Consulta as leituras da última 1 hora para determinado `sensor`.
- Calcula:
  - `total_itens_encontrados`: Total de medições na última hora.
  - `alerta`: Quantidade de medições onde o nível ultrapassou o limiar de alerta (ex: `Valor > 75`).
- Retorna JSON contendo os dados de alerta, a lista de contatos vinculados e a URL da imagem do gráfico (`chart_image_url`).

## Gerador de Imagens de Gráficos ([get_chart_image.php](file:///e:/DEV/relatorioAguaMiami/get_chart_image.php))

- Monta a estrutura das opções do Highcharts para determinado período (`hours` ou datas `start`/`end`).
- Envia o JSON do gráfico via POST para o servidor oficial de exportação do Highcharts (`https://export.highcharts.com`).
- Retorna a imagem em formato PNG pronta para anexar em mensagens de alerta (WhatsApp / n8n).

## Gestão de Contatos WhatsApp ([ajax_contatos.php](file:///e:/DEV/relatorioAguaMiami/ajax_contatos.php))

- Tabela de destino: `h2o.contatos_notificacao`.
- Ações: `list`, `add`, `delete`.
- Formatação de número: Garante o sufixo `@s.whatsapp.net` caso não esteja presente, garantindo compatibilidade com motores de automação como n8n.
