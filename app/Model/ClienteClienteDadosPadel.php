<?php 
class ClienteClienteDadosPadel extends AppModel {
    public $useTable = 'clientes_clientes_dados_padel';

    public $name = 'ClienteClienteDadosPadel';

    public $belongsTo = array(

		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
    );

    public $validate = array();

}
