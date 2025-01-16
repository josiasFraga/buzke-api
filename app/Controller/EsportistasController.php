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
        
        $tipo = null;
        if ( !empty($dados['tipo']) ) {
            $tipo = $dados['tipo'];
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Seguidor');
        $this->loadModel('UsuarioBloqueado');

        $dados_retornar = [];
        $usuario_id = !empty($dados['usuario_id']) ? $dados['usuario_id'] : $dados_usuario['Usuario']['id'];

        $checkIsBlocked = $this->UsuarioBloqueado->checkIsBlocked($dados_usuario['Usuario']['id'], $usuario_id);

        if ( $checkIsBlocked['isBloqued'] ) {
            return new CakeResponse([
                'type' => 'json', 
                'body' => json_encode([
                    'status' => 'ok', 
                    'dados' => [], 
                    'msg' => $checkIsBlocked['motive']
                ])]);

        }

        if ( empty($tipo) ) {
            $dados_retornar = $this->buscaDadosUsuario($usuario_id);
        } else if ( $tipo === 'padel' ) {
            $dados_retornar = $this->buscaDadosPadel($usuario_id);
        }

        $dados_retornar['_perfis'] = $this->busca_perfis_esportista($usuario_id);
        $can_follow = $this->Seguidor->checkCanFollow($dados_usuario['Usuario']['id'], $usuario_id);
        $dados_retornar['_can_follow'] = $can_follow;

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));

    }

    private function buscaDadosUsuario($usuario_id = null) {

        $this->loadModel('Usuario');

        $dados_usuario = $this->Usuario->find('first',[
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                'Usuario.usuario',
                'Usuario.img'
            ],
            'conditions' => [
                'Usuario.id' => $usuario_id
            ],
            'link' => []
        ]);

        $dados_retornar = $dados_usuario['Usuario'];
        $dados_retornar['_img'] = $dados_usuario['Usuario']['img'];
        $dados_retornar['_user'] = $dados_usuario['Usuario']['usuario'];

        return $dados_retornar;
    }

    private function buscaDadosPadel($usuario_id = null) {

        $this->loadModel('UsuarioDadosPadel');
        $this->loadModel('UsuarioPadelCategoria');
        $this->loadModel('ToProJogo');


        $dados = $this->UsuarioDadosPadel->findByUserId($usuario_id);

        if ( empty($dados) ) {
            return [
                'sexo' => '',
                'lado' => '',
                'localidade' => '',
                'categorias' => [],
                'privado' => '',
                'receber_convites' => 'Y',
                'restringir_horarios_convites' => 'N',
                'horarios_convites' => []
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

        $dados_retornar['horarios_convites'] = $this->ToProJogo->buscaDisponibilidadeUsuario([7], $usuario_id);

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

        $dados_retornar['_user'] = $dados['Usuario']['usuario'];

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
        $this->loadModel('ToProJogo');
    
        $dados_padelista_atualizar = [];
        $dados_cliente_cliente_atualizar = [];

        $categorias = isset($dados->categorias) ? $dados->categorias : [];
        $horarios_convites = !empty($dados->horarios_convites) ? $dados->horarios_convites : [];
        
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

        if ( !empty($dados->localidade) ) {
            $dados_padelista_atualizar['localidade'] = $dados->localidade;
        }

        if ( !empty($dados->receber_convites) ) {
            $dados_padelista_atualizar['receber_convites'] = $dados->receber_convites;
        }

        if ( !empty($dados->restringir_horarios_convites) ) {
            $dados_padelista_atualizar['restringir_horarios_convites'] = $dados->restringir_horarios_convites;
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

        // Atualiza os horários de convites de jogos e desafios
        if ( !empty($horarios_convites) ) {

            $meus_ids_de_cliente = $this->ClienteCliente->buscaDadosSemVinculo($usuario_id, false);

            if ( count($meus_ids_de_cliente) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos seus dados como cliente.'))));
            }

            $dados_to_pro_jogo_salvar = [];
            $horarios_convites = json_decode(json_encode($horarios_convites), true);

            $ids_permanecer = [];
            foreach( $horarios_convites as $key => $horario_convite ) {
                if ( !empty($horario_convite['id']) ) {
                    $ids_permanecer[] = $horario_convite['id'];
                }
            }

            $ids_excluir = array_values($this->ToProJogo->find('list',[
                'conditions' => [
                    'not' => [
                        'ToProJogo.id' => $ids_permanecer
                    ],
                    'ToProJogo.cliente_cliente_id' => $meus_ids_de_cliente[0]['ClienteCliente']['id'],
                    'ToProJogoEsporte.subcategoria_id' => 7
                ],
                'fields' => [
                    'ToProJogo.id',
                    'ToProJogo.id'
                ],
                'link' => ['ToProJogoEsporte']
            ]));

            if ( count($ids_excluir) > 0 ) {
                $this->ToProJogo->deleteAll(['ToProJogo.id' => $ids_excluir]);
            }
            

            foreach( $horarios_convites as $key => $horario_convite ) {
                $dados_to_pro_jogo_salvar['ToProJogo'] = [
                    'cliente_cliente_id' => $meus_ids_de_cliente[0]['ClienteCliente']['id'],
                    'dia_semana' => $horario_convite['dia_semana'],
                    'hora_fim' => $horario_convite['hora_fim'],
                    'hora_inicio' => $horario_convite['hora_inicio']
                ];

                // Se o usuário tiver alterando um item
                if ( !empty($horario_convite['id']) ) {
                    $check_item = $this->ToProJogo->find('count', [
                        'conditions' => [
                            'ToProJogo.id' => $horario_convite['id'],
                            'ToProJogo.cliente_cliente_id' => $meus_ids_de_cliente[0]['ClienteCliente']['id']
                        ],
                        'link' => []
                    ]);

                    // o item não pertense ao cliente
                    if ( $check_item === 0 ) {
                        continue;
                    }

                    $dados_to_pro_jogo_salvar['ToProJogo']['id'] = $horario_convite['id'];

                } else {
                    $this->ToProJogo->create();
                    
                    $dados_to_pro_jogo_salvar['ToProJogoEsporte'] = [];
                    $dados_to_pro_jogo_salvar['ToProJogoEsporte'][] = [
                        'subcategoria_id' => 7
                    ];
                }


                if ( !$this->ToProJogo->saveAssociated($dados_to_pro_jogo_salvar, ['deep' => true]) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não conseguimos salvar seus horários para convites de jogos.'))));
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
                    'acao_selecionada_desc' => "'Recusado'"
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

    public function busca_stats() {

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
        $usuario_id = !empty($dados['usuario_id']) ? $dados['usuario_id'] : $dados_usuario['Usuario']['id'];
        if ( $tipo === 'padel' ) {
            $dados_retornar = $this->buscaStats($usuario_id);
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));

    }

    private function buscaStats($usuario_id = null) {
        $this->loadModel('EstatisticaPadel');
    
        // Busca estatísticas do usuário
        $estatisticas = $this->EstatisticaPadel->find('all', [
            'fields' => [
                'EstatisticaPadel.usuario_id',
                'SUM(EstatisticaPadel.vitorias) as total_vitorias',
                'SUM(EstatisticaPadel.torneio_jogos) as total_torneio_jogos',
                'SUM(EstatisticaPadel.torneios_participados) as total_torneios_participados',
                'SUM(EstatisticaPadel.torneios_vencidos) as total_torneios_vencidos',
                'SUM(EstatisticaPadel.finais_perdidas) as total_finais_perdidas',
                'SUM(EstatisticaPadel.avancos_de_fase) as total_avancos_de_fase',
                'SUM(EstatisticaPadel.pontuacao_total) as total_pontuacao'
            ],
            'conditions' => ['EstatisticaPadel.usuario_id' => $usuario_id],
            'group' => ['EstatisticaPadel.usuario_id']
        ]);
    
        // Inicializa com valores zerados se não houver estatísticas
        if (empty($estatisticas)) {
            $dados_retornar = [
                'n_torneios' => 0,
                'n_torneios_vencidos' => 0,
                'n_jogos' => 0,
                'n_vitorias' => 0,
                'n_finais_perdidas' => 0,
                'n_avancos_fase' => 0,
                'pontuacao_total' => 0,
                'posicao_ranking' => $this->calcularPosicaoRanking(0) // Calcula posição no ranking com pontuação 0
            ];
        } else {
            // Extrai os dados retornados pelo banco
            $dados_retornar = [
                'n_torneios' => $estatisticas[0][0]['total_torneios_participados'],
                'n_torneios_vencidos' => $estatisticas[0][0]['total_torneios_vencidos'],
                'n_jogos' => $estatisticas[0][0]['total_torneio_jogos'],
                'n_vitorias' => $estatisticas[0][0]['total_vitorias'],
                'n_finais_perdidas' => $estatisticas[0][0]['total_finais_perdidas'],
                'n_avancos_fase' => $estatisticas[0][0]['total_avancos_de_fase'],
                'pontuacao_total' => $estatisticas[0][0]['total_pontuacao'],
                'posicao_ranking' => $this->calcularPosicaoRanking($estatisticas[0][0]['total_pontuacao']) // Calcula posição com a pontuação do usuário
            ];
        }
    
        return $dados_retornar;
    }

    public function busca_perfis_esportista($usuario_id = null) {

        $this->loadModel('Subcategoria');

        $esportes = $this->Subcategoria->find('all', [
            'conditions' => [
                'NOT' => [
                    //'Subcategoria.esporte_nome' => [null, $ignorar],
                    'Subcategoria.cena_criacao_perfil' => null
                ]
            ],
            'link' => []
        ]);

        $check_is_padelist = false;
        $this->loadModel('UsuarioDadosPadel');
        $check_is_padelist = $this->UsuarioDadosPadel->checkIsAthlete($usuario_id);

        $esportes_retornar = array_map(function($esporte) use($check_is_padelist) {

            if ( $check_is_padelist && $esporte['Subcategoria']['id'] == 7 ) {
                $esporte['Subcategoria']['have_profile'] = true;
            } else {
                $esporte['Subcategoria']['have_profile'] = false;
            }
    
            return $esporte['Subcategoria'];
        },$esportes);

        
        return $esportes_retornar;
    }

    
    /**
     * Calcula a posição do usuário no ranking
     * @param int $pontuacao Pontuação total do usuário
     * @return int Posição no ranking
     */
    private function calcularPosicaoRanking($pontuacao) {
        $this->loadModel('EstatisticaPadel');
    
        // Busca todas as pontuações, ordenadas por pontuacao_total desc
        $ranking = $this->EstatisticaPadel->find('all', [
            'fields' => ['EstatisticaPadel.usuario_id', 'SUM(EstatisticaPadel.pontuacao_total) as total_pontuacao'],
            'group' => ['EstatisticaPadel.usuario_id'],
            'order' => ['total_pontuacao DESC']
        ]);
    
        // Se não há registros, posição é 1 (primeiro lugar)
        if (empty($ranking)) {
            return 1;
        }
    
        // Itera para determinar a posição
        $posicao = 1;
        foreach ($ranking as $entry) {
            if ($entry[0]['total_pontuacao'] > $pontuacao) {
                $posicao++;
            } else {
                break;
            }
        }
    
        // Retorna a posição no ranking
        return $posicao;
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