<?php
class TorneioInscricao extends AppModel {
	public $useTable = 'torneio_inscricoes';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
		'TorneioCategoria' => array(
			'foreignKey' => 'torneio_categoria_id'
        ),
	);

	public $hasMany = array(
		'TorneioInscricaoImpedimento' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
	);

	public function getByClientClientId($cliente_cliente_id = null, $torneio_id = null) {
		if ( $cliente_cliente_id == null || $torneio_id == null ){
			return false;
		}

		return $this->find('first',[
			'conditions' => [
				'TorneioInscricao.torneio_id' => $torneio_id,
				'or' => [
					'TorneioInscricao.cliente_cliente_id' => $cliente_cliente_id,
					'TorneioInscricao.dupla_id' => $cliente_cliente_id
				]
			],
			'link' => []
		]);

	}
}