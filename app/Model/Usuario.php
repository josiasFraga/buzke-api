<?php 
class Usuario extends AppModel {
    public $useTable = 'usuarios';

    public $name = 'Usuario';

    public $hasMany = array(
		'Token' => array(
			'foreignKey' => 'usuario_id'
		),
		'ClienteCliente' => array(
			'foreignKey' => 'usuario_id'
		),
        'UsuarioResetSenha' => [
			'foreignKey' => 'usuario_id'
        ]
    );

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );
    
    public $validate = array();

    public $actsAs = array(
		'Upload.Upload' => array(
			'img' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}usuarios", // {ONDE ARQ ESTA}{ENTRA}webroot{ENTRA}img{ENTRA}lotes
				'thumbnailSizes' => array(
                    'thumb' => '512x512',
				),
				'pathMethod' => 'flat',
				'nameCallback' => 'rename'
			)
		)
	);

    public function rename($field, $currentName, array $data, array $options) {
        $ext = pathinfo($currentName, PATHINFO_EXTENSION);
        $name = md5(uniqid(rand())).'.'.mb_strtolower($ext);
        return $name;
    }

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['senha']) && $this->data[$this->alias]['senha'] != '') {
            $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        }
        return true;
    }

    public function listUsuarios() {
        return $this->find('list', array(
            'fields' => array(
                'Usuario.id',
                'Usuario.nome'
            ),
            'conditions' => array(
                'Usuario.ativo' => 'Y',
                'Usuario.nivel !=' => 'admin'
            )
        ));
    }

	public function findIdsOneSingalAdmins() {
		return $this->find('list',array(
			'fields' => array(
				'Usuario.id', 
				'Usuario.notifications_id'
			),
			'conditions' => array(
				'Usuario.usuario_tipo_id' => array(2,3)
			)
		));
	}

}

?>