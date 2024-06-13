<?php
class SincronizacaoAgenda extends AppModel {
	public $useTable = 'sincronizacoes_agenda';
	public $hasMany = [];
	public $belognsTo = array(
		'Usuario' => array('foreignKey' => 'usuario_id')
	);
}