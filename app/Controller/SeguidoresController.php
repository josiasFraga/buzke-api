<?php
class SeguidoresController extends AppController {

    public $components = array('RequestHandler');

    private function protectAuthenticatedPost( $dados ) {

        if ( empty($dados->token) || empty($dados->email) ) {
            throw new BadRequestException('Dados de usuário não informados!', 401);
        }
    
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
    
        if (!$dados_usuario) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        return $dados_usuario;

    }


    public function add() {

        $this->layout = 'ajax';
        $this->autoRender = false;
        $this->response->type('json');
        
        // Recebendo dados da requisição
        $dados = isset($this->request->data['dados']) ? $this->request->data['dados'] : null;

        // Verifica se os dados foram enviados
        if (empty($dados)) {
            throw new BadRequestException('Nenhum dado enviado!', 400);
        }

        //$this->log(addslashes($dados), 'debug');
        //die();

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        }
        else {
            $dados = json_decode($dados);
        }

        if ( empty($dados->user_id) ) {
            throw new BadRequestException('Usuário não informado!', 400);
        }

        // Protege a rota autenticada
        $dados_usuario = $this->protectAuthenticatedPost($dados);

        if ( $dados_usuario['Usuario']['id'] === $dados->user_id ) {
            $response = [
                'status' => 'warning',
                'msg' => 'Você não pode seguir a si mesmo!'
            ];
    
            return $this->response->body(json_encode($response));
        }

        $this->loadModel('Seguidor');
        $this->loadModel('Usuario');
        $this->loadModel('UsuarioBloqueado');

        // Verifica se o usuário está que está seguindo está bloqueado pelo seguido
        $checkIsBlocked = $this->UsuarioBloqueado->checkIsBlocked($dados_usuario['Usuario']['id'], $dados->user_id);

        if ( $checkIsBlocked['isBloqued'] ) {
            $response = [
                'status' => 'erro',
                'msg' => 'Não encontramos os dados do usuário',
                'motive' => $checkIsBlocked['motive']
            ];
    
            return $this->response->body(json_encode($response));
        }

        // Verifica se o usuário que será seguido está bloqueado pelo seguidor
        $checkIsBlocked = $this->UsuarioBloqueado->checkIsBlocked($dados->user_id, $dados_usuario['Usuario']['id']);

        if ( $checkIsBlocked['isBloqued'] ) {
            $response = [
                'status' => 'warning',
                'msg' => 'Você não pode seguir um usuário que você bloqueou',
                'motive' => $checkIsBlocked['motive']
            ];
    
            return $this->response->body(json_encode($response));
        }

        // Busca os dados do usuário que será seguido
        $dados_usuario_seguido = $this->Usuario->find('first',[
            'fields' => [
                'Usuario.perfil_esportista_privado'
            ],
            'conditions' => [
                'Usuario.id' => $dados->user_id
            ],
            'link' => []
        ]);

        // Se não encontrar os dados do usuário a ser seguido
        if ( empty($dados_usuario_seguido) ) {
            $response = [
                'status' => 'erro',
                'msg' => 'Não encontramos os dados do usuário'
            ];
    
            return $this->response->body(json_encode($response));
        }

        // Se o perfil do usuário a ser seguido for privado
        if ( $dados_usuario_seguido['Usuario']['perfil_esportista_privado'] === 'N' ) {
            $status = 'ativo';
            $notificacao_motivo = 'novo_seguidor';
        } else {
            $status = 'pendente';
            $notificacao_motivo = 'permissao_seguir';
        }

        // Verifica se já existe um registro de seguidor
        $busca_dados = $this->Seguidor->find('first',[
            'fields' => [
                'Seguidor.id'
            ],
            'conditions' => [
                'Seguidor.usuario_seguidor_id' => $dados_usuario['Usuario']['id'],
                'Seguidor.usuario_seguido_id' => $dados->user_id
            ],
            'link' => []
        ]);

        $dados_salvar = [
            'status' => $status,
            'deleted' => 0,
            'usuario_seguidor_id' => $dados_usuario['Usuario']['id'],
            'usuario_seguido_id' => $dados->user_id
        ];

        if ( !empty($busca_dados) ) {
            $dados_salvar['id'] = $busca_dados['Seguidor']['id'];
        } else {
            $this->Seguidor->create();
        }

        $busca_dados = $this->Seguidor->save($dados_salvar);
        if ( !$busca_dados ) {
            $response = [
                'status' => 'erro',
                'msg' => 'Erro ao seguir usuário'
            ];
        } else {
            $response = [
                'status' => 'ok',
                'msg' => 'Sucesso!'
            ];

            $this->loadModel('Token');

            // Avisa o usuário que ele foi seguido
            $notifications_ids = $this->Token->getIdsNotificationsUsuario($dados->user_id);

            $this->sendNotificationNew( 
                $dados->user_id,
                $notifications_ids, 
                $busca_dados['Seguidor']['id'],
                null,
                $notificacao_motivo,
                ["en"=> '$[notif_count] Novo seguidor']
            );
        }
    
        return $this->response->body(json_encode($response));

    }

    public function aceitar() {

        $this->layout = 'ajax';
        $this->autoRender = false;
        $this->response->type('json');
        
        // Recebendo dados da requisição
        $dados = isset($this->request->data['dados']) ? $this->request->data['dados'] : null;

        // Verifica se os dados foram enviados
        if (empty($dados)) {
            throw new BadRequestException('Nenhum dado enviado!', 400);
        }

        //$this->log(addslashes($dados), 'debug');
        //die();

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        }
        else {
            $dados = json_decode($dados);
        }

        if (empty($dados->user_id)) {
            throw new BadRequestException('Usuário não informado!', 400);
        }

        // Protege a rota autenticada
        $dados_usuario = $this->protectAuthenticatedPost($dados);

        $this->loadModel('Seguidor');

        $busca_dados = $this->Seguidor->find('first',[
            'fields' => [
                'Seguidor.id'
            ],
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $dados_usuario['Usuario']['id'],
                'Seguidor.usuario_seguidor_id' => $dados->user_id,
                'Seguidor.status' => 'pendente'
            ],
            'link' => []
        ]);

        if ( empty($busca_dados) ) {

            $response = [
                'status' => 'warning',
                'msg' => 'Não encontramos os dados da solicitação, talvez ela tenha sido exluída pelo usuário.'
            ];
    
            return $this->response->body(json_encode($response));

        }

        $dados_salvar = [
            'id' => $busca_dados['Seguidor']['id'],
            'status' => 'ativo'
        ];

        if ( !$this->Seguidor->save($dados_salvar) ) {
        //if ( 1 == 2 ) {
            $response = [
                'status' => 'erro',
                'msg' => 'Erro ao aceitar seguidor'
            ];
        } else {
            $response = [
                'status' => 'ok',
                'msg' => 'Sucesso!'
            ];

            

            $this->loadModel('Notificacao');

            $notificacoes_atualizar = array_values($this->Notificacao->find('list',[
                'fields' => [
                    'Notificacao.id',
                    'Notificacao.id'
                ],
                'conditions' => [
                    'Notificacao.acao_selecionada' => null,
                    'Notificacao.usuario_origem' => $dados->user_id,
                    'NotificacaoUsuario.usuario_id' => $dados_usuario['Usuario']['id'],
                    'Notificacao.registro_id' => $busca_dados['Seguidor']['id'],
                    'NotificacaoMotivo.nome' => 'permissao_seguir',
                ],
                'link' => [
                    'NotificacaoUsuario',
                    'NotificacaoMotivo'
                ]
            ]));

            if ( !empty($notificacoes_atualizar) ) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'Y'",
                    'acao_selecionada_desc' => "'Aceito'"
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);
            }

            $this->loadModel('Token');

            // Avisa o usuário que ele foi aceito como seguidor 
            $notifications_ids = $this->Token->getIdsNotificationsUsuario($dados->user_id);

            $this->sendNotificationNew( 
                $dados->user_id,
                $notifications_ids, 
                $busca_dados['Seguidor']['id'],
                null,
                'seguidor_aceito',
                ["en"=> '$[notif_count] Você foi aceito como seguidor']
            );
        }
    
        return $this->response->body(json_encode($response));

    }

    public function remover() {
        
        $this->layout = 'ajax';
        $this->autoRender = false;
        $this->response->type('json');
        
        // Recebendo dados da requisição
        $dados = isset($this->request->data['dados']) ? $this->request->data['dados'] : null;

        // Verifica se os dados foram enviados
        if (empty($dados)) {
            throw new BadRequestException('Nenhum dado enviado!', 400);
        }
        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        }
        else {
            $dados = json_decode($dados);
        }

        if (empty($dados->user_id)) {
            throw new BadRequestException('Usuário não informado!', 400);
        }


        // Protege a rota autenticada
        $dados_usuario = $this->protectAuthenticatedPost($dados);

        $this->loadModel('Seguidor');

        $busca_dados = $this->Seguidor->find('first',[
            'fields' => [
                'Seguidor.id'
            ],
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $dados_usuario['Usuario']['id'],
                'Seguidor.usuario_seguidor_id' => $dados->user_id
            ],
            'link' => []
        ]);

        if ( empty($busca_dados) ) {

            $response = [
                'status' => 'ok',
                'msg' => 'Registro inexistente'
            ];
    
            return $this->response->body(json_encode($response));

        }

        $dados_salvar = [
            'id' => $busca_dados['Seguidor']['id'],
            'deleted' => 1
        ];

        if ( !$this->Seguidor->save($dados_salvar) ) {
            $response = [
                'status' => 'erro',
                'msg' => 'Erro ao remover o seguidor'
            ];
        } else {
            $response = [
                'status' => 'ok',
                'msg' => 'Sucesso!'
            ];
        }
    
        return $this->response->body(json_encode($response));

    }

    public function recusar() {
        
        $this->layout = 'ajax';
        $this->autoRender = false;
        $this->response->type('json');
        
        // Recebendo dados da requisição
        $dados = isset($this->request->data['dados']) ? $this->request->data['dados'] : null;

        //$this->log(addslashes($dados), 'debug');
        //die();

        // Verifica se os dados foram enviados
        if (empty($dados)) {
            throw new BadRequestException('Nenhum dado enviado!', 400);
        }

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        }
        else {
            $dados = json_decode($dados);
        }

        if (empty($dados->user_id)) {
            throw new BadRequestException('Usuário não informado!', 400);
        }

        // Protege a rota autenticada
        $dados_usuario = $this->protectAuthenticatedPost($dados);

        $this->loadModel('Seguidor');

        $busca_dados = $this->Seguidor->find('first',[
            'fields' => [
                'Seguidor.id'
            ],
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $dados_usuario['Usuario']['id'],
                'Seguidor.usuario_seguidor_id' => $dados->user_id,
                'Seguidor.status' => 'pendente'
            ],
            'link' => []
        ]);

        if ( empty($busca_dados) ) {

            $response = [
                'status' => 'warning',
                'msg' => 'Registro inexistente. Tavlez o usuário removeu o pedido para seguir.'
            ];
    
            return $this->response->body(json_encode($response));

        }

        $dados_salvar = [
            'id' => $busca_dados['Seguidor']['id'],
            'deleted' => 1
        ];

        if ( !$this->Seguidor->save($dados_salvar) ) {
            $response = [
                'status' => 'erro',
                'msg' => 'Erro ao remover o seguidor'
            ];
        } else {
            $response = [
                'status' => 'ok',
                'msg' => 'Sucesso!'
            ];

            $this->loadModel('Notificacao');

            $notificacoes_atualizar = array_values($this->Notificacao->find('list',[
                'fields' => [
                    'Notificacao.id',
                    'Notificacao.id'
                ],
                'conditions' => [
                    'Notificacao.acao_selecionada' => null,
                    'Notificacao.usuario_origem' => $dados->user_id,
                    'NotificacaoUsuario.usuario_id' => $dados_usuario['Usuario']['id'],
                    'Notificacao.registro_id' => $busca_dados['Seguidor']['id'],
                    'NotificacaoMotivo.nome' => 'permissao_seguir',
                ],
                'link' => [
                    'NotificacaoUsuario',
                    'NotificacaoMotivo'
                ]
            ]));

            if ( !empty($notificacoes_atualizar) ) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'N'",
                    'acao_selecionada_desc' => "'Recusado'"
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);
            }
        }
    
        return $this->response->body(json_encode($response));

    }


}