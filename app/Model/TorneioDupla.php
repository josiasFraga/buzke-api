<?php 
class TorneioDupla extends AppModel {
    public $useTable = 'clientes_clientes';

    public $name = 'TorneioDupla';

    public $hasMany = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'dupla_id'
		),
    );
}
