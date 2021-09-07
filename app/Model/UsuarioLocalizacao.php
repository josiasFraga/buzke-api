<?php 

class UsuarioLocalizacao extends AppModel {
    public $useTable = 'usuarios_localizacoes';

    public $name = 'UsuarioLocalizacao';
    
    public $validate = array();

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
        )
    );

}
