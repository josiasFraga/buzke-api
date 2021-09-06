<?php
class Token extends AppModel {
	public $useTable = 'tokens';

    public $name = 'Token';

	public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
		)
	);
}