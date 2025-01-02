<?php 
class Cliente extends AppModel {
    public $useTable = 'clientes';

    public $name = 'Cliente';

    public $hasMany = array(
		'ClienteSubcategoria' => array(
			'foreignKey' => 'cliente_id'
		),
		'Usuario' => array(
			'foreignKey' => 'cliente_id'
		),
		'ClienteHorarioAtendimento' => array(
			'foreignKey' => 'cliente_id'
		),
		'ClienteHorarioAtendimentoExcessao' => array(
			'foreignKey' => 'cliente_id'
		),
		'Agendamento' => array(
			'foreignKey' => 'cliente_id'
		),
		'ClienteServico' => array(
			'foreignKey' => 'cliente_id'
		),
		'Torneio' => array(
			'foreignKey' => 'cliente_id'
		),
		'ClienteCartaoCredito' => array(
			'foreignKey' => 'cliente_id'
		),
		'ClienteAssinatura' => array(
			'foreignKey' => 'cliente_id'
		),
		'ProdutoCategoria' => array(
			'foreignKey' => 'cliente_id'
		),
		'Mesa' => array(
			'foreignKey' => 'cliente_id'
		),
		'Comanda' => array(
			'foreignKey' => 'cliente_id'
		),
		'Pdv' => array(
			'foreignKey' => 'cliente_id'
		),
		'Promocao' => array(
			'foreignKey' => 'cliente_id'
		),
    );

    public $hasOne = array(
		'ClienteConfiguracao' => array(
			'foreignKey' => 'cliente_id'
		),
    );

    public $belongsTo = array(
		'Localidade' => array(
			'foreignKey' => 'cidade_id'
		),
		'Plano' => array(
			'foreignKey' => 'plano_id'
		),
		'MetodoPagamento' => array(
			'foreignKey' => 'metodo_pagamento_id'
		),
		'UruguaiCidade' => array(
			'foreignKey' => 'ui_cidade'
		),
		'UruguaiDepartamento' => array(
			'foreignKey' => 'ui_departamento'
		),
    );

	public $virtualFields = array(
        'avg_avaliacao' => 'SELECT AVG(avaliacao) FROM cliente_servico_avaliacoes WHERE cliente_servico_avaliacoes.cliente_servico_id IN (SELECT id FROM clientes_servicos WHERE clientes_servicos.cliente_id = Cliente.id)'
    );
    
    public $validate = array();

    // Método que será chamado para fazer o upload da imagem
    public function uploadImage($file) {
        $imageUploader = new ImageUploader();

        // Faz o upload da imagem para o S3
        $imageUrl = $imageUploader->uploadToS3($file, 'business', true);

        if ($imageUrl) {
            // Armazene a URL da imagem no banco de dados (ou qualquer outra ação)
            $this->data['Cliente']['logo'] = $imageUrl;
            return true;
        } else {
            // Retorne um erro caso o upload falhe
            return false;
        }
    }


    public function beforeSave($options = array()) {
        /*if ( isset($this->data[$this->alias]['senha']) && $this->data[$this->alias]['senha'] != '') {
            $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        }*/

        // Verifique se há uma imagem enviada
        if (!empty($this->data['Cliente']['logo'])) {
            $file = $this->data['Cliente']['logo'];
            // Faça o upload da imagem
            $this->uploadImage($file);
        }
        return true;
    }

	public function findEmpresaNomeById($cliente_id) {

		$dados_empresa = $this->find('first',[
			'fields' => ['Cliente.nome'],
			'conditions' => [
				'Cliente.id' => $cliente_id,
			],
			'link' => []
		]);

		if ( count($dados_empresa) == 0 )
			return '';
		
		return $dados_empresa['Cliente']['nome'];
	}

	public function findByCpf($cpf, $not_cliente_id = null) {

		$conidtions = [
			'Cliente.cpf' => $cpf,
		];

		if ( $not_cliente_id != null ) {
			$conidtions = array_merge($conidtions,[
				'not' => [
					'Cliente.id' => $not_cliente_id
				]
			]);
		}

		return $this->find('first',[
			'fields' => ['Cliente.nome'],
			'conditions' => $conidtions,
			'link' => []
		]);
	}

	public function findByCnpj($cnpj, $not_cliente_id = null) {

		$conidtions = [
			'Cliente.cnpj' => $cnpj,
		];

		if ( $not_cliente_id != null ) {
			$conidtions = array_merge($conidtions,[
				'not' => [
					'Cliente.id' => $not_cliente_id
				]
			]);
		}

		return $this->find('first',[
			'fields' => ['Cliente.nome'],
			'conditions' => $conidtions,
			'link' => []
		]);
	}

	public function findBySinatureId($signatureId = null) {

		if ( $signatureId == null ){
			return [];
		}

		$conditions = [
			'ClienteAssinatura.external_id' => $signatureId,
		];

		return $this->find('first',[
			'fields' => ['Cliente.id', 'Cliente.nome', 'ClienteAssinatura.id', 'ClienteAssinatura.status'],
			'conditions' => $conditions,
			'link' => ['ClienteAssinatura']
		]);
	}



}
