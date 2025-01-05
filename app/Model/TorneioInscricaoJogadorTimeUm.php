<?php
class TorneioInscricaoJogadorTimeUm extends AppModel {
	public $useTable = 'torneio_inscricao_jogadores';

	public $belongsTo = [
		'TorneioJogoTimeUm' => [
			'foreignKey' => 'torneio_inscricao_id'
        ],
		'ClienteClienteTimeUm' => [
			'className' => 'ClienteCliente',
			'foreignKey' => 'cliente_cliente_id',
			'dependent' => false,
		],
	];


}