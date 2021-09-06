<?php 
class PeneuHistorico extends AppModel {
    public $useTable = 'pneu_historicos';

    public $belongsTo = array(
		'Peneu' => array(
			'foreignKey' => 'pneu_id'
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