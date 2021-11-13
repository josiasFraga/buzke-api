<?php 
class Sugestao extends AppModel {
    public $useTable = 'sugestoes';

    public $name = 'Sugestao';

    public $belogsTo = array(
      'Usuario' => array(
        'foreignKey' => 'usuario_id'
      ),
    );
    
    public $validate = array();

}
