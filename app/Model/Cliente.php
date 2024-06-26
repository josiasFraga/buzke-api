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

    public $actsAs = array(
		'Upload.Upload' => array(
			'logo' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}clientes", // {ONDE ARQ ESTA}{ENTRA}webroot{ENTRA}img{ENTRA}lotes
				'thumbnailSizes' => array(
                    'thumb' => '512x512',
				),
				'pathMethod' => 'flat',
				'nameCallback' => 'rename',
                'keepFilesOnDelete' => true,
			)
		)
	);

    public function rename($field, $currentName, array $data, array $options) {
        $ext = pathinfo($currentName, PATHINFO_EXTENSION);
        $name = md5(uniqid(rand())).'.'.mb_strtolower($ext);
        return $name;
    }

    public function beforeSave($options = array()) {
        /*if ( isset($this->data[$this->alias]['senha']) && $this->data[$this->alias]['senha'] != '') {
            $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        }*/
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
