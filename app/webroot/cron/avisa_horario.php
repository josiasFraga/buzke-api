<?php
date_default_timezone_set('America/Sao_Paulo');
define('HOST','zapshop.com.br');
define('DB','zapshopcom_ctff');
define('USER','zapshopcom_ctff');
define('PASS','zap3537shop11');

$conexao = 'mysql:host='.HOST.';dbname='.DB;

try{
	$conecta = new PDO ($conexao, USER, PASS);
	$conecta ->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}catch(PDOexception $error_conecta){
	echo 'Erro ao conectar '.$error_conecta->getMessage().", por favor, informe no e-mail contato@zapshop.com.br."." ".PHP_EOL;	
}

function sendMessage($arr_ids = array(), $dados_sessao = null, $titulo = "", $mensagem = ""){

    if ( count($arr_ids) == 0 )
        return false;

    if ( $dados_sessao == null )
        return false;

    if ( $mensagem == "" )
        $mensagem = $titulo;
        
    $heading = array(
        "en" => $titulo
    );

    $content = array(
        "en" => $mensagem
    );

    //var_dump($arr_ids);

    $arr_ids_web = array();
    $arr_ids_app = array();

    foreach( $arr_ids as $id) {
        //if( $id['tipo'] == 'App' ) {
            $arr_ids_app[] = $id['notifications_id'];
        //} elseif(  $id['tipo'] == 'Web'  ) {
        //    $arr_ids_web[] = $id['one_singal_id'];
        //}
    }
   
    if ( count($arr_ids_app) > 0 ) {
        $fields = array(
            'app_id' => "2dc32c45-d5ad-4028-b47b-b8b38f285c73",
            'include_player_ids' => $arr_ids_app,
            'data' => array('motivo' => 'aviso_horario', 'dados_sessao' => $dados_sessao),
            'small_icon' => 'https://www.zapshop.com.br/ctff/restfull/pushservice/icons/logo_icon.png',
            'large_icon' => 'https://www.zapshop.com.br/ctff/restfull/pushservice/icons/logo_icon_large.png', 
            'contents' => $content
        );
        
        $fields = json_encode($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                                                'Authorization: Basic NzU1MmUwMGUtY2Q4ZS00Yzg4LTk0ZjUtMGY0MjI5YTUyNTRi'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);
    }
    
    return true;
}

function bIdsOneSignal($usuario_id) {
    global $conecta;
    $sql_bIdsOneSignal = "SELECT notifications_id FROM usuarios WHERE id = :id";

    try{
        $q = $conecta->prepare($sql_bIdsOneSignal);
        $q -> bindValue(':id',$usuario_id,PDO::PARAM_INT);
        $q -> execute();

        $r = $q->fetchAll(PDO::FETCH_ASSOC);
        $n = $q->rowCount(PDO::FETCH_ASSOC);
        if ( $n == 0 ) {
            return array();
        } else
            return $r;
    }
    catch(PDOexception $e){
        echo $e->getMessage();
        return false;
    }
}

function bMinutosAntecendenciaAviso() {
    global $conecta;
    $sql_bHoras = "SELECT aviso_minutos_antecedencia FROM config ORDER BY id DESC LIMIT 1";

    try{
        $q = $conecta->prepare($sql_bHoras);
        $q -> execute();

        $r = $q->fetchAll(PDO::FETCH_ASSOC);
        $n = $q->rowCount(PDO::FETCH_ASSOC);
        if ( $n == 0 ) {
            return 0;
        } else
            return $r[0]['aviso_minutos_antecedencia'];
    }
    catch(PDOexception $e){
        echo $e->getMessage();
        return false;
    }
}

function buscaDadosInstrutor($agendamento){
    global $conecta;
    $sql_bInstrutor = "SELECT usuarios.id as id, usuarios.nome, usuarios.foto FROM agendamentos LEFT JOIN usuarios ON agendamentos.instrutor_id = usuarios.id WHERE agendamentos.id = :agendamento_id LIMIT 1";

    try{
        $q = $conecta->prepare($sql_bInstrutor);
        $q -> bindValue(':agendamento_id',$agendamento);
        $q -> execute();

        $r = $q->fetchAll(PDO::FETCH_ASSOC);
        $n = $q->rowCount(PDO::FETCH_ASSOC);
        if ( $n == 0 ) {
            return array();
        } else
            return $r[0];
    }
    catch(PDOexception $e){
        echo $e->getMessage();
        return false;
    }

}

$minutos_antecedencia = bMinutosAntecendenciaAviso();
$minutos_antecedencia_min = $minutos_antecedencia-6;
$minutos_antecedencia_max = $minutos_antecedencia+6;

$s_bAgendamentosAAvisar = "SELECT * FROM agendamentos WHERE avisado = 0 AND cancelado = 0 AND data_hora <= DATE_ADD(NOW(), INTERVAL :minutos_antecedencia_max MINUTE) AND data_hora >= DATE_ADD(NOW(), INTERVAL :minutos_antecedencia_min MINUTE)";
try{
    $q = $conecta->prepare($s_bAgendamentosAAvisar);
    $q -> bindValue(':minutos_antecedencia_max',$minutos_antecedencia_max);
    $q -> bindValue(':minutos_antecedencia_min',$minutos_antecedencia_min);
    $q -> execute();
    $agendamentos = $q->fetchAll(PDO::FETCH_ASSOC);
    $n_agendamentos = $q->rowCount(PDO::FETCH_ASSOC);
}
catch(PDOexception $e){
    echo 'Erro ao buscar os agendamentos'." ".$e->getMessage().PHP_EOL;
    die();    
}

if ( $n_agendamentos == 0 ) {
    echo 'Nenhum agendamento para avisar!'." ".PHP_EOL;
    die();
}

echo date("d/m/Y H:i:s")."".PHP_EOL;

$s_setaAvisado = "UPDATE agendamentos SET agendamentos.avisado = 1 WHERE agendamentos.id = :id LIMIT 1";

foreach( $agendamentos as $key_agendamento => $agend ) {
    $ids_notification = bIdsOneSignal($agend['aluno_id']);

    if ( $ids_notification === false ) {
        echo "Erro ao buscar os ids de notificação. ".PHP_EOL;
        die();
    }

    try{
        $q = $conecta->prepare($s_setaAvisado);
        $q -> bindValue(':id',$agend['id'],PDO::PARAM_INT);
        $q -> execute();
    
        echo 'Agendamento id='.$agend['id']." setado com avisado com sucesso. ";

        if ( count($ids_notification) == 0 || $ids_notification == '' ) {
            echo 'Agendamento id='.$agend['id']." Nenhum ID de notificação cadastrado. ";
        } else {
            $dados_instrutor = buscaDadosInstrutor($agend['id']);
            $dados_agendamento = array(
                'Agendamento' => array(
                    'id' => $agend['id'],
                    'aluno_id' => $agend['id'],
                    'instrutor_id' => $agend['instrutor_id'],
                    'data_hora' => $agend['data_hora'],
                    'cancelado' => $agend['cancelado'],
                    'data_br' => date('d/m/Y',strtotime($agend['data_hora'])),
                    'hora' => date('H:i',strtotime($agend['data_hora'])),
                ), 
                'Instrutor' => $dados_instrutor
            );

            if ( !sendMessage($ids_notification, $dados_agendamento, "Não esqueça!", "Sua sessão está marcada para daqui algumas horas. Só passamos pra te avisar.") ) {
                echo "Erro ao enviar as notificações para o usuário. ".PHP_EOL;
            } else {
                echo "notificações enviadas com sucesso! ".PHP_EOL;
            }
        }
    }
    catch(PDOexception $e){
        echo 'Erro ao setar como avisado. id='.$agend['id']." ".PHP_EOL;
        die();    
    }
    
}