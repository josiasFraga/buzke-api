<?php
class ProdutoAdicional extends AppModel {
	public $useTable = 'produto_adicionais';
	public $belongsTo = array(
		'Produto' => array('foreignKey' => 'produto_id')
	);
	public $hasMany = array(
		'ClienteClienteComandaProdutoAdicional' => array('foreignKey' => 'produto_adicional_id')
	);

	public function listar($cliente_id, $aditional_conditions = []) {

		$conditions = [
			'Produto.cliente_id' => $cliente_id
		];

		$conditions = array_merge($conditions, $aditional_conditions);

		return $this->find('all', array(
			'conditions' => $conditions,
			'fields' => array(
				'ProdutoAdicional.*'
			),
			'order' => 'ProdutoAdicional.descricao',
            'link' => ['Produto']
		));
	}

	public function beforeSave($options = array()) {
		return true;
	}
}