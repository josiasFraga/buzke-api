<?php
class TorneioQuadraPeriodo extends AppModel {
	public $useTable = 'torneio_quadra_periodo';

	public $belongsTo = array(
		'TorneioQuadra' => array(
			'foreignKey' => 'torneio_quadra_id'
        ),
	);

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['inicio']) && $this->data[$this->alias]['inicio'] != '') {
            $this->data[$this->alias]['inicio'] = $this->datetimeBrEn($this->data[$this->alias]['inicio']).":00";
        }
        if ( isset($this->data[$this->alias]['fim']) && $this->data[$this->alias]['fim'] != '') {
            $this->data[$this->alias]['fim'] = $this->datetimeBrEn($this->data[$this->alias]['fim']).":00";
        }
        return true;
    }
}