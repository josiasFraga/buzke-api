<?php
class TorneioJogoTimeDois extends AppModel {
	public $useTable = 'torneio_inscricoes';

	public $hasMany = array(
		'TorneioInscricaoJogadorTimeDois' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
		'TorneioJogo' => array(
			'foreignKey' => 'time_2'
        ),
	);

    
}