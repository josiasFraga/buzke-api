<?php
class NotificacaoConfiguracaoUsuario extends AppModel {
	public $useTable = 'notificacoes_configuracoes_usuario';

	public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
		),
	);

	public function checkPermission( $usuario_id = null, $tipo = null )  {

		if ( $usuario_id == null || $tipo == null ) {
			return false;
		}

		$disabled = $this->find('first', [
			'conditions' => [
				'NotificacaoConfiguracaoUsuario.usuario_id' => $usuario_id,
				'NotificacaoConfiguracaoUsuario.'.$tipo => 0
			],
			'link' => []
		]);

		if ( count($disabled) > 0 ) {
			return false;
		}

		return true;

	}
}
