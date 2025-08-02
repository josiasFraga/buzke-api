<?php
class AgendamentosController extends AppController {

    public $helpers = array('Html', 'Form');
    public $components = array('RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function usuarios_verificar() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');

        $dataLimite = date('Y-m-d H:i:s', strtotime('-2 weeks'));
        //$dataLimite = date('Y-m-d H:i:s', strtotime('-4 years'));

        $usuarios_verificar = $this->Agendamento->find('all',[
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                'Usuario.img',
                'Usuario.created',
                'Usuario.telefone_ddi',
                'Usuario.telefone',
                'Usuario.email',
                'ClienteCliente.endereco',

                'ClienteCliente.id',
                'ClienteCliente.nacionalidade',
                'ClienteCliente.pais',
                'ClienteCliente.bairro',
                'ClienteCliente.endreceo_n',
                'ClienteCliente.cep',
                'Localidade.loc_no',
                'Uf.ufe_sg',
            ],
            'conditions' => [
                'Agendamento.cliente_id' => $dados_token['Usuario']['cliente_id'],
                'Usuario.created >=' => $dataLimite
            ],
            'link' => [
                'ClienteCliente' => [
                    'Usuario',
                    'Localidade',
                    'Uf'
                ],
                'ClienteServico'
            ],
            'group' => [
                'Usuario.id'
            ]
        ]);


        foreach( $usuarios_verificar as $key => $usuario ){ 
            $usuarios_verificar[$key]['ClienteCliente']['telefone_ddi'] = $usuarios_verificar[$key]['Usuario']['telefone_ddi'];
            $usuarios_verificar[$key]['ClienteCliente']['telefone'] = $usuarios_verificar[$key]['Usuario']['telefone'];
            $usuarios_verificar[$key]['ClienteCliente']['telefone'] = $usuarios_verificar[$key]['Usuario']['telefone'];
            $usuarios_verificar[$key]['ClienteCliente']['nome'] = $usuarios_verificar[$key]['Usuario']['nome'];
            $usuarios_verificar[$key]['ClienteCliente']['email'] = $usuarios_verificar[$key]['Usuario']['email'];
            $usuarios_verificar[$key]['ClienteCliente']['img'] = $usuario['Usuario']['img'];
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $usuarios_verificar))));
    }

    public function convitesAdicionais(){
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }


        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->cliente_id) || $dados->cliente_id == "" || !is_numeric($dados->cliente_id) ) {
            throw new BadRequestException('Dados da empresa não informada!', 401);
        }

        if ( !isset($dados->horaSelecionada) || $dados->horaSelecionada == "" ) {
            throw new BadRequestException('Hora não informada!', 401);
        }

        if ( gettype($dados->horaSelecionada) === 'string' ) {
            list($data_selecionada, $horario_selecionado) = explode(' ',$dados->horaSelecionada);
        } else {
            list($data_selecionada, $horario_selecionado) = explode(' ',$dados->horaSelecionada->horario);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        $this->loadModel('ClienteCliente');
        $this->loadModel('Agendamento');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('Cliente');

        //busca os dados da empresa
        $dados_cliente = $this->Cliente->find('first',[
            'fields' => ['Cliente.id', 'Localidade.loc_no', 'Localidade.ufe_sg'],
            'conditions' => [
                'Cliente.id' => $dados->cliente_id,
                'Cliente.ativo' => 'Y'
            ],
            'link' => ['Localidade']
        ]);

        if (count($dados_cliente) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Empresa não encontrada!'))));
        }

        $agendamento_dia_semana = date('w',strtotime($data_selecionada.' '.$horario_selecionado));
        $agendamento_dia_mes = (int)date('d',strtotime($data_selecionada.' '.$horario_selecionado));

        //verifica se a empresa é uma quadra, se não for, nào sào permitidos convites
        $isCourt = $this->ClienteSubcategoria->checkIsCourt($dados->cliente_id);

        if (!$isCourt) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O agendamento não pertence a uma quadra!'))));
        }
        
        //busca os dados do usuário do agendamento como cliente
        $dados_usuario_como_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], true);
        $dados_agendamento = $this->Agendamento->find('first',[
            'conditions' => [
                'Agendamento.cliente_id' => $dados->cliente_id,
                'Agendamento.cliente_cliente_id' => $dados_usuario_como_cliente,
                'TIME(Agendamento.horario)' => $horario_selecionado,
                'Agendamento.cancelado' => 'N',
                'or' => [
                    [
                        'DATE(Agendamento.horario)' => $data_selecionada,
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => null,
                    ],[
                        'Agendamento.dia_semana' => $agendamento_dia_semana,
                    ],[
                        'Agendamento.dia_mes' => $agendamento_dia_mes,
                    ]
                ]
            ],
            'link' => []
        ]);

        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Agendamento não encontrado!'))));
        }

        if ( isset($dados->convites_tpj) && is_array($dados->convites_tpj)) {
            $dados->convites_tpj = (object)$dados->convites_tpj;
        }

        if ( isset($dados->convites_grl) && is_array($dados->convites_grl)) {
            $dados->convites_grl = (object)$dados->convites_grl;
        }

        if ( gettype($dados->horaSelecionada) === 'string' ) {
            $dados_agendamento['Agendamento']['horario'] = $dados->horaSelecionada;
        } else {
            $dados_agendamento['Agendamento']['horario'] = $dados->horaSelecionada->horario;
        }

        $this->enviaConvites($dados, $dados_agendamento, $dados_cliente['Localidade']);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! Convites enviados com sucesso!'))));
    }

    private function enviaConvites ($dados, $dados_agendamento_salvo, $cliente_localizacao) {

        $this->loadModel('Usuario');
        $this->loadModel('UsuarioLocalizacao');
    
        $clientes_clientes_ids_convidados = [];

        //convites do to pro jogo
        if (isset($dados->convites_tpj) && count(get_object_vars($dados->convites_tpj)) > 0) {
            foreach($dados->convites_tpj as $key => $convite){
                if($convite) {
                    list($discard, $id_convidado) = explode('_',$key);
                    $clientes_clientes_ids_convidados[] = $id_convidado;
                }
            }
        }

        //convites geral
        if (isset($dados->convites_grl) && count(get_object_vars($dados->convites_grl)) > 0) {
            $usuarios_perfil_convite = $this->Usuario->getClientDataByPadelistProfile($dados->convites_grl);
            $usuarios_perfil_convite = $this->UsuarioLocalizacao->filterByLastLocation($usuarios_perfil_convite, $cliente_localizacao);
            $clientes_clientes_ids_convidados = array_merge($clientes_clientes_ids_convidados, $usuarios_perfil_convite);
            $clientes_clientes_ids_convidados = array_values($clientes_clientes_ids_convidados);
        }

        if ( count($clientes_clientes_ids_convidados) > 0 ) {
            $this->saveInvitesAndSendNotification($clientes_clientes_ids_convidados, $dados_agendamento_salvo['Agendamento']);
        }

    }

    public function excluir(){
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');

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

        if ( !isset($dados->tipo) || $dados->tipo == "" ) {
            throw new BadRequestException('Tipo não informado!', 401);
        }

        if ( !isset($dados->horario) || $dados->horario == "" ) {
            throw new BadRequestException('Horário não informado!', 401);
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
                'Agendamento.dia_semana', 
                'Agendamento.dia_mes',  
                'ClienteCliente.*',
                'Cliente.id',
                'Cliente.nome',
                'Usuario.id', 
                'Usuario.nome'
            ],
            'conditions' => $conditions,
            'link' => [
                'ClienteCliente' => [
                    'Usuario'
                ], 
                'Cliente'
            ]
        ]);
       
        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O agendamento que você está tentando exlcuir, não existe!'))));
        }

        if ( $dados_agendamento['Agendamento']['dia_semana'] != '' || $dados_agendamento['Agendamento']['dia_mes'] != '' ) {
            if ( $dados->tipo == 1 ) {
                $this->loadModel('AgendamentoFixoCancelado');
                $dados_salvar = [
                    'agendamento_id' => $dados_agendamento['Agendamento']['id'],
                    'cliente_cliente_id' => $dados_agendamento['ClienteCliente']['id'],
                    'horario' => $dados->horario,
                    'cancelado_por' => $cancelado_por,
                    'cancelado_por_id' => $dados_usuario['Usuario']['id'],
                ];

                if ( !$this->AgendamentoFixoCancelado->save($dados_salvar) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cancelar o agendamento. Por favor, tente novamente mais tarde!'))));
                }

                // Seta o convite como agendamento cancelado e envia notificação aos usuários
                $this->avisaConvidadosCancelamento($dados->horario, $dados_agendamento['Agendamento']['id']);

                // Envia notificação de cancelamento para o usuário titular ou para a empresa
                $this->enviaNotificacaoDeCancelamento($cancelado_por, $dados->horario, $dados_agendamento);

                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Agendamento cancelado com sucesso!'))));

            } 
        }

        $dados_salvar['Agendamento']['id'] = $dados_agendamento['Agendamento']['id'];
        $dados_salvar['Agendamento']['cancelado'] = 'Y';        
        $dados_salvar['Agendamento']['cancelado_por_id'] = $dados_usuario['Usuario']['id'];

        if ( !$this->Agendamento->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cancelar o agendamento. Por favor, tente mais tarde!'))));
        }
        
        // Seta o convite como agendamento cancelado e envia notificação aos usuários
        $this->avisaConvidadosCancelamento($dados->horario, $dados_agendamento['Agendamento']['id']);

        // Envia notificação de cancelamento para o usuário titular ou para a empresa
        $this->enviaNotificacaoDeCancelamento($cancelado_por, $dados->horario, $dados_agendamento);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Agendamento cancelado com sucesso!'))));

    }

    public function setSyncId() {

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        } elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteCliente');
        $this->loadModel('AgendamentoClienteCliente');
    
        //busca os dados do usuário do agendamento como cliente
        $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], true);

        $dados_agendamento = $this->Agendamento->find('first',[
            'fields' => [
                'Agendamento.id',
                'AgendamentoClienteCliente.id',
                'Agendamento.cliente_cliente_id'
            ],
            'conditions' => [
                'Agendamento.id' => $dados->id,
                'OR' => [
                    'AgendamentoClienteCliente.cliente_cliente_id' => $meus_ids_de_cliente,
                    'Agendamento.cliente_cliente_id' => $meus_ids_de_cliente
                ]
            ],
            'link' => [
                'AgendamentoClienteCliente'
            ]
        ]);

        if ( count($dados_agendamento) === 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Agendamento não encontrado!'))));
        }

        $dados_salvar = [
            'agendamento_id' => $dados->id,
            'cliente_cliente_id' => $dados_agendamento['Agendamento']['cliente_cliente_id']
        ];

        if ( !empty($dados_agendamento['AgendamentoClienteCliente']['id']) ) {
            $dados_salvar['id'] = $dados_agendamento['AgendamentoClienteCliente']['id'];
        } else {
            $this->AgendamentoClienteCliente->create();
        }

        if ( strtolower($dados->plataforma) === 'ios' ) {
            $dados_salvar['id_sync_ios'] = $dados->id_sync;
            $dados_salvar['data_sync_ios'] = date('Y-m-d H:i:d');
        }  else {
            $dados_salvar['id_sync_google'] = $dados->id_sync;
            $dados_salvar['data_sync_google'] = date('Y-m-d H:i:d');
        }

        if ( !$this->AgendamentoClienteCliente->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao sincronizar o agendamento!'))));
        }
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Item sincronizado com sucesso!'))));

    }
}