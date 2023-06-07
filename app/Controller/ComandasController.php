<?php
class ComandasController extends AppController {

    public function index()
    {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $conditions = [];
        if ( isset($dados['searchText']) && $dados['searchText'] != '' ) {
            $searchText = $dados['searchText'];
            $conditions = array_merge($conditions, [
                'or' => [
                    ['Comanda.descricao LIKE' => "%".$searchText."%"]
                ]
            ]);
        }

        if ( isset($dados['descricao']) && $dados['descricao'] != "" ) {
            $conditions = array_merge($conditions, [
                'Comanda.descricao' => $dados['descricao']              
            ]);
        }


        $this->loadModel('Comanda');
        $data = $this->Comanda->listar($dados_token['Usuario']['cliente_id'], $conditions);

        // Define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');

        $response = array(
            'status' => 'ok',
            'data' => $data
        );

        // Converte os dados em formato JSON
        $json = json_encode($response);

        // Retorna a resposta JSON
        $this->response->body($json);
    }

    public function cadastrar()
    {
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = json_decode(json_encode($this->request->data['dados']));

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->descricao) || $dados->descricao == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados_salvar['Comanda']['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
        $dados_salvar['Comanda']['descricao'] = $dados->descricao;

        $this->loadModel('Comanda');

        $verifica_por_nome = $this->Comanda->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->descricao);

        if ( count($verifica_por_nome) > 0 && (!isset($dados->confirma) || $dados->confirma == 0 ) )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'waning', 'message' => 'Já existe uma comanda cadastrada com esse nome.'))));

        if ( !$this->Comanda->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao salvar os dados da comanda'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Comanda cadastrada com sucesso!'))));

    }

    public function alterar()
    {
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = json_decode(json_encode($this->request->data['dados']));

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->descricao) || $dados->descricao == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        if ( !isset($dados->id) || $dados->id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Comanda');

        $vComanda = $this->Comanda->find('first',[
            'conditions' => [
                'Comanda.id' => $dados->id,
                'Comanda.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($vComanda) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Não encontramos os dados da comanda.'))));
        }

        $dados_salvar['Comanda']['id'] = $dados->id;
        $dados_salvar['Comanda']['descricao'] = $dados->descricao;

        $verifica_por_nome = $this->Comanda->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->descricao, $dados->id);

        if ( count($verifica_por_nome) > 0 && (!isset($dados->confirma) || $dados->confirma == 0 ) )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'message' => 'Já existe uma comanda cadastrada com esse nome.'))));


        if ( !$this->Comanda->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao salvar os dados da comanda'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Comanda alterada com sucesso!'))));

    }

    public function excluir()
    {
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = json_decode(json_encode($this->request->data['dados']));

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->id) || $dados->id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Comanda');
        $dados_mesa = $this->Comanda->find('first',[
            'conditions' => [
                'Comanda.id' => $dados->id,
                'Comanda.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_mesa) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'A comanda que você está tentando exlcuir, não existe!'))));
        }

        if ( !$this->Comanda->deleteAll(['Comanda.id' => $dados->id]) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao tentar exluir a comanda. Por favor, tente mais tarde!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Comanda excluída com sucesso!'))));

    }

    public function verificaAberta() 
    {
        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Comanda');

        if ( isset($dados['descricao']) && $dados['descricao'] != "" ) {

            $dados_comanda = $this->Comanda->find('first',[
                'conditions' => [
                    'Comanda.cliente_id' => $dados_token['Usuario']['cliente_id'],
                    'Comanda.descricao' => $dados['descricao']
                ],
                'link' => []
            ]);

            if ( count($dados_comanda) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "message" => "Comanda não econtrada!"))));
            }

        }

        $comanda_numero = $dados['descricao'];   
    
        // verifica se a comanda está aberta 
        $this->loadModel('ClienteClienteComanda');
        $comanda_aberta = $this->ClienteClienteComanda->find('first', array(
            'fields' => array(
                'ClienteClienteComanda.data_hora_entrada', 
                'ClienteClienteComanda.cliente_cliente_id', 
                'ClienteCliente.nome', 
                'ClienteCliente.cpf'
            ),
            'link' => array('ClienteCliente'),
            'conditions' => array(
                'ClienteClienteComanda.comanda_id' => $dados_comanda['Comanda']['id'],
                'ISNULL(ClienteClienteComanda.data_hora_saida)'
            )
        ));
    
        if ( count($comanda_aberta) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "ok", "reponse" => "comanda_fechada"))));
        } else {
            return new CakeResponse(array(
                'type' => 'json', 
                'body' => json_encode(array(
                    "status" => "ok", 
                    "reponse" => "comanda_aberta", 
                    "cliente" => $comanda_aberta['ClienteClienteComanda']['cliente_id'], 
                    //"mesa" => $comanda_aberta['ClienteClienteComanda']['cliente_id']
                ))
            ));
        }

    }
}