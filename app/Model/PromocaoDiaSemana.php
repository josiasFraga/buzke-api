<?php
class PromocaoDiaSemana extends AppModel {
	public $useTable = 'promocao_dias_semana';
	public $belongsTo = [
		'Promocao' => [
			'foreignKey' => 'promocao_id'
		]
	];
}