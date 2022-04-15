<?php
class NotificacaoUsuario extends AppModel {
	public $useTable = 'notificacoes_usuarios';

	public $belongsTo = array(
		'Notificacao' => array(
			'foreignKey' => 'notificacao_id'
		),
	);
}