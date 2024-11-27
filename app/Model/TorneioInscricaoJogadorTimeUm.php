<?php
class TorneioInscricaoJogadorTimeUm extends AppModel {
	public $useTable = 'torneio_inscricao_jogadores';

	public $belongsTo = array(
		'TorneioJogoTimeUm' => array(
			'foreignKey' => 'torneio_inscricao_id'
        )
	);

	public $hasMany = array(
	);

}