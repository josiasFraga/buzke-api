<?php
class ClienteClienteComanda extends AppModel {
	public $useTable = 'clientes_clientes_comandas';
    public $belongsTo = array(
        'ClienteCliente' => array('foreignKey' => 'cliente_cliente_id'),
        'Comanda' => array('foreignKey' => 'comanda_id'),
        //'ClienteEndereco' => array('foreignKey' => 'cliente_endereco_id'),
        'Pdv' => array('foreignKey' => 'pdv_id'),
    );
    public $hasMany = array(
        'ClienteClienteComandaProduto' => array('foreignKey' => 'cliente_comanda_id'),
        //'Caixa' => array('foreignKey' => 'cliente_comanda_id'),
        'ClienteClienteComandaPedido' => array('foreignKey' => 'cliente_comanda_id')
    );
}