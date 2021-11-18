<?php
class ToProJogoEsporte extends AppModel {
	public $useTable = 'to_pro_jogo_esportes';

    public $name = 'ToProJogoEsporte';

	public $belongsTo = array(
		'ToProJogo' => array(
			'foreignKey' => 'to_pro_jogo_id'
        ),
		'Subcategoria' => array(
			'foreignKey' => 'subcategoria_id'
		)
	);

	public $hasMany = array(
	);
}