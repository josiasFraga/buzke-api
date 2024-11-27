<?php
class TorneioInscricaoJogadorTimeDois extends AppModel {
	public $useTable = 'torneio_inscricao_jogadores';

	public $belongsTo = array(
		'TorneioJogoTimeDois' => array(
			'foreignKey' => 'torneio_inscricao_id'
        )
	);

	public $hasMany = array(
	);

}