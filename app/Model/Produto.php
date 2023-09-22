<?php
class Produto extends AppModel {
	public $useTable = 'produtos';
	public $hasMany = array(
		//'ClienteComandaProduto' => array('foreignKey' => 'produto_id'),
		'ProdutoAdicional' => array('foreignKey' => 'produto_id'),
	);
	public $belongsTo = array(
		//'ClienteComandaProduto' => array('foreignKey' => 'produto_id'),
		'ProdutoCategoria' => array('foreignKey' => 'categoria_id'),
	);

	public $validate = array(

	);
	
	public function existeCodigoDuplicado($codigo, $clienteId, $registroId = null) {
		$conditions = array(
			'codigo' => $codigo,
			'cliente_id' => $clienteId
		);
	
		if ($registroId !== null) {
			$conditions['NOT']['id'] = $registroId;
		}
	
		return $this->find('count', array('conditions' => $conditions, 'link' => [])) > 0;
	}

	public function beforeSave($options = array()) {
		
		if( isset($this->data['Produto']['valor_custo']) && !empty($this->data['Produto']['valor_custo']) ) {
			$this->data['Produto']['valor_custo'] = $this->currencyToFloat($this->data['Produto']['valor_custo']);
		} 
		
		if ( isset($this->data['Produto']['valor_venda']) && !empty($this->data['Produto']['valor_venda']) ){
			$this->data['Produto']['valor_venda'] = $this->currencyToFloat($this->data['Produto']['valor_venda']);
		}
	}

	public function listar($cliente_id, $aditional_conditions = []) {

		$conditions = [
			'Produto.cliente_id' => $cliente_id
		];

		$conditions = array_merge($conditions, $aditional_conditions);

		return $this->find('all', array(
			'conditions' => $conditions,
			'fields' => array(
				'Produto.*'
			),
			'order' => 'Produto.descricao',
            'link' => []
		));
	}

	public function buscaPorNome($cliente_id, $nome, $id=null) {
		$conditions = [
			'Produto.cliente_id' => $cliente_id,
			'Produto.descricao' => $nome
		];

		if ( $id != null ) {
			$conditions = array_merge([
				'not' => [
					'Produto.id' => $id
				]
			],
			$conditions);
		}

		return $this->find('first',[
			'conditions' => $conditions
		]);

	}

	public function buscaPorCodigo($cliente_id, $codigo, $id=null) {
		$conditions = [
			'Produto.cliente_id' => $cliente_id,
			'Produto.codigo' => $codigo
		];

		if ( $id != null ) {
			$conditions = array_merge([
				'not' => [
					'Produto.id' => $id
				]
			],
			$conditions);
		}

		return $this->find('first',[
			'conditions' => $conditions
		]);

	}

	public function buscaPorId($cliente_id, $id=null) {
	
		$conditions = [
			'Produto.cliente_id' => $cliente_id,
			'Produto.id' => $id
		];

		return $this->find('first',[
			'conditions' => $conditions,
			'link' => []
		]);

	}
}