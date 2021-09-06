<?php 
class Categoria extends AppModel {
    public $useTable = 'categorias';

    public $name = 'Categoria';

    public $hasMany = array(
      'Subcategoria' => array(
        'foreignKey' => 'categoria_id'
      ),
    );
    
    public $validate = array();

}
