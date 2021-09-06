<?php

class PadelController extends AppController {

    public function categorias() {

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

        $this->loadModel('PadelCategoria');
        $categorias = $this->PadelCategoria->find('all',[
            'fields' => [
                'PadelCategoria.id', 'PadelCategoria.titulo'
            ],
            'order' => ['PadelCategoria.titulo'],
            'link' => []
        ]);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $categorias))));

    }
}