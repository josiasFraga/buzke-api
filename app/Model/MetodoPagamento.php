<?php 
class MetodoPagamento extends AppModel {
    public $useTable = 'financeiro_metodos_pagamento';

    public $belongsTo = array(
    );

    public $hasMany = array(
		'Cliente' => array(
			'foreignKey' => 'metodo_pagamento_id'
        )
    );

    public function beforeSave($options = array()) {
        // if ( isset($this->data[$this->alias]['senha']) ) {
        //     $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        // }
        return true;
    }

}

?>