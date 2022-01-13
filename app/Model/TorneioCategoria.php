<?php
class TorneioCategoria extends AppModel {
	public $useTable = 'torneio_categorias';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
        ),
		'PadelCategoria' => array(
			'foreignKey' => 'categoria_id'
		)
	);

	public $hasMany = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_categoria_id'
		)
	);

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['inscricoes_de']) && $this->data[$this->alias]['inscricoes_de'] != '') {
            $this->data[$this->alias]['inscricoes_de'] = $this->dateBrEn($this->data[$this->alias]['inscricoes_de']);
        }
        if ( isset($this->data[$this->alias]['inscricoes_ate']) && $this->data[$this->alias]['inscricoes_ate'] != '') {
            $this->data[$this->alias]['inscricoes_ate'] = $this->dateBrEn($this->data[$this->alias]['inscricoes_ate']);
        }
        if ( isset($this->data[$this->alias]['valor_inscricao']) && $this->data[$this->alias]['valor_inscricao'] != '') {
            $this->data[$this->alias]['valor_inscricao'] = $this->currencyToFloat($this->data[$this->alias]['valor_inscricao']);
        }
        return true;
    }
}