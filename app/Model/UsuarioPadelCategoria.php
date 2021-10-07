<?php 
class UsuarioPadelCategoria extends AppModel {
    public $useTable = 'usuarios_padel_categorias';

    public $name = 'UsuarioPadelCategoria';

    public $belongsTo = array(
      'Usuario' => array(
        'foreignKey' => 'usuario_id'
      ),
      'PadelCategoria' => array(
        'foreignKey' => 'categoria_id'
      ),
    );

    public $validate = array();

    public function findByUserId($user_id = null) {
      if ( $user_id == null ) {
        return [];
      }

      return $this->find('all',[
        'conditions' => [
          'UsuarioPadelCategoria.usuario_id' => $user_id
        ],
        'link' => []
      ]);

    }

}
