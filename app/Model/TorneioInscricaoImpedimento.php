<?php
class TorneioInscricaoImpedimento extends AppModel {
	public $useTable = 'torneio_inscricao_impedimentos';

	public $belongsTo = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
	);
}