<?php 
class ClienteServico extends AppModel {
    public $useTable = 'clientes_servicos';

    public $name = 'ClienteServico';


    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );
    
    public $validate = array();



    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['valor']) && $this->data[$this->alias]['valor'] != '') {
            $this->data[$this->alias]['valor'] = $this->currencyToFloat($this->data[$this->alias]['valor']);
        }
        return true;
    }





}
