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
}
