<?php

class EstadosController extends AppController {
    
    public $helpers = array('Html', 'Form');	
    public $components = array('RequestHandler');
    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];

        $dados_token = $this->verificaValidadeToken($token);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Uf');

        $ufs = $this->Uf->find('all',[
            'fields' => [
                'Uf.ufe_sg',
                'Uf.ufe_no'
            ],
            'link' => [],
            'order' => ['Uf.ufe_no']
        ]);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $ufs))));
    }

}