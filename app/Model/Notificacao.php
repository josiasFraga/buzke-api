<?php
class Notificacao extends AppModel {
	public $useTable = 'notificacoes';

	public $belongsTo = array(
		'NotificacaoMotivo' => array(
			'foreignKey' => 'notificacao_motivo_id'
		),
	);

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
                'Notificacao.registro_id',
                'Notificacao.notificacao_motivo_id',
                'Notificacao.agendamento_data_hora',
                'Notificacao.read',
                'Notificacao.usuario_origem',
                'NotificacaoMotivo.nome'
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
            'link' => ['NotificacaoUsuario', 'NotificacaoMotivo']
        ]);

    }

    public function getByUserId( $user_id = null ){
        if ( empty($user_id) ){
            return [];
        }

        return $this->find('all',[
            'fields' => [
                'Notificacao.id',
                'Notificacao.created',
                'Notificacao.title',
                'Notificacao.message',
                'Notificacao.registro_id',
                'Notificacao.registro_id',
                'Notificacao.notificacao_motivo_id',
                'Notificacao.agendamento_data_hora',
                'Notificacao.read',
                'Notificacao.acao_selecionada',
                'Notificacao.acao_selecionada_desc',
                'Notificacao.large_icon',
                'Notificacao.usuario_origem',
                'NotificacaoMotivo.nome'
            ],
            'conditions' => [
                'NotificacaoUsuario.usuario_id' => $user_id
            ],
            'order' => [
                'Notificacao.id DESC'
            ],
            'group' => [
                'Notificacao.id'
            ],
            'link' => ['NotificacaoUsuario', 'NotificacaoMotivo']
        ]);

    }

    public function countByTokens( $tokens = [] ){
        if ( count($tokens) == 0 ){
            return 0;
        }

        return $this->find('count',[
            'conditions' => [
                'NotificacaoUsuario.token' => $tokens,
                'Notificacao.read' => 'N'
            ],
            'group' => [
                'Notificacao.id'
            ],
            'link' => ['NotificacaoUsuario']
        ]);

    }

    public function countByUserId( $user_id = null ){
        if ( empty($user_id) ){
            return 0;
        }

        return $this->find('count',[
            'conditions' => [
                'NotificacaoUsuario.usuario_id' => $user_id,
                'Notificacao.read' => 'N'
            ],
            'group' => [
                'Notificacao.id'
            ],
            'link' => ['NotificacaoUsuario']
        ]);

    }

    public function setRead( $type = 'one', $notification_id = null, $user_id = null) {
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

            if ( empty($user_id) ){
                return false;
            }

            $notifications_ids = $this->find('list',[
                'fields' => ['Notificacao.id', 'Notificacao.id'],
                'conditions' => ['NotificacaoUsuario.usuario_id' => $user_id],
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

    public function getIdFromOneSginalId($one_signal_id = null) {
        if ( empty($one_signal_id) ) {
            return null;
        }

        $dados_notificacao = $this->find('first',[
            'conditions' => [
                'id_one_signal' => $one_signal_id
            ],
            'link' => []
        ]);

        if ( count($one_signal_id) === 0 ) {
            return null;
        }

        return $dados_notificacao['Notificacao']['id'];
    }
}
