<?php 

class Veiculo extends AppModel {
    public $useTable = 'veiculos';

    public $name = 'Veiculo';

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
        )
    );

    public $hasMany = array(
        'Viagem' => array(
			'foreignKey' => 'veiculo_id'
        ),
        'VeiculoEixoPeneu' => array(
			'foreignKey' => 'veiculo_id'
        )
    );
    
    public $validate = array(
        'placa' => array(
            'required' => array(
                'rule' => array('notBlank'),
                'message' => 'Informe um placa.'
            )
        ),
        'img' => array(
            'rule' => array('isValidMimeType', array('image/jpeg', 'image/png')),
            'required' => false,
            'allowEmpty' => true,
            'message' => 'Arquivo inválido!'
        )
    );

    public $actsAs = array(
		'Upload.Upload' => array(
			'img' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}veiculos", // {ONDE ARQ ESTA}{ENTRA}webroot{ENTRA}img{ENTRA}lotes
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
        // if ( isset($this->data[$this->alias]['senha']) ) {
        //     $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        // }
        return true;
    }

    public function listVeiculos() {
        return $this->find('list', array(
            'fields' => array(
                'Veiculo.id',
                'Veiculo.placa',
            ),
            'conditions' => array(
                'Veiculo.ativo' => 'Y',
                // 'OR' => array(
                //     array('Veiculo.usuario_id !=' => null),
                //     array('Veiculo.usuario_id' => 0)
                // )
            )
        ));
    }

}

?>