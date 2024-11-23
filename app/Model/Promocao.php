<?php
class Promocao extends AppModel {
	public $useTable = 'promocoes';
	public $hasMany = [
		'PromocaoDiaSemana' => [
            'foreignKey' => 'promocao_id'
        ],
		'PromocaoServico' => [
            'foreignKey' => 'promocao_id'
        ],
		'PromocaoVisita' => [
            'foreignKey' => 'promocao_id'
        ],
		'PromocaoClique' => [
            'foreignKey' => 'promocao_id'
        ]
    ];
	public $belongsTo = array(
		'Cliente' => array('foreignKey' => 'cliente_id')
	);
	

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['valor_padrao']) && $this->data[$this->alias]['valor_padrao'] != '' && strpos($this->data[$this->alias]['valor_padrao'], 'R$') !== false) {
            $this->data[$this->alias]['valor_padrao'] = $this->currencyToFloat($this->data[$this->alias]['valor_padrao']);
        }
        if ( isset($this->data[$this->alias]['valor_fixos']) && $this->data[$this->alias]['valor_fixos'] != '' && strpos($this->data[$this->alias]['valor_fixos'], 'R$') !== false) {
            $this->data[$this->alias]['valor_fixos'] = $this->currencyToFloat($this->data[$this->alias]['valor_fixos']);
        }
        if ( isset($this->data[$this->alias]['validade_inicio']) && $this->data[$this->alias]['validade_inicio'] != '') {
            $this->data[$this->alias]['validade_inicio'] = $this->datetimeBrEn($this->data[$this->alias]['validade_inicio']);
        }
        if ( isset($this->data[$this->alias]['validade_fim']) && $this->data[$this->alias]['validade_fim'] != '') {
            $this->data[$this->alias]['validade_fim'] = $this->datetimeBrEn($this->data[$this->alias]['validade_fim']);
        }
        return true;
    }
}