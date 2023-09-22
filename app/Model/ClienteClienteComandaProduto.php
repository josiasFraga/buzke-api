<?php
class ClienteClienteComandaProduto extends AppModel {
	public $useTable = 'clientes_clientes_comanda_produtos';
	public $belongsTo = array(
	    'ClienteClienteComandaPedido' => array('foreignKey' => 'cliente_comanda_pedido_id'),
		'ClienteClienteComanda' => array('foreignKey' => 'cliente_comanda_id'),
		'Mesa' => array('foreignKey' => 'mesa_id'),
		//'Atendente' => array('foreignKey' => 'atendente_id', 'className' => 'Usuario'),
		'Produto' => array('foreignKey' => 'produto_id'),
		//'Vendedor' => array('foreignKey' => 'vendedor_id'),
	);
	public $hasMany = array(
		'ClienteClienteComandaProdutoAdicional' => array('foreignKey' => 'cliente_comanda_produto_id')
	);

	public function beforeSave($options = array()) {
		if (isset($this->data['ClienteClienteComandaProduto']['valor_unitario']) && !empty($this->data['ClienteClienteComandaProduto']['valor_unitario'])) {
			$this->data['ClienteClienteComandaProduto']['valor_unitario'] = $this->currencyToFloat($this->data['ClienteClienteComandaProduto']['valor_unitario']);
		}

		if (isset($this->data['ClienteClienteComandaProduto']['valor_total']) && !empty($this->data['ClienteClienteComandaProduto']['valor_total'])) {
			$this->data['ClienteClienteComandaProduto']['valor_total'] = $this->currencyToFloat($this->data['ClienteClienteComandaProduto']['valor_total']);
		}

		if (isset($this->data['ClienteClienteComandaProduto']['desconto']) && !empty($this->data['ClienteClienteComandaProduto']['desconto'])) {
			$this->data['ClienteClienteComandaProduto']['desconto'] = $this->currencyToFloat($this->data['ClienteClienteComandaProduto']['desconto']);
		}

		if (isset($this->data['ClienteClienteComandaProduto']['valor_final']) && !empty($this->data['ClienteClienteComandaProduto']['valor_final'])) {
			$this->data['ClienteClienteComandaProduto']['valor_final'] = $this->currencyToFloat($this->data['ClienteClienteComandaProduto']['valor_final']);
		}

		if (isset($this->data['ClienteClienteComandaProduto']['mesa_id']) && empty($this->data['ClienteClienteComandaProduto']['mesa_id'])) {
			$this->data['ClienteClienteComandaProduto']['mesa_id'] = NULL;
		}

		if (isset($this->data['ClienteClienteComandaProduto']['observacoes']) && empty($this->data['ClienteClienteComandaProduto']['observacoes'])) {
			$this->data['ClienteClienteComandaProduto']['observacoes'] = NULL;
		}

		return true;
	}
}