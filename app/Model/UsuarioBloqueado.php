<?php
App::uses('AppModel', 'Model');

class UsuarioBloqueado extends AppModel {
    public $name = 'UsuarioBloqueado';
    public $useTable = 'usuarios_bloqueados';

    // Relacionamentos
    public $belongsTo = [
        'UsuarioBloqueador' => [
            'className' => 'Usuario',
            'foreignKey' => 'usuario_bloqueador_id',
        ],
        'DadosUsuarioBloqueado' => [
            'className' => 'Usuario',
            'foreignKey' => 'usuario_bloqueado_id',
        ]
    ];

    public function checkIsBlocked($usuario_bloqueado_id = null, $usuario_bloqueador_id = null) {
        if ( empty($usuario_bloqueado_id) || empty($usuario_bloqueador_id) ) {
            return [
                'isBloqued' => false,
                'motive' => 'Usuário não informado'
            ];
        }

        if ( $usuario_bloqueado_id == $usuario_bloqueador_id ) {
            return [
                'isBloqued' => false,
                'motive' => 'Mesmo usuário informado'
            ];
        }

        $conditions = [
            'usuario_bloqueado_id' => $usuario_bloqueado_id,
            'usuario_bloqueador_id' => $usuario_bloqueador_id
        ];

        $bloqueado = $this->find('first', [
            'conditions' => $conditions,
            'link' => []
        ]);

        if ( empty($bloqueado) ) {
            return [
                'isBloqued' => false,
                'motive' => 'Usuário não está bloqueado'
            ];
        }
    
        return [
            'isBloqued' => true,
            'motive' => 'Usuário está bloqueado'
        ];

    }
}
