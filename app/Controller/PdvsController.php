<?php
class PdvsController extends AppController {

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


        $this->loadModel('Pdv');
        $data = $this->Pdv->listar($dados_token['Usuario']['cliente_id'], $conditions);

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

   
}