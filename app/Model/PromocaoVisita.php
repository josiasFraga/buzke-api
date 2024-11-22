<?php 
class PromocaoVisita extends AppModel {
    public $useTable = 'promocao_cliques';

    public $belogsTo = array(
      'Promocao' => array(
        'foreignKey' => 'promocao_id'
      ),
      'ClienteServico' => array(
        'foreignKey' => 'servico_id'
      ),
      'Token' => array(
        'foreignKey' => 'usuario_id'
      ),
    );
    
    public $validate = array();

}
