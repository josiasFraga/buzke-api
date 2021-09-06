<?php 

class Manutencao extends AppModel {
    public $useTable = 'manutencoes';

    public $name = 'Manutencao';

    public $belongsTo = array(
		'Viagem' => array(
			'foreignKey' => 'viagem_id'
        )
    );

	public $actsAs = array(
		'Upload.Upload' => array(
			'anexo' => array(
				'path' => ROOT.DS."app".DS."webroot".DS."img".DS."anexos",
				'thumbnailSizes' => array(
					'xvga' => '1024x768',
					'vga' => '640x480',
					'thumb' => '80x80'
				),
				'pathMethod' => 'flat',
				'nameCallback' => 'rename'
			)
		)
    );
    
    public $validate = array();

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['valor']) ) {
            $this->data[$this->alias]['valor'] = $this->currencyToFloat($this->data[$this->alias]['valor']);
        }
        if ( isset($this->data[$this->alias]['km']) ) {
            $this->data[$this->alias]['km'] = floatval(str_replace(',', '.', $this->data[$this->alias]['km']));
        }
        return true;
    }

    public function calculoValorTotalManutencaoByViagemId($id = null) {
        if (is_null($id) || !is_numeric($id)) return (float) 0.00;
        $this->virtualFields['total'] = 'SUM(Manutencao.valor)';
        $retorno = $this->find('first', array(
            'conditions' => array( 
                'Manutencao.viagem_id' => $id,
            ),
            'fields' => array( 
                'Manutencao.total',
            )
        ));
        unset($this->virtualFields['total']);
        return (float) $retorno['Manutencao']['total'];
    }

    public function historicoManutencoesByUserId($user_id) {
        return $this->find('all', array(
            'fields' => array(
                'Manutencao.*',
            ),
            'conditions' => array(
                'Viagem.usuario_id' => $user_id
            ),
            'link' => array(
                'Viagem'
            ),
            'order' => 'Manutencao.created DESC'
        ));
    }

	public function rename($field, $currentName, array $data, array $options) {
        $ext = pathinfo($currentName, PATHINFO_EXTENSION);
        $name = md5(uniqid(rand())).'.'.mb_strtolower($ext);
        return $name;
    }


}

?>