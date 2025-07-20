<meta http-equiv="refresh" content="180" />

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
    <meta http-equiv="refresh" content="180" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link rel="stylesheet" href="css.css">
</head>
<body>

<div class="container">
    <div class="progress">
        <div id='timer' class="determinate" style="width: 0%"></div>
    </div>

    <div class="row">
        <div class="col s12">
            <h5>Bem-vindo, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h5>
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

// Timer para a barra de atualização da página
var progress = 0;
var timer = setInterval(function() {
    let x = (progress++ / 180) * 100;
    $("#timer").css("width", x + "%");
}, 1000);

// --- A MÁGICA ACONTECE AQUI ---
$(document).ready(function() {
    // Inicializa componentes do Materialize (como os seletores de data)
    M.AutoInit();

    // Pega as datas do formulário ou usa as default
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start') || '';
    const endDate = urlParams.get('end') || '';

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
                    // Se sucesso, renderiza o gráfico com os dados recebidos
                    Highcharts.chart(containerId, response.chartOptions);
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
});
</script>

</body>
</html>