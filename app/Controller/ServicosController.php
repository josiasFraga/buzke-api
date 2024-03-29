<?php
class ServicosController extends AppController {

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = null;

        if ( isset($dados['email']) && $dados['email'] != "" ) {
            $email = $dados['email'];
        }

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteServicoHorario');

        $conditions = [];
        if ( isset($dados['tipo']) && $dados['tipo'] == 'meus' ) {

            if ( !isset($dados_token['Usuario']) ) {
                throw new BadRequestException('Usuario não logado!', 401);
            }

            if ( $dados_token['Usuario']['cliente_id'] == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
            }

            $conditions = array_merge($conditions, [
                'ClienteServico.cliente_id' => $dados_token['Usuario']['cliente_id']
            ]);

        } else {

            if ( isset($dados['cliente_id']) && $dados['cliente_id'] != '' ) {
                $conditions = [
                    'ClienteServico.cliente_id' => $dados['cliente_id']
                ];

                if ( $dados['cliente_id'] == 55 ) {
                    $order_cliente_servico = [
                        'ClienteServico.id'
                    ];
        
                }
            } else {
                $conditions = [
                    'ClienteServico.cliente_id' => $dados_token['Usuario']['cliente_id']
                ];

                if ( $dados_token['Usuario']['cliente_id'] == 55 ) {
                    $order_cliente_servico = [
                        'ClienteServico.id'
                    ];
        
                }
            }
        }

        $order_quadras = ['ClienteServico.nome'];

        if ( $dados_token['Usuario']['cliente_id'] == 55 ) {
            $order_quadras = ['ClienteServico.id'];
        }

        $quadras = $this->ClienteServico->find('all',[
            'fields' => [
                'ClienteServico.*'
            ],
            'conditions' => $conditions,
            'order' => $order_quadras,
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
            $quadras[$key]["ClienteServico"]["_dias_semana"] = $this->ClienteServicoHorario->lsitaDiasSemana($qua['ClienteServico']['id']);

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
                        'vagas_por_horario',
                        'a_domicilio'
                    ]
                ]
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

    public function add() {
        
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

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

        $dados_salvar = [
            'cliente_id' => $dados_token['Usuario']['cliente_id'],
            'tipo' => $dados->tipo,
            'nome' => $dados->nome,
            'descricao' => $dados->descricao,
            'valor' => $this->currencyToFloat($dados->_valor),
            'ativo' => $dados->ativo,
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

        // Iniciar a transação
        $dataSource->begin();
    
        try {
            $dados_servico_salvo = $this->ClienteServico->save($dados_salvar);

            if ( !$dados_servico_salvo ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar salvar os dados do serviço/quadra!'))));
            }
            
            $servico_id = $dados_servico_salvo['ClienteServico']['id'];

            $ids_imagens_permanecer = [];    
            // Pega os ids das fotos passadas no post
            if ( isset($dados->fotos) && is_array($dados->fotos) ) {
        
                $fotos = $dados->fotos;
                $ids_imagens_permanecer = array_values(array_map(function($foto){
                    return $foto->id;
                }, $fotos));
    
            }
            
            // Remove as fotos que não estão no post      
            $this->ClienteServicoFoto->deleteAll([
                'ClienteServicoFoto.cliente_servico_id' => $servico_id,
                'not' => [
                    'ClienteServicoFoto.id' => $ids_imagens_permanecer
                ]
            ]);


            $horarios_salvar = [];
            foreach( $dados->horarios as $key => $horario ){
    
                $horarios_salvar[$key] = [
                    "cliente_servico_id" => $servico_id,
                    "inicio" => $horario->inicio,
                    "fim" => $horario->fim,
                    "dia_semana" => $horario->dia_semana,
                    "duracao" => $horario->duracao,
                    "vagas_por_horario" => $dados->tipo === "Quadra" ? 1 : $horario->vagas_por_horario,
                    "a_domicilio" => $dados->tipo === "Quadra" ? 0 : $horario->a_domicilio,
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

}
