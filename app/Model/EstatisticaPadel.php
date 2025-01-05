<?php 
class EstatisticaPadel extends AppModel {
    public $useTable = 'estatisticas_padel';

    public $belongsTo = [
		'Usuario' => [
			'foreignKey' => 'usuario_id'
        ],
        'PadelCategoria' => [
			'foreignKey' => 'categoria_id'
        ]
    ];

	
}
