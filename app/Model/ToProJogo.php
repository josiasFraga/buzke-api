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

	public function findUsers($horario = null, $data = null) {
		if ( $horario == null || $data == null) {
			return [];
		}

		$dia_semana = date('w',strtotime($data));
		$dia_mes = (int)date('d',strtotime($data));

		return $this->find('all',[
			'conditions' => [
				'or' => [
					[
						'ToProJogo.data_inicio <=' => $data,
						'ToProJogo.data_fim >=' => $data
					],
					
					['ToProJogo.dia_semana' => $dia_semana],
					['ToProJogo.dia_mes' => $dia_mes],

				]
			],
			'group' => ['ToProJogo.cliente_cliente_id'],
			'contain' => ['ClienteCliente' => ['Usuario']],
			'order' => ['ClienteCliente.nome'],
		]);

	}
}