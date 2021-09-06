<?php 

class CargaDescarga extends AppModel {
    public $useTable = 'cargas_descargas';

    public $name = 'CargaDescarga';

    public $belongsTo = array(
		'Viagem' => array(
			'foreignKey' => 'viagem_id'
        )
    );
    
    public $validate = array();

    // public function beforeSave($options = array()) {
    //     if ( isset($this->data[$this->alias]['senha']) ) {
    //         $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
    //     }
    //     return true;
    // }

}

?>