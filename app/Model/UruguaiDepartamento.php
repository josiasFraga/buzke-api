<?php 
class UruguaiDepartamento extends AppModel {

    public $useTable = 'uruguai_departamentos';
    public $name = 'UruguaiDepartamento';

    public $hasMany = [
		'UruguaiCidade' => [
			'foreignKey' => 'departamento_id'
        ],
		'Cliente' => [
			'foreignKey' => 'ui_departamento'
        ]
    ];
}