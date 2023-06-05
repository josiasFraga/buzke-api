<?php
class ProdutosCategoriasController extends AppController {

    public function index() {

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
                    ['ProdutoCategoria.nome LIKE' => "%".$searchText."%"]
                ]
            ]);
        }


        $this->loadModel('ProdutoCategoria');
        $data = $this->ProdutoCategoria->listar($dados_token['Usuario']['cliente_id'], $conditions);

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

        if ( !isset($dados->nome) || $dados->nome == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados_salvar['ProdutoCategoria']['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
        $dados_salvar['ProdutoCategoria']['nome'] = $dados->nome;

        $this->loadModel('ProdutoCategoria');

        $verifica_por_nome = $this->ProdutoCategoria->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->nome);
        //$verifica_por_email = $this->ClienteCliente->buscaPorEmail($dados_usuario['Usuario']['cliente_id'], $dados->email_cliente);

        if ( count($verifica_por_nome) > 0 && (!isset($dados->confirma) || $dados->confirma == 0 ) )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'waning', 'message' => 'Já existe uma categoria cadastrada com esse nome.'))));


        if ( !$this->ProdutoCategoria->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao salvar os dados da categoria'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Categoria cadastrada com sucesso!'))));

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

        if ( !isset($dados->nome) || $dados->nome == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        if ( !isset($dados->id) || $dados->id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ProdutoCategoria');

        $vCategoria = $this->ProdutoCategoria->find('first',[
            'conditions' => [
                'ProdutoCategoria.id' => $dados->id,
                'ProdutoCategoria.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($vCategoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos os dados da categoria.'))));
        }

        $dados_salvar['ProdutoCategoria']['id'] = $dados->id;
        $dados_salvar['ProdutoCategoria']['nome'] = $dados->nome;

        $verifica_por_nome = $this->ProdutoCategoria->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->nome, $dados->id);

        if ( count($verifica_por_nome) > 0 && (!isset($dados->confirma) || $dados->confirma == 0 ) )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Já existe uma categoria cadastrada com esse nome.'))));


        if ( !$this->ProdutoCategoria->saveAssociated($dados_salvar) ) {
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

        $this->loadModel('ProdutoCategoria');
        $dados_categoria = $this->ProdutoCategoria->find('first',[
            'conditions' => [
                'ProdutoCategoria.id' => $dados->id,
                'ProdutoCategoria.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_categoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'A categoria que você está tentando exlcuir, não existe!'))));
        }

        if ( !$this->ProdutoCategoria->deleteAll(['ProdutoCategoria.id' => $dados->id]) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao tentar exluir a categoria. Por favor, tente mais tarde!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Categoria excluída com sucesso!'))));

    }
}