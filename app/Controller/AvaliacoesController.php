<?php
class AvaliacoesController extends AppController {

    public $components = array('RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        $this->loadModel('ClienteServicoAvaliacao');

        $conditions = [];

        if ( isset($dados['cliente_id']) && !empty($dados['cliente_id']) ) {
            $conditions['ClienteServico.cliente_id'] = $dados['cliente_id'];
        }       

        $avaliacoes = $this->ClienteServicoAvaliacao->find('all',[
            'fields' => [
                'ClienteServicoAvaliacao.*',
                'ClienteServico.nome',
                'Usuario.nome',
                'Usuario.img'
            ],
            'conditions' => $conditions,
            'link' => [
                'ClienteServico',
                'Usuario'
            ],
            'group' => [
                'ClienteServicoAvaliacao.id'
            ],
            'order' => [
                'ClienteServicoAvaliacao.created DESC'
            ]
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $avaliacoes))));

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

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteCliente');
        $this->loadModel('ClienteServicoAvaliacao');

        $dados_servico = $this->ClienteServico->find('first',[
            'conditions' => [
                'ClienteServico.id' => $dados->servico_id
            ],
            'link' => [

            ]
        ]);

        if ( count($dados_servico) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do serviço não enontrados!'))));
        }

        $empresa_id = $dados_servico['ClienteServico']['cliente_id'];

        $dados_usuario_como_cliente = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_token['Usuario']['id'], $empresa_id);

        if ( count($dados_usuario_como_cliente) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do agendamento não enontrados!'))));
        }

        $dados_agendamento = $this->Agendamento->find('first',[
            'conditions' => [
                'Agendamento.cliente_cliente_id' => $dados_usuario_como_cliente['ClienteCliente']['id'],
                'Agendamento.servico_id' => $dados->servico_id,
                'Agendamento.domicilio' => 'N',
                'Agendamento.horario <' => date('Y-m-d H:i:s')
            ],
            'link' => []
        ]);

        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do agendamento não enontrados!'))));
        }

        $verifica_avaliacao = $this->ClienteServicoAvaliacao->find('first',[
            'conditions' => [
                'ClienteServicoAvaliacao.cliente_servico_id' => $dados->servico_id,
                'ClienteServicoAvaliacao.usuario_id' => $dados_token['Usuario']['id'],
            ],
            'link' => []
        ]);

        $dados_salvar = [
            'cliente_servico_id' => $dados->servico_id,
            'avaliacao' => $dados->avaliacao,
            'comentario' => $dados->comentario,
            'usuario_id' => $dados_token['Usuario']['id'],
        ];

        if ( count($verifica_avaliacao) == 0 ) {
            $this->ClienteServicoAvaliacao->create();
        } else {
            $dados_salvar['id'] = $verifica_avaliacao['ClienteServicoAvaliacao']['id'];
        }

        if ( !$this->ClienteServicoAvaliacao->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar salvar a avaliação!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Avaliação cadastrada comn sucesso!'))));

    }

    public function avaliacoes_pendentes() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        $token = $dados['token'];
        $email = $dados['email'];

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteCliente');
        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteServicoAvaliacao');

        $dado_de_usuario_como_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dado_usuario['Usuario']['id'], true);

        $servicos = $this->Agendamento->find('all',[
            'fields' => [
                'Agendamento.horario',
                'ClienteServico.*',
                'Cliente.nome',
                'Cliente.logo'
            ],
            'conditions' => [
                'Agendamento.cliente_cliente_id' => $dado_de_usuario_como_cliente,
                'Agendamento.horario <' => date('Y-m-d H:i:s'),
                'Agendamento.cancelado' => 'N',
                'Agendamento.torneio_id' => null,
                'not' => [
                    'ClienteServico.id' => null
                ]
            ],
            'link' => [
                'Cliente',
                'ClienteServico' => [
                    'ClienteServicoFoto' => [
                        'fields' => [
                            'ClienteServicoFoto.imagem'
                        ]
                    ]
                ]
            ],
            'group' => [
                'Agendamento.servico_id'
            ],
            'order' => [
                'Agendamento.horario DESC'
            ]
        ]);

        foreach( $servicos as $key => $servico ) {

            $verifica_avaliou = $this->ClienteServicoAvaliacao->find('count',[
                'conditions' => [
                    'ClienteServicoAvaliacao.cliente_servico_id' => $servico['ClienteServico']['id'],
                    'ClienteServicoAvaliacao.usuario_id' => $dado_usuario['Usuario']['id']
                ],
                'link' => []
            ]);


            if ( $verifica_avaliou > 0 ) {
                unset($servicos[$key]);
                continue;
            }
        }

        $servicos = array_values($servicos);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $servicos))));

    }

    public function minhas_avaliacoes() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        $token = $dados['token'];
        $email = $dados['email'];

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteServico');

        $servicos = $this->ClienteServico->find('all',[
            'fields' => [
                'ClienteServico.*',
                'ClienteServicoAvaliacao.*',
                'Cliente.nome',
                'Cliente.logo',
                'Usuario.img',
                'Usuario.nome'
            ],
            'conditions' => [
                'ClienteServicoAvaliacao.usuario_id' => $dado_usuario['Usuario']['id']
            ],
            'link' => [
                'Cliente',
                'ClienteServicoAvaliacao' => [
                    'Usuario'
                ]
            ],
            'order' => [
                'ClienteServicoAvaliacao.created'
            ]
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $servicos))));

    }

}