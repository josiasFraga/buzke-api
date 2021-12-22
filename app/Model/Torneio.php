<?php
class Torneio extends AppModel {
	public $useTable = 'torneios';

	public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		)
	);

	public $hasMany = array(
		'TorneioCategoria' => array(
			'foreignKey' => 'torneio_id'
        ),
		'TorneioData' => array(
			'foreignKey' => 'torneio_id'
		),
		'TorneioQuadra' => array(
			'foreignKey' => 'torneio_id'
        ),
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_id'
		)
	);
}