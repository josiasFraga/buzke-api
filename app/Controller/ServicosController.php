<?php
class ServicosController extends AppController {

    public $components = array('RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        
        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('ClienteServicoHorario');
        $this->loadModel('Agendamento');
        $this->loadModel('AgendamentoFixoCancelado');
        $this->loadModel('TorneioQuadraPeriodo');

        $conditions = [];
        $order = ['ClienteServico.nome'];
    
        // Se o usuário está passando autenticação
        if ( isset($dados['token']) && $dados['token'] != "" && isset($dados['email']) && $dados['email'] != "" ) {
    
            $token = $dados['token'];
            $email = $dados['email'];
    
            $dado_usuario = $this->verificaValidadeToken($token, $email);

            if ( !$dado_usuario ) {
                throw new BadRequestException('Usuário não logado!', 401);
            }

            // É uma empresa buscando
            if ( !empty($dado_usuario['Usuario']['cliente_id']) ) {

                $conditions = array_merge($conditions, [
                    'ClienteServico.cliente_id' => $dado_usuario['Usuario']['cliente_id']
                ]);

            }

            // É um usuário final mas não passou o código da emrpesa
            else if ( $dado_usuario['Usuario']['cliente_id'] == null && (!isset($dados['cliente_id']) || $dados['cliente_id'] == '') ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Empresa não informada'))));
            } 

            // É um usuário final e passou o código da empresa
            else {               

                $conditions = array_merge($conditions, [
                    'ClienteServico.cliente_id' => $dados['cliente_id']
                ]);
            }

        } else if ( isset($dados['cliente_id']) && $dados['cliente_id'] != '' ) {
            
            $conditions = [
                'ClienteServico.cliente_id' => $dados['cliente_id']
            ];

            if ( $dados['cliente_id'] == 55 ) {
                $order = [
                    'ClienteServico.id'
                ];
            }
            
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'error', 'msg' => 'Quadra não informada!'))));
        }


        $quadras = $this->ClienteServico->find('all',[
            'fields' => [
                'ClienteServico.*'
            ],
            'conditions' => $conditions,
            'order' => $order,
            'contain' => [
                'ClienteServicoFoto' => [
                    'fields' => [
                        'id',
                        'imagem'
                    ]
                ],
                'Cliente' => [
                    'fields' => [
                        'nome'
                    ]
                ]
            ]
        ]);

        foreach($quadras as $key => $qua){

            if ( !empty($dados['day']) ) {

                if ( empty($dados['simple_list']) ) {
                    $quadras[$key]["ClienteServico"]["_horarios"] = $this->quadra_horarios($qua['ClienteServico']['id'], $dados['day'], false);
                }

                $range_valores = $this->ClienteServicoHorario->buscaRangeValores($qua['ClienteServico']['id'], date('w', strtotime($dados['day'])));
            } else {
                $quadras[$key]["ClienteServico"]["_dias_semana"] = $this->ClienteServicoHorario->listaDiasSemana($qua['ClienteServico']['id']);
                $range_valores = $this->ClienteServicoHorario->buscaRangeValores($qua['ClienteServico']['id']);
            }

            $quadras[$key]['ClienteServico']['_valor'] = '';

            if ( !empty($range_valores) ) {
                $quadras[$key]['ClienteServico']['_valor'] = $range_valores[0] === $range_valores[1] ? number_format($range_valores[0], 2, ',', '.') : number_format($range_valores[0], 2, ',', '.') . ' - ' . number_format($range_valores[1], 2, ',', '.');
            }

            if ( count($qua['ClienteServicoFoto']) === 0 ) {
                $quadras[$key]['ClienteServicoFoto'][0]['imagem'] = "https://buzke-images.s3.sa-east-1.amazonaws.com/services/sem_imagem.jpeg";
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $quadras))));

    }

    public function add(){
        
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        } else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        if ( !isset($dados->horarios) || !is_array($dados->horarios) || count($dados->horarios) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Horários de atendimento não informados!'))));
        }

        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteServicoFoto');
        $this->loadModel('ClienteServicoHorario');
        $this->loadModel('ClienteServicoProfissional');

        $dados_salvar = [
            'cliente_id' => $dados_token['Usuario']['cliente_id'],
            'tipo' => $dados->tipo,
            'nome' => $dados->nome,
            'descricao' => $dados->descricao,
            'ativo' => $dados->ativo
        ];
        

        // Alterando
        if ( isset($dados->id) && !empty($dados->id) ) {
            $dados_servico = $this->ClienteServico->find('first',[
                'conditions' => [
                    'ClienteServico.id' => $dados->id,
                    'ClienteServico.cliente_id' => $dados_token['Usuario']['cliente_id'],
                ],
                'link' => []
            ]);

            if ( count($dados_servico) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do serviço não encontrados!'))));
            }

            $dados_salvar['id'] = $dados->id;
        }

        $dataSource = $this->ClienteServico->getDataSource();
        $dataSource->begin();
    
        try {
            $dados_servico_salvo = $this->ClienteServico->save($dados_salvar);

            if ( !$dados_servico_salvo ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar salvar os dados do serviço/quadra!'))));
            }
            
            $servico_id = $dados_servico_salvo['ClienteServico']['id'];

            // Remove os profissionais que não estão no post
            $this->ClienteServicoProfissional->deleteAll([
                'ClienteServicoProfissional.cliente_servico_id' => $servico_id,
                'not' => [
                    'ClienteServicoProfissional.usuario_id' => $dados->profissionais
                ]
            ]);

            if ( $dados_salvar['tipo'] === "Serviço" ) {

                $profissionais_salvar = [];

                foreach ( $dados->profissionais as $key => $profissional ) {

                    $verifica_ja_esta_cadastrado = $this->ClienteServicoProfissional->find('count',[
                        'conditions' => [
                            'usuario_id' => $profissional,
                            'cliente_servico_id' => $servico_id
                        ],
                        'link' => []
                    ]) > 0;


                    if ( !$verifica_ja_esta_cadastrado ) {
                        $profissionais_salvar[] = [
                            'usuario_id'=> $profissional,
                            'cliente_servico_id' => $servico_id
                        ];

                    }
                }

                if ( count($profissionais_salvar) > 0 ) {
                    if ( !$this->ClienteServicoProfissional->saveMany($profissionais_salvar) ) {
                        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar salvar os profissionais do serviço!'))));
                    }                    
                }

            }

            $ids_imagens_permanecer = [];    
            // Pega os ids das fotos passadas no post
            if ( isset($dados->fotos) && is_array($dados->fotos) ) {
        
                $fotos = $dados->fotos;
                $ids_imagens_permanecer = array_values(array_map(function($foto){
                    return isset($foto->id) ? $foto->id : "";
                }, $fotos));
    
            }

            // Remove as fotos que não estão no post      
            $this->ClienteServicoFoto->deleteAll([
                'ClienteServicoFoto.cliente_servico_id' => $servico_id,
                'not' => [
                    'ClienteServicoFoto.id' => $ids_imagens_permanecer
                ]
            ]);

            $imagens_salvar = [];
            if (isset($this->request->params['form']['fotos']) && is_array($this->request->params['form']['fotos']) && count($this->request->params['form']['fotos']) > 0) {
    
                $fotos = $this->request->params['form']['fotos'];
    
                foreach ($fotos['name'] as $index => $name) {
                    if ($fotos['error'][$index] === UPLOAD_ERR_OK) {
                        $imagens_salvar[] = [
                            'imagem' => [
                                'name' => $fotos['name'][$index],
                                'type' => $fotos['type'][$index],
                                'tmp_name' => $fotos['tmp_name'][$index],
                                'error' => $fotos['error'][$index],
                                'size' => $fotos['size'][$index],
                            ],
                            'cliente_servico_id' => $servico_id
                        ];
                        
                    }
                }
                
             
            }

            // Salva as novas fotos
            if ( count($imagens_salvar) > 0 ) {
                $this->ClienteServicoFoto->saveMany($imagens_salvar);
            }

            $horarios_salvar = [];
            $horarios_permanecer = [];
            foreach( $dados->horarios as $key => $horario ){
    
                $horarios_salvar[$key] = [
                    "cliente_servico_id" => $servico_id,
                    "inicio" => $horario->inicio,
                    "fim" => $horario->fim,
                    "dia_semana" => $horario->dia_semana,
                    "duracao" => $horario->duracao,
                    "fixos" => $horario->fixos,
                    "valor_padrao" => $horario->_valor_padrao,
                    "fixos_tipo" => $horario->fixos_tipo,
                    "valor_fixos" => $horario->_valor_fixos,
                    "a_domicilio" => $dados->tipo === "Quadra" ? 0 : $horario->a_domicilio,
                    "apenas_a_domocilio" => $dados->tipo === "Quadra" ? 0 : $horario->apenas_a_domocilio,
                ];
    
                if ( is_numeric($horario->id) ) {
                    $horarios_salvar[$key]['id'] = $horario->id;
                    $horarios_permanecer[] = $horario->id;
                }
    
            }

            // Remove os horarios que não estão no post      
            $this->ClienteServicoHorario->deleteAll([
                'ClienteServicoHorario.cliente_servico_id' => $servico_id,
                'not' => [
                    'ClienteServicoHorario.id' => $horarios_permanecer
                ]
            ]);

            $this->ClienteServicoHorario->saveMany($horarios_salvar);

            $dataSource->commit();
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Serviço cadastrado/alterado com sucesso!'))));
    
        } catch (Exception $e) {
            // Se ocorreu algum erro, desfazer todas as operações
            $dataSource->rollback();
            // Tratar o erro, possivelmente retornando uma resposta de erro para o usuário
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => $e->getMessage()))));
        }

    }

    public function view($id = null) {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteServicoHorario');

        $conditions = [
            'ClienteServico.id' => $id
        ];

        $dados_servico = $this->ClienteServico->find('first',[
            'fields' => [
                'ClienteServico.*'
            ],
            'conditions' => $conditions,
            'contain' => [
                'ClienteServicoFoto' => [
                    'fields' => [
                        'id',
                        'imagem'
                    ]
                ],
                'ClienteServicoHorario' => [
                    'fields' => [
                        'id',
                        'cliente_servico_id',
                        'inicio',
                        'fim',
                        'dia_semana',
                        'duracao',
                        'a_domicilio',
                        'apenas_a_domocilio',
                        'fixos',
                        'fixos_tipo',
                        'valor_padrao',
                        'valor_fixos'
                    ]
                ],
                'ClienteServicoProfissional',
                'ClienteServicoAvaliacao' => [
                    'Usuario' => [
                        'fields' => [
                            'nome',
                            'img'
                        ]
                    ]
                ]

            ]
        ]);

        $range_valores = $this->ClienteServicoHorario->buscaRangeValores($dados_servico['ClienteServico']['id']);

        $dados_servico['ClienteServico']['_valor'] = '';

        if ( !empty($range_valores) ) {
            $dados_servico['ClienteServico']['_valor'] = $range_valores[0] === $range_valores[1] ? number_format($range_valores[0], 2, ',', '.') : number_format($range_valores[0], 2, ',', '.') . ' - ' . number_format($range_valores[1], 2, ',', '.');
        }
        
        if ( count($dados_servico['ClienteServicoFoto']) === 0 ) {
            $dados_servico['ClienteServicoFoto'][0]['imagem'] = "https://buzke-images.s3.sa-east-1.amazonaws.com/services/sem_imagem.jpeg";
        }
        
        if ( isset($dados_servico['ClienteServicoHorario']) && count($dados_servico['ClienteServicoHorario']) > 0 ) {

            foreach( $dados_servico['ClienteServicoHorario'] as $key => $horario ){
                $dados_servico['ClienteServicoHorario'][$key]['_valor_padrao'] = number_format($horario['valor_padrao'], 2, ',', '.');
                $dados_servico['ClienteServicoHorario'][$key]['_valor_fixos'] = !empty($horario['valor_fixos']) ? number_format($horario['valor_fixos'], 2, ',', '.') : "";
            }

        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_servico))));

    }

    public function dados_para_agendamento() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        $token = $dados['token'];
        $email = $dados['email'];

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('ClienteServicoHorario');
        $this->loadModel('Agendamento');
        $this->loadModel('AgendamentoFixoCancelado');
        $this->loadModel('TorneioQuadraPeriodo');
        $this->loadModel('Promocao');
        $this->loadModel('PromocaoDiaSemana');
        $this->loadModel('PromocaoServico');

        $conditions = [
            'ClienteServico.id' => $dados['servico_id']
        ];

        $dados_servico = $this->ClienteServico->find('first',[
            'fields' => [
                'ClienteServico.*',
                'Cliente.prazo_maximo_para_canelamento'
            ],
            'conditions' => $conditions,
            'link' => [
                'Cliente'
            ]
        ]);

        if ( count($dados_servico) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Serviço não encontrado!'))));
        }
        
        $isCourt = $this->ClienteSubcategoria->checkIsCourt($dados_servico['ClienteServico']['cliente_id']);
        $isPaddleCourt = $this->ClienteSubcategoria->checkIsPaddleCourt($dados_servico['ClienteServico']['cliente_id']);

        $horarios = $this->quadra_horarios($dados['servico_id'], $dados['day']);

        $promocoes = $this->Promocao->find('all', [
            'fields' => [
                'Promocao.*',
            ],
            'conditions' => [
                'Promocao.finalizada' => 'N',
                'OR' => [
                    ['Promocao.validade_ate_cancelar' => 'Y'],
                    [
                        'DATE(Promocao.validade_inicio) <=' => $dados['day'],
                        'DATE(Promocao.validade_fim) >=' => $dados['day'],
                    ]
                ],
                'PromocaoDiaSemana.dia_semana' => (int)date('w', strtotime($dados['day'])),
                'ClienteServico.ativo' => 'Y',
                'ClienteServico.id' => $dados_servico['ClienteServico']['id']
            ],
            'link' => [
                'PromocaoDiaSemana',
                'PromocaoServico' => [
                    'ClienteServico'
                ]
            ]
        ]);

        foreach( $promocoes as $key => $promocao ){

            $promocoes[$key]['_dias_semana'] = array_values($this->PromocaoDiaSemana->find('list',[
                'fields' => [
                    'PromocaoDiaSemana.dia_semana',
                    'PromocaoDiaSemana.dia_semana'
                ],
                'conditions' => [
                    'PromocaoDiaSemana.promocao_id' => $promocao['Promocao']['id']
                ]
            ]));

            $promocoes[$key]['_servicos'] = $this->PromocaoServico->find('list',[
                'fields' => [
                    'ClienteServico.id',
                    'ClienteServico.nome'
                ],
                'conditions' => [
                    'PromocaoServico.promocao_id' => $promocao['Promocao']['id']
                ],
                'link' => [
                    'ClienteServico'
                ]
            ]);
        }

        $dados_retornar = [
            'origem' => !empty($dado_usuario['Usuario']['cliente_id']) ? 'empresa' : 'cliente',
            'is_court' => $isCourt,
            'is_paddle_court' => $isPaddleCourt,
            'tipo' => $dados_servico['ClienteServico']['tipo'],
            'prazo_cancelamento' => $dados_servico['Cliente']['prazo_maximo_para_canelamento'],
            'horarios' => $horarios,
            'promocoes' => $promocoes
        ];

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));

    }

    public function horarios_disponiveis_hoje() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        $token = $dados['token'];
        $email = $dados['email'];

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $data = date('Y-m-d');
        $this->loadModel('ClienteServico');

        $servicos = $this->ClienteServico->find('all',[
            'fields' => [
                'ClienteServico.id',
                'ClienteServico.nome',
                'ClienteServico.cliente_id',
                'ClienteServicoFoto.id',
                'ClienteServicoFoto.imagem',
                'Cliente.nome'
            ],
            'conditions' => [
                'ClienteServico.cliente_id' => $dado_usuario['Usuario']['cliente_id'],
                'ClienteServico.ativo' => "Y"
            ],
            'link' => ['ClienteServicoFoto', 'Cliente'],
            'group' => [
                'ClienteServico.id'
            ]
        ]);

        $dados_retornar = [];

        $horarios = [];
        foreach($servicos as $key => $servico) {
            // Verifica e define a imagem do serviço apenas uma vez fora do loop interno
            if (empty($servico['ClienteServicoFoto']['id'])) {
                $servicos[$key]['ClienteServicoFoto']['imagem'] = "https://buzke-images.s3.sa-east-1.amazonaws.com/services/sem_imagem.jpeg";
            }
        
            // Define a imagem associada ao serviço
            $servicos[$key]['ClienteServico']['foto'] = $servicos[$key]['ClienteServicoFoto']['imagem'];
        
            $servicos[$key]['ClienteServico']['_cliente_nome'] = $servico['Cliente']['nome'];
        
            // Pega os horários disponíveis para esse serviço
            $servicos[$key]['_horarios'] = $this->quadra_horarios($servico['ClienteServico']['id'], $data, false);
        
            if (count($servicos[$key]['_horarios']) > 0) {
                foreach ($servicos[$key]['_horarios'] as $index => $horario) {
                    if ($horario['active']) {
                        // Adiciona o serviço com a imagem já corrigida
                        $horario['_servico'] = $servicos[$key]['ClienteServico'];
                        $dados_retornar[] = $horario;
                    }
                }
            }
        }

        // Passo 1: Ordenar a array
        usort($dados_retornar, function ($a, $b) {
            if ($a['time'] === $b['time']) {
                return $a['_servico']['id'] <=> $b['_servico']['id'];
            }
            return $a['time'] <=> $b['time'];
        });

        // Passo 2: Reorganizar a array para intercalar os serviços
        $sortedSlots = [];
        $times = array_unique(array_column($dados_retornar, 'time')); // Extraí todos os horários únicos
        foreach ($times as $time) {
            // Coleta todos os slots para esse horário específico
            foreach ($dados_retornar as $slot) {
                if ($slot['time'] === $time) {
                    $sortedSlots[] = $slot;
                }
            }
        }

        $limitedSlots = array_slice($sortedSlots, 0, 10);


        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $sortedSlots))));

    }

    public function em_promocao() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        
        $this->loadModel('Promocao');

        $dia_semana_atual = (int)date('w');

        $conditions = [
            'Cliente.mostrar' => 'Y',
            'Cliente.ativo' => 'Y',
            'Promocao.finalizada' => 'N',
            'OR' => [
                [
                    'Promocao.validade_ate_cancelar' => 'Y'
                ],
                [
                    'Promocao.validade_inicio <=' => date('Y-m-d H:i:s'),
                    'Promocao.validade_fim >=' => date('Y-m-d H:i:s'),
                ]
            ],
            'PromocaoDiaSemana.dia_semana' => $dia_semana_atual,
            'ClienteServicoHorario.dia_semana' => $dia_semana_atual,
            'ClienteServico.ativo' => 'Y'
        ];

        if ( isset($dados['address']) && $dados['address'] != '' ) {

            if ( isset($dados['address'][1]) && (trim($dados['address'][1]) == "Uruguai" || trim($dados['address'][1]) == "Uruguay") ) {
                $this->loadModel('UruguaiCidade');
                $dados_localidade = $this->UruguaiCidade->findByGoogleAddress($dados['address'][0]);

                $conditions = array_merge($conditions, [
                    'Cliente.ui_cidade' => $dados_localidade['UruguaiCidade']['id'],
                ]);

            } else {
                $this->loadModel('Localidade');
                $dados_localidade = $this->Localidade->findByGoogleAddress($dados['address']);

                $conditions = array_merge($conditions, [
                    'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                    'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
                ]);
            }
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        if ( isset($dados['cliente_id']) && $dados['cliente_id'] != '' ) {
            $conditions[] = [
                'Cliente.id' => $dados['cliente_id']
            ];
        }

        $this->loadModel('ClienteServico');

        $limit = [];

        if ( !empty($dados['limit']) ) {
            $limit = ['limit' => $dados['limit']];
        }

        $servicos = $this->ClienteServico->find('all',[
            'fields' => [
                'ClienteServico.id',
                'ClienteServico.tipo',
                'ClienteServico.nome',
                'ClienteServico.descricao',
                'ClienteServico.avg_avaliacao',
                'ClienteServicoHorario.inicio',
                'ClienteServicoHorario.fim',
                'ClienteServicoHorario.dia_semana',
                'ClienteServicoHorario.valor_padrao',
                'ClienteServicoHorario.valor_fixos',
                'ClienteServicoFoto.imagem',
                'Cliente.id',
                'Cliente.nome',
                'Cliente.logo',
                'Promocao.*'
            ],
            'link' => [
                'Cliente',
                'ClienteServicoFoto',
                'ClienteServicoHorario',
                'PromocaoServico' => [
                    'Promocao' => [
                        'PromocaoDiaSemana'
                    ]
                ]
            ],
            'conditions' => $conditions,
            'group' => ['ClienteServico.id'],
            $limit
        ]);

        foreach($servicos as $key => $ser){

            if ( empty($ser['ClienteServicoFoto']['imagem']) ) {
                $servicos[$key]['ClienteServicoFoto']['imagem'] = "https://buzke-images.s3.sa-east-1.amazonaws.com/services//sem_imagem.jpeg";
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $servicos))));

    }

    public function add_visit() {

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if (is_array($dados)) {
            $dados = json_decode(json_encode($dados, true));
        } else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->servico_id) || empty($dados->servico_id)) {
            throw new BadRequestException('Serviço não informado', 400);
        }

        if (!isset($dados->data) || empty($dados->data)) {
            throw new BadRequestException('Data não informada', 400);
        }

        $email = null;
        if ( filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            $email = $dados->email;
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( !empty($dados_token['Usuario']) && $dados_token['Usuario']['nivel_id'] != 3 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Usuário sem permissão de salvamento de visita'))));
        }

        $this->loadModel('ServicoVisita');

        $dados_salvar = [
            'token_id' => $dados_token['Token']['id'],
            'cliente_servico_id' => $dados->servico_id,
            'data' => $dados->data,
        ];

        $this->ServicoVisita->create();

        if ( !$this->ServicoVisita->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Erro ao salvar!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Visita salva com sucesso!'))));

    }

}