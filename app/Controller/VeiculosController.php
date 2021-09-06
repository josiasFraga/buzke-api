<?php
class VeiculosController extends AppController {
    
    public $helpers = array('Html', 'Form');	
    public $components = array('RequestHandler');
    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function dados_motorista_caminhao() {
        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ((!isset($dados['token']) || $dados['token'] == "") ||  (!isset($dados['phone']) || $dados['phone'] == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ((!isset($dados['veiculo_id']) || $dados['veiculo_id'] == "" || !is_numeric($dados['veiculo_id']))) {
            throw new BadRequestException('Veículo não informada!', 400);
        }

        $usuario_token = $dados['token'];
        $usuario_phone = $dados['phone'];
        $veiculo_id = $dados['veiculo_id'];

        $dados_usuario = $this->verificaValidadeToken($usuario_token, $usuario_phone);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

		$this->loadModel('Veiculo');
        $dados_veiculo = $this->Veiculo->find('first', array(
			'conditions' => array(
                'Veiculo.usuario_id' => $dados_usuario['Usuario']['id'],
                'Veiculo.tipo_id' => 1,
                'Veiculo.id' => $veiculo_id
            ),
            'link' => []
        ));

        if (!$dados_veiculo) {
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'dados' => 'Os dados do veículo não foram encontrados!'))));
        }
        
        $dados_reboque = $this->Veiculo->find('first', array(
			'conditions' => array(
                'Veiculo.usuario_id' => $dados_usuario['Usuario']['id'],
                'not' => [
                    'Veiculo.tipo_id' => 1,
                ],
                'Veiculo.frota' => $dados_veiculo['Veiculo']['frota']
            ),
            'link' => []
        ));

        if (!$dados_reboque) {
			$placa_reboque = '';
        } else {
            $placa_reboque = $dados_reboque['Veiculo']['placa'];
        }

        $dados_retornar = [
            'kmL' => $this->calcula_km_l($dados_veiculo['Veiculo']['id'], $dados_usuario['Usuario']['id']),
            'placa' => $dados_veiculo['Veiculo']['placa'],
            'descricao' => $dados_veiculo['Veiculo']['descricao_tipo'],
            'placa_reboque' => $placa_reboque,
        ];        

		return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));

    }

    private function calcula_km_l($veiculo_id, $motorista_id) {
        return 'DUV';
    }
}