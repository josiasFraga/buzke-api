<?php 
class ClienteServicoFoto extends AppModel {
    public $useTable = 'cliente_servico_fotos';

    public $belongsTo = array(
      'ClienteServico' => array(
        'foreignKey' => 'cliente_servico_id'
      ),
    );
    
    public $validate = array();

}
