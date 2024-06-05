<?php
class Pesquisa extends AppModel {
	public $useTable = 'pesquisas';
	public $hasMany = array(

	);
	public $belongsTo = array(
		'Token' => array(
			'foreignKey' => 'token_id'
        ),
		'UsuarioLocalizacao' => array(
			'foreignKey' => 'usuario_localizacao_id'
		)
	);

}