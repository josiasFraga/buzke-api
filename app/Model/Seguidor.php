<?php
App::uses('AppModel', 'Model');

class Seguidor extends AppModel {
    public $name = 'Seguidor';
    public $useTable = 'seguidores';

    // Relacionamentos
    public $belongsTo = [
        'UsuarioSeguidor' => [
            'className' => 'Usuario',
            'foreignKey' => 'usuario_seguidor_id',
        ],
        'UsuarioSeguido' => [
            'className' => 'Usuario',
            'foreignKey' => 'usuario_seguido_id',
        ]
    ];

    public function checkCanFollow($seguidor = null, $seguido = null) {
        if ( empty($seguidor) || empty($seguido) ) {
            return [
                'can' => false,
                'motive' => 'Usuário não informado'
            ];
        }

        if ( $seguidor == $seguido ) {
            return [
                'can' => false,
                'motive' => 'Usuário não pode seguir a si mesmo'
            ];
        }

        $conditions = [
            'usuario_seguidor_id' => $seguidor,
            'usuario_seguido_id' => $seguido
        ];

        $seguidor = $this->find('first', [
            'conditions' => $conditions,
            'link' => []
        ]);


        if ( empty($seguidor) || $seguidor['Seguidor']['deleted'] ) {
            return [
                'can' => true,
                'motive' => 'Usuário pode seguir este usuário'
            ];
        }

        if ( $seguidor['Seguidor']['status'] === 'ativo' ) {
            return [
                'can' => false,
                'can_remove' => true,
                'motive' => 'Usuário já segue este usuário'
            ];
        }

        if ( $seguidor['Seguidor']['status'] === 'bloqueado' ) {
            return [
                'can' => false,
                'motive' => 'Usuário bloqueado'
            ];
        }

        if ( $seguidor['Seguidor']['status'] === 'pendente' ) {
            return [
                'can' => false,
                'can_cancel' => true,
                'motive' => 'Usuário já solicitou seguir este usuário'
            ];
        }

        return [
            'can' => false,
            'motive' => 'Usuário não pode seguir este usuário'
        ];
    }
}
