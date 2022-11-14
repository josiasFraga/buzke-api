<?php

class CidadesController extends AppController {
    
    public $helpers = array('Html', 'Form');	
    public $components = array('RequestHandler');
    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index($uf=null) {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];

        if ( isset($dados['email']) && $dados['email'] != "" ) {
            $dados_token = $this->verificaValidadeToken($token, $dados['email']);
        } else {
            $dados_token = $this->verificaValidadeToken($token);
        }

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $uf == null ) {            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        $this->loadModel('Localidade');

        $ufs = $this->Localidade->find('all',[
            'fields' => [
                'Localidade.loc_nu_sequencial',
                'Localidade.loc_no'
            ],
            'link' => [],
            'conditions' => [
                'Localidade.ufe_sg' => $uf
            ],
            'order' => ['Localidade.loc_no']
        ]);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $ufs))));
    }

}