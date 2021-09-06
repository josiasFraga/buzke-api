<?php 
class ClienteClientePadelCategoria extends AppModel {
    public $useTable = 'clientes_clientes_padel_categorias';

    public $name = 'ClienteClientePadelCategoria';

    public $belongsTo = array(
      'ClienteCliente' => array(
        'foreignKey' => 'cliente_cliente_id'
      ),
      'PadelCategoria' => array(
        'foreignKey' => 'categoria_id'
      ),
    );

    public $validate = array();

}
