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

	public function findUsers($horario = null, $data = null, $usuario_id, $subcategorias, $dados_empresa) {

		if ( $horario == null || $data == null) {
			return [];
		}

		$dia_semana = date('w',strtotime($data));
		$dia_mes = (int)date('d',strtotime($data));
		
		$conditions = [
			'ToProJogoEsporte.subcategoria_id' => array_values($subcategorias),
			["UsuarioLocalizacao.description like" => "%".$dados_empresa['Localidade']['loc_no']."%"],
			["UsuarioLocalizacao.description like" => "%".$dados_empresa['Localidade']['ufe_sg'].",%"],
			'not' => ['Usuario.id' => $usuario_id],
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
		];

		//$this->log($conditions, 'debug');

		$dados =  $this->find('all',[
			'fields' => ['*'],
			'conditions' => $conditions,
			'group' => ['ToProJogo.cliente_cliente_id'],
			'link' => ['ToProJogoEsporte', 'UsuarioLocalizacao', 'ClienteCliente' => ['Usuario' => ['UsuarioDadosPadel']]],
			'order' => ['ClienteCliente.nome'],
		]);
		//$this->log($dados, 'debug');
		return $dados;

	}

	public function buscaDisponibilidadeUsuario($subcategorias = [], $usuario_id = null) {

		$dados = $this->find('all', [
			'fields' => [
				'ToProJogo.id',
				'ToProJogo.dia_semana',
				'ToProJogo.hora_inicio',
				'ToProJogo.hora_fim'
			],
			'conditions' => [
				'ToProJogoEsporte.subcategoria_id' => $subcategorias,
				'ClienteCliente.usuario_id' => $usuario_id,
			],
			'link' => [
				'ToProJogoEsporte',
				'ClienteCliente'
			]
		]);

		if ( !empty($dados) ) {
			return array_map(function($dado) {
				return [
					'id' => $dado['ToProJogo']['id'],
					'dia_semana' => $dado['ToProJogo']['dia_semana'],
					'hora_inicio' => $dado['ToProJogo']['hora_inicio'],
					'hora_fim' => $dado['ToProJogo']['hora_fim'],
				];
			}, $dados);
		}

		return [];
	}
}