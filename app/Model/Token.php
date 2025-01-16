<?php
class Token extends AppModel {
	public $useTable = 'tokens';

    public $name = 'Token';

	public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
		)
	);

	public $hasMany = array(
		'UsuarioLocalizacao' => array(
			'foreignKey' => 'token_id'
		),
		'Pesquisa' => array(
			'foreignKey' => 'token_id'
		),
		'PromocaoVisita' => array(
			'foreignKey' => 'token_id'
		)
	);

	public function getIdsNotificationsUsuario($usuario_id = null) {

		if ( $usuario_id == null )
			return [];

		$notifications_ids = [];

		if ( !is_array($usuario_id) ){

			$notification_id = $this->getLastUserNotificationId($usuario_id);

			if ( $notification_id != "" ) {
				$notifications_ids[] = $notification_id;
			}

		} else {

			$usuario_id = array_unique($usuario_id);
			foreach( $usuario_id as $key => $us_id ){

				$notification_id = $this->getLastUserNotificationId($us_id);

				if ( $notification_id != "" ) {
					$notifications_ids[] = $notification_id;
				}
			}

		}

		return $notifications_ids;				

	}

	private function getLastUserNotificationId( $user_id = null ) {

		if ( $user_id == null )
			return "";

		$notification_data = $this->find('first',[
			'fields' => [
				'Token.id', 'Token.notification_id'
			],
			'conditions' => [
				'Usuario.id' => $user_id,
				'Token.data_validade >=' => date('Y-m-d') ,
				'not' => [
					['Token.notification_id' => null],
					['Token.notification_id' => "painel"]
				]
			],
			'link' => ['Usuario'],
			'order' => ['Token.data_validade DESC', 'Token.id'],
			//'group' => ['Token.notification_id']
		]);

		if ( count($notification_data) == 0 ){
			return "";
		}

		return $notification_data["Token"]["notification_id"];

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
					['Token.notification_id' => null],
					['Token.notification_id' => "painel"]
				]
			],
			'link' => ['Usuario'],
			//'group' => ['Token.notification_id'],//não usar, senão buga quando o cliente reloga usando um id antigo
			'order' => ['Token.id DESC'],
			//"limit" => 3,//não usar, senão buga quando o cliente reloga usando um id antigo
		]);

		if ( count($notifications_ids) > 0 ) {
			$notifications_ids = array_slice(array_unique(array_values($notifications_ids)), 0, 3);
			return $notifications_ids;
		}

		return [];		

	}
}