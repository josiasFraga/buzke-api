<?php
class AgendamentosController extends AppController {
    
    public $helpers = array('Html', 'Form');	
    public $components = array('RequestHandler');	

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");

    }

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['agendamento_id']) || $dados['agendamento_id'] == "" ) {
            throw new BadRequestException('Agendamento não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $agendamento_id = $dados['agendamento_id'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');

        $conditions = [
            'Agendamento.id' => $agendamento_id,
            'not' => [
                'Agendamento.cancelado' => 'Y'
            ]
        ];

        $agendamento = $this->Agendamento->find('first',[
            'conditions' => $conditions,
            'fields' => [
                'Agendamento.*',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.telefone',
                'Cliente.id',
                'Cliente.nome',
                'ClienteCliente.img',
                'ClienteCliente.endereco',
                'ClienteCliente.endreceo_n',
                'Localidade.loc_no'
            ],
            'link' => ['ClienteCliente' => ['Localidade'], 'Cliente']
        ]);

        if ( count($agendamento) > 0 ) {
            $agendamento['ClienteCliente']['img'] = $this->images_path . 'clientes_clientes/' . $agendamento['ClienteCliente']['img'];
            $agendamento['Agendamento']['horario_str'] = date('d/m',strtotime($agendamento['Agendamento']['horario']))." às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
            $data_agendamento = date('Y-m-d',strtotime($agendamento['Agendamento']['horario']));
            if ( $data_agendamento == date('Y-m-d') ) {
                $agendamento['Agendamento']['horario_str'] = "Hoje às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
            }
        }
        
        
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $agendamento))));
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
                'Agendamento.duracao',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.img'
            ],
            'link' => ['ClienteCliente'],
            'order' => ['Agendamento.horario']
        ]);
    

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
                $duracao = $agend['Agendamento']['duracao'];

                if ( $duracao != '') {
                    $timeBase = new DateTime($agend['Agendamento']['horario']);
                    list($hours,$minutes,$seconds) = explode(':',$duracao);
                    $timeToAdd = new DateInterval('PT'.$hours.'H'.$minutes.'M'.$seconds.'S'); 
                    $timeBase->add($timeToAdd);
                    $duracao = $timeBase->format('H:i');
                }


                $arr_dados = ['name' => $hora, 'height' => 75, 'usuario' => $agend['ClienteCliente']['nome'], 'id' => $agend['Agendamento']['id'], 'termino' => $duracao, 'img' => $this->images_path.'clientes_clientes/'.$agend['ClienteCliente']['img']];
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
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        //$this->log($dados, 'debug');

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
        
        $this->loadModel('ClienteCliente');

        //verifico quem está tentando salvar o agendamento, se é uma empresa ou um usuário
        if ( $dados_usuario['Usuario']['cliente_id'] != '' && $dados_usuario['Usuario']['cliente_id'] != null ) {

            if ( !isset($dados->client_client_id) || $dados->client_client_id == "" ) {
                throw new BadRequestException('Cliente não informado!', 401);
            }

            $dados->cliente_id = $dados_usuario['Usuario']['cliente_id'];
            $cliente_cliente_id = $dados->client_client_id;
            $cadastrado_por = 'cliente';
            $dados_cliente_cliente = $this->ClienteCliente->buscaDadosClienteCliente($cliente_cliente_id, $dados->cliente_id);
    
            if ( !$dados_cliente_cliente || count($dados_cliente_cliente) == 0) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Lamentamos. Não conseguimos encontrar os dados do cliente! ;('))));
            }

        } else {
            $cadastrado_por = 'cliente_cliente';

            //busca os dados do usuário do agendamento como cliente
            $dados_usuario_como_cliente = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], $dados->cliente_id);
    
            if ( !$dados_usuario_como_cliente || count($dados_usuario_como_cliente) == 0) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Lamentamos. Não conseguimos encontrar seus dados! ;('))));
            }
            $cliente_cliente_id = $dados_usuario_como_cliente['ClienteCliente']['id'];
    
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');

        //verfica se o cliente abrirá no dia
        $verificaFechamento = $this->ClienteHorarioAtendimentoExcessao->verificaExcessao($dados->cliente_id, $data_selecionada, 'F');

        if ( count($verificaFechamento) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A empresa não atenderá no dia e horário escolhido!'))));
        }

        //verifica se o usuário já não possui um agendamento pro mesmo dia e horário que está tentando
        $verificaAgendamento = $this->Agendamento->verificaAgendamento($cliente_cliente_id, null, $data_selecionada, $horario_selecionado);
        if ( $verificaAgendamento !== false && count($verificaAgendamento) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você já tem um agendamento neste horário!'))));
        }
        
        //busca o nº de agendamentos que o cliente tem neste dia e horário
        $n_agendamentos_cliente = $this->Agendamento->nAgendamentosCliente($dados->cliente_id, $data_selecionada, $horario_selecionado);
    
        $this->loadModel('ClienteHorarioAtendimento');

        //conta quantas vagas existem para o dia e horário escolhidos
        $vagas_restantes = $this->ClienteHorarioAtendimento->contaVagaRestantesHorario($dados->cliente_id, $data_selecionada, $horario_selecionado, $n_agendamentos_cliente);

        //$this->log($dados->cliente_id,'debug');
        //$this->log($data_selecionada,'debug');
        //$this->log($horario_selecionado,'debug');
        //$this->log($n_agendamentos_cliente,'debug');
        //$this->log($dados,'debug');

        if ( !$vagas_restantes ) {

            //verifica se abrirá com excessão
            $verificaAbertura = $this->ClienteHorarioAtendimentoExcessao->verificaExcessao($dados->cliente_id, $data_selecionada, 'A');

            if ( count($verificaAbertura) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A empresa não atenderá no dia e horário escolhido! 1'))));
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
            'cliente_cliente_id' => $cliente_cliente_id,
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

        //busca os ids do onesignal do usuário a ser notificado do cancelamento do horário
        $this->loadModel('Token');
        if ( $cadastrado_por == 'cliente' ) {
            $this->loadModel('Cliente');
            $notifications_ids = $this->Token->getIdsNotificationsUsuario($dados_cliente_cliente['ClienteCliente']['usuario_id']);
            $cadastrado_por = $this->Cliente->findEmpresaNomeById($dados->cliente_id);
        } else {
            $notifications_ids = $this->Token->getIdsNotificationsEmpresa($dados->cliente_id);
            $cadastrado_por = $dados_usuario['Usuario']['nome'];
        }

        if ( count($notifications_ids) > 0 ) {
            $data_str_agendamento = date('d/m',strtotime($dados_agendamento_salvo['Agendamento']['horario']));
            $hora_str_agendamento = date('H:i',strtotime($dados_agendamento_salvo['Agendamento']['horario']));
            $this->sendNotification( $notifications_ids, $dados_agendamento_salvo['Agendamento']['id'], "Novo Agendamento :)", "Você tem um novo agendamento de ".$cadastrado_por." às ".$hora_str_agendamento." do dia ".$data_str_agendamento, "agendamento" );
        }

        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! Agendamento cadastrado com sucesso!'))));
    }

    public function excluir(){
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->agendamento_id) || $dados->agendamento_id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }


        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }


        $this->loadModel('Agendamento');

        $conditions = [
            'Agendamento.id' => $dados->agendamento_id,
        ];

        $cancelado_por = 'cliente';
        if ( $dados_usuario['Usuario']['cliente_id'] != '' ) {
            $conditions = array_merge($conditions, [
                'Agendamento.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ]);
        } else {
            $conditions = array_merge($conditions, [
                'ClienteCliente.usuario_id' => $dados_usuario['Usuario']['id']
            ]);
            $cancelado_por = 'cliente_cliente';
        }

        $dados_agendamento = $this->Agendamento->find('first',[
            'fields' => [
                'Agendamento.id', 
                'Agendamento.horario', 
                'ClienteCliente.*',
                'Cliente.id',
                'Cliente.nome',
                'Usuario.id', 
                'Usuario.nome'
            ],
            'conditions' => $conditions,
            'link' => [
                'ClienteCliente' => ['Usuario'], 'Cliente'
            ]
        ]);
       

        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O agendamento que você está tentando exlcuir, não existe!'))));
        }

        $dados_salvar['Agendamento']['id'] = $dados_agendamento['Agendamento']['id'];
        $dados_salvar['Agendamento']['cancelado'] = 'Y';

        if ( !$this->Agendamento->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cancelar o agendamento. Por favor, tente mais tarde!'))));
        }

        //busca os ids do onesignal do usuário a ser notificado do cancelamento do horário
        $this->loadModel('Token');
        if ( $cancelado_por == 'cliente' ) {    
            $notifications_ids = $this->Token->getIdsNotificationsUsuario($dados_agendamento['Usuario']['id']);
            $nome_usuario_cancelou = $dados_agendamento['Cliente']['nome'];
        } else {
            $notifications_ids = $this->Token->getIdsNotificationsEmpresa($dados_agendamento['Cliente']['id']);
            $nome_usuario_cancelou = $dados_agendamento['Usuario']['nome'];
        }

        if ( count($notifications_ids) > 0 ) {
            $data_str_agendamento = date('d/m',strtotime($dados_agendamento['Agendamento']['horario']));
            $hora_str_agendamento = date('H:i',strtotime($dados_agendamento['Agendamento']['horario']));
            $this->sendNotification( $notifications_ids, $dados_agendamento['Agendamento']['id'], "Agendamento Cancelado :(", $nome_usuario_cancelou." cancelou o agendamento das ".$hora_str_agendamento." do dia ".$data_str_agendamento, "agendamento_cancelado" );
        }
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Agendamento cancelado com sucesso!'))));

    }
}