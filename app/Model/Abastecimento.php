<?php 
class Abastecimento extends AppModel {
    public $useTable = 'abastecimentos';

    public $name = 'Abastecimento';

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
        if ( isset($this->data[$this->alias]['valor_arla']) ) {
            $this->data[$this->alias]['valor_arla'] = $this->currencyToFloat($this->data[$this->alias]['valor_arla']);
        }
        if ( isset($this->data[$this->alias]['litragem']) ) {
            $this->data[$this->alias]['litragem'] = floatval(str_replace(',', '.', $this->data[$this->alias]['litragem']));
        }
        if ( isset($this->data[$this->alias]['litragem_arla']) ) {
            $this->data[$this->alias]['litragem_arla'] = floatval(str_replace(',', '.', $this->data[$this->alias]['litragem_arla']));
        }
        return true;
    }

    public function calculoValorTotalAbastecimentoByViagemId($id = null) {
        if (is_null($id) || !is_numeric($id)) return (float) 0.00;
        $this->virtualFields['total'] = 'SUM(Abastecimento.valor)';
        $retorno = $this->find('first', array(
            'conditions' => array( 
                'Abastecimento.viagem_id' => $id,
            ),
            'fields' => array( 
                'Abastecimento.total',
            )
        ));
        unset($this->virtualFields['total']);
        return (float) $retorno['Abastecimento']['total'];
    }

	public function rename($field, $currentName, array $data, array $options) {
        $ext = pathinfo($currentName, PATHINFO_EXTENSION);
        $name = md5(uniqid(rand())).'.'.mb_strtolower($ext);
        return $name;
    }

}

?>