<?php
class SincronizacoesController extends AppController {

    public $components = array('RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        
        $this->loadModel('SincronizacaoAgenda');

        $token = $dados['token'];
        $email = $dados['email'];

        $dado_usuario = $this->verificaValidadeToken($token, $email);
        
        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $sincronizacoes = $this->SincronizacaoAgenda->find('all',[
            'fields' => [
                'SincronizacaoAgenda.*'
            ],
            'conditions' => [
                'SincronizacaoAgenda.usuario_id' => $dado_usuario['Usuario']['id']
            ],
            'link' => []
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $sincronizacoes))));

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
        if ( !$dados_token || !empty($dados_token['Usuario']['cliente_id']) ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
       
        
        $this->loadModel('SincronizacaoAgenda');

        $dados_salvar = [
            'usuario_id' => $dados_token['Usuario']['id'],
            'plataforma' => $dados->plataforma,
            'novos' => $dados->novos,
            'removidos' => $dados->removidos
        ];

        $this->SincronizacaoAgenda->create();

        if ( !$this->SincronizacaoAgenda->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar a sincronização!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Sincronização cadastrada com sucesso!'))));
    
    }

    public function getLast() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        
        $this->loadModel('SincronizacaoAgenda');

        $token = $dados['token'];
        $email = $dados['email'];
    

        $dado_usuario = $this->verificaValidadeToken($token, $email);
        
        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $sincronizacao = $this->SincronizacaoAgenda->find('first',[
            'fields' => [
                'SincronizacaoAgenda.*'
            ],
            'conditions' => [
                'SincronizacaoAgenda.usuario_id' => $dado_usuario['Usuario']['id']
            ],
            'order' => [
                'SincronizacaoAgenda.created DESC'
            ],
            'link' => []
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $sincronizacao))));
    }

    public function buscaIdsSincronizadosCancelados() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        
        $this->loadModel('AgendamentoClienteCliente');
        $this->loadModel('ClienteCliente');
        $this->loadModel('Agendamento');


        $token = $dados['token'];
        $email = $dados['email'];
    

        $dado_usuario = $this->verificaValidadeToken($token, $email);
        
        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dado_usuario['Usuario']['id'], true);

        $ids_cancelados = $this->AgendamentoClienteCliente->find('all',[
            'fields' => [
                'AgendamentoClienteCliente.id_sync_google',
                'AgendamentoClienteCliente.id_sync_ios',
                'AgendamentoClienteCliente.agendamento_id'
            ],
            'conditions' => [
                'AgendamentoClienteCliente.cliente_cliente_id' => $meus_ids_de_cliente,
                'Agendamento.cancelado' => 'Y',
                'OR' => [
                    [
                        'NOT' => [
                            'AgendamentoClienteCliente.id_sync_google' => null
                        ]
                        ],
                    [
                        'NOT' => [
                            'AgendamentoClienteCliente.id_sync_ios' => null
                        ]
                    ]
                ]
            ],
            'link' => ['Agendamento']
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $ids_cancelados))));
    }

}