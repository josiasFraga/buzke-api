<?php 
class ClienteServicoSubcategoria extends AppModel {
	public $useTable = 'cliente_servicos_subcategorias';

	public $belongsTo = [
		'ClienteServico' => [
			'foreignKey' => 'cliente_servico_id'
		],
	];
	
	public $validate = array();

}
