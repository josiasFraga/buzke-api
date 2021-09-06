<?php 
class Plano extends AppModel {
    public $useTable = 'planos';

    public $belongsTo = array(
    );

    public $hasMany = array(
		'Cliente' => array(
			'foreignKey' => 'plano_id'
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