<?php
class TorneioJogoSeguidor extends AppModel {
	public $useTable = 'torneio_jogo_seguidor';

	public $belongsTo = [
		'TorneioJogo' => [
			'foreignKey' => 'torneio_jogo_id'
        ],
		'TorneioInscricao' => [
			'foreignKey' => 'torneio_inscricao_id'
        ],
    ];

	public $hasMany = array(
	);

    public function isFollowing($usuario_id = null, $torneio_jogo_id = null, $torneio_inscricao_id = null) {

        if ( empty($usuario_id) ) {
            return false;
        }

        if ( empty($torneio_jogo_id) && empty($torneio_inscricao_id) ) {
            return false;
        }

        $conditions = [
            'TorneioJogoSeguidor.usuario_id' => $usuario_id
        ];

        if ( !empty($torneio_jogo_id) ) {
            $conditions['TorneioJogoSeguidor.torneio_jogo_id'] = $torneio_jogo_id;
        }

        if ( !empty($torneio_inscricao_id) ) {
            $conditions['TorneioJogoSeguidor.torneio_inscricao_id'] = $torneio_inscricao_id;
        }

        return $this->find('count', [
            'conditions' => $conditions,
            'link' => []
        ]) > 0;

    }

    public function findFollowers($torneio_jogo_id = null, $torneio_inscricao_id = null) {

        if ( empty($torneio_jogo_id) && empty($torneio_inscricao_id) ) {
            return [];
        }

        $conditions = [];

        if ( !empty($torneio_jogo_id) && !empty($torneio_inscricao_id) ) {
            $conditions = [
                'OR' => [
                    'TorneioJogoSeguidor.torneio_jogo_id' => $torneio_jogo_id,
                    'TorneioJogoSeguidor.torneio_inscricao_id' => $torneio_inscricao_id
                ]
            ];

        } else if ( !empty($torneio_jogo_id) ) {
            $conditions['TorneioJogoSeguidor.torneio_jogo_id'] = $torneio_jogo_id;
        } else if ( !empty($torneio_inscricao_id) ) {
            $conditions['TorneioJogoSeguidor.torneio_inscricao_id'] = $torneio_inscricao_id;
        }

        return array_values($this->find('list', [
            'fields' => [
                'TorneioJogoSeguidor.usuario_id',
                'TorneioJogoSeguidor.usuario_id'
            ],
            'conditions' => $conditions,
            'group' => [
                'TorneioJogoSeguidor.usuario_id'
            ],
            'link' => []
        ]));

    }

    
}