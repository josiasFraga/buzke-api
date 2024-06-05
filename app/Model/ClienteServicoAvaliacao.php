<?php 
class ClienteServicoAvaliacao extends AppModel {
    public $useTable = 'cliente_servico_avaliacoes';

    public $belongsTo = [
      'ClienteServico' => [
        'foreignKey' => 'cliente_servico_id'
      ],
      'Usuario' => [
        'foreignKey' => 'usuario_id'
      ],
    ];

}
