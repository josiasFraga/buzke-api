<?php
class TorneioInscricaoJogador extends AppModel {
	public $useTable = 'torneio_inscricao_jogadores';

	public $belongsTo = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
	);
}