<?php 
class PadelCategoria extends AppModel {
	public $useTable = 'padel_categorias';

	public $name = 'PadelCategoria';

	public $hasMany = array(
		'ClienteClientePadelCategoria' => array(
			'foreignKey' => 'categoria_id'
		),
		'UsuarioPadelCategoria' => array(
			'foreignKey' => 'categoria_id'
		),
		'TorneioCategoria' => array(
			'foreignKey' => 'categoria_id'
		),
		'EstatisticaPadel' => array(
			'foreignKey' => 'categoria_id'
		),
	);

	public $validate = array();

}
