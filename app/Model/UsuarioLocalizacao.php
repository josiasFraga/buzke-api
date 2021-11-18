<?php 

class UsuarioLocalizacao extends AppModel {
    public $useTable = 'usuarios_localizacoes';

    public $name = 'UsuarioLocalizacao';
    
    public $validate = array();

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
        ),
		'Token' => array(
			'foreignKey' => 'token_id'
        )
    );
    
    public function findByUserIdAndData($usuario_id = null, $localizacao = []) {
        if ( $usuario_id == null || count($localizacao) == 0 ) {
            return [];
        }

        $string_localizacao = str_replace(' ', '', implode(',',array_map("trim",array_filter($localizacao))));
            

        return $this->find('first',[
            'conditions' => [
                'or' => [
                    'Token.usuario_id' => $usuario_id,
                    'UsuarioLocalizacao.usuario_id' => $usuario_id,
                ],
                
                "REPLACE(UsuarioLocalizacao.description, ' ','')" => $string_localizacao,
            ],
            'link' => ['Token'],
            'order' => ['UsuarioLocalizacao.id DESC']
        ]);

    }
}
