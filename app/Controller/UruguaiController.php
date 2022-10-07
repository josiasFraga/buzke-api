<?php 

class UruguaiController extends AppController {

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function departamentos () {

        $this->layout = 'ajax';
        $this->loadModel("UruguaiDepartamento");

        $dados = $this->UruguaiDepartamento->find("all", [
            "order" => [
                "UruguaiDepartamento.nome"
            ],
            "link" => []
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));
    }

    public function cidades ($departamento_id = null) {

        $this->layout = 'ajax';
        $this->loadModel("UruguaiCidade");

        $dados = $this->UruguaiCidade->find("all", [
            "conditions" => [
                "UruguaiCidade.departamento_id" => $departamento_id
            ],
            "order" => [
                "UruguaiCidade.nome"
            ],
            "link" => []
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));
    }
}