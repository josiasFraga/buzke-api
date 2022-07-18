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

    public function findByUserId($user_id = null, $minified = false) {
      if ( $user_id == null ) {
        return [];
      }

      $categorias = $this->find('all',[
        'fields' => ['*'],
        'conditions' => [
          'UsuarioPadelCategoria.usuario_id' => $user_id
        ],
        'link' => [
          'PadelCategoria'
        ]
      ]);

      if ( !$minified ) {
        return $categorias;
      }

      $arr_categorias = [];
      if ( count($categorias) > 0 ){
        foreach( $categorias as $key => $categoria ){
          $arr_categorias[] = $categoria['PadelCategoria']['titulo'];
        }
      }

      return implode(',',$arr_categorias);

    }

    public function getFromUsers($users = []){
      if ( count($users) == 0 ) {
        return [];
      }

      foreach( $users as $key => $user ){
        $categorias = $this->findByUserId($user['Usuario']['id'],true);
        $users[$key]['UsuarioDadosPadel']['_categorias'] = $categorias;
      }

      return $users;
    }

}
