<?php 

class Viagem extends AppModel {
    public $useTable = 'viagens';

    public $name = 'Viagem';

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
        ),
        'Veiculo' => array(
			'foreignKey' => 'veiculo_id'
        )
    );

    public $hasMany = array(
        'Abastecimento' => array(
            'foreignKey' => 'viagem_id'
        ),
        'CargaDescarga' => array(
            'foreignKey' => 'viagem_id'
        ),
        'Despesa' => array(
            'foreignKey' => 'viagem_id'
        ),
        'Manutencao' => array(
            'foreignKey' => 'viagem_id'
        )
    );

    public $validate = array();

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['valor_frete']) ) {
            $this->data[$this->alias]['valor_frete'] = $this->currencyToFloat($this->data[$this->alias]['valor_frete']);
            if ( $this->data[$this->alias]['valor_frete'] > 0 ) {
                $this->data[$this->alias]['valor_comissao'] = (($this->data[$this->alias]['valor_frete']*12)/100);
            }
        }
        if ( isset($this->data[$this->alias]['valor_adiantamento']) ) {
            $this->data[$this->alias]['valor_adiantamento'] = $this->currencyToFloat($this->data[$this->alias]['valor_adiantamento']);
        }
        return true;
    }

    public function historicoViagensByUserId($id = null) {
        if (is_null($id) || !is_numeric($id)) return false;
        return $this->find('all', array(
            'contain' => array(
                'Veiculo'
            ),
            'fields' => array(
                'Viagem.*',
                'Veiculo.placa',
            ),
            'conditions' => array(
                'Viagem.ativo' => 'Y',
                'Viagem.is_finalizada' => 'Y',
                'Viagem.usuario_id' => $id,
            ),
            'order' => 'Viagem.data_viagem_ini DESC'
        ));
    }

    public function viagensInformacoesById($id = null) {
        if (is_null($id) || !is_numeric($id)) return false;
        return $this->find('first', array(
            'contain' => array(
                'Veiculo',
                'Abastecimento',
                'CargaDescarga',
                'Despesa',
                'Manutencao'
            ),
            'conditions' => array(
                'Viagem.id' => $id
            ),
            'fields' => array(
                '*',
            ),
        ));
    }

    public function validaIniciarViagem($id = null) {
        if (is_null($id) || !is_numeric($id)) return false;
        $busca = $this->find('all', array(
            'conditions' => array(
                'Viagem.usuario_id' => $id,
                'Viagem.is_finalizada' => 'N',
                'Viagem.data_viagem_fim' => null
            )
        ));
		if (count($busca) > 0) {
			return false;
		} else {
			return true;
		}
    }

}

?>