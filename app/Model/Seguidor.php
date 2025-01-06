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
}
