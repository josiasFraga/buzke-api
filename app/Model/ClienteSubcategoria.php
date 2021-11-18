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

    public function checkIsCourt($cliente_id=null) {
  
      if ($cliente_id == null){
        return false;
      }

      $isCourt = false;
      $check_subcategoria = $this->find('first',[
        'conditions' => [
          'ClienteSubcategoria.cliente_id' => $cliente_id,
          'Subcategoria.mostrar_no_to_pro_jogo' => 'Y',
        ],
        'link' => ['Subcategoria']
      ]);

      if (count($check_subcategoria) > 0)
        $isCourt = true;

      return $isCourt;

    }

    public function getArrIdsByBusinessId($cliente_id = null) {

      if($cliente_id == null) {
        return [];
      }

      return $this->find('list',[
        'fields' => ['ClienteSubcategoria.id','ClienteSubcategoria.id'],
        'conditions' => [
          'ClienteSubcategoria.cliente_id' => $cliente_id
        ],
        'link' => []
      ]);

    }

}
