<?php
class EsportistasController extends AppController {

    public $components = array('RequestHandler');

    public function index() {
        
        $dados = $this->request->query;

        // Validação dos parâmetros obrigatórios
        if (
            (!isset($dados['token']) || $dados['token'] == "") ||  
            (!isset($dados['email']) || $dados['email'] == "")
        ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
    
        // Caso a busca esteja vazia, retorna um resultado vazio
        if (empty($dados['search']) || strlen($dados['search']) < 3) {
            return new CakeResponse(array(
                'type' => 'json', 
                'body' => json_encode(array('status' => 'ok', 'dados' => []))
            ));
        }
    
        $token = $dados['token'];
        $email = $dados['email'];
        $search = $dados['search'];
    
        // Verifica o token e obtém os dados do usuário autenticado
        $dados_usuario = $this->verificaValidadeToken($token, $email);
        if (!$dados_usuario) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
    
        $usuario_id = $dados_usuario['Usuario']['id'];
    
        // Carrega os modelos necessários
        $this->loadModel('Usuario');
        $this->loadModel('Seguidor');
        $this->loadModel('UsuarioDadosPadel');
    
        // Realiza a busca conforme a lógica definida
        $usuarios = $this->Usuario->find('all', [
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                'Usuario.usuario',
                'Usuario.img',
                'UsuarioDadosPadel.lado',
                'UsuarioDadosPadel.img',
                'UsuarioDadosPadel.privado',
            ],
            'joins' => [
                [
                    'table' => 'usuarios_dados_padel',
                    'alias' => 'UsuarioDadosPadel',
                    'type' => 'INNER',
                    'conditions' => ['Usuario.id = UsuarioDadosPadel.usuario_id']
                ],
                [
                    'table' => 'seguidores',
                    'alias' => 'Seguidor',
                    'type' => 'LEFT',
                    'conditions' => [
                        'Seguidor.usuario_seguido_id = Usuario.id',
                        'Seguidor.usuario_seguidor_id' => $usuario_id,
                        'Seguidor.status' => 'ativo'
                    ]
                ]
            ],
            'conditions' => [
                'AND' => [
                    [
                        'NOT' => [
                            'Usuario.id' => $usuario_id
                        ]
                    ],
                    [
                        'OR' => [
                            ['Usuario.usuario LIKE' => '%' . $search . '%'],
                            ['Usuario.nome LIKE' => '%' . $search . '%'],
                            ['Usuario.email LIKE' => '%' . $search . '%']
                        ]
                    ],
                    [
                        'OR' => [
                            ['UsuarioDadosPadel.privado' => 'N'],
                            ['UsuarioDadosPadel.privado' => 'Y', 'Seguidor.id !=' => null]
                        ]
                    ]
                ]
            ],
            'recursive' => -1, // Evita carregamento automático de associações
            'limit' => 50, // Limita o número de resultados para performance
            'order' => ['Usuario.nome' => 'ASC'] // Ordena os resultados por nome
        ]);
    
        // Estrutura os dados para resposta
        $dados_retornar = [];
        foreach ($usuarios as $usuario) {
            $dados_retornar[] = [
                'id' => $usuario['Usuario']['id'],
                'nome' => $usuario['Usuario']['nome'],
                'usuario' => $usuario['Usuario']['usuario'],
                'img' => $usuario['Usuario']['img'],
                'lado' => $usuario['UsuarioDadosPadel']['lado'],
                'img_padel' => $usuario['UsuarioDadosPadel']['img'],
                'privado' => $usuario['UsuarioDadosPadel']['privado'],
            ];
        }
    
        // Retorna a resposta em formato JSON
        return new CakeResponse(array(
            'type' => 'json', 
            'body' => json_encode(['status' => 'ok', 'dados' => $dados_retornar])
        ));


    }

    public function busca_perfil() {
        
        $dados = $this->request->query;
        
        if ((!isset($dados['token']) || $dados['token'] == "") ||  (!isset($dados['email']) || $dados['email'] == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        
        if ( empty($dados['tipo']) ) {
            throw new BadRequestException('Tipo não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $tipo = $dados['tipo'];

        $dados_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados_retornar = [];
        if ( $tipo === 'padel' ) {
            $dados_retornar = $this->buscaDadosPadel(!empty($dados['usuario_id']) ? $dados['usuario_id'] : $dados_usuario['Usuario']['id']);
        }

        $dados_retornar['_user'] = $dados_usuario['Usuario']['usuario'];

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));

    }

    private function buscaDadosPadel($usuario_id = null) {

        $this->loadModel('UsuarioDadosPadel');
        $this->loadModel('UsuarioPadelCategoria');

        $dados = $this->UsuarioDadosPadel->findByUserId($usuario_id);

        if ( empty($dados) ) {
            return [
                'sexo' => '',
                'lado' => '',
                'categorias' => [],
            ];
        }

        $dados_retornar = $dados['UsuarioDadosPadel'];
        $dados_retornar['sexo'] = $dados['ClienteCliente']['sexo'];
        $dados_retornar['data_nascimento'] = $dados['ClienteCliente']['data_nascimento'];
        $dados_retornar['nome'] = $dados['Usuario']['nome'];
        $dados_retornar['_img'] = empty($dados_retornar['img']) ? $dados['Usuario']['img'] : $dados_retornar['img'];
        $categorias = $this->UsuarioPadelCategoria->findByUserId($usuario_id);

        $categorias_retornar = [];

        foreach( $categorias as $key => $categoria ) {
            $categorias_retornar[] = (int)$categoria['PadelCategoria']['id'];
        }

        $dados_retornar['categorias'] = $categorias_retornar;

        if ( !empty($dados_retornar['dupla_fixa']) ) {

            $this->loadModel('Usuario');

            $dados_dupla_fixa = $this->Usuario->find('first',[
                'fields' => [
                    'Usuario.id',
                    'Usuario.nome',
                    'Usuario.usuario',
                    'Usuario.img',
                    'UsuarioDadosPadel.lado',
                    'UsuarioDadosPadel.img'
                ],
                'conditions' => [
                    'Usuario.id' => $dados_retornar['dupla_fixa']
                ],
                'link' => [
                    'UsuarioDadosPadel'
                ]
            ]);

            $dados_retornar['dupla_fixa'] = [
                'id' => $dados_dupla_fixa['Usuario']['id'],
                'nome' => $dados_dupla_fixa['Usuario']['nome'],
                'usuario' => $dados_dupla_fixa['Usuario']['usuario'],
                'img' => $dados_dupla_fixa['Usuario']['img'],
                'lado' => $dados_dupla_fixa['UsuarioDadosPadel']['lado'],
                'img_padel' => $dados_dupla_fixa['UsuarioDadosPadel']['img'],
            ];
        }

        return $dados_retornar;
    }

    public function atualiza_perfil() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log(addslashes($dados), 'debug');
        //die();

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        }else {
            $dados = json_decode($dados);
        }

        if ( empty($dados->token) ||  empty($dados->email) ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
    
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
    
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( empty($dados->tipo) ) {
            throw new BadRequestException('Tipo não informado', 400);
        }

        $tipo = $dados->tipo;

        if ( $tipo === 'padel' ) {
            return $this->atualizaDadosPadel($dados_usuario['Usuario']['id'], $dados);
        }

    }

    private function atualizaDadosPadel( $usuario_id, $dados ) {

        $this->loadModel('UsuarioDadosPadel');
        $this->loadModel('ClienteCliente');
        $this->loadModel('UsuarioPadelCategoria');
    
        $dados_padelista_atualizar = [];
        $dados_cliente_cliente_atualizar = [];

        $categorias = isset($dados->categorias) ? $dados->categorias : [];
        
        if ( empty($categorias) && empty($this->request->params['form']['img'])  &&  empty($this->request->params['form']['img_capa']) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione ao menos uma categoria antes de clicar em "Atualizar Dados"'))));
        }

        if ( !empty($categorias) && count($categorias) > 2 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione no máximo 2 categorias'))));
        }

        if ( !empty($dados->lado) ) {
            $dados_padelista_atualizar['lado'] = $dados->lado;
        }

        if ( !empty($dados->privado) ) {
            $dados_padelista_atualizar['privado'] = $dados->privado;
        }

        if ( !empty($dados->dupla_fixa) ) {
            $dados_padelista_atualizar['dupla_fixa'] = $dados->dupla_fixa->id;
        }

        if ( !empty($this->request->params['form']['img']) ) {
            $dados_padelista_atualizar['img'] = $this->request->params['form']['img'];
        }

        if ( !empty($this->request->params['form']['img_capa']) ) {
            $dados_padelista_atualizar['img_capa'] = $this->request->params['form']['img_capa'];
        }

        if ( !empty($dados->sexo) ) {
            $dados_cliente_cliente_atualizar['ClienteCliente.sexo'] = "'".$dados->sexo."'";
        }

        if ( !empty($dados->data_nascimento) ) {
            $dados_cliente_cliente_atualizar['ClienteCliente.data_nascimento'] = $dados->data_nascimento;
        }

        $dados_padelista = $this->UsuarioDadosPadel->find('first',[
            'fields' => [
                'id',
                'usuario_id',
                'dupla_fixa'
            ],
            'conditions' => [
                'UsuarioDadosPadel.usuario_id' => $usuario_id
            ],
            'link' => []
        ]);
 
        // Atualiza os dados de padelista
        if ( !empty($dados_padelista_atualizar) ) {

            $enviar_notificacao_parceiro = false;

            if ( !empty($dados_padelista) ) {

                $dados_padelista_atualizar['id'] = $dados_padelista['UsuarioDadosPadel']['id'];

                if ( !empty($dados_padelista_atualizar['dupla_fixa']) && $dados_padelista_atualizar['dupla_fixa'] != $dados_padelista['UsuarioDadosPadel']['dupla_fixa'] ) {
                    $dados_padelista_atualizar['dupla_fixa_aprovado'] = null;
                    $enviar_notificacao_parceiro = true;
                }
            } else {
                $this->UsuarioDadosPadel->create();
                $dados_padelista_atualizar['usuario_id'] = $usuario_id;
                if ( !empty($dados_padelista_atualizar['dupla_fixa']) ) {
                    $dados_padelista_atualizar['dupla_fixa_aprovado'] = null;
                    $enviar_notificacao_parceiro = true;
                }
            }

            $dados_padelista = $this->UsuarioDadosPadel->save($dados_padelista_atualizar);

            if ( !$dados_padelista ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde! Dados Padelista'))));
            }

            if ( $enviar_notificacao_parceiro ) {

                // Envia notificação pro usuário adicionado
                $notifications_ids = $this->Token->getIdsNotificationsUsuario($dados_padelista_atualizar['dupla_fixa']);

                $this->sendNotificationNew( 
                    $dados_padelista_atualizar['dupla_fixa'],
                    $notifications_ids, 
                    $dados_padelista['UsuarioDadosPadel']['id'],
                    null,
                    'dupla_fixa_padel',
                    ["en"=> '$[notif_count] Adição como dupla fixa']
                );

            }

        }

        // Atualiza os dados da clientes_clientes
        if ( !empty($dados_cliente_cliente_atualizar) ) {
            $this->ClienteCliente->updateAll($dados_cliente_cliente_atualizar, ['usuario_id' => $usuario_id]);
        }

        // Atualiza as categorias do usuário
        if ( !empty($categorias) ) {
            // Remove as categorias que não vieram no post
            $this->UsuarioPadelCategoria->deleteAll([
                'usuario_id' => $usuario_id,
                'NOT' => [
                    'categoria_id' => $categorias
                ]
            ]);

            // Salva as categorias novas
            foreach( $categorias as $key => $categoria ) {

                $check_categoria = $this->UsuarioPadelCategoria->find('count',[
                    'conditions' => [
                        'usuario_id' => $usuario_id,
                        'categoria_id' => $categoria
                    ],
                    'link' => []
                ]);

                if ( $check_categoria === 0 ) {
                    $dados_salvar = [
                        'usuario_id' => $usuario_id,
                        'categoria_id' => $categoria                        
                    ];

                    $this->UsuarioPadelCategoria->create();

                    if ( !$this->UsuarioPadelCategoria->save($dados_salvar) ) {
                        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
                    }
                }

            }
        }
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Seus dados de padelista foram atualizados!'))));
        
    }

    public function dupla_fixa_acao() {
        $this->layout = 'ajax';
        
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->tipo) || $dados->tipo == '') {
            throw new BadRequestException('Tipo do convite não informado', 400);
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        if ( empty($dados->convidante_id) ) {
            throw new BadRequestException('Token não informado', 400);
        }

        if ( empty($dados->action) || !in_array($dados->action, [1,2]) ) {//1 confirmar = Y, 2 recusar = R
            throw new BadRequestException('Ação não informada', 400);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $acao = $dados->action;
        $tipo = $dados->tipo;
        $conviante_id = $dados->convidante_id;
        $usuario_id = $dados_token['Usuario']['id'];

        if ( $tipo === 'padel' ) {

            $this->loadModel('UsuarioDadosPadel');
            $this->loadModel('Notificacao');
            $this->loadModel('Token');

            // Esses são os dados de padelista do usuário que adicionou este 
            $dados_padel_usuario_convidante = $this->UsuarioDadosPadel->find('first', [
                'fields' => [
                    'UsuarioDadosPadel.id',
                    'UsuarioDadosPadel.dupla_fixa'
                ],
                'conditions' => [
                    'usuario_id' => $conviante_id
                ],
                'link' => []
            ]);
    
            if ( empty($dados_padel_usuario_convidante) ) {
                throw new BadRequestException('Dados de padelista não encontrados', 400);
            }

            $notificacoes_atualizar = array_values($this->Notificacao->find('list',[
                'fields' => [
                    'Notificacao.id',
                    'Notificacao.id'
                ],
                'conditions' => [
                    'Notificacao.acao_selecionada' => null,
                    'Notificacao.usuario_origem' => $conviante_id,
                    'NotificacaoUsuario.usuario_id' => $usuario_id,
                    'Notificacao.registro_id' => $dados_padel_usuario_convidante['UsuarioDadosPadel']['id'],
                    'NotificacaoMotivo.nome' => 'dupla_fixa_padel',
                ],
                'link' => [
                    'NotificacaoUsuario',
                    'NotificacaoMotivo'
                ]
            ]));

            $enviar_notificacao_convidante = false;

            // Se o usuário convidante já setou outro usuário como dupla fixa e o usuario está recusando
            if ( $dados_padel_usuario_convidante['UsuarioDadosPadel']['dupla_fixa'] !== $usuario_id && $acao == 2 ) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'N'",
                    'acao_selecionada_desc' => 'Recusada'
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);
            }
            // Se o usuário convidante já setou outro usuário como dupla fixa e o usuario está aceitando
            else if ( $dados_padel_usuario_convidante['UsuarioDadosPadel']['dupla_fixa'] !== $usuario_id && $acao == 1 ) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'N'",
                    'acao_selecionada_desc' => 'Expirada'
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);
            }
            // Se o usuário aceitou ser dupla fixa
            else if ($acao == 1) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'Y'",
                    'acao_selecionada_desc' => "'Confirmado'"
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);

                $dados_atualizar = [
                    'id' => $dados_padel_usuario_convidante['UsuarioDadosPadel']['id'],
                    'dupla_fixa_aprovado' => "Y"
                ];
    
                if ( !$this->UsuarioDadosPadel->save($dados_atualizar) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar a ação!'))));
                }

                $enviar_notificacao_convidante = true;
            }
            // Se o usuário recusou ser dupla fixa
            else if ($acao == 2) {
                $this->Notificacao->updateAll([
                    'acao_selecionada' => "'N'",
                    'acao_selecionada_desc' => 'Recusado'
                ], [
                    'Notificacao.id' => $notificacoes_atualizar
                ]);

                $dados_atualizar = [
                    'id' => $dados_padel_usuario_convidante['UsuarioDadosPadel']['id'],
                    'dupla_fixa_aprovado' => "Y"
                ];
    
                if ( !$this->UsuarioDadosPadel->save($dados_atualizar) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar a ação!'))));
                }

                $enviar_notificacao_convidante = true;
            }

            if ( $enviar_notificacao_convidante ) {
    
                // Envia notificação pro usuário que convidou para dupla fixa
                $notifications_ids = $this->Token->getIdsNotificationsUsuario($conviante_id);

                $this->sendNotificationNew( 
                    $conviante_id,
                    $notifications_ids, 
                    $dados_padel_usuario_convidante['UsuarioDadosPadel']['id'],
                    null,
                    'dupla_fixa_padel_resposta',
                    ["en"=> '$[notif_count] Resposta de adição como dupla fixa']
                );
            }
    

        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Ação registrada com sucesso!'))));

    }
}