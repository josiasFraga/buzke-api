<?php
class ClienteClienteComandaPedido extends AppModel {
    public $useTable = 'clientes_clientes_comanda_pedidos';
    public $belongsTo = array(
        'ClienteClienteComanda' => array('foreignKey' => 'cliente_comanda_id'),
        'Usuario' => array('foreignKey' => 'finalizado_por'),
        //'ClienteEndereco' => array('foreignKey' => 'cliente_endereco_id'),
    );
    public $hasMany = array(
        'ClienteClienteComandaProduto' => array('foreignKey' => 'cliente_comanda_pedido_id'),
    );
}