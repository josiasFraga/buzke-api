<?php 

class UsuarioVeiculo extends AppModel {
    public $useTable = 'usuarios_has_veiculos';

    public $name = 'UsuarioVeiculo';
    
    public $validate = array();

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
        ),
        'Veiculo' => array(
            'foreignKey' => 'veiculo_id'
        )
    );

}

?>