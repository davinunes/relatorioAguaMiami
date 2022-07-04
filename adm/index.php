<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<div class='container'>
<?php

include "../database.php";

if(true){ // Seleciona quais caixas farão parte do relatório
	$sql = "select * from h2o.reservatorio r where r.sensor <= '6' order by r.nome asc";
	$caixas = DBQ($sql);
}

// var_dump($caixas);

/* array(5) {
    ["id"]=>
    string(1) "5"
    ["sensor"]=>
    string(1) "3"
    ["alturaSonda"]=>
    string(2) "20"
    ["fosso"]=>
    string(3) "240"
    ["nome"]=>
    string(7) "Torre A"
  } */
echo "<table>";
echo "<tr>";
	echo "<th>";
		echo "Caixa";
	echo "</th>";
	echo "<th>";
		echo "Descrição";
	echo "</th>";
	echo "<th>";
		echo "Sensor";
	echo "</th>";
	echo "<th>";
		echo "Fator de Correção";
	echo "</th>";
	echo "<th>";
		echo "Profundidade";
	echo "</th>";
	echo "<th>";
		echo "Editar";
	echo "</th>";
echo "</tr>";
foreach($caixas as $c){
	echo "<tr>";
		echo "<td>";
			echo $c[id];
		echo "</td>";
		echo "<td>";
			echo $c[nome];
		echo "</td>";
		echo "<td>";
			echo $c[sensor];
		echo "</td>";
		echo "<td>";
			echo $c[alturaSonda];
		echo "</td>";
		echo "<td>";
			echo $c[fosso];
		echo "</td>";
		echo "<td>";
			echo "<a caixa_id='$c[id]' caixa_nome='$c[nome]' caixa_sensor='$c[sensor]' caixa_fc='$c[alturaSonda]' caixa_fosso='$c[fosso]' class='editar waves-effect waves-light btn modal-trigger' href='#modal1'>Editar</a>";
		echo "</td>";
		
	echo "</tr>";
}
echo "</table>";
?>
</div>

<script>
	$(document).ready(function(){
		$('.modal').modal();
		$('.editar').click(function(){
			$("#id").val($(this).attr("caixa_id"));
			$("#nome").val($(this).attr("caixa_nome"));
			$("#sensor").val($(this).attr("caixa_sensor"));
			$("#alturaSonda").val($(this).attr("caixa_fc"));
			$("#fosso").val($(this).attr("caixa_fosso"));
		});
		
		$('#salvar').click(function(){
			let dados = {
				id: $("#id").val(),
				nome: $("#nome").val(),
				sensor: $("#sensor").val(),
				alturaSonda: $("#alturaSonda").val(),
				fosso: $("#fosso").val()
			}
			console.log(dados);
			
			$.post("u.php", dados, function(retorna){
				console.log(retorna);
				M.toast({html: retorna});
				setTimeout(function() {
				  location.reload();
				}, 500);
				
				
			});
			
			
		});
	});
</script>

<!-- Modal Structure -->
  <div id="modal1" class="modal">
    <div class="modal-content">
				 <div class="row">
				<form class="col s12">
				  <div class="row">
					<div class="input-field col s3">
					  <input disabled placeholder="0" id="id" type="number" class="validate">
					  <label for="id">Caixa</label>
					</div>
					
				  
				  
					<div class="input-field col s3">
					  <input disabled value="0" id="sensor" type="number" class="validate">
					  <label for="sensor">Sensor</label>
					</div>
				 
				  
					<div class="input-field col s3">
					  <input placeholder="-1" id="alturaSonda" type="number" class="validate">
					  <label for="alturaSonda">Fator de correção</label>
					</div>
				  
				  
					<div class="input-field col s3">
					  <input placeholder="240" id="fosso" type="number" class="validate">
					  <label for="fosso">Profundidade</label>
					</div>
				  
				  <div class="input-field col s12">
					  <input placeholder="Torre X" id="nome" type="text" class="validate">
					  <label for="nome">Descrição</label>
					</div>
				  <div class="row">
					
				  </div>
				</form>
			  </div>
    </div>
    <div class="modal-footer">
      <a id='salvar' class="btn modal-close waves-effect waves-green btn-flat">Salvar</a>
    </div>
  </div>