<?php
class TorneioQuadra extends AppModel {
	public $useTable = 'torneio_quadras';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
        ),
		'ClienteServico' => array(
			'foreignKey' => 'servico_id'
		)
	);

	public $hasMany = array(
		'TorneioQuadraPeriodo' => array(
			'foreignKey' => 'torneio_quadra_id'
        ),
	);
}