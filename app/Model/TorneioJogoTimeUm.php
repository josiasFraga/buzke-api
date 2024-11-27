<?php
class TorneioJogoTimeUm extends AppModel {
	public $useTable = 'torneio_inscricoes';

	public $belongsTo = array(
	);

	public $hasMany = array(
		'TorneioInscricaoJogadorTimeUm' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
		'TorneioJogo' => array(
			'foreignKey' => 'time_1'
        ),
	);

    
}