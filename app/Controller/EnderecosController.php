<?php
App::uses('HttpSocket', 'Network/Http'); // Cake 2.x
class EnderecosController extends AppController {
    
    public $helpers = array('Html', 'Form');	
    public $components = array('RequestHandler');
    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function buscaDadosCep() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['cep']) || $dados['cep'] == "" ) {
            throw new BadRequestException('Dados de CEP não informado!', 401);
        }

        $cep = $dados['cep'];
        $cep = str_replace('-','',$cep);

        $HttpSocket = new HttpSocket();
        $results = $HttpSocket->get('http://viacep.com.br/ws/'.$cep.'/json/', 'q=cakephp');

        $retorno = json_decode($results->body, true);
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $retorno))));
    }

}