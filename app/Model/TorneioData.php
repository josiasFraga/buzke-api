<?php
class TorneioData extends AppModel {
	public $useTable = 'torneio_datas';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
		)
	);

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['data']) && $this->data[$this->alias]['data'] != '') {
            $this->data[$this->alias]['data'] = $this->dateBrEn($this->data[$this->alias]['data']);
        }
        if ( isset($this->data[$this->alias]['inicio']) && $this->data[$this->alias]['inicio'] != '') {
            $this->data[$this->alias]['inicio'] = $this->data[$this->alias]['inicio'].":00";
        }
        if ( isset($this->data[$this->alias]['fim']) && $this->data[$this->alias]['fim'] != '') {
            $this->data[$this->alias]['fim'] = $this->data[$this->alias]['fim'].":00";
        }
        if ( isset($this->data[$this->alias]['duracao_jogos']) && $this->data[$this->alias]['duracao_jogos'] != '') {
            $this->data[$this->alias]['duracao_jogos'] = $this->data[$this->alias]['duracao_jogos'].":00";
        }
        return true;
    }
}