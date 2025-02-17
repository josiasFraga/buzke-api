<?php

class NotificacoesController extends AppController {
    
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
        if ( empty($dados['email']) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Notificacao');
        $notificacoes = $this->Notificacao->getByUserId($dados_token['Usuario']['id']);

        foreach( @$notificacoes as $key => $notificacao ) {
            $notificacoes[$key]['Notificacao']['_data'] = date('d/m/Y',strtotime($notificacao['Notificacao']['created']));
            $notificacoes[$key]['Notificacao']['_hora'] = date('H:i',strtotime($notificacao['Notificacao']['created']));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $notificacoes))));
    }

    public function marcar_como_lida() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }


        $token = $dados->token;
        $email = $dados->email;

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
       
        $this->loadModel('Notificacao');

        if ( !isset($dados->id) || $dados->id == "" ) {
            if ( !$this->Notificacao->setRead('all', null, $dados_token['Usuario']['id']) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Erro ao setar notificações como lidas.'))));
            }
        } else {
            $notificacao_id = $dados->id;

            // Posso passar o id do one signal
            if ( !is_numeric($dados->id) ) {
                $notificacao_id = $this->Notificacao->getIdFromOneSginalId($dados->id);
            }

            if ( !$this->Notificacao->setRead('one', $notificacao_id) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Erro ao setar notificação como lida.'))));
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo.'))));
    }

    public function n_nao_lidas() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => 0))));
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Notificacao');
        $n_notificacoes = $this->Notificacao->countByUserId($dados_token['Usuario']['id']);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => ($n_notificacoes == null ? 0 : $n_notificacoes)))));
    }

    public function user_configurations() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $usuario_id = $dados_token['Usuario']['id'];

        $this->loadModel('NotificacaoConfiguracaoUsuario');

        $configs_usuario = $this->NotificacaoConfiguracaoUsuario->find('first',[
            'conditions' => [
                'NotificacaoConfiguracaoUsuario.usuario_id' => $usuario_id
            ],
            'link' => []
        ]);

        if ( count($configs_usuario) == 0 ) {
            $dados_salvar = [
                'usuario_id' => $usuario_id
            ];

            $this->NotificacaoConfiguracaoUsuario->create();

            $configs_usuario = $this->NotificacaoConfiguracaoUsuario->save($dados_salvar);

            if ( !$configs_usuario ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Ocorreu um erro ao buscar as configurações de notificações.'))));
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $configs_usuario))));

    }

    public function save_user_configurations() {

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        } else {
            $dados = json_decode($dados);
        }

        $token = $dados->token;
        $email = $dados->email;

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $usuario_id = $dados_token['Usuario']['id'];
       
        $this->loadModel('NotificacaoConfiguracaoUsuario');

        $configuracao = $this->NotificacaoConfiguracaoUsuario->find('first', [
            'conditions' => [
                'NotificacaoConfiguracaoUsuario.usuario_id' => $usuario_id
            ],
            'link' => []
        ]);

        $dados_salvar = [
            'usuario_id' => $usuario_id
        ];

        if ($configuracao) {
            $dados_salvar['id'] = $configuracao['NotificacaoConfiguracaoUsuario']['id'];
        } else {
            $this->NotificacaoConfiguracaoUsuario->create();
        }

        $dados_salvar['receber_promocoes'] = isset($dados->receber_promocoes) ? $dados->receber_promocoes : false;
        $dados_salvar['receber_lembretes'] = isset($dados->receber_lembretes) ? $dados->receber_lembretes : false;
        $dados_salvar['receber_avaliacoes'] = isset($dados->receber_avaliacoes) ? $dados->receber_avaliacoes : false;
        $dados_salvar['receber_convites'] = isset($dados->receber_convites) ? $dados->receber_convites : false;
        $dados_salvar['receber_avisos_cancelamentos'] = isset($dados->receber_avisos_cancelamentos) ? $dados->receber_avisos_cancelamentos : false;
        $dados_salvar['receber_aviso_novo_torneio_padel'] = isset($dados->receber_aviso_novo_torneio_padel) ? $dados->receber_aviso_novo_torneio_padel : false;
        $dados_salvar['receber_aviso_jogos_padel_liberados'] = isset($dados->receber_aviso_jogos_padel_liberados) ? $dados->receber_aviso_jogos_padel_liberados : false;
        $dados_salvar['receber_avisos_novos_agendamentos'] = isset($dados->receber_avisos_novos_agendamentos) ? $dados->receber_avisos_novos_agendamentos : false;
        $dados_salvar['receber_convite_dupla_desafio'] = isset($dados->receber_convite_dupla_desafio) ? $dados->receber_convite_dupla_desafio : false;
        $dados_salvar['receber_resultados_jogos'] = isset($dados->receber_resultados_jogos) ? $dados->receber_resultados_jogos : false;

        if ( !$this->NotificacaoConfiguracaoUsuario->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar as configurações de notificações.'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Configurações de notificações salvar com sucesso!'))));

    }

    public function testar() {

        $dados = $this->request->query;
        $usuario_id = $dados['usuario_id'];
        $agendamento_id = $dados['agendamento_id'];
        $notification_msg = 'Teste mensagem';
        $agendamento_data = $dados['agendamento_data'];
        $motivo = $dados['motivo'];

        $this->loadModel('Token');
        
        $notifications_ids = $this->Token->getIdsNotificationsUsuario($usuario_id);
         

        $this->sendNotificationNew( 
            $usuario_id,
            $notifications_ids, 
            $agendamento_id,
            $agendamento_data,
            $motivo,
            ["en"=> '$[notif_count] Novos Agendamentos']
        );

        die('fim');

    }

}