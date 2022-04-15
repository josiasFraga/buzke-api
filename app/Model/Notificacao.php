<?php
class Notificacao extends AppModel {
	public $useTable = 'notificacoes';

	public $hasMany = array(
		'NotificacaoUsuario' => array(
			'foreignKey' => 'notificacao_id'
		),
	);

    public function getByTokens( $tokens = [] ){
        if ( count($tokens) == 0 ){
            return [];
        }

        return $this->find('all',[
            'fields' => [
                'Notificacao.id',
                'Notificacao.created',
                'Notificacao.title',
                'Notificacao.message',
                'Notificacao.read',
            ],
            'conditions' => [
                'NotificacaoUsuario.token' => $tokens
            ],
            'order' => [
                'Notificacao.id DESC'
            ],
            'group' => [
                'Notificacao.id'
            ],
            'link' => ['NotificacaoUsuario']
        ]);

    }

    public function countByTokens( $tokens = [] ){
        if ( count($tokens) == 0 ){
            return 0;
        }

        return $this->find('count',[
            'conditions' => [
                'NotificacaoUsuario.token' => $tokens
            ],
            'group' => [
                'Notificacao.id'
            ],
            'link' => ['NotificacaoUsuario']
        ]);

    }

    public function setRead( $type = 'one', $notification_id = null, $ids_one_singal = []) {
        if ( $type == 'one' ){

            if ( $notification_id == null ){
                return false;
            }


            return $this->save(
                [
                    'id' => $notification_id,
                    'read' => 'Y',
                    'read_on' => date('Y-m-d H:i:s'),
                ]
            );
        }
        else if ( $type == 'all' ){

            if ( count($ids_one_singal) == 0 ){
                return false;
            }

            $notifications_ids = $this->find('list',[
                'fields' => ['Notificacao.id', 'Notificacao.id'],
                'conditions' => ['NotificacaoUsuario.token' => $ids_one_singal],
                'link' => ['NotificacaoUsuario']
            ]);

            $notifications_ids = array_values($notifications_ids);

            foreach( $notifications_ids as $key_notificacao_id => $n_id){
                $this->save(
                    [
                        'id' => $n_id,
                        'read' => 'Y',
                        'read_on' => date('Y-m-d H:i:s'),
                    ]
                );

            }

            return true;
        } else {
            return false;
        }

    }
}
