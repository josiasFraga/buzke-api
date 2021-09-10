<?php
class Token extends AppModel {
	public $useTable = 'tokens';

    public $name = 'Token';

	public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
		)
	);

	public function getIdsNotificationsUsuario($usuario_id = null) {

		if ( $usuario_id == null )
			return [];
		
		$notifications_ids = $this->find('list',[
			'fields' => [
				'Token.id', 'Token.notification_id'
			],
			'conditions' => [
				'Usuario.id' => $usuario_id,
				'Token.data_validade >=' => date('Y-m-d') ,
				'not' => [
					'Token.notification_id' => null
				]
			],
			'link' => ['Usuario'],
			'group' => ['Token.notification_id']
		]);

		if ( count($notifications_ids) > 0 ) {
			return array_values($notifications_ids);
		}

		return [];		

	}

	public function getIdsNotificationsEmpresa($empresa_id = null) {

		if ( $empresa_id == null )
			return [];
		
		$notifications_ids = $this->find('list',[
			'fields' => [
				'Token.id', 'Token.notification_id'
			],
			'conditions' => [
				'Usuario.cliente_id' => $empresa_id,
				'Token.data_validade >=' => date('Y-m-d') ,
				'not' => [
					'Token.notification_id' => null
				]
			],
			'link' => ['Usuario'],
			'group' => ['Token.notification_id']
		]);

		if ( count($notifications_ids) > 0 ) {
			return array_values($notifications_ids);
		}

		return [];		

	}
}