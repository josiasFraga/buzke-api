<?php
class ProdutoAdicional extends AppModel {
	public $useTable = 'produto_adicionais';
	public $belongsTo = array(
		'Produto' => array('foreignKey' => 'produto_id')
	);
	public $hasMany = array(
		'ClienteComandaProdutoAdicional' => array('foreignKey' => 'produto_adicional_id')
	);

	public function beforeSave($options = array()) {
	}
}