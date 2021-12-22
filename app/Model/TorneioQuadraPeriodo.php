<?php
class TorneioQuadraPeriodo extends AppModel {
	public $useTable = 'torneio_quadra_periodo';

	public $belongsTo = array(
		'TorneioQuadra' => array(
			'foreignKey' => 'torneio_quadra_id'
        ),
	);
}