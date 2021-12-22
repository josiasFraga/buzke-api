<?php
class TorneioData extends AppModel {
	public $useTable = 'torneio_datas';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
		)
	);
}