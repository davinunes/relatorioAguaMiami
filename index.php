<meta http-equiv="refresh" content="180" />

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

<script src="https://code.highcharts.com/11.1.0/highcharts.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
<link rel="stylesheet" href="css.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<div class='container'>
<div class='row'>
	<form class="col s12">
		<div class="input-field col l2 s4">
			<input id="start" name="start" type="date" class="validate">
			<label for="start">De</label>
		</div>
		<div class="input-field col l2 s4">
			<input id="end" name="end" type="date" class="validate">
			<label for="end">Até</label>
		</div>
		<div class="input-field col s2">
			<button class="btn waves-effect waves-light" type="submit" name="action">Filtrar</button>
		</div>
		<div class="input-field col s2">
			<a style="display: none;" class="alternar btn waves-effect waves-light blue" >Detalhar Gráficos</a>
			<a  class="alternar btn waves-effect waves-light blue" >Combinar Gráficos</a>
		</div>
	</form>
</div >

<script>
Highcharts.setOptions({
    time: {
        timezone: 'America/Recife'
    }
}); 
</script>

<div class='row'>

<?php
error_reporting(E_ALL & ~(E_NOTICE | E_WARNING));

// delete from h2o.leituras WHERE sensor = 4 and `timestamp` BETWEEN '2022-03-14 14:03:00' and '2022-04-01 16:39:00' and Valor > 200

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

if(true){ // Seleciona quais caixas farão parte do relatório
	$sql = "select * from h2o.reservatorio r where r.sensor <= '6' and r.ativo = true order by r.nome asc";
	$caixas = DBQ($sql);
}
?>
<div class="progress">
	<div id='timer' class="determinate" style="width: 0%"></div>
</div>

<script>
var progress = 0;
var timer = setInterval(updateProgressbar, 1000);

function updateProgressbar(){
	let x = (progress++/180)*100;
    $("#timer").css("width", x + "%");
}
</script>
<?php

if(true){ // Gera os históricos separados
	if(isset($_GET[umSensor])){
		historico($_GET[umSensor], 200, "Sensor: ".$_GET[umSensor], $start, $end, $zoomFiltro, 0);
	}else{
		foreach($caixas as $caixa){
			historico($caixa[sensor], $caixa[fosso], $caixa[nome], $start, $end, $zoomFiltro, $caixa[alturaSonda]);
		}
		
	}
}

echo "Tamanho do banco de dados: ".dbSize()."Mb sendo ".dbRecords()." leituras desde ".dbstart();

function historico($sensor, $fosso, $nome, $start, $end, $zoomFiltro, $ajuste){
	$defasado = "";
	$aviso = "";
	global $cor;
	$sql = "select * from h2o.leituras l WHERE l.sensor = ".$sensor." ORDER  by l.id desc limit 1";
	$ultimo = DBQ($sql);
	if($ultimo != NULL){
		
	
	
		$ult = date("d/m/Y H:i:s",strtotime($ultimo[0][timestamp]));
		// var_dump($ult);
		if(strtotime($ult) < (strtotime(date("d/m/Y H:i:s")) - (60*60))){
			// $defasado = "red lighten-5";
			// $aviso = "<br>ULTIMA LEITURA FAZ MAIS DE 1 HORA!";
		}
		// echo (strtotime(date("d-m-Y H:m:s")) + 60*60);
			
		$sql = "select * from h2o.leituras l WHERE l.sensor = ".$sensor." and l.`timestamp` BETWEEN $start AND $end ORDER by l.`id` asc";
		// var_dump($sql);
		$historico = DBQ($sql);
		
		$anterior = false;

		foreach($historico as $i => $h){
			$h[Valor] += $ajuste;
			if($h[Valor] > 240 or $h[Valor] == 0){
				continue;
			}
			if($anterior){
				// var_dump($anterior);
				$intervalo = strtotime($h[timestamp]) - $anterior;
				// var_dump($intervalo);
				
			}
			$anterior = strtotime($h[timestamp]);
			$h[Valor] > $fosso ? $porcentagem = $fosso : $porcentagem = $h[Valor];
			$date = date("Y-m-d H:i:s", strtotime($h[timestamp]));
			$now = date("Y-m-d H:i:s");
			if($zoomFiltro){
				$now = date("Y-m-d H:i:s", strtotime($_GET[end]));
			};
			// $pontoDeControle = "\t \t \t },{ name: 'now', \n data: [  \n  [Date.parse('".$now."'),1]],	\n	 	 	 }]	\n";
			$pontoDeControle = "\t \t \t },{ name: 'now', \n data: [  \n  [Date.UTC(".date('Y,', strtotime($now)).(date('m', strtotime($now))-1).date(',d,H,i,s', strtotime($now))."),-180]],	\n	 	 	 }]	\n"; //Date.UTC(".date('Y,m,d,H,i,s', strtotime($now)).")
			// var_dump($date);
			$progresso = 100*$porcentagem/$fosso;
			$progresso = 100 - $progresso;
			$progresso = round($progresso, 2);
			$progresso = $h[Valor]*-1;
			
			if($intervalo > 600){// Necessário ser antes da série original para que o espaço vago seja notável
				// $dados .= "\t[Date.parse('$datavazia'), null],\n";
				$dados .= "\t\t\t\t\t\t\t[Date.UTC(".date('Y,', strtotime($h[timestamp])).(date('m', strtotime($h[timestamp]))-1).date(',d,H,i,s', strtotime($h[timestamp]))."), null],//".$intervalo."\n";
			}

			// var_dump($h);
			$dados .= "\t\t\t\t\t\t\t[";
			// $dados .= "Date.parse('$h[timestamp]')".",".$progresso; 
			$dados .= "Date.UTC(".date('Y,', strtotime($h[timestamp])).(date('m', strtotime($h[timestamp]))-1).date(',d,H,i,s', strtotime($h[timestamp])).")".",".$progresso;
			// $dados .= $progresso;
			$dados .= "]";
			if($i === array_key_last($historico)){
				
			}else{
				$dados .= ",\n";
			}
		
		}
		// var_dump();


		// echo "<div class=\"container\">";
		echo "<div style='' class='agua col s12'>";
		echo "<div class=\"card-panel $defasado\">";
			echo "<span class='card-title'></span>";
			echo "<div id='grafico$sensor'></div>\n";
		echo "</div>";
		echo "</div>";
		// echo "</div>";
		
		echo "\n\n<script>	\n";
		// echo "\t $('#grafico$sensor').highcharts({	\n";
		echo "\t Highcharts.chart('grafico$sensor', { \n";
		echo "\t \t chart: { type: 'spline',
				zoomType:'x',
				panning: {
					enabled: true,
					type: 'x'
				},
				panKey:'shift',
				},	\n";
		echo "\t \t title: { text: 'Histórico do nível da água: $nome' },
					subtitle: {
					text: 'Ultima atualização: $ult $aviso'
				  },	\n";
		echo "\t \t xAxis: {  type: 'datetime',
			title: {
				text: 'Data/Hora'
			}},	\n";
		echo "\t \t yAxis: { tooltip: {
				headerFormat: '<b>{series.name}</b><br>',
				pointFormat: '{point.x:%e. %b}: {point.y:.2f} m'
							},
		title: { text: 'centimetros' }, 
		plotBands: [{ // Pouca água
				from: 0,
				to: -30,
				color: 'rgba(227, 22, 22, 0.1)',
				label: {
					text: 'Nivel de Trabalho da Boia',
					style: {
						color: '#606060'
					}
				}
			}, { // Light breeze
				from: -31,
				to: -100,
				color: 'rgba(29, 27, 22, 0.1)',
				label: {
					text: 'Normal',
					style: {
						color: '#606060'
					}
				}
			}, { // Gentle breeze
				from: -100,
				to: -240,
				color: 'rgba(68, 170, 213, 0.1)',
				label: {
					text: 'Melhor correr',
					style: {
						color: '#606060'
					}
				}
				}]
		 },	\n";
		echo "\t \t time: { useUTC: true },	\n";
		echo "\t \t legend: { enabled: false },	\n";
		echo "\t \t credits: { enabled: false },	\n";
		echo "\t \t tooltip: { shared: true }, exporting: { enabled: true },	\n";
		echo "\t \t series: [{ name: '$nome',	\n";
		echo "\t \t \t \t \t data: [\n".$dados." \n\t\t\t\t\t\t\t],\n\tcolor: '$cor[1]'	\n";
		echo $pontoDeControle;
		echo "\t \t });	\n";
		echo "</script>	\n";
	}
}

function barras($sensor, $fosso, $nome, $start, $end, $zoomFiltro, $ajuste){
	global $cor;
	$sql = "select * from h2o.leituras l WHERE l.sensor = ".$sensor." ORDER  by l.id desc limit 1";
	$ultimo = DBQ($sql);
	if($ultimo != NULL and $ultimo[0][Valor] < 300){
		$ultimo_dt = date("d-m-Y H:i:s",strtotime($ultimo[0][timestamp]));
		if(strtotime($ultimo_dt) > (strtotime(date("d-m-Y H:m:s")) - 60*60)){
			$ultimo[0][Valor] += $ajuste;
			$ultimo[0][Valor] > $fosso ? $porcentagem = $fosso : $porcentagem = $ultimo[0][Valor];
			$progresso = 100*$porcentagem/$fosso;
			$progresso = 100 - $progresso;
			$progresso = round($progresso, 2);
			$Series 	.= "{name: '$nome', data: \n[".$progresso."],color: '$cor[$sensor]'},\n";
			return $Series;
		}
	}
}

function allLevels($sensor, $fosso, $nome, $start, $end, $zoomFiltro, $ajuste){
	global $cor;
	$sql = "select * from h2o.leituras l WHERE l.sensor = ".$sensor." ORDER  by l.id desc limit 1";
	$ultimo = DBQ($sql);
	if($ultimo != NULL){
		
	
	
		$ultimo = date("d-m-Y H:i:s",strtotime($ultimo[0][timestamp]));
		if(strtotime($ultimo) < (strtotime(date("d-m-Y H:m:s")) - 60*60)){
			$defasado = "red lighten-5";
			$aviso = "<br>ULTIMA LEITURA FAZ MAIS DE 1 HORA!";
		}
		// echo (strtotime(date("d-m-Y H:m:s")) + 60*60);
			
		$sql = "select * from h2o.leituras l WHERE l.sensor = ".$sensor." and l.`timestamp` BETWEEN $start AND $end ORDER by l.`id` asc";
		// var_dump($sql);
		$historico = DBQ($sql);
		$anterior = false;
		foreach($historico as $i => $h){
			if($h[Valor] > 220){
				continue;
			}
			if($anterior){
				// var_dump($anterior);
				$intervalo = strtotime($h[timestamp]) - $anterior;
				// var_dump($intervalo);
				
			}
			$anterior = strtotime($h[timestamp]);
			
			$h[Valor] += $ajuste;
			$h[Valor] > $fosso ? $porcentagem = $fosso : $porcentagem = $h[Valor];
			$date = date("Y-m-d H:i:s", strtotime($h[timestamp]));
			$now = date("Y-m-d H:i:s");
			if($zoomFiltro){
				$now = date("Y-m-d H:i:s", strtotime($_GET[end]));
			};
			// $pontoDeControle = "\t \t \t },{ name: 'now', \n data: [  \n  [Date.parse('".$now."'),1]],	\n	 	 	 }]	\n";
			$pontoDeControle = "\t \t \t },{ name: 'now', \n data: [  \n  [Date.UTC(".date('Y,', strtotime($now)).(date('m', strtotime($now))-1).date(',d,H,i,s', strtotime($now))."),1]],	\n	 	 	 }]	\n";
			// var_dump($date); date('Y,m,d,H,i,s', $h[timestamp])
			$progresso = 100*$porcentagem/$fosso;
			$progresso = 100 - $progresso;
			$progresso = round($progresso, 2);
			if($intervalo > 200){// Necessário ser antes da série original para que o espaço vago seja notável
				// $dados .= "\t[Date.parse('$datavazia'), null],\n";
				$dados .= "\t[Date.UTC(".date('Y,', strtotime($h[timestamp])).(date('m', strtotime($h[timestamp]))-1).date(',d,H,i,s', strtotime($h[timestamp]))."), null],//".$intervalo."\n";
			}
			// var_dump($h);
			$dados .= "\t[";
			// $dados .= "Date.parse('$h[timestamp]')".",\t".$progresso;
			$dados .= "Date.UTC(".date('Y,', strtotime($h[timestamp])).(date('m', strtotime($h[timestamp]))-1).date(',d,H,i,s', strtotime($h[timestamp])).")".",\t".$progresso;
			// $dados .= $progresso;
			$dados .= "]";
			if($i === array_key_last($historico)){
				$dados .= "\n";
			}else{
				$dados .= ",\n";
			}
			

		
		}
		$_Series 	.= "series: [";
		$Series 	.= "{name: '$nome', data: \n[".$dados."],color: '$cor[$sensor]'},\n";
		// $_Series 	.= "{ name: 'Agora', 	data: [ [Date.parse('".$now."'),1]]}]";
		$_Series 	.= "{ name: 'Agora', 	data: [ [Date.UTC(".date('Y,m,d,H,i,s', strtotime($now))."),1]]}]";
		return $Series;

	}
}

?>

</div>

</div>
<script>
	$(document).on('click', '.alternar', function(){
		$(".agua").toggle();
		$(".alternar").toggle();
	});
</script>
