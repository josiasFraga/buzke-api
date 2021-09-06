<?php 
class Subcategoria extends AppModel {
    public $useTable = 'subcategorias';

    public $name = 'Subcategoria';

    public $hasMany = array(
      'ClienteSubcategoria' => array(
        'foreignKey' => 'subcategoria_id'
      ),
    );

    public $belongsTo = array(
      'Categoria' => array(
        'foreignKey' => 'categoria_id'
      ),
    );
    
    public $validate = array();

}
