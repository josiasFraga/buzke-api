<?php
class TorneioCategoria extends AppModel {
	public $useTable = 'torneio_categorias';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
		)
	);

	public $hasMany = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_categoria_id'
		)
	);
}