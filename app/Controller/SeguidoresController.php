<?php
class SeguidoresController extends AppController {

    public $components = array('RequestHandler');

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
                $conditions_status[] = 'pendente';
                $my_following = true;
            }

        }

        $this->loadModel('Seguidor');

        $seguindo = $this->Seguidor->find('all', [
            'fields' => [
                'Seguidor.id',
                'Seguidor.status',
                'UsuarioSeguidor.img',
                'UsuarioSeguidor.usuario',
                'UsuarioSeguidor.nome'
            ],
            'conditions' => [
                'Seguidor.usuario_seguidor_id' => $usuario_id,
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

        $seguindo = array_map(function($seguidor) use($my_following) {
            return [
                'status' => $seguidor['Seguidor']['status'],
                'img' => $seguidor['UsuarioSeguidor']['img'],
                'usuario' => $seguidor['UsuarioSeguidor']['usuario'],
                'nome' => $seguidor['UsuarioSeguidor']['nome'],
                'id' => $seguidor['UsuarioSeguidor']['id'],
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
                'dados' => 0
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
}