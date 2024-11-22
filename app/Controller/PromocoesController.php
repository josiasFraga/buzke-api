<?php
class PromocoesController extends AppController {

    public $components = array('RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
    
        $token = $dados['token'];
        $email = $dados['email'];

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dado_usuario['Usuario']['nivel_id'] !== "2" ) {            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão de gerenciamento de promoções!'))));
        }
        
        $this->loadModel('Promocao');
        $this->loadModel('PromocaoDiaSemana');
        $this->loadModel('PromocaoServico');

        $conditions = [];
        $order = ['Promocao.created'];

        $conditions = array_merge($conditions, [
            'Promocao.cliente_id' => $dado_usuario['Usuario']['cliente_id']
        ]);

        $promocoes = $this->Promocao->find('all',[
            'fields' => [
                'Promocao.*'
            ],
            'conditions' => $conditions,
            'link' => [],
            'order' => $order
        ]);
        
        foreach ( $promocoes as $key => $pro ) {

            $promocoes[$key]['Promocao']['_expirada'] = false;

            if ( $pro['Promocao']['validade_ate_cancelar'] === 'Y' && $pro['Promocao']['finalizada'] === 'Y' ) {
                $promocoes[$key]['Promocao']['_expirada'] = true;
            } else if ( $pro['Promocao']['validade_ate_cancelar'] === 'N' && $pro['Promocao']['validade_fim'] < date('Y-m-d H:i:s') ){
                $promocoes[$key]['Promocao']['_expirada'] = true;
            }

            $promocoes[$key]['Promocao']['dias_semana'] = array_values($this->PromocaoDiaSemana->find('list',[
                'fields' => [
                    'PromocaoDiaSemana.dia_semana',
                    'PromocaoDiaSemana.dia_semana'
                ],
                'conditions' => [
                    'PromocaoDiaSemana.promocao_id' => $pro['Promocao']['id']
                ]
            ]));

            $promocoes[$key]['Promocao']['servicos'] = array_values($this->PromocaoServico->find('list',[
                'fields' => [
                    'PromocaoServico.servico_id'
                ],
                'conditions' => [
                    'PromocaoServico.promocao_id' => $pro['Promocao']['id']
                ]
            ]));

            $promocoes[$key]['Promocao']['servicos_nomes'] = array_values($this->PromocaoServico->find('list',[
                'fields' => [
                    'ClienteServico.nome'
                ],
                'conditions' => [
                    'PromocaoServico.promocao_id' => $pro['Promocao']['id']
                ],
                'link' => ['ClienteServico']
            ]));
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $promocoes))));

    }

    public function add(){

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if (is_array($dados)) {
            $dados = json_decode(json_encode($dados, true));
        } else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->titulo) || empty($dados->titulo)) {
            throw new BadRequestException('Título da promoção não informado', 400);
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

        $this->loadModel('Promocao');
        $this->loadModel('PromocaoServico');
        $this->loadModel('PromocaoDiaSemana');

        if ( $dados_token['Usuario']['nivel_id'] !== "2" ) {            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão de cadastro de promoção!'))));
        }

        $validade_inicio = null;
        $validade_fim = null;

        if ( !isset($dados->validade_ate_cancelar) || $dados->validade_ate_cancelar === false ) {

            $validade_inicio = $this->dateBrEn($dados->validade_inicio).' '.$dados->validade_inicio_hora;
            $validade_fim = $this->dateBrEn($dados->validade_fim).' '.$dados->validade_fim_hora;
        }

        //debug($dados);
        //die();

        // Verificação de conflitos com outras promoções
        $conflito = $this->verificarConflitosPromocao(
            $dados->servicos,
            $validade_inicio,
            $validade_fim,
            $dados->dias_semana,
            null
        );
    
        if (!empty($conflito)) {
            $nome_servico_conflito = $conflito['ClienteServico']['nome'];
            $dia_semana_conflito = $conflito['PromocaoDiaSemana']['dia_semana'];
            $titulo_promocao_conflito = $conflito['Promocao']['titulo'];
    
            $dias_semana = [
                0 => 'Domingo',
                1 => 'Segunda-feira',
                2 => 'Terça-feira',
                3 => 'Quarta-feira',
                4 => 'Quinta-feira',
                5 => 'Sexta-feira',
                6 => 'Sábado'
            ];
    
            return new CakeResponse(array(
                'type' => 'json',
                'body' => json_encode(array(
                    'status' => 'erro',
                    'msg' => "Conflito de promoção: o serviço '{$nome_servico_conflito}' já possui a promoção '{$titulo_promocao_conflito}' no dia {$dias_semana[$dia_semana_conflito]} dentro do mesmo período informado!"
                ))
            ));
        }

        $dados_salvar = [
            'cliente_id' => $dados_token['Usuario']['cliente_id'],
            'titulo' => $dados->titulo,
            'descricao' => $dados->descricao,
            'validade_ate_cancelar' => isset($dados->validade_ate_cancelar) && $dados->validade_ate_cancelar === true ? 'Y' : 'N',
            'validade_inicio' => $validade_inicio,
            'validade_fim' => $validade_fim,
            'horario_inicio' => $dados->horario_inicio ?? null,
            'horario_fim' => $dados->horario_fim ?? null,
            'promocao_para_fixos' => isset($dados->promocao_para_fixos) && $dados->promocao_para_fixos === true ? 'Y' : 'N',
            'promocao_para_padrao' => isset($dados->promocao_para_padrao) && $dados->promocao_para_padrao === true ? 'Y' : 'N',
            'valor_fixos' => isset($dados->promocao_para_fixos) && $dados->promocao_para_fixos === true ? $dados->valor_fixos : null,
            'valor_padrao' => isset($dados->promocao_para_padrao) && $dados->promocao_para_padrao === true ? $dados->valor_padrao : null,
            'limite_ate' => $dados->limite_ate ?? null
        ];

        $dataSource = $this->Promocao->getDataSource();
        $dataSource->begin();

        try {
            $promocao_salva = $this->Promocao->save($dados_salvar);

            if (!$promocao_salva) {
                throw new Exception('Erro ao salvar promoção');
            }

            $promocao_id = $promocao_salva['Promocao']['id'];

            // Remove os serviços que não estão no post
            $this->PromocaoServico->deleteAll([
                'PromocaoServico.promocao_id' => $promocao_id,
                'not' => [
                    'PromocaoServico.servico_id' => $dados->servicos
                ]
            ]);

            // Adiciona os serviços
            $servicos_salvar = [];
            foreach ($dados->servicos as $servico_id) {
                $servicos_salvar[] = [
                    'promocao_id' => $promocao_id,
                    'servico_id' => $servico_id,
                ];
            }

            if (!$this->PromocaoServico->saveMany($servicos_salvar)) {
                throw new Exception('Erro ao salvar serviços da promoção');
            }

            // Remove os dias da semana que não estão no post
            $this->PromocaoDiaSemana->deleteAll([
                'PromocaoDiaSemana.promocao_id' => $promocao_id,
                'not' => [
                    'PromocaoDiaSemana.dia_semana' => $dados->dias_semana
                ]
            ]);

            // Adiciona os dias da semana
            $dias_semana_salvar = [];
            foreach ($dados->dias_semana as $dia_semana) {
                $dias_semana_salvar[] = [
                    'promocao_id' => $promocao_id,
                    'dia_semana' => $dia_semana,
                ];
            }

            if (!$this->PromocaoDiaSemana->saveMany($dias_semana_salvar)) {
                throw new Exception('Erro ao salvar dias da semana da promoção');
            }

            $dataSource->commit();
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Promoção cadastrada com sucesso!'))));

        } catch (Exception $e) {
            $dataSource->rollback();
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => $e->getMessage()))));
        }

    }

    public function view($id = null) {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if (!isset($dados['email']) || $dados['email'] == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados['token']) || $dados['token'] == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        if ( empty($id) || !is_numeric($id) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Item não informado!'))));
        }
    
        $token = $dados['token'];
        $email = $dados['email'];

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        $this->loadModel('Promocao');
        $this->loadModel('PromocaoDiaSemana');
        $this->loadModel('PromocaoServico');

        $promocao = $this->Promocao->find('first',[
            'fields' => [
                'Promocao.*'
            ],
            'conditions' => [
                'Promocao.id' => $id,
            ],
            'link' => []
        ]);

        $promocao['dias_semana'] = array_values($this->PromocaoDiaSemana->find('list',[
            'fields' => [
                'PromocaoDiaSemana.dia_semana',
                'PromocaoDiaSemana.dia_semana'
            ],
            'conditions' => [
                'PromocaoDiaSemana.promocao_id' => $promocao['Promocao']['id']
            ]
        ]));

        $promocao['servicos'] = array_values($this->PromocaoServico->find('list',[
            'fields' => [
                'PromocaoServico.servico_id'
            ],
            'conditions' => [
                'PromocaoServico.promocao_id' => $promocao['Promocao']['id']
            ]
        ]));

        $promocao['Promocao']['valor_padrao'] = floatval($promocao['Promocao']['valor_padrao']);
        $promocao['Promocao']['valor_fixos'] = floatval($promocao['Promocao']['valor_fixos']);

        $promocao['Promocao']['_expirada'] = false;

        if ( $promocao['Promocao']['validade_ate_cancelar'] === 'Y' && $promocao['Promocao']['finalizada'] === 'Y' ) {
            $promocao['Promocao']['_expirada'] = true;
        } else if ( $promocao['Promocao']['validade_ate_cancelar'] === 'N' && $promocao['Promocao']['validade_fim'] < date('Y-m-d H:i:s') ){
            $promocao['Promocao']['_expirada'] = true;
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $promocao))));

    }

    public function edit(){

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if (is_array($dados)) {
            $dados = json_decode(json_encode($dados, true));
        } else {
            $dados = json_decode($dados);
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

        if ( $dados_token['Usuario']['nivel_id'] !== "2" ) {            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão de cadastro de promoção!'))));
        }

        $this->loadModel('Promocao');
        $this->loadModel('PromocaoServico');
        $this->loadModel('PromocaoDiaSemana');

        $dados_promocao = $this->Promocao->find('first', [
            'conditions' => [
                'Promocao.id' => $dados->id
            ],
            'contain' => [
                'PromocaoDiaSemana',
                'PromocaoServico'
            ]
        ]);

        if ( count($dados_promocao) == 0 ) {            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Os dados da promoção não foram encontrados!'))));
        }

        $validade_inicio = null;
        $validade_fim = null;

        if ( $dados_promocao['Promocao']['validade_ate_cancelar'] ) {
            $validade_fim = $this->dateBrEn($dados->validade_fim).' '.$dados->validade_fim_hora;
        }

        if ( !empty($validade_fim) ) {
            // Verificar se a validade_fim é inferior à validade_fim armazenada na tabela
            $validade_fim_tabela = $dados_promocao['Promocao']['validade_fim'];
            if (strtotime($validade_fim) < strtotime($validade_fim_tabela)) {
                return new CakeResponse(array(
                    'type' => 'json',
                    'body' => json_encode(array(
                        'status' => 'erro',
                        'msg' => 'A nova validade final não pode ser anterior à validade final já definida para a promoção!'
                    ))
                ));
            }
        }

        $conflito = $this->verificarConflitosPromocao(
            $dados->servicos,
            $validade_inicio,
            $validade_fim,
            $dados->dias_semana,
            $dados->id
        );
    
        if (!empty($conflito)) {
            $nome_servico_conflito = $conflito['ClienteServico']['nome'];
            $dia_semana_conflito = $conflito['PromocaoDiaSemana']['dia_semana'];
            $titulo_promocao_conflito = $conflito['Promocao']['titulo'];
    
            $dias_semana = [
                0 => 'Domingo',
                1 => 'Segunda-feira',
                2 => 'Terça-feira',
                3 => 'Quarta-feira',
                4 => 'Quinta-feira',
                5 => 'Sexta-feira',
                6 => 'Sábado'
            ];
    
            return new CakeResponse(array(
                'type' => 'json',
                'body' => json_encode(array(
                    'status' => 'erro',
                    'msg' => "Conflito de promoção: o serviço '{$nome_servico_conflito}' já possui a promoção '{$titulo_promocao_conflito}' no dia {$dias_semana[$dia_semana_conflito]} dentro do mesmo período informado!"
                ))
            ));
        }

        $dados_salvar = [
            'id' => $dados->id,
            'validade_fim' => $validade_fim,
        ];

        $dataSource = $this->Promocao->getDataSource();
        $dataSource->begin();

        try {
            $promocao_salva = $this->Promocao->save($dados_salvar);

            if (!$promocao_salva) {
                throw new Exception('Erro ao salvar promoção');
            }

            $promocao_id = $promocao_salva['Promocao']['id'];

            $servicos_salvar = [];
            foreach ($dados->servicos as $servico_id) {
                $existe_servico = $this->PromocaoServico->find('count', [
                    'conditions' => [
                        'PromocaoServico.promocao_id' => $promocao_id,
                        'PromocaoServico.servico_id' => $servico_id
                    ]
                ]);

                if ($existe_servico == 0) {
                    $servicos_salvar[] = [
                        'promocao_id' => $promocao_id,
                        'servico_id' => $servico_id,
                    ];
                }
            }

            if (!empty($servicos_salvar) && !$this->PromocaoServico->saveMany($servicos_salvar)) {
                throw new Exception('Erro ao salvar serviços da promoção');
            }

            $dias_semana_salvar = [];
            foreach ($dados->dias_semana as $dia_semana) {
                $existe_dia_semana = $this->PromocaoDiaSemana->find('count', [
                    'conditions' => [
                        'PromocaoDiaSemana.promocao_id' => $promocao_id,
                        'PromocaoDiaSemana.dia_semana' => $dia_semana
                    ]
                ]);

                if ($existe_dia_semana == 0) {
                    $dias_semana_salvar[] = [
                        'promocao_id' => $promocao_id,
                        'dia_semana' => $dia_semana,
                    ];
                }
            }

            if (!empty($dias_semana_salvar) && !$this->PromocaoDiaSemana->saveMany($dias_semana_salvar)) {
                throw new Exception('Erro ao salvar dias da semana da promoção');
            }

            $dataSource->commit();
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Promoção alterada com sucesso!'))));

        } catch (Exception $e) {
            $dataSource->rollback();
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => $e->getMessage()))));
        }
    }

    private function verificarConflitosPromocao($servicos, $validade_inicio, $validade_fim, $dias_semana, $promocao_id = null) {

        $conditions = [
            'PromocaoServico.servico_id IN' => $servicos,
            'Promocao.finalizada' => 'N',
            'Promocao.id !=' => $promocao_id,
            'PromocaoDiaSemana.dia_semana IN' => $dias_semana
        ];

        if ( !empty(trim($validade_inicio)) && !empty(trim($validade_fim)) ) {
            $conditions['OR'] = [
                ['Promocao.validade_ate_cancelar' => 'Y'], // Promoções sem data de fim
                [
                    'Promocao.validade_inicio <=' => $validade_fim,
                    'Promocao.validade_fim >=' => $validade_inicio
                ]
            ];
        }

        $conflito = $this->PromocaoServico->find('first', [
            'fields' => [
                'Promocao.titulo',
                'PromocaoServico.servico_id',
                'PromocaoDiaSemana.dia_semana',
                'ClienteServico.nome'
            ],
            'conditions' => $conditions,
            'link' => [
                'Promocao' => [
                    'PromocaoDiaSemana'
                ],
                'ClienteServico'
            ]
        ]);

    
        return $conflito;
    }

    public function finish() {

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if (is_array($dados)) {
            $dados = json_decode(json_encode($dados, true));
        } else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->id) || empty($dados->id)) {
            throw new BadRequestException('Promoção não informada', 400);
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

        if ( $dados_token['Usuario']['nivel_id'] !== "2" ) {            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão de cadastro de promoção!'))));
        }

        $this->loadModel('Promocao');

        $cliente_id = $dados_token['Usuario']['cliente_id'];
        $id = $dados->id;

        $dados_promocao = $this->Promocao->find('first',[
            'conditions' => [
                'Promocao.id' => $id,
                'Promocao.cliente_id' => $cliente_id,
                'Promocao.finalizada' => 'N',
                'Promocao.validade_ate_cancelar' => 'Y'
            ],
            'link' => []
        ]);

        if ( count($dados_promocao) === 0 ) {            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Os dados da promoção não foram encontrados!'))));
        }

        $dados_salvar = [
            'id' => $dados->id,
            'finalizada' => 'Y'
        ];

        $promocao_salva = $this->Promocao->save($dados_salvar);

        if (!$promocao_salva) {
            throw new Exception('Erro ao finalizar a promoção');
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Promoção finalizada com sucesso!'))));
        
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

        if (!isset($dados->promocao_id) || empty($dados->promocao_id)) {
            throw new BadRequestException('Promoção não informada', 400);
        }

        if (!isset($dados->servico_id) || empty($dados->servico_id)) {
            throw new BadRequestException('Serviço não informado', 400);
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

        if ( $dados_token['Usuario']['nivel_id'] != 3 ) {
            throw new BadRequestException('Usuário sem permissão de salvamento de visita', 400);
        }

        $this->loadModel('PromocaoVisita');

        $dados_salvar = [
            'token_id' => $dados_token['Token']['id'],
            'promocao_id' => $dados->promocao_id,
            'servico_id' => $dados->servico_id,
        ];

        $this->PromocaoVisita->create();

        if ( !$this->PromocaoVisita->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Erro ao salvar!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Visita salva com sucesso!'))));

    }

}