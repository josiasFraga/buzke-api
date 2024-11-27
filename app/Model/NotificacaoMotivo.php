<?php
class NotificacaoMotivo extends AppModel {
	public $useTable = 'notificacoes_motivos';

	public $hasMany = array(
		'Notificacao' => array(
			'foreignKey' => 'notificacao_motivo_id'
		),
	);
}