<?php 

class UsuarioResetSenha extends AppModel {
    public $useTable = 'usuario_reset_senha';

    public $name = 'UsuarioResetSenha';
    
    public $validate = array();

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
        )
    );

}
