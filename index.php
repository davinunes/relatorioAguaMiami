<!-- removido autorefresh -->

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

<script src="https://code.highcharts.com/11.1.0/highcharts.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
<link rel="stylesheet" href="css.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<div class=''>

<script>
Highcharts.setOptions({
    time: {
        timezone: 'America/Recife'
    }
}); 
</script>

<div class='row'>

<?php
session_start();
// Oculta warnings e notices, como no seu original
error_reporting(E_ALL & ~(E_NOTICE | E_WARNING));

// 1. VERIFICA SE O USUÁRIO ESTÁ LOGADO
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$dev = $_SESSION['user_role'] === 'dev' ? true : false;

// delete from h2o.leituras WHERE sensor = 4 and `timestamp` BETWEEN '2022-03-14 14:03:00' and '2022-04-01 16:39:00' and Valor > 300

$cor[1] = "rgb(067, 067, 072)";	#Preto
$cor[2] = "rgb(124, 181, 236)";	#Azul
$cor[3] = "rgb(144, 237, 125)";	#Verde
$cor[4] = "rgb(247, 163, 092)";	#Laranja
$cor[5] = "rgb(128, 133, 233)";	#Roxo
$cor[6] = "rgb(255, 010, 010)";	#Vermelho

include "database.php";

function dbSize(){
	
	$sql = 'SELECT table_schema "Database", ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) "Size(MB)" FROM information_schema.tables  WHERE table_schema = "h2o"';
	$r = DBQ($sql);
	
	// var_dump($r);
	return $r[0]["Size(MB)"];
}

function dbRecords(){
	
	$sql = "SELECT count(1) as leituras from h2o.leituras WHERE sensor BETWEEN '1' and '6'";
	$r = DBQ($sql);
	
	// var_dump($r);
	return $r[0][leituras];
}


function dbstart(){
	
	$sql = "SELECT l.timestamp as leituras from h2o.leituras l WHERE l.sensor BETWEEN '1' and '6' order by l.id asc limit 1";
	$r = DBQ($sql);
	
	$time = strtotime($r[0][leituras]);
	return date('d-m-Y', $time);
}

if($_GET['start']){
	$start = date("Y-m-d H:i:s", strtotime($_GET[start]));
	$start = "'$start'";
}else{
	$now = date("Y-m-d H:i:s");
	$start = date("Y-m-d H:i:s", strtotime($now . ' -1 day'));
	$start = "'$start'";
}

if($_GET['end']){
	$end = date("Y-m-d H:i:s", strtotime($_GET[end]));
	$end = "'$end'";
	$zoomFiltro = true;
}else{
	$now = date("Y-m-d H:i:s");
	$end = "'$now'";
	$zoomFiltro = false;
}


// Pega o ID do usuário da sessão
$user_id = $_SESSION['user_id'];
$user_id_escaped = DBEscape($user_id); // Escapar por segurança, embora venha da sessão

// A consulta agora busca apenas os sensores associados ao usuário logado.
$sql = "SELECT r.* FROM h2o.reservatorio r
        JOIN h2o.usuario_sensores us ON r.sensor = us.sensor_id
        WHERE us.usuario_id = '$user_id_escaped' 
          AND r.ativo = true 
        ORDER BY r.nome ASC";
$caixas = DBQ($sql);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Seus Gráficos</title>
    <!-- removido autorefresh -->
 <!--    <meta name="viewport" content="width=device-width, initial-scale=1.0">  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css.css">
</head>
<body>

<div class="container">
    <div class="progress">
        <div id='timer' class="determinate" style="width: 0%"></div>
    </div>

    <div class="row" style="margin-top: 20px;">
        <div class="col s8">
            <h5>Bem-vindo, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h5>
        </div>
        <div class="col s4 right-align">
            <a href="#modal-contatos" class="btn green modal-trigger"><i class="material-icons left">notifications</i>Contatos</a>
        </div>
    </div>
    <div class="row">
        <form class="col s12" method="GET" action="">
            <div class="input-field col l4 s4">
                <input id="start" name="start" type="date" class="validate" value="<?= htmlspecialchars($_GET['start']) ?>">
                <label for="start">De</label>
            </div>
            <div class="input-field col l4 s4">
                <input id="end" name="end" type="date" class="validate" value="<?= htmlspecialchars($_GET['end']) ?>">
                <label for="end">Até</label>
            </div>
            <div class="input-field col l2 s2">
                <button class="btn waves-effect waves-light" type="submit" name="action">Filtrar</button>
            </div>
            <div class="input-field col l1 s2 right-align">
                <a href="logout.php" class="btn red">Sair</a>
            </div>
            <?php
            // A variável $dev foi definida no topo do seu PHP
            if ($dev) { ?>
                <div class="input-field col l1 s2 right-align">
                    <a href="/adm" class="btn blue">ADM</a>
                </div>
            <?php } ?>
        </form>
    </div>
    <div class="row">
        <?php
        if (empty($caixas)) {
            echo "<div class='col s12'><div class='card-panel yellow lighten-3'><p>Nenhum sensor associado a este usuário.</p></div></div>";
        } else {
            foreach ($caixas as $caixa) {
                // Criamos o placeholder com um loader do Materialize
                echo "
                <div class='col s12'>
                    <div class='card-panel'>
                        <div class='chart-container' id='grafico{$caixa['sensor']}' data-sensor-id='{$caixa['sensor']}'>
                            <p>Carregando dados para '<strong>" . htmlspecialchars($caixa['nome']) . "</strong>'...</p>
                            <div class='progress'>
                                <div class='indeterminate'></div>
                            </div>
                        </div>
                    </div>
                </div>";
            }
        }
        ?>
    </div>

    <!-- Modal para Gerenciar Contatos -->
    <div id="modal-contatos" class="modal modal-fixed-footer">
        <div class="modal-content">
            <h4>Contatos de Notificação</h4>
            <p>Cadastre os números de WhatsApp (no formato 55DDD9XXXXYYYY) para receber alertas automáticos de nível baixo ou falta de comunicação.</p>
            
            <div class="row">
                <div class="input-field col s12 m6">
                    <select id="contato-sensor-select" class="browser-default" style="display:block; width:100%;">
                        <option value="" disabled selected>Selecione um Sensor</option>
                        <?php if (!empty($caixas)) foreach ($caixas as $caixa): ?>
                            <option value="<?= $caixa['sensor'] ?>"><?= htmlspecialchars($caixa['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-field col s12 m6">
                    <input id="contato-numero" type="text" placeholder="Ex: 5561999999999">
                    <label for="contato-numero">Número WhatsApp</label>
                </div>
            </div>
            <div class="row right-align">
                <button id="btn-adicionar-contato" class="btn blue waves-effect waves-light">Adicionar Número</button>
            </div>

            <hr style="border: 0; border-top: 1px solid #e0e0e0; margin: 20px 0;">

            <h5>Números Vinculados</h5>
            <div id="lista-contatos-carregando" class="progress" style="display: none;">
                <div class="indeterminate"></div>
            </div>
            <table class="striped">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th class="right-align">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabela-contatos-corpo">
                    <tr>
                        <td colspan="2" class="center-align grey-text">Selecione um sensor acima para ver os contatos.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="modal-footer">
            <a href="#!" class="modal-close waves-effect waves-grey btn-flat">Fechar</a>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script src="https://code.highcharts.com/11.1.0/highcharts.js"></script>

<script>
Highcharts.setOptions({
    time: {
        timezone: 'America/Recife'
    }
});

// Global object to store active chart instances and their metadata
var chartInstances = {};
var updateInterval = 120; // seconds
var progress = 0;
var timer = null;

// Function to update charts dynamically
function updateChartsData() {
    for (const sensorId in chartInstances) {
        if (!chartInstances.hasOwnProperty(sensorId)) continue;
        
        const instance = chartInstances[sensorId];
        const chart = instance.chart;
        const lastTimestampJs = instance.lastTimestampJs;
        
        if (!lastTimestampJs) continue;
        
        const sinceSeconds = Math.floor(lastTimestampJs / 1000);
        
        $.ajax({
            url: 'get_new_readings.php',
            type: 'GET',
            data: {
                sensor: sensorId,
                since: sinceSeconds
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.newPoints && response.newPoints.length > 0) {
                    const series = chart.series[0];
                    let maxTimestampJs = lastTimestampJs;
                    
                    response.newPoints.forEach(function(pt) {
                        const currentTimestampJs = pt.timestamp_js;
                        const lastPointTimestampJs = maxTimestampJs;
                        const intervalSeconds = (currentTimestampJs - lastPointTimestampJs) / 1000;
                        
                        // Insert null point if there is a gap greater than 10 minutes (600 seconds)
                        if (intervalSeconds > 600) {
                            series.addPoint([currentTimestampJs - 1000, null], false);
                        }
                        
                        series.addPoint([currentTimestampJs, pt.valor_plot], false);
                        maxTimestampJs = Math.max(maxTimestampJs, currentTimestampJs);
                    });
                    
                    instance.lastTimestampJs = maxTimestampJs;
                    
                    if (response.ult_att) {
                        chart.setTitle(null, { text: 'Ultima atualização: ' + response.ult_att });
                    }
                    
                    // Update the "Now" reference line (series[1])
                    const d = new Date();
                    const now = Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes(), d.getSeconds());
                    const yesterday = now - 24 * 60 * 60 * 1000;
                    chart.series[1].setData([
                        [yesterday, -240, '24h antes'],
                        [now, -240, 'Agora']
                    ], false);
                    
                    chart.redraw();
                } else {
                    // Update the "Now" line to keep it moving forward
                    const d = new Date();
                    const now = Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes(), d.getSeconds());
                    const yesterday = now - 24 * 60 * 60 * 1000;
                    chart.series[1].setData([
                        [yesterday, -240, '24h antes'],
                        [now, -240, 'Agora']
                    ], true);
                }
            },
            error: function() {
                console.error("Erro ao atualizar o sensor " + sensorId);
            }
        });
    }
}

// --- A MÁGICA ACONTECE AQUI ---
$(document).ready(function() {
    // Inicializa componentes do Materialize (como os seletores de data)
    M.AutoInit();

    // Pega as datas do formulário ou usa as default
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start') || '';
    const endDate = urlParams.get('end') || '';
    const isHistoricalView = (endDate !== '');

    // Configure the progress bar timer if it's not a historical view
    if (isHistoricalView) {
        $(".progress").first().hide(); // Hide the top-level progress bar
    } else {
        timer = setInterval(function() {
            progress++;
            let x = (progress / updateInterval) * 100;
            $("#timer").css("width", x + "%");
            if (progress >= updateInterval) {
                progress = 0;
                updateChartsData();
            }
        }, 1000);
    }

    // Para cada placeholder de gráfico...
    $('.chart-container').each(function() {
        const container = $(this);
        const sensorId = container.data('sensor-id');
        const containerId = container.attr('id');

        // ...faça uma chamada AJAX para buscar os dados
        $.ajax({
            url: 'get_chart_data.php',
            type: 'GET',
            data: {
                sensor: sensorId,
                start: startDate,
                end: endDate
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Find the last valid timestamp in the initial series data BEFORE Highcharts mutates the options object
                    const seriesData = response.chartOptions.series[0].data;
                    let maxTimestampJs = 0;
                    if (seriesData) {
                        for (let i = seriesData.length - 1; i >= 0; i--) {
                            const pt = seriesData[i];
                            if (pt && pt[0] !== null && pt[0] !== undefined) {
                                maxTimestampJs = Math.max(maxTimestampJs, pt[0]);
                            }
                        }
                    }

                    // Se sucesso, renderiza o gráfico com os dados recebidos
                    const chart = Highcharts.chart(containerId, response.chartOptions);
                    
                    chartInstances[sensorId] = {
                        chart: chart,
                        lastTimestampJs: maxTimestampJs
                    };
                } else {
                    // Se falhar (ex: sem dados), mostra a mensagem de erro
                    container.html("<div class='card-panel red lighten-4'><p class='center-align'>" + response.message + "</p></div>");
                }
            },
            error: function() {
                // Em caso de erro no servidor/ajax
                container.html("<div class='card-panel red lighten-3'><p class='center-align'>Ocorreu um erro ao carregar os dados para o sensor " + sensorId + ".</p></div>");
            }
        });
    });

    // Quando o usuário muda o sensor no dropdown, recarrega a lista de contatos
    $("#contato-sensor-select").change(function() {
        const sensorId = $(this).val();
        carregarContatos(sensorId);
    });

    // Adicionar novo contato
    $("#btn-adicionar-contato").click(function() {
        const sensorId = $("#contato-sensor-select").val();
        const numero = $("#contato-numero").val();

        if (!sensorId) {
            M.toast({html: 'Por favor, selecione um sensor.', classes: 'orange'});
            return;
        }
        if (!numero || numero.trim() === '') {
            M.toast({html: 'Por favor, insira o número de WhatsApp.', classes: 'orange'});
            return;
        }

        $.ajax({
            url: 'ajax_contatos.php',
            type: 'POST',
            data: {
                action: 'add',
                sensor_id: sensorId,
                numero: numero
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    M.toast({html: response.message, classes: 'green'});
                    $("#contato-numero").val('');
                    carregarContatos(sensorId);
                } else {
                    M.toast({html: response.message, classes: 'red'});
                }
            },
            error: function() {
                M.toast({html: 'Erro ao adicionar contato.', classes: 'red'});
            }
        });
    });

    // Deletar contato
    $(document).on('click', '.btn-deletar-contato', function() {
        const id = $(this).data('id');
        const sensorId = $("#contato-sensor-select").val();

        if (confirm('Deseja realmente remover este número de notificação?')) {
            $.ajax({
                url: 'ajax_contatos.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    id: id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        M.toast({html: response.message, classes: 'green'});
                        carregarContatos(sensorId);
                    } else {
                        M.toast({html: response.message, classes: 'red'});
                    }
                },
                error: function() {
                    M.toast({html: 'Erro ao remover contato.', classes: 'red'});
                }
            });
        }
    });
});

function carregarContatos(sensorId) {
    if (!sensorId) return;
    $("#lista-contatos-carregando").show();
    $("#tabela-contatos-corpo").html('');
    
    $.ajax({
        url: 'ajax_contatos.php',
        type: 'GET',
        data: {
            action: 'list',
            sensor_id: sensorId
        },
        dataType: 'json',
        success: function(response) {
            $("#lista-contatos-carregando").hide();
            if (response.success) {
                let html = '';
                if (response.contatos && response.contatos.length > 0) {
                    response.contatos.forEach(function(c) {
                        html += `<tr>
                            <td>${c.numero}</td>
                            <td class="right-align">
                                <button class="btn-flat red-text btn-deletar-contato" data-id="${c.id}">
                                    <i class="material-icons">delete</i>
                                </button>
                            </td>
                        </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="2" class="center-align grey-text">Nenhum número cadastrado para este sensor.</td></tr>';
                }
                $("#tabela-contatos-corpo").html(html);
            } else {
                M.toast({html: response.message, classes: 'red'});
            }
        },
        error: function() {
            $("#lista-contatos-carregando").hide();
            M.toast({html: 'Erro ao carregar contatos.', classes: 'red'});
        }
    });
}
</script>

</body>
</html>