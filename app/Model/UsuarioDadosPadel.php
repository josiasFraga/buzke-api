<?php 
class UsuarioDadosPadel extends AppModel {
    public $useTable = 'usuarios_dados_padel';

    public $name = 'UsuarioDadosPadel';

    public $belongsTo = array(
      'Usuario' => array(
        'foreignKey' => 'usuario_id'
      ),
    );

    public function findByUserId($user_id = null) {
      if ( $user_id == null ) {
        return [];
      }

      return $this->find('first',[
        'conditions' => [
          'UsuarioDadosPadel.usuario_id' => $user_id
        ],
        'link' => []
      ]);

    }

    public $validate = array();

}
