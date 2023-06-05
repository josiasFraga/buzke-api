<?php
class ProdutosController extends AppController {

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
                    ['Produto.descricao LIKE' => "%".$searchText."%"],
                    ['Produto.codigo LIKE' => "%".$searchText."%"]
                ]
            ]);
        }


        $this->loadModel('Produto');
        $data = $this->Produto->listar($dados_token['Usuario']['cliente_id'], $conditions);

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

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Produto');

        $verifica_por_codigo = $this->Produto->buscaPorCodigo($dados_usuario['Usuario']['cliente_id'], $dados->codigo);
        //$verifica_por_email = $this->ClienteCliente->buscaPorEmail($dados_usuario['Usuario']['cliente_id'], $dados->email_cliente);

        if ( count($verifica_por_codigo) > 0 )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'waning', 'message' => 'Já existe um produto cadastrada com esse código.'))));

        $dados_salvar['Produto']['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
        $dados_salvar['Produto']['cadastrado_por'] = $dados_usuario['Usuario']['id'];
        $dados_salvar['Produto']['descricao'] = $dados->descricao;
        $dados_salvar['Produto']['unidade_entrada_id'] = $dados->unidade_entrada_id;
        $dados_salvar['Produto']['unidade_saida_id'] = $dados->unidade_saida_id;
        $dados_salvar['Produto']['categoria_id'] = $dados->categoria_id;
        $dados_salvar['Produto']['codigo'] = $dados->codigo;
        $dados_salvar['Produto']['descricao'] = $dados->descricao;
        $dados_salvar['Produto']['estoque_minimo'] = $dados->estoque_minimo;
        $dados_salvar['Produto']['estoque_inicial'] = $dados->estoque_inicial;
        $dados_salvar['Produto']['data_estoque'] = $dados->data_estoque;
        $dados_salvar['Produto']['valor_custo'] = $dados->valor_custo;
        $dados_salvar['Produto']['valor_venda'] = $dados->valor_venda;
        $dados_salvar['Produto']['alcoolico'] = $dados->alcoolico;
        $dados_salvar['Produto']['cozinha'] = $dados->destino_impressao == 'cozinha' ? 1 : 0;
        $dados_salvar['Produto']['ativo'] = $dados->ativo;
        $dados_salvar['Produto']['peso'] = $dados->peso;
        $dados_salvar['Produto']['classe_imposto'] = $dados->classe_imposto;
        $dados_salvar['Produto']['ncm'] = $dados->ncm;
        $dados_salvar['Produto']['cest'] = $dados->cest;
        $dados_salvar['Produto']['vendido_separadamente'] = $dados->vendido_separadamente;
        $dados_salvar['Produto']['destino_impressao'] = $dados->destino_impressao;
        $dados_salvar['Produto']['parte_do_cupom'] = $dados->parte_do_cupom;

        if ( !$this->Produto->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao salvar os dados da categoria'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Produto cadastrado com sucesso!'))));

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

        $this->loadModel('Produto');

        $vProduto = $this->Produto->find('first',[
            'conditions' => [
                'Produto.id' => $dados->id,
                'Produto.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($vProduto) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos os dados do produto.'))));
        }

        $dados_salvar['Produto']['id'] = $dados->id;
        $dados_salvar['Produto']['descricao'] = $dados->descricao;
        $dados_salvar['Produto']['unidade_entrada_id'] = $dados->unidade_entrada_id;
        $dados_salvar['Produto']['unidade_saida_id'] = $dados->unidade_saida_id;
        $dados_salvar['Produto']['categoria_id'] = $dados->categoria_id;
        $dados_salvar['Produto']['codigo'] = $dados->codigo;
        $dados_salvar['Produto']['descricao'] = $dados->descricao;
        $dados_salvar['Produto']['estoque_minimo'] = $dados->estoque_minimo;
        $dados_salvar['Produto']['estoque_inicial'] = $dados->estoque_inicial;
        $dados_salvar['Produto']['data_estoque'] = $dados->data_estoque;
        $dados_salvar['Produto']['valor_custo'] = $dados->valor_custo;
        $dados_salvar['Produto']['valor_venda'] = $dados->valor_venda;
        $dados_salvar['Produto']['alcoolico'] = $dados->alcoolico;
        $dados_salvar['Produto']['cozinha'] = $dados->destino_impressao == 'cozinha' ? 1 : 0;
        $dados_salvar['Produto']['ativo'] = $dados->ativo;
        $dados_salvar['Produto']['peso'] = $dados->peso;
        $dados_salvar['Produto']['classe_imposto'] = $dados->classe_imposto;
        $dados_salvar['Produto']['ncm'] = $dados->ncm;
        $dados_salvar['Produto']['cest'] = $dados->cest;
        $dados_salvar['Produto']['vendido_separadamente'] = $dados->vendido_separadamente;
        $dados_salvar['Produto']['destino_impressao'] = $dados->destino_impressao;
        $dados_salvar['Produto']['parte_do_cupom'] = $dados->parte_do_cupom;

        $verifica_por_codigo = $this->Produto->buscaPorCodigo($dados_usuario['Usuario']['cliente_id'], $dados->codigo, $dados->id);

        if ( count($verifica_por_codigo) > 0 )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'waning', 'message' => 'Já existe um produto cadastrado com esse código.'))));


        if ( !$this->Produto->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar os dados do produto'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Produto alterado com sucesso!'))));

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

        $this->loadModel('Produto');
        $dados_categoria = $this->Produto->find('first',[
            'conditions' => [
                'Produto.id' => $dados->id,
                'Produto.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_categoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Produto que você está tentando exlcuir, não existe!'))));
        }

        if ( !$this->Produto->deleteAll(['Produto.id' => $dados->id]) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao tentar exluir o produto. Por favor, tente mais tarde!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Produto excluído com sucesso!'))));

    }
}