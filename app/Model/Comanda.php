<?php 

class Comanda extends AppModel {
    public $useTable = 'comandas';

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
        )
    );

    public $hasMany = array(
		'ClienteClienteComanda' => array(
			'foreignKey' => 'comanda_id'
        )
    );

	public function listar($cliente_id, $aditional_conditions = []) {

		$conditions = [
			'Comanda.cliente_id' => $cliente_id
		];

		$conditions = array_merge($conditions, $aditional_conditions);

		return $this->find('all', array(
			'conditions' => $conditions,
			'fields' => array(
				'Comanda.id',
				'Comanda.descricao'
			),
			'order' => 'Comanda.descricao',
            'link' => []
		));
	}

	public function buscaPorNome($cliente_id, $nome, $id=null) {
		$conditions = [
			'Comanda.cliente_id' => $cliente_id,
			'Comanda.descricao' => $nome
		];

		if ( $id != null ) {
			$conditions = array_merge([
				'not' => [
					'Comanda.id' => $id
				]
			],
			$conditions);
		}

		return $this->find('first',[
			'conditions' => $conditions,
			'link' => []
		]);

	}

	
}
