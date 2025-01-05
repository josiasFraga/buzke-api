<?php
class TorneioInscricaoJogadorTimeDois extends AppModel {
	public $useTable = 'torneio_inscricao_jogadores';

	public $belongsTo = [
		'TorneioJogoTimeDois' => [
			'foreignKey' => 'torneio_inscricao_id'
        ],
		'ClienteClienteTimeDois' => [
			'className' => 'ClienteCliente',
			'foreignKey' => 'cliente_cliente_id',
			'dependent' => false,
		],
	];

}