<?php
class TorneioJogo extends AppModel {
	public $useTable = 'torneio_jogos';

	public $belongsTo = array(
		'Agendamento' => array(
			'foreignKey' => 'agendamento_id'
        ),
	);

	public $hasMany = array(
	);
}