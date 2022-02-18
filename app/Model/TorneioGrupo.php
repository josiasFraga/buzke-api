<?php
class TorneioGrupo extends AppModel {
	public $useTable = 'torneio_grupos';

	public $belongsTo = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
	);

	public $hasMany = array(
	);
}