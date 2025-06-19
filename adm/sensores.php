<?php
// Inicia a sessão para verificar as permissões do usuário
session_start();
require '../database.php'; // Ajuste o caminho se necessário

// --- VERIFICAÇÃO DE SEGURANÇA ---
// Se não estiver logado ou for apenas 'monitor', redireciona
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'monitor') {
    header('Location: ../login.php'); // Ajuste o caminho para a página de login
    exit();
}

// --- LÓGICA DA PÁGINA ---
// 1. Busca TODOS os sensores do reservatório, ordenados por nome.
$sql = "SELECT * FROM h2o.reservatorio ORDER BY nome ASC";
$caixas = DBQ($sql);

// 2. Prepara as datas padrão para a função de limpar dados.
// Define o fuso horário para garantir que os cálculos de data estejam corretos.
date_default_timezone_set('America/Recife');
// Data final: exatamente 2 semanas atrás de agora.
$dataFimLimpeza = date('Y-m-d\TH:i', strtotime('-2 weeks'));
// Data inicial: uma data bem antiga para pegar "tudo antes de".
$dataInicioLimpeza = '2020-01-01T00:00';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Sensores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
<div class='container'>
    <h3 class="center-align">Gerenciamento de Sensores</h3>
    
    <ul class="collection with-header">
        <li class="collection-header"><h4>Sensores Cadastrados</h4></li>
        
        <?php if ($caixas): foreach($caixas as $c): ?>
            <li class="collection-item avatar">
                <i class="material-icons circle <?= $c['ativo'] ? 'green' : 'grey' ?>">sensors</i>
                <span class="title"><strong><?= htmlspecialchars($c['nome']) ?></strong></span>
                <p>
                    ID do Sensor: <?= htmlspecialchars($c['sensor']) ?> | 
                    Profundidade do Poço: <?= htmlspecialchars($c['fosso']) ?> cm <br>
                    Fator de Correção (Altura da Sonda): <?= htmlspecialchars($c['alturaSonda']) ?> cm
                </p>
                <div class="secondary-content">
                    <a href="#modal-editar" class="btn-floating waves-effect waves-light blue tooltipped editar modal-trigger" 
                       data-tooltip="Editar Sensor"
                       data-id="<?= $c['id'] ?>"
                       data-nome="<?= htmlspecialchars($c['nome']) ?>"
                       data-sensor="<?= $c['sensor'] ?>"
                       data-alturasonda="<?= $c['alturaSonda'] ?>"
                       data-fosso="<?= $c['fosso'] ?>"
                       data-ativo="<?= $c['ativo'] ?>">
                       <i class="material-icons">edit</i>
                    </a>
                    <a href="#modal-limpar" class="btn-floating waves-effect waves-light red tooltipped limpar modal-trigger"
                       data-tooltip="Limpar Leituras Antigas"
                       data-sensor="<?= $c['sensor'] ?>"
                       data-nome="<?= htmlspecialchars($c['nome']) ?>">
                       <i class="material-icons">delete_sweep</i>
                    </a>
                </div>
            </li>
        <?php endforeach; else: ?>
            <li class="collection-item">Nenhum sensor encontrado.</li>
        <?php endif; ?>
    </ul>
	

    <div class="center-align" style="margin-top: 20px;">
        <a href='index.php' class="btn-large waves-effect waves-light"><i class="material-icons left">arrow_back</i>Voltar</a>
    </div>
</div>
<div class="fixed-action-btn">
    <a href="#modal-criar" class="btn-floating btn-large red modal-trigger tooltipped" data-tooltip="Adicionar Novo Sensor">
        <i class="large material-icons">add</i>
    </a>
</div>

<div id="modal-editar" class="modal modal-fixed-footer">
    <div class="modal-content">
        <h4>Editar Sensor: <span id="editar-nome-sensor"></span></h4>
        <div class="row">
            <form class="col s12" id="form-editar">
                <input type="hidden" id="editar-id">
                <div class="row">
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">tag</i>
                        <input disabled id="editar-sensor" type="text">
                        <label for="editar-sensor">ID do Sensor</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">label</i>
                        <input id="editar-nome" type="text" class="validate">
                        <label for="editar-nome">Descrição do Sensor</label>
                    </div>
                </div>
                <div class="row">
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">straighten</i>
                        <input id="editar-fosso" type="number" class="validate">
                        <label for="editar-fosso">Profundidade do Poço (cm)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">vertical_align_top</i>
                        <input id="editar-alturasonda" type="number" class="validate">
                        <label for="editar-alturasonda">Fator de Correção (cm)</label>
                    </div>
                </div>
                <div class="row">
                    <div class="col s12">
                        <div class="switch">
                            <label>
                                Inativo
                                <input type="checkbox" id="editar-ativo">
                                <span class="lever"></span>
                                Ativo
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="modal-footer">
        <a href="#!" class="modal-close waves-effect waves-grey btn-flat">Cancelar</a>
        <a href="#!" id="salvar" class="waves-effect waves-green btn-flat">Salvar</a>
    </div>
</div>

<div id="modal-limpar" class="modal">
    <div class="modal-content">
        <h4>Limpar Leituras Antigas</h4>
        <p>Você está prestes a deletar permanentemente as leituras do sensor: <strong id="limpar-nome-sensor"></strong>.</p>
        <p class="red-text text-darken-2"><b>Atenção:</b> Esta ação não pode ser desfeita!</p>
        
        <form id="form-limpar">
            <input type="hidden" id="limpar-sensor">
            <p>O padrão é limpar todos os registros com **mais de 2 semanas**.</p>
            <div class="row">
                <div class="input-field col s6">
                    <label class="active"  for="limpar-start">De (Início)</label>
                    <input id="limpar-start" type="datetime-local" value="<?= $dataInicioLimpeza ?>">
                </div>
                <div class="input-field col s6">
                    <label class="active" for="limpar-end">Até (Fim)</label>
                    <input id="limpar-end" type="datetime-local" value="<?= $dataFimLimpeza ?>">
                </div>
            </div>
        </form>
    </div>
    <div class="modal-footer">
        <a href="#!" class="modal-close waves-effect waves-grey btn-flat">Cancelar</a>
        <a href="#!" id="clear" class="waves-effect waves-red btn-flat red-text text-darken-2">Limpar Registros</a>
    </div>
</div>

<div id="modal-criar" class="modal modal-fixed-footer">
    <div class="modal-content">
        <h4>Adicionar Novo Sensor</h4>
        <div class="row">
            <form class="col s12" id="form-criar">
                <div class="row">
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">tag</i>
                        <input id="criar-sensor-id" type="number" class="validate" required>
                        <label for="criar-sensor-id">ID do Sensor (Número Único)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">label</i>
                        <input id="criar-nome" type="text" class="validate" required>
                        <label for="criar-nome">Descrição do Sensor</label>
                    </div>
                </div>
                <div class="row">
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">straighten</i>
                        <input id="criar-fosso" type="number" class="validate">
                        <label for="criar-fosso">Profundidade do Poço (cm)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <i class="material-icons prefix">vertical_align_top</i>
                        <input id="criar-alturasonda" type="number" class="validate">
                        <label for="criar-alturasonda">Fator de Correção (cm)</label>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="modal-footer">
        <a href="#!" class="modal-close waves-effect waves-grey btn-flat">Cancelar</a>
        <a href="#!" id="criar-novo" class="waves-effect waves-green btn-flat">Criar</a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
<script>
$(document).ready(function(){
    // Inicializa todos os componentes do Materialize
    $('.modal').modal();
    $('.tooltipped').tooltip();
	$('.fixed-action-btn').floatingActionButton();

    // Ação ao clicar no botão EDITAR de um sensor
    $('.editar').click(function(){
        // Pega os dados do sensor pelos atributos data-* do botão
        const id = $(this).data("id");
        const nome = $(this).data("nome");
        const sensor = $(this).data("sensor");
        const alturasonda = $(this).data("alturasonda");
        const fosso = $(this).data("fosso");
        const ativo = $(this).data("ativo");

        // Preenche o modal de edição com os dados
        $('#editar-nome-sensor').text(nome);
        $('#editar-id').val(id);
        $('#editar-nome').val(nome);
        $('#editar-sensor').val(sensor);
        $('#editar-alturasonda').val(alturasonda);
        $('#editar-fosso').val(fosso);
        $('#editar-ativo').prop('checked', ativo == 1); // Marca o switch se estiver ativo
        
        // Atualiza os labels para o estado 'active' se houver conteúdo
        M.updateTextFields();
    });
    
    // Ação ao clicar no botão LIMPAR de um sensor
    $('.limpar').click(function(){
        const sensor = $(this).data("sensor");
        const nome = $(this).data("nome");
        
        // Preenche o modal de limpeza com os dados
        $('#limpar-nome-sensor').text(nome);
        $('#limpar-sensor').val(sensor);
    });
    
	// Ação para o botão CRIAR do modal de criação
	$('#criar-novo').click(function(){
        let dados = {
            sensor_id: $("#criar-sensor-id").val(),
            nome_sensor: $("#criar-nome").val(),
            fosso: $("#criar-fosso").val(),
            altura_sonda: $("#criar-alturasonda").val()
        };

        // Validação simples
        if (!dados.sensor_id || !dados.nome_sensor) {
            M.toast({html: 'ID do Sensor e Nome são obrigatórios!'});
            return;
        }
        
        // Usaremos o novo script c.php
        $.post("c.php", dados, function(retorna){
            M.toast({html: retorna});
            if(retorna.includes("sucesso")){
                setTimeout(() => location.reload(), 1000);
            }
        });
    });
    // Ação ao clicar no botão SALVAR do modal de edição
    $('#salvar').click(function(){
        let dados = {
            id: $("#editar-id").val(),
            nome: $("#editar-nome").val(),
            alturaSonda: $("#editar-alturasonda").val(),
            fosso: $("#editar-fosso").val(),
            ativo: $("#editar-ativo").is(':checked') ? 1 : 0 // Pega o valor do switch
        };
        
        $.post("u.php", dados, function(retorna){
            M.toast({html: retorna});
            if(retorna.includes("Feito")){
                setTimeout(() => location.reload(), 1000);
            }
        });
    });
    
    // Ação ao clicar no botão LIMPAR REGISTROS do modal de limpeza
    $('#clear').click(function(){
        let dados = {
            sensor: $("#limpar-sensor").val(),
            start: $("#limpar-start").val(),
            end: $("#limpar-end").val()
        };
        
        // Desabilita o botão para evitar cliques duplos
        $(this).addClass('disabled');
        
        $.post("d.php", dados, function(retorna){
            M.toast({html: retorna});
            if(retorna.includes("Feito")){
                setTimeout(() => location.reload(), 1500);
            } else {
                 $('#clear').removeClass('disabled'); // Reabilita se falhar
            }
        });
    });
});
</script>
</body>
</html>