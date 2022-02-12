<?php
class TorneioInscricao extends AppModel {
	public $useTable = 'torneio_inscricoes';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
        ),
		'TorneioCategoria' => array(
			'foreignKey' => 'torneio_categoria_id'
        ),
	);

	public $hasMany = array(
		'TorneioInscricaoImpedimento' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
		'TorneioInscricaoJogador' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
	);

	public function checkSubscription($dados_cliente_cliente = [], $torneio_id = null) {

		if ( count($dados_cliente_cliente) == 0 || $torneio_id == null ){
			return false;
		}


		$first_check = $this->find('first',[
			'conditions' => [
				'TorneioInscricao.torneio_id' => $torneio_id,
				'TorneioInscricaoJogador.cliente_cliente_id' => $dados_cliente_cliente['ClienteCliente']['id'],
				'not' => [
					'TorneioInscricao.confirmado' => 'R'
				]
			],
			'link' => ['TorneioInscricaoJogador']
		]);

		if ( count($first_check) > 0 )
			return $first_check;

		if ($dados_cliente_cliente['ClienteCliente']['usuario_id'] == null) 
			return false;

		$second_check = $this->find('first',[
			'conditions' => [
				'TorneioInscricao.torneio_id' => $torneio_id,
				'ClienteCliente.usuario_id' => $dados_cliente_cliente['ClienteCliente']['usuario_id'],
				'not' => [
					'TorneioInscricao.confirmado' => 'R'
				]
			],
			'link' => ['TorneioInscricaoJogador' => ['ClienteCliente']]
		]);

		if ( count($second_check) > 0 )
			return $second_check;

		return false;

		

	}
}