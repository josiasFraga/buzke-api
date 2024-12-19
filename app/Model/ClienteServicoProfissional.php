<?php 
class ClienteServicoProfissional extends AppModel {
    public $useTable = 'clientes_servicos_profissionais';

    public $belongsTo = [
        'ClienteServico' => [
            'foreignKey' => 'cliente_servico_id'
        ],
        'Usuario' => [
            'foreignKey' => 'usuario_id'
        ]
    ];
    
    public $validate = [
        
    ];

}
