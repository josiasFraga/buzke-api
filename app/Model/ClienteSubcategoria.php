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

    public function checkIsPaddleCourt($dados_cliente = []) {
      
      if ( !isset($dados_cliente['ClienteSubcategoria']) || count($dados_cliente['ClienteSubcategoria']) == 0 ) {
        return false;
      }

      $paddle_court = false;
      foreach( $dados_cliente['ClienteSubcategoria'] as $subcategoria){
        if ( $subcategoria['subcategoria_id'] == 7 ) {
          $paddle_court = true;
        }
      }

      return $paddle_court;

    }

}
