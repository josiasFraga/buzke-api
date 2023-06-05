<?php
class ProdutoCategoria extends AppModel {
	public $useTable = 'produtos_categorias';
	public $hasMany = array(
		'Produto' => array('foreignKey' => 'categoria_id')
	);
	public $belognsTo = array(
		'Cliente' => array('foreignKey' => 'cliente_id')
	);

	public function beforeSave($options = array()) {
	}

	public function listar($cliente_id, $aditional_conditions = []) {

		$conditions = [
			'ProdutoCategoria.cliente_id' => $cliente_id
		];

		$conditions = array_merge($conditions, $aditional_conditions);

		return $this->find('all', array(
			'conditions' => $conditions,
			'fields' => array(
				'ProdutoCategoria.id',
				'ProdutoCategoria.nome'
			),
			'order' => 'ProdutoCategoria.nome',
            'link' => []
		));
	}

	public function buscaPorNome($cliente_id, $nome, $id=null) {
		$conditions = [
			'ProdutoCategoria.cliente_id' => $cliente_id,
			'ProdutoCategoria.nome' => $nome
		];

		if ( $id != null ) {
			$conditions = array_merge([
				'not' => [
					'ProdutoCategoria.id' => $id
				]
			],
			$conditions);
		}

		return $this->find('first',[
			'conditions' => $conditions
		]);

	}
}