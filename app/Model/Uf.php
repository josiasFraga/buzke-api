<?php
class Uf extends AppModel {
	public $useTable = 'log_faixa_uf';
	public $primaryKey = 'ufe_sg';

	public $hasMany = array(
		'Localidade' => array(
			'foreignKey' => 'ufe_sg'
		),
		'ClienteCliente' => array(
			'foreignKey' => 'estado_id'
		)
	);
}