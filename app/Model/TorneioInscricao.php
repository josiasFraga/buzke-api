<?php
class TorneioInscricao extends AppModel {
	public $useTable = 'torneio_inscricoes';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
		'TorneioCategoria' => array(
			'foreignKey' => 'torneio_categoria_id'
        ),
	);

	public $hasMany = array(
		'TorneioInscricaoImpedimento' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
	);
}