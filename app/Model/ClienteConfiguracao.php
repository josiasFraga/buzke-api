<?php 
class ClienteConfiguracao extends AppModel {
    public $useTable = 'cliente_configs';

    public $name = 'ClienteConfiguracao';

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );
    
    public $validate = array();

    public function findByBusinessId($cliente_id = null) {

      if($cliente_id == null) {
        return false;
      }

      return $this->find('first',[
        'conditions' => [
          'ClienteConfiguracao.cliente_id' => $cliente_id
        ]
      ]);

    }
}
