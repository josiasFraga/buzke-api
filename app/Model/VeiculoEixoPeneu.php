<?php 
class VeiculoEixoPeneu extends AppModel {
    public $useTable = 'veiculo_eixo_pneus';

    public $belongsTo = array(
		'Veiculo' => array(
			'foreignKey' => 'veiculo_id'
        )
    );

    public $hasMany = array(
        /*'Viagem' => array(
			'foreignKey' => 'veiculo_id'
        )*/
    );

    public function beforeSave($options = array()) {
        // if ( isset($this->data[$this->alias]['senha']) ) {
        //     $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        // }
        return true;
    }

}

?>