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
                ]
            ]
        ]);

        foreach($quadras as $key => $qua){

            $quadras[$key]['ClienteServico']['_valor'] = number_format($qua['ClienteServico']['valor'],2,',','.');

            if ( isset($dados['day']) && !empty($dados['day']) ) {
                $quadras[$key]["ClienteServico"]["_horarios"] = $this->quadra_horarios($qua['ClienteServico']['id'], $dados['day'], false);
            } else {
                $quadras[$key]["ClienteServico"]["_dias_semana"] = $this->ClienteServicoHorario->listaDiasSemana($qua['ClienteServico']['id']);
            }

            if ( count($qua['ClienteServicoFoto']) > 0 ) {
                foreach( $qua['ClienteServicoFoto'] as $key_imagem => $imagem){
                    $quadras[$key]['ClienteServicoFoto'][$key_imagem]['imagem'] = $this->images_path . "/servicos/" . $imagem['imagem'];
                }
            } else {
                $quadras[$key]['ClienteServicoFoto'][0]['imagem'] = $this->images_path . "/servicos/sem_imagem.jpeg";
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
            'valor' => $this->currencyToFloat($dados->_valor),
            'ativo' => $dados->ativo,
            'fixos' => $dados->fixos,
            'fixos_tipo' => $dados->fixos_tipo,
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
                    $profissionais_salvar[] = [
                        'usuario_id'=> $profissional,
                        'cliente_servico_id' => $servico_id
                    ];
                }

                if ( !$this->ClienteServicoProfissional->saveMany($profissionais_salvar) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar salvar os profissionais do serviço!'))));
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
            foreach( $dados->horarios as $key => $horario ){
    
                $horarios_salvar[$key] = [
                    "cliente_servico_id" => $servico_id,
                    "inicio" => $horario->inicio,
                    "fim" => $horario->fim,
                    "dia_semana" => $horario->dia_semana,
                    "duracao" => $horario->duracao,
                    "a_domicilio" => $dados->tipo === "Quadra" ? 0 : $horario->a_domicilio,
                    "apenas_a_domocilio" => $dados->tipo === "Quadra" ? 0 : $horario->apenas_a_domocilio,
                ];
    
                if ( is_numeric($horario->id) ) {
                    $horarios_salvar[$key]['id'] = $horario->id;
                }
    
            }

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
                        'apenas_a_domocilio'
                    ]
                ],
                'ClienteServicoProfissional'

            ]
        ]);

        $dados_servico['ClienteServico']['_valor'] = number_format($dados_servico['ClienteServico']['valor'],2,',','.');
        //$dados_servico["ClienteServico"]["_dias_semana"] = $this->ClienteServicoHorario->lsitaDiasSemana($qua['ClienteServico']['id']);
        
        if ( count($dados_servico['ClienteServicoFoto']) > 0 ) {
            foreach( $dados_servico['ClienteServicoFoto'] as $key_imagem => $imagem){
                $dados_servico['ClienteServicoFoto'][$key_imagem]['imagem'] = $this->images_path . "/servicos/" . $imagem['imagem'];
            }
        } else {
            $dados_servico['ClienteServicoFoto'][0]['imagem'] = $this->images_path . "/servicos/sem_imagem.jpeg";
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

        $conditions = [
            'ClienteServico.id' => $dados['servico_id']
        ];

        $dados_servico = $this->ClienteServico->find('first',[
            'fields' => [
                'ClienteServico.*'
            ],
            'conditions' => $conditions,
            'link' => []
        ]);

        if ( count($dados_servico) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Serviço não encontrado!'))));
        }
        
        $isCourt = $this->ClienteSubcategoria->checkIsCourt($dados_servico['ClienteServico']['cliente_id']);
        $isPaddleCourt = $this->ClienteSubcategoria->checkIsPaddleCourt($dados_servico['ClienteServico']['cliente_id']);

        $horarios = $this->quadra_horarios($dados['servico_id'], $dados['day'], $dados_servico['ClienteServico']['fixos']);

        $dados_retornar = [
            'origem' => !empty($dado_usuario['Usuario']['cliente_id']) ? 'empresa' : 'cliente',
            'is_court' => $isCourt,
            'is_paddle_court' => $isPaddleCourt,
            'enable_fixed_shedulling' => $dados_servico['ClienteServico']['fixos'] === 'Y',
            'fixed_shedulling' => $dados_servico['ClienteServico']['fixos_tipo'],
            'tipo' => $dados_servico['ClienteServico']['tipo'],
            'horarios' => $horarios
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
                'ClienteServico.nome'
            ],
            'conditions' => [
                'ClienteServico.cliente_id' => $dado_usuario['Usuario']['cliente_id'],
                'ClienteServico.ativo' => "Y"
            ],
            'link' => [],
            'group' => [
                'ClienteServico.id'
            ]
        ]);

        $dados_retornar = [];

        $horarios = [];
        foreach($servicos as $key => $servico){
            $servicos[$key]['_horarios'] = $this->quadra_horarios($servico['ClienteServico']['id'], $data, false);

            if ( count($servicos[$key]['_horarios']) > 0 ){

                foreach( $servicos[$key]['_horarios'] as $key => $horario ){

                    if ( $horario['active'] ) {

                        $horario['_servico'] = $servico['ClienteServico'];
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


        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $sortedSlots))));

    }

}