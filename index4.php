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

<meta http-equiv="refresh" content="180" />
<!-- <meta name="viewport" content="width=device-width, initial-scale=1.0"> -->
<div class='container'>
    <div class="row">
        <div class="col s4">
            <h5>Bem-vindo, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h5>
        </div>
	</div>
	<div class="row">
		<form class="col s12">
		<div class="input-field col l4 s4">
			<input id="start" name="start" type="date" class="validate">
			<label for="start">De</label>
		</div>
		<div class="input-field col l4 s4">
			<input id="end" name="end" type="date" class="validate">
			<label for="end">Até</label>
		</div>
		<div class="input-field col l2 s2">
			<button class="btn waves-effect waves-light" type="submit" name="action">Filtrar</button>
		</div>
        <div class="input-field col l1 s2 right-align">
            <a href="logout.php" class="btn red">Sair</a>
        </div>
			<?php  
			if($dev){ ?>
				<div class="input-field col l1 s2 right-align">
					<a href="/adm" class="btn blue">ADM</a>
				</div>
			<?php  }?>
	</form>
    </div>
    
    
</div>

<?php

if(true){ // Gera os históricos separados
	if(isset($_GET[umSensor])){
		historico($_GET[umSensor], 200, "Sensor: ".$_GET[umSensor], $start, $end, $zoomFiltro, 0);
	}else if (empty($caixas)) {
        echo "<div class='col s12'><div class='card-panel yellow lighten-3'><p>Nenhum sensor associado a este usuário. Peça ao administrador para configurar seu acesso em <a href='usuarios.php'>Gerenciar Usuários</a>.</p></div></div>";
    } else {
        
        foreach($caixas as $caixa){
            historico($caixa['sensor'], $caixa['fosso'], $caixa['nome'], $start, $end, $zoomFiltro, $caixa['alturaSonda']);
        }
    }
}

// echo "Tamanho do banco de dados: ".dbSize()."Mb sendo ".dbRecords()." leituras desde ".dbstart();

function historico($sensor, $fosso, $nome, $start, $end, $zoomFiltro, $ajuste){
	$defasado = "";
	global $cor;
	$aviso = "";
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
		
		$dados_para_grafico = limpaRuido($historico,0,3,5,4);
		$dados_para_grafico = $historico;
		
		// dump($dados_para_grafico);

		
		if (!empty($dados_para_grafico)) {
		
			$anterior = false;

			foreach($dados_para_grafico as $i => $h){
				$h[Valor] += $ajuste;
				if($h[Valor] > 220 or $h[Valor] < 2){
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

			echo "<div style='' class='agua col s12'>";
			echo "<div class=\"card-panel $defasado\">";
				echo "<span class='card-title'></span>";
				echo "<div id='grafico$sensor'></div>\n";
			echo "</div>";
			echo "</div>";
			
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
			echo "\t \t title: { text: '$nome' },
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
						text: '...',
						style: {
							color: '#606060'
						}
					}
				}, { // Light breeze
					from: -31,
					to: -100,
					color: 'rgba(29, 27, 22, 0.1)',
					label: {
						text: '...',
						style: {
							color: '#606060'
						}
					}
				}, { // Gentle breeze
					from: -100,
					to: -240,
					color: 'rgba(68, 170, 213, 0.1)',
					label: {
						text: '...',
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
			
		}else {
            // Mensagem que aparece se não houver dados no período
            echo "<div class='col s12'>";
            echo "  <div class='card-panel teal lighten-5'>";
            echo "    <p class='center-align'>Nenhum dado de leitura encontrado para o sensor '<strong>".htmlspecialchars($nome)."</strong>' no período selecionado.</p>";
            echo "  </div>";
            echo "</div>";
        }
	}
}

function dump($el){
	echo "<pre>";
		print_r($el);
	echo "</pre>";
}


/**
 * Filtra e corrige ruídos em dados de série temporal usando um método de dois passos:
 * 1. Identificação: Marca pontos como 'valido' ou 'ruido' com base na taxa de variação.
 * 2. Correção: Substitui os pontos marcados como 'ruido' por um valor obtido por 
 * interpolação linear entre os vizinhos válidos mais próximos.
 *
 * @param array $dados_brutos O array de leituras do banco.
 * @param float $ajuste O valor de ajuste a ser somado a cada leitura.
 * @param int   $janela_vizinhos O número de vizinhos a serem considerados em cada lado (padrão: 3).
 * @param float $taxa_maxima_cm_por_minuto A variação máxima permitida em cm/minuto (padrão: 5.0).
 * @param int   $votos_minimos_para_ruido O número de vizinhos que precisam "votar" para um ponto ser ruído (padrão: 4).
 * @return array O array de dados corrigido e com os valores já ajustados.
 */
function limpaRuido(
    array $dados_brutos,
    float $ajuste,
    int $janela_vizinhos = 3,
    float $taxa_maxima_cm_por_minuto = 5.0,
    int $votos_minimos_para_ruido = 4
): array {
    $total_pontos = count($dados_brutos);
    if ($total_pontos < 3) { // Precisa de pelo menos 3 pontos para fazer sentido
        foreach ($dados_brutos as &$ponto) { $ponto['Valor'] += $ajuste; }
        return $dados_brutos;
    }

    // --- PASSO 1: MAPEAR OS PONTOS VÁLIDOS E RUIDOSOS ---
    $status_pontos = [];
    for ($i = 0; $i < $total_pontos; $i++) {
        // Pontos nas bordas são considerados válidos por padrão
        if ($i < $janela_vizinhos || $i >= $total_pontos - $janela_vizinhos) {
            $status_pontos[$i] = 'valido';
            continue;
        }

        $votos_ruido = 0;
        $valor_atual_sem_ajuste = $dados_brutos[$i]['Valor'];
        $time_atual_ts = strtotime($dados_brutos[$i]['timestamp']);
        
        for ($j = 1; $j <= $janela_vizinhos; $j++) {
            // Compara com vizinhos anteriores e posteriores usando os dados brutos originais
            if (isRuidoContraVizinho($valor_atual_sem_ajuste, $time_atual_ts, $dados_brutos[$i - $j], 0, $taxa_maxima_cm_por_minuto)) $votos_ruido++;
            if (isRuidoContraVizinho($valor_atual_sem_ajuste, $time_atual_ts, $dados_brutos[$i + $j], 0, $taxa_maxima_cm_por_minuto)) $votos_ruido++;
        }

        $status_pontos[$i] = ($votos_ruido >= $votos_minimos_para_ruido) ? 'ruido' : 'valido';
    }

    // --- PASSO 2: CORRIGIR OS PONTOS MARCADOS COMO RUÍDO ---
    $dados_corrigidos = [];
    for ($i = 0; $i < $total_pontos; $i++) {
        $ponto_original = $dados_brutos[$i];

        if ($status_pontos[$i] === 'valido') {
            $ponto_original['Valor'] += $ajuste; // Aplica ajuste somente nos válidos
            $dados_corrigidos[] = $ponto_original;
            continue;
        }

        // Se o ponto é ruído, encontramos os vizinhos válidos mais próximos
        $ponto_anterior_valido = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if ($status_pontos[$j] === 'valido') {
                $ponto_anterior_valido = $dados_brutos[$j];
                break;
            }
        }

        $ponto_seguinte_valido = null;
        for ($j = $i + 1; $j < $total_pontos; $j++) {
            if ($status_pontos[$j] === 'valido') {
                $ponto_seguinte_valido = $dados_brutos[$j];
                break;
            }
        }
        
        $valor_corrigido = null;
        if ($ponto_anterior_valido && $ponto_seguinte_valido) {
            // Interpolação Linear: mais preciso que a média simples
            $v_ant = $ponto_anterior_valido['Valor'] + $ajuste;
            $t_ant = strtotime($ponto_anterior_valido['timestamp']);
            
            $v_seg = $ponto_seguinte_valido['Valor'] + $ajuste;
            $t_seg = strtotime($ponto_seguinte_valido['timestamp']);

            $t_atual = strtotime($ponto_original['timestamp']);
            
            // Evita divisão por zero se os pontos válidos tiverem o mesmo timestamp
            if (($t_seg - $t_ant) != 0) {
                $fracao_tempo = ($t_atual - $t_ant) / ($t_seg - $t_ant);
                $valor_corrigido = $v_ant + $fracao_tempo * ($v_seg - $v_ant);
            } else {
                $valor_corrigido = $v_ant; // Fallback para o valor anterior
            }

        } elseif ($ponto_anterior_valido) {
            $valor_corrigido = $ponto_anterior_valido['Valor'] + $ajuste; // Usa o último válido
        } elseif ($ponto_seguinte_valido) {
            $valor_corrigido = $ponto_seguinte_valido['Valor'] + $ajuste; // Usa o próximo válido
        }
        
        // Se conseguimos calcular um valor, aplicamos a correção. Senão, o ponto é descartado (caso raro).
        if ($valor_corrigido !== null) {
            $ponto_original['Valor'] = $valor_corrigido;
            $dados_corrigidos[] = $ponto_original;
        }
    }
    
    return $dados_corrigidos;
}


/**
 * Função auxiliar para verificar se um ponto é ruidoso em relação a UM vizinho.
 * @param float $valor_ponto_atual Valor (sem ajuste) do ponto sendo avaliado.
 * @param int $time_ponto_atual Timestamp (em segundos) do ponto sendo avaliado.
 * @param array $vizinho O array do ponto vizinho.
 * @param float $ajuste O ajuste a ser aplicado. ATENÇÃO: nesta versão, o ajuste é sempre 0 na chamada.
 * @param float $taxa_maxima A taxa de variação máxima permitida.
 * @return bool
 */
function isRuidoContraVizinho($valor_ponto_atual, $time_ponto_atual, $vizinho, $ajuste, $taxa_maxima): bool
{
    // Note: O ajuste não é mais usado aqui, pois comparamos os valores brutos.
    // O ajuste é aplicado somente no Passo 2, sobre os valores já validados/corrigidos.
    $valor_vizinho = $vizinho['Valor']; 
    $time_vizinho = strtotime($vizinho['timestamp']);

    $diff_tempo_seg = abs($time_ponto_atual - $time_vizinho);
    if ($diff_tempo_seg == 0) return false;

    $diff_tempo_min = $diff_tempo_seg / 60.0;
    $diff_valor = abs($valor_ponto_atual - $valor_vizinho);

    $taxa_calculada = $diff_valor / $diff_tempo_min;

    return $taxa_calculada > $taxa_maxima;
}



?>




</div>

</div>

