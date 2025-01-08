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

    public function n_seguidores() {
        
        // Desabilita a renderização automática e define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');
    
        $dados = $this->request->query;

        $usuario_id = !empty($dados['usuario_id']) ? $dados['usuario_id'] : null;
        $token = !empty($dados['token']) ? $dados['token'] : null;
        $email = !empty($dados['token']) ? $dados['email'] : null;
        
        if ( empty($usuario_id) && empty($token) ) {
            $response = [
                'status' => 'ok',
                'dados' => 0
            ];
            return $this->response->body(json_encode($response));
        }

        if ( !empty($token) ) {

            $dados_usuario = $this->verificaValidadeToken($token, $email);
    
            if ( !$dados_usuario ) {
                throw new BadRequestException('Usuário não logado!', 401);
            }

            if ( empty($usuario_id) ) {
                $usuario_id = $dados_usuario['Usuario']['id'];
            }

        }

        $this->loadModel('Seguidor');

        $n_seguidores = $this->Seguidor->find('count', [
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $usuario_id,
                'status' => 'ativo',
                'deleted' => '0'
            ],
            'link' => []
        ]);

        $response = [
            'status' => 'ok',
            'dados' => $n_seguidores,
            'usuario_id' => $usuario_id
        ];

        return $this->response->body(json_encode($response));



    }

    public function n_seguindo() {
        
        // Desabilita a renderização automática e define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');
    
        $dados = $this->request->query;

        $usuario_id = !empty($dados['usuario_id']) ? $dados['usuario_id'] : null;
        $token = !empty($dados['token']) ? $dados['token'] : null;
        $email = !empty($dados['token']) ? $dados['email'] : null;
        
        if ( empty($usuario_id) && empty($token) ) {
            $response = [
                'status' => 'ok',
                'dados' => 0
            ];
            return $this->response->body(json_encode($response));
        }

        if ( !empty($token) ) {

            $dados_usuario = $this->verificaValidadeToken($token, $email);
    
            if ( !$dados_usuario ) {
                throw new BadRequestException('Usuário não logado!', 401);
            }

            if ( empty($usuario_id) ) {
                $usuario_id = $dados_usuario['Usuario']['id'];
            }

        }

        $this->loadModel('Seguidor');

        $n_seguidores = $this->Seguidor->find('count', [
            'conditions' => [
                'Seguidor.usuario_seguidor_id' => $usuario_id,
                'status' => 'ativo',
                'deleted' => '0'
            ],
            'link' => []
        ]);

        $response = [
            'status' => 'ok',
            'dados' => $n_seguidores,
            'usuario_id' => $usuario_id
        ];

        return $this->response->body(json_encode($response));



    }

    public function seguidores() {

        // Desabilita a renderização automática e define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');
    
        $dados = $this->request->query;

        $usuario_id = !empty($dados['usuario_id']) ? $dados['usuario_id'] : null;
        $token = !empty($dados['token']) ? $dados['token'] : null;
        $email = !empty($dados['token']) ? $dados['email'] : null;
        $conditions_status[] = 'ativo';
        $my_followers = false;
        
        if ( empty($usuario_id) && empty($token) ) {
            $response = [
                'status' => 'ok',
                'dados' => []
            ];
            return $this->response->body(json_encode($response));
        }

        if ( !empty($token) ) {

            $dados_usuario = $this->verificaValidadeToken($token, $email);
    
            if ( !$dados_usuario ) {
                throw new BadRequestException('Usuário não logado!', 401);
            }

            if ( empty($usuario_id) ) {
                $usuario_id = $dados_usuario['Usuario']['id'];
                //$conditions_status[] = 'pendente';
                $my_followers = true;
            }

        }

        $this->loadModel('Seguidor');

        $seguidores = $this->Seguidor->find('all', [
            'fields' => [
                'Seguidor.id',
                'Seguidor.status',
                'UsuarioSeguidor.img',
                'UsuarioSeguidor.usuario',
                'UsuarioSeguidor.nome'
            ],
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $usuario_id,
                'status' => $conditions_status,
                'deleted' => '0'
            ],
            'contain' => [
                'UsuarioSeguidor'
            ],
            'order' => [
                'status DESC',
                'UsuarioSeguidor.nome ASC'
            ]
        ]);

        $seguidores = array_map(function($seguidor) use($my_followers) {
            return [
                'status' => $seguidor['Seguidor']['status'],
                'img' => $seguidor['UsuarioSeguidor']['img'],
                'usuario' => $seguidor['UsuarioSeguidor']['usuario'],
                'nome' => $seguidor['UsuarioSeguidor']['nome'],
                'id' => $seguidor['UsuarioSeguidor']['id'],
                'can_remove' => $my_followers
            ];
        },$seguidores);

        $response = [
            'status' => 'ok',
            'dados' => $seguidores,
            'usuario_id' => $usuario_id
        ];

        return $this->response->body(json_encode($response));



    }

    public function seguindo() {

        // Desabilita a renderização automática e define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');
    
        $dados = $this->request->query;

        $usuario_id = !empty($dados['usuario_id']) ? $dados['usuario_id'] : null;
        $token = !empty($dados['token']) ? $dados['token'] : null;
        $email = !empty($dados['token']) ? $dados['email'] : null;
        $conditions_status[] = 'ativo';
        $my_following = false;
        
        if ( empty($usuario_id) && empty($token) ) {
            $response = [
                'status' => 'ok',
                'dados' => []
            ];
            return $this->response->body(json_encode($response));
        }

        if ( !empty($token) ) {

            $dados_usuario = $this->verificaValidadeToken($token, $email);
    
            if ( !$dados_usuario ) {
                throw new BadRequestException('Usuário não logado!', 401);
            }

            if ( empty($usuario_id) ) {
                $usuario_id = $dados_usuario['Usuario']['id'];
                $conditions_status[] = 'pendente';
                $my_following = true;
            }

        }

        $this->loadModel('Seguidor');

        $seguindo = $this->Seguidor->find('all', [
            'fields' => [
                'Seguidor.id',
                'Seguidor.status',
                'UsuarioSeguido.img',
                'UsuarioSeguido.usuario',
                'UsuarioSeguido.nome'
            ],
            'conditions' => [
                'Seguidor.usuario_seguidor_id' => $usuario_id,
                'status' => $conditions_status,
                'deleted' => '0'
            ],
            'contain' => [
                'UsuarioSeguido'
            ],
            'order' => [
                'status DESC',
                'UsuarioSeguido.nome ASC'
            ]
        ]);

        $seguindo = array_map(function($seguidor) use($my_following) {
            return [
                'status' => $seguidor['Seguidor']['status'],
                'img' => $seguidor['UsuarioSeguido']['img'],
                'usuario' => $seguidor['UsuarioSeguido']['usuario'],
                'nome' => $seguidor['UsuarioSeguido']['nome'],
                'id' => $seguidor['UsuarioSeguido']['id'],
                'can_remove' => $my_following
            ];
        },$seguindo);

        $response = [
            'status' => 'ok',
            'dados' => $seguindo,
            'usuario_id' => $usuario_id
        ];

        return $this->response->body(json_encode($response));



    }

    public function seguidores_pendentes() {

        // Desabilita a renderização automática e define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');
    
        $dados = $this->request->query;

       
        $token = !empty($dados['token']) ? $dados['token'] : null;
        $email = !empty($dados['token']) ? $dados['email'] : null;
        $conditions_status[] = 'pendente';
        
        if ( empty($email) || empty($token) ) {
            $response = [
                'status' => 'ok',
                'dados' => []
            ];
            return $this->response->body(json_encode($response));
        }

        $dados_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $usuario_id = $dados_usuario['Usuario']['id'];

        $this->loadModel('Seguidor');

        $seguidores = $this->Seguidor->find('all', [
            'fields' => [
                'Seguidor.id',
                'Seguidor.status',
                'UsuarioSeguidor.img',
                'UsuarioSeguidor.usuario',
                'UsuarioSeguidor.nome'
            ],
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $usuario_id,
                'status' => $conditions_status,
                'deleted' => '0'
            ],
            'contain' => [
                'UsuarioSeguidor'
            ],
            'order' => [
                'status DESC',
                'UsuarioSeguidor.nome ASC'
            ]
        ]);

        $seguidores = array_map(function($seguidor) {
            return [
                'status' => $seguidor['Seguidor']['status'],
                'img' => $seguidor['UsuarioSeguidor']['img'],
                'usuario' => $seguidor['UsuarioSeguidor']['usuario'],
                'nome' => $seguidor['UsuarioSeguidor']['nome'],
                'id' => $seguidor['UsuarioSeguidor']['id']
            ];
        },$seguidores);

        $response = [
            'status' => 'ok',
            'dados' => $seguidores,
            'usuario_id' => $usuario_id
        ];

        return $this->response->body(json_encode($response));



    }

    public function bloqueados() {

        // Desabilita a renderização automática e define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');
    
        $dados = $this->request->query;

        $usuario_id = !empty($dados['usuario_id']) ? $dados['usuario_id'] : null;
        $token = !empty($dados['token']) ? $dados['token'] : null;
        $email = !empty($dados['token']) ? $dados['email'] : null;
        
        if ( empty($usuario_id) && empty($token) ) {
            $response = [
                'status' => 'ok',
                'dados' => []
            ];
            return $this->response->body(json_encode($response));
        }

        if ( !empty($token) ) {

            $dados_usuario = $this->verificaValidadeToken($token, $email);
    
            if ( !$dados_usuario ) {
                throw new BadRequestException('Usuário não logado!', 401);
            }

            if ( empty($usuario_id) ) {
                $usuario_id = $dados_usuario['Usuario']['id'];
            }

        }

        $this->loadModel('UsuarioBloqueado');

        $bloqueados = $this->UsuarioBloqueado->find('all', [
            'fields' => [
                'DadosUsuarioBloqueado.id',
                'DadosUsuarioBloqueado.img',
                'DadosUsuarioBloqueado.usuario',
                'DadosUsuarioBloqueado.nome'
            ],
            'conditions' => [
                'UsuarioBloqueado.usuario_bloqueador_id' => $usuario_id
            ],
            'contain' => [
                'DadosUsuarioBloqueado'
            ],
            'order' => [
                'DadosUsuarioBloqueado.nome ASC'
            ]
        ]);

        $bloqueados = array_map(function($bloqueado) {
            return [
                'img' => $bloqueado['DadosUsuarioBloqueado']['img'],
                'usuario' => $bloqueado['DadosUsuarioBloqueado']['usuario'],
                'nome' => $bloqueado['DadosUsuarioBloqueado']['nome'],
                'id' => $bloqueado['DadosUsuarioBloqueado']['id']
            ];
        },$bloqueados);

        $response = [
            'status' => 'ok',
            'dados' => $bloqueados,
            'usuario_id' => $usuario_id
        ];

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
                'status' => 'error',
                'msg' => 'Erro ao aceitar seguidor'
            ];
        } else {
            $response = [
                'status' => 'ok',
                'msg' => 'Sucesso!'
            ];

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
                'status' => 'error',
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
                'status' => 'error',
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

    public function deixar_de_seguir() {
        
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

        // Protege a rota autenticada
        $dados_usuario = $this->protectAuthenticatedPost($dados);

        $this->loadModel('Seguidor');

        $busca_dados = $this->Seguidor->find('first',[
            'fields' => [
                'Seguidor.id'
            ],
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $dados->user_id,
                'Seguidor.usuario_seguidor_id' => $dados_usuario['Usuario']['id']
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
                'msg' => 'Erro ao deixar de seguir'
            ];
        } else {
            $response = [
                'status' => 'ok',
                'msg' => 'Sucesso'
            ];
        }
    
        return $this->response->body(json_encode($response));

    }

    public function bloquear() {

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

        // Protege a rota autenticada
        $dados_usuario = $this->protectAuthenticatedPost($dados);

        $this->loadModel('Seguidor');
        $this->loadModel('UsuarioBloqueado');

        // Verifica se o usuário a ser bloqueado está sendo o que quer bloqueá-lo
        $busca_dados = $this->Seguidor->find('first',[
            'fields' => [
                'Seguidor.id',
                'Seguidor.status'
            ],
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $dados_usuario['Usuario']['id'],
                'Seguidor.usuario_seguidor_id' => $dados->user_id
            ],
            'link' => []
        ]);

        $this->criaRegistroBloqueio($dados_usuario['Usuario']['id'], $dados->user_id);

        if ( empty($busca_dados) ) {
            $response = [
                'status' => 'ok',
                'msg' => 'Registro inexistente'
            ];    
            return $this->response->body(json_encode($response));
        }

        $dados_salvar = [
            'id' => $busca_dados['Seguidor']['id'],
            'status' => 'bloqueado',
            'prev_status' => $busca_dados['Seguidor']['status'],
        ];

        if ( !$this->Seguidor->save($dados_salvar) ) {
            $response = [
                'status' => 'erro',
                'msg' => 'Erro ao bloquear o usuário'
            ];
        } else {
            $response = [
                'status' => 'ok',
                'msg' => 'Usuário Bloqueado'
            ];
        }
    
        return $this->response->body(json_encode($response));

    }

    public function desbloquear() {

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

        // Protege a rota autenticada
        $dados_usuario = $this->protectAuthenticatedPost($dados);

        $this->loadModel('Seguidor');
        $this->loadModel('UsuarioBloqueado');

        // Verifica se o usuário a ser bloqueado está sendo o que quer bloqueá-lo
        $busca_dados = $this->Seguidor->find('first',[
            'fields' => [
                'Seguidor.id',
                'Seguidor.prev_status'
            ],
            'conditions' => [
                'Seguidor.usuario_seguido_id' => $dados_usuario['Usuario']['id'],
                'Seguidor.usuario_seguidor_id' => $dados->user_id
            ],
            'link' => []
        ]);

        $this->excluiRegistroBloqueio($dados_usuario['Usuario']['id'], $dados->user_id);

        if ( empty($busca_dados) ) {
            $response = [
                'status' => 'ok',
                'msg' => 'Usuário desbloqueado'
            ];    
            return $this->response->body(json_encode($response));
        }

        $dados_salvar = [
            'id' => $busca_dados['Seguidor']['id'],
            'status' => $busca_dados['Seguidor']['prev_status'],
            'prev_status' => null
        ];

        if ( !$this->Seguidor->save($dados_salvar) ) {
            $response = [
                'status' => 'erro',
                'msg' => 'Erro ao debloquear o usuário'
            ];
        } else {
            $response = [
                'status' => 'ok',
                'msg' => 'Usuário Desbloqueado'
            ];
        }
    
        return $this->response->body(json_encode($response));

    }

    private function criaRegistroBloqueio($usuario_bloqueador_id, $usuario_bloqueado_id) {

        $this->loadModel('UsuarioBloqueado');

        // Verifica se já não existe um bloqueio
        $dados_bloqueio = $this->UsuarioBloqueado->find('first',[
            'conditions' => [
                'UsuarioBloqueado.usuario_bloqueador_id' => $usuario_bloqueador_id,
                'UsuarioBloqueado.usuario_bloqueado_id' => $usuario_bloqueado_id
            ],
            'link' => []
        ]);

        // Se não existe um bloqueio para o usuário, devemos criálo
        if ( empty($dados_bloqueio) ) {
    
            $dados_bloqueio_salvar = [
                'usuario_bloqueador_id' => $usuario_bloqueador_id,
                'usuario_bloqueado_id' => $usuario_bloqueado_id
            ];

            $this->UsuarioBloqueado->create();

            if ( !$this->UsuarioBloqueado->save($dados_bloqueio_salvar) ) {
                $response = [
                    'status' => 'erro',
                    'msg' => 'Ocorreu um erro ao bloquear o usuario'
                ];
                return $this->response->body(json_encode($response));
            }

        }
    }

    private function excluiRegistroBloqueio($usuario_bloqueador_id, $usuario_bloqueado_id) {

        $this->loadModel('UsuarioBloqueado');
    
        $conditions = [
            'usuario_bloqueador_id' => $usuario_bloqueador_id,
            'usuario_bloqueado_id' => $usuario_bloqueado_id
        ];

        // Verifica se já não existe um bloqueio
        $dados_bloqueio = $this->UsuarioBloqueado->find('first',[
            'conditions' => $conditions,
            'link' => []
        ]);

        // Se não existe um bloqueio para o usuário, devemos criálo
        if ( !empty($dados_bloqueio) ) {
    
            $this->UsuarioBloqueado->create();

            if ( $this->UsuarioBloqueado->delete($dados_bloqueio['UsuarioBloqueado']['id']) ) {
                $response = [
                    'status' => 'erro',
                    'msg' => 'Ocorreu um erro ao desbloquear o usuario'
                ];
                return $this->response->body(json_encode($response));
            }

        }
    }
}