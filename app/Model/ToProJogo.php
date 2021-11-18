<?php
class ToProJogo extends AppModel {
	public $useTable = 'to_pro_jogo';

    public $name = 'ToProJogo';

	public $belongsTo = array(
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'UsuarioLocalizacao' => array(
			'foreignKey' => 'localizacao_id'
		)
	);

	public $hasMany = array(
		'ToProJogoEsporte' => array(
			'foreignKey' => 'to_pro_jogo_id'
		)
	);

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['data_inicio']) && $this->data[$this->alias]['data_inicio'] != '') {
            $this->data[$this->alias]['data_inicio'] = $this->dateBrEn($this->data[$this->alias]['data_inicio']);
        }
        if ( isset($this->data[$this->alias]['data_fim']) && $this->data[$this->alias]['data_fim'] != '') {
            $this->data[$this->alias]['data_fim'] = $this->dateBrEn($this->data[$this->alias]['data_fim']);
        }
        return true;
    }
}