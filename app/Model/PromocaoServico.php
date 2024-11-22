<?php
class PromocaoServico extends AppModel {
	public $useTable = 'promocao_servicos';
	public $belongsTo = [
		'Promocao' => [
            'foreignKey' => 'promocao_id'
        ],
		'ClienteServico' => [
            'foreignKey' => 'servico_id'
        ]
    ];
}