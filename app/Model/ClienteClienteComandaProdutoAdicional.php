<?php
class ClienteComandaProdutoAdicional extends AppModel {
	public $useTable = 'clientes_clientes_comanda_produto_adicionais';
	public $belongsTo = array(
		'ClienteClienteComandaProduto' => array('foreignKey' => 'cliente_comanda_produto_id'),
		'ProdutoAdicional' => array('foreignKey' => 'produto_adicional_id'),
	);
}