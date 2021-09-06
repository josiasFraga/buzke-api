<?php 
class Cliente extends AppModel {
    public $useTable = 'clientes';

    public $name = 'Cliente';

    public $hasMany = array(
		'ClienteSubcategoria' => array(
			'foreignKey' => 'cliente_id'
		),
		'Usuario' => array(
			'foreignKey' => 'cliente_id'
		),
		'ClienteHorarioAtendimento' => array(
			'foreignKey' => 'cliente_id'
		),
		'ClienteHorarioAtendimentoExcessao' => array(
			'foreignKey' => 'cliente_id'
		),
		'Agendamento' => array(
			'foreignKey' => 'cliente_id'
		),
    );

    public $belongsTo = array(
		'Localidade' => array(
			'foreignKey' => 'cidade_id'
		),
		'Plano' => array(
			'foreignKey' => 'plano_id'
		),
    );
    
    public $validate = array();

    public $actsAs = array(
		'Upload.Upload' => array(
			'img' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}clientes", // {ONDE ARQ ESTA}{ENTRA}webroot{ENTRA}img{ENTRA}lotes
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
        /*if ( isset($this->data[$this->alias]['senha']) && $this->data[$this->alias]['senha'] != '') {
            $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        }*/
        return true;
    }



}
