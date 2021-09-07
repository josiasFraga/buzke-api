<?php
class AgendamentosController extends AppController {
    
    public $helpers = array('Html', 'Form');	
    public $components = array('RequestHandler');	

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");

    }

    public function empresa() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['data']) || $dados['data'] == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }
        if ( !isset($dados['type']) || $dados['type'] == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $data = $dados['data'];
        $type = $dados['type'];
        $year_week = date('oW',strtotime($data. ' +1 day'));

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');

        if ( $type == 1) {
            $conditions = [
                'Agendamento.cliente_id' => $dados_token['Usuario']['cliente_id'],
                'MONTH(Agendamento.horario)' => date('m',strtotime($data)),
                'not' => [
                    'Agendamento.cancelado' => 'Y'
                ]
            ];
        }
        else if ( $type == 2) {
            $conditions = [
                'Agendamento.cliente_id' => $dados_token['Usuario']['cliente_id'],
                'YEARWEEK(Agendamento.horario, 4)' => $year_week,
                'not' => [
                    'Agendamento.cancelado' => 'Y'
                ]
            ];
        }

        $agendamentos = $this->Agendamento->find('all',[
            'conditions' => $conditions,
            'fields' => [
                'Agendamento.id',
                'Agendamento.horario',
                'ClienteCliente.id',
                'ClienteCliente.nome',
            ],
            'link' => ['ClienteCliente'],
            'order' => ['Agendamento.horario']
        ]);
        
        foreach($agendamentos as $key => $agend) {

        }

        $dados_retornar = [];
        $dados_retornar = $this->formataAgendamentos($agendamentos, $data, $type);
        //$dados_retornar = json_encode($dados_retornar, true);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));
    }

    private function formataAgendamentos($agendamentos = [], $data = '', $type = '') {

        if ( $data == '' )
            return [];
        if ( $type == '' )
            return [];

        $php_date = new DateTime($data);
        $day_of_week = $php_date->format("w");
        $php_date->modify("-".($day_of_week)." days");
        $primeiro_dia_semana = $php_date->format('Y-m-d');
        $primeiro_dia_mes = date('Y-m-01',strtotime($data));
        $ultimo_dia_mes = date("t-m-Y", strtotime($data));

        $arr_retornar = [];

        if ($type == 1) {
            $proxima_data = $primeiro_dia_mes;
            while (strtotime($proxima_data) <= strtotime($ultimo_dia_mes)) {
                $arr_retornar[$proxima_data] = [];
                $proxima_data = date('Y-m-d',strtotime($proxima_data." + 1 days"));
            }

        } else if ( $type == 2 ) {
            
            $proxima_data = $primeiro_dia_semana;
            for ($i = 0; $i <= 6; $i++) {
                $arr_retornar[$proxima_data] = [];
                $proxima_data = date('Y-m-d',strtotime($proxima_data." + 1 days"));
            }
        }

        $last_data = '';
        $count = -1;
        if ( count($agendamentos) > 0 ) {
            foreach( $agendamentos as $key => $agend) {
                $hora = date('H:i',strtotime($agend['Agendamento']['horario']));
                $data = date('Y-m-d',strtotime($agend['Agendamento']['horario']));
                $arr_dados = ['name' => $hora, 'height' => 50, 'usuario' => $agend['ClienteCliente']['nome'], 'id' => $agend['Agendamento']['id']];
                if ( $data != $last_data ) {
                    $count++;
                    $arr_retornar[$data][] = $arr_dados;
                    $last_data = $data;
                } else {
                    $arr_retornar[$data][] = $arr_dados;
                }
            }
        }

        

        return $arr_retornar;

    }

    public function cadastrar(){
        $this->layout = 'ajax';
    
        $dados = json_decode(json_encode($this->request->data['dados']));

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->cliente_id) || $dados->cliente_id == "" || !is_numeric($dados->cliente_id) ) {
            throw new BadRequestException('Dados da empresa não informada!', 401);
        }

        if ( !isset($dados->day) || $dados->day == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }

        if ( !isset($dados->horaSelecionada) || $dados->horaSelecionada == "" ) {
            throw new BadRequestException('Hora não informada!', 401);
        }

        $data_selecionada = $dados->day->dateString;
        $horario_selecionado = $dados->horaSelecionada->horario;

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');

        $this->loadModel('ClienteHorarioAtendimentoExcessao');

        //verfica se o cliente abrirá no dia
        $verificaFechamento = $this->ClienteHorarioAtendimentoExcessao->verificaExcessao($dados->cliente_id, $data_selecionada, 'F');

        if ( count($verificaFechamento) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A empresa não atenderá no dia e horário escolhido!'))));
        }

        //busca os dados do usuário como cliente
        $this->loadModel('ClienteCliente');
        $dados_usuario_como_cliente = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], $dados->cliente_id);

        if ( !$dados_usuario_como_cliente || count($dados_usuario_como_cliente) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Lamentamos. Não conseguimos encontrar seus dados! ;('))));

        }

        //debug($dados_usuario_como_cliente); die();

        //verifica se o usuário já não possui um agendamento pro mesmo dia e horário que está tentando
        $verificaAgendamento = $this->Agendamento->verificaAgendamento($dados_usuario_como_cliente['ClienteCliente']['id'], null, $data_selecionada, $horario_selecionado);
        if ( $verificaAgendamento !== false && count($verificaAgendamento) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você já tem um agendamento neste horário!'))));
        }
        
        //busca o nº de agendamentos que o cliente tem neste dia e horário
        $n_agendamentos_cliente = $this->Agendamento->nAgendamentosCliente($dados->cliente_id, $data_selecionada, $horario_selecionado);
    
        $this->loadModel('ClienteHorarioAtendimento');

        //conta quantas vagas existem para o dia e horário escolhidos
        $vagas_restantes = $this->ClienteHorarioAtendimento->contaVagaRestantesHorario($dados->cliente_id, $data_selecionada, $horario_selecionado, $n_agendamentos_cliente);

        if ( !$vagas_restantes ) {

            //verifica se abrirá com excessão
            $verificaAbertura = $this->ClienteHorarioAtendimentoExcessao->verificaExcessao($dados->cliente_id, $data_selecionada, 'A');

            if ( count($verificaAbertura) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A empresa não atenderá no dia e horário escolhido!'))));
            }

            //verifica quantas vagas existem no horário
            if ( strtotime($verificaAbertura['ClienteHorarioAtendimentoExcessao']['abertura']) <= strtotime($horario_selecionado) && strtotime($verificaAbertura['ClienteHorarioAtendimentoExcessao']['fechamento']) >= strtotime($horario_selecionado) ) {
                $vagas_restantes = ($verificaAbertura['ClienteHorarioAtendimentoExcessao']['vagas_por_horario'] - $n_agendamentos_cliente);
            }
  
        }

        if ( $vagas_restantes <= 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Lamentamos. Não existe mais vagas para esse horário! ;('))));
        }

        $dados_salvar = [
            'cliente_cliente_id' => $dados_usuario_como_cliente['ClienteCliente']['id'],
            'cliente_id' => $dados->cliente_id,
            'horario' => $data_selecionada.' '.$horario_selecionado,
        ];

        if ( isset($dados->domicilio) && $dados->domicilio == 1 ) {
            $dados_salvar = array_merge($dados_salvar, ['domicilio' => 'Y']);
        }

        $this->Agendamento->create();
        $this->Agendamento->set($dados_salvar);
        $dados_agendamento_salvo = $this->Agendamento->save($dados_salvar);
        if ( !$dados_agendamento_salvo ) {

            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cadastrar seu agendamento!'))));
        }

        //busca os ids do onesignal da empresa
        $this->loadModel('Token');
        $idsNotificacoesCliente = $this->Token->find('list',[
            'fields' => [
                'Token.id', 'Token.notification_id'
            ],
            'conditions' => [
                'Usuario.cliente_id' => $dados->cliente_id,
                'Token.data_validade >=' => date('Y-m-d') 
            ],
            'link' => ['Usuario'],
            'group' => ['Token.notification_id']
        ]);

        $idsNotificacoesCliente = array_values($idsNotificacoesCliente);


        if ( count($idsNotificacoesCliente) > 0 )
            $this->sendNotification( $idsNotificacoesCliente, $dados_agendamento_salvo['Agendamento']['id'], "Novo Agendamento :)", "Você tem um novo agendamento de ".$dados_usuario['Usuario']['nome'], "agendamento" );
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! Agendamento cadastrado com sucesso!'))));
    }
}