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

	public function findUsers($horario = null, $data = null, $usuario_id, $subcategorias) {
		if ( $horario == null || $data == null) {
			return [];
		}

		$dia_semana = date('w',strtotime($data));
		$dia_mes = (int)date('d',strtotime($data));

		return $this->find('all',[
			'fields' => ['*'],
			'conditions' => [
				'ToProJogoEsporte.subcategoria_id' => array_values($subcategorias),
				//'not' => ['Usuario.id' => $usuario_id],
				'or' => [
					[
						'ToProJogo.data_inicio <=' => $data,
						'ToProJogo.data_fim >=' => $data,
						'ToProJogo.hora_inicio <=' => $horario,
						'ToProJogo.hora_fim >=' => $horario,
					],
					
					[
						'ToProJogo.dia_semana' => $dia_semana,
						'ToProJogo.hora_inicio <=' => $horario,
						'ToProJogo.hora_fim >=' => $horario,
					],
					[
						'ToProJogo.dia_mes' => $dia_mes,
						'ToProJogo.hora_inicio <=' => $horario,
						'ToProJogo.hora_fim >=' => $horario,
					],

				]
			],
			'group' => ['ToProJogo.cliente_cliente_id'],
			'link' => ['ToProJogoEsporte', 'ClienteCliente' => ['Usuario' => ['UsuarioDadosPadel']]],
			'order' => ['ClienteCliente.nome'],
		]);

	}
}