<?php 

class Mesa extends AppModel {
    public $useTable = 'mesas';

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
        )
    );

	public function listar($cliente_id, $aditional_conditions = []) {

		$conditions = [
			'Mesa.cliente_id' => $cliente_id
		];

		$conditions = array_merge($conditions, $aditional_conditions);

		return $this->find('all', array(
			'conditions' => $conditions,
			'fields' => array(
				'Mesa.id',
				'Mesa.descricao'
			),
			'order' => 'Mesa.descricao',
            'link' => []
		));
	}

	public function buscaPorNome($cliente_id, $nome, $id=null) {
		$conditions = [
			'Mesa.cliente_id' => $cliente_id,
			'Mesa.descricao' => $nome
		];

		if ( $id != null ) {
			$conditions = array_merge([
				'not' => [
					'Mesa.id' => $id
				]
			],
			$conditions);
		}

		return $this->find('first',[
			'conditions' => $conditions
		]);

	}

	
}
