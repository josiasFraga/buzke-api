<?php 
class ServicoVisita extends AppModel {
    public $useTable = 'cliente_servico_cliques';

    public $belogsTo = array(
      'ClienteServico' => array(
        'foreignKey' => 'servico_id'
      ),
      'Token' => array(
        'foreignKey' => 'usuario_id'
      ),
    );
    
    public $validate = array();

}
