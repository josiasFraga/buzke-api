<?php
class ProdutosAdicionaisController extends AppController {

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['produto_id']) || $dados['produto_id'] == "" ) {
            throw new BadRequestException('Dados do produto não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $produto_id = $dados['produto_id'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $conditions = [
            'ProdutoAdicional.produto_id' => $produto_id,
            'ProdutoAdicional.ativo' => 1
        ];

        if ( isset($dados['searchText']) && $dados['searchText'] != '' ) {
            $searchText = $dados['searchText'];
            $conditions = array_merge($conditions, [
                'or' => [
                    ['ProdutoAdicional.descricao LIKE' => "%".$searchText."%"]
                ]
            ]);
        }

        $this->loadModel('ProdutoAdicional');
        $data = $this->ProdutoAdicional->listar($dados_token['Usuario']['cliente_id'], $conditions);

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

    public function cadastrar(){
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

        if ( !isset($dados->produto_id) || $dados->produto_id == "" ) {
            throw new BadRequestException('Produto não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Produto');

        $vProduto = $this->Produto->find('first',[
            'conditions' => [
                'Produto.id' => $dados->produto_id,
                'Produto.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($vProduto) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos os dados do produto.'))));
        }

        $dados_salvar['ProdutoAdicional']['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
        $dados_salvar['ProdutoAdicional']['descricao'] = $dados->descricao;
        $dados_salvar['ProdutoAdicional']['valor'] = $dados->valor;
        $dados_salvar['ProdutoAdicional']['produto_id'] = $dados->produto_id;

        $this->loadModel('ProdutoAdicional');

        if ( !$this->ProdutoAdicional->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao salvar os dados do adicional'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Adicional cadastrado com sucesso!'))));

    }

    public function alterar(){
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

        $this->loadModel('ProdutoAdicional');

        $vCategoria = $this->ProdutoAdicional->find('first',[
            'fields' => ['ProdutoAdicional.*'],
            'conditions' => [
                'ProdutoAdicional.id' => $dados->id,
                'Produto.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => ['Produto']
        ]);

        if ( count($vCategoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos os dados da categoria.'))));
        }

        $dados_salvar['ProdutoAdicional']['id'] = $dados->id;
        $dados_salvar['ProdutoAdicional']['descricao'] = $dados->descricao;
        $dados_salvar['ProdutoAdicional']['valor'] = $dados->valor;

        if ( !$this->ProdutoAdicional->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar os dados da categoria'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Categoria alterada com sucesso!'))));

    }

    public function excluir(){
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

        $this->loadModel('ProdutoAdicional');
        $dados_adicional = $this->ProdutoAdicional->find('first',[
            'conditions' => [
                'ProdutoAdicional.id' => $dados->id,
                'Produto.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => ['Produto']
        ]);

        if ( count($dados_adicional) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'O adicional que você está tentando exlcuir, não existe!'))));
        }

        $dados_adicional['ProdutoAdicional']['ativo'] = 0;

        if ( !$this->ProdutoAdicional->saveAssociated($dados_adicional) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao tentar exluir o adicional. Por favor, tente mais tarde!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Adicional excluído com sucesso!'))));

    }
}