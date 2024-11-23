<?php
class NotificacaoConfiguracaoUsuario extends AppModel {
	public $useTable = 'notificacoes_configuracoes_usuario';

	public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
		),
	);
}
