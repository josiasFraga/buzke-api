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



}
