<?php 
class ClienteSubcategoria extends AppModel {
    public $useTable = 'cliente_subcategorias';

    public $name = 'ClienteSubcategoria';

    public $belongsTo = array(
      'Subcategoria' => array(
        'foreignKey' => 'subcategoria_id'
      ),
      'Cliente' => array(
        'foreignKey' => 'cliente_id'
      ),
    );
    
    public $validate = array();

}
