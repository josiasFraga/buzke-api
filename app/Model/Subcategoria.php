<?php 
class Subcategoria extends AppModel {
    public $useTable = 'subcategorias';

    public $name = 'Subcategoria';

    public $hasMany = array(
      'ClienteSubcategoria' => array(
        'foreignKey' => 'subcategoria_id'
      ),
      'ToProJogoEsporte' => array(
        'foreignKey' => 'subcategoria_id'
      ),
    );

    public $belongsTo = array(
      'Categoria' => array(
        'foreignKey' => 'categoria_id'
      ),
    );
    
    public $validate = array();

    public function buscaSubcategoriasQuadras($only_ids = false) {
      if ( !$only_ids ) {
        return $this->find('all',[
          'conditions' => [
            'Subcategoria.mostrar_no_to_pro_jogo' => 'Y'
          ],
          'link' => []
        ]);
      }
      return $this->find('list',[
        'fields' => [
          'Subcategoria.id'
        ],
        'conditions' => [
          'Subcategoria.mostrar_no_to_pro_jogo' => 'Y'
        ],
        'link' => []
      ]);

    }

}
