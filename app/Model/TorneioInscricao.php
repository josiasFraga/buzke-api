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
		/*'TorneioInscricaoImpedimento' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),*/
		'TorneioInscricaoJogador' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
		'TorneioGrupo' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
		'TorneioJogoSeguidor' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
	);

	public function checkSubscriptionInCategory($dados_cliente_cliente = [], $dados_torneio = [], $categoria_id = null) {

		if ( count($dados_cliente_cliente) == 0 || count($dados_torneio) == 0 || $categoria_id == null ){
			return false;
		}

		$torneio_id = $dados_torneio['Torneio']['id'];

		return $this->find('count',[
			'conditions' => [
				'TorneioInscricao.torneio_id' => $torneio_id,
				'TorneioInscricao.torneio_categoria_id' => $categoria_id,
				'or' => [
					[
						'TorneioInscricaoJogador.cliente_cliente_id' => $dados_cliente_cliente['ClienteCliente']['id']
					],
					[
						'ClienteCliente.usuario_id' => $dados_cliente_cliente['ClienteCliente']['usuario_id'],
						'not' => [
							'ClienteCliente.usuario_id' => null,
						]
					]
					
				],
				'not' => [
					'TorneioInscricao.confirmado' => 'R'
				]
			],
			'link' => ['TorneioInscricaoJogador' => ['ClienteCliente']]
		]);		

	}

	public function checkSubscriptionsLimit($dados_cliente_cliente = [], $dados_torneio = []) {

		if ( count($dados_cliente_cliente) == 0 || count($dados_torneio) == 0 ){
			return false;
		}

		$torneio_id = $dados_torneio['Torneio']['id'];

		$first_check = $this->find('count',[
			'conditions' => [
				'TorneioInscricao.torneio_id' => $torneio_id,
				'TorneioInscricaoJogador.cliente_cliente_id' => $dados_cliente_cliente['ClienteCliente']['id'],
				'ClienteCliente.usuario_id' => null,
				'not' => [
					'TorneioInscricao.confirmado' => 'R'
				]
			],
			'link' => ['TorneioInscricaoJogador' => ['ClienteCliente']]
		]);


		$second_check = 0;
		if ($dados_cliente_cliente['ClienteCliente']['usuario_id'] != null) {

			$second_check = $this->find('count',[
				'conditions' => [
					'TorneioInscricao.torneio_id' => $torneio_id,
					'ClienteCliente.usuario_id' => $dados_cliente_cliente['ClienteCliente']['usuario_id'],
					'not' => [
						'TorneioInscricao.confirmado' => 'R'
					]
				],
				'link' => ['TorneioInscricaoJogador' => ['ClienteCliente']]
			]);

		}

		if ( $dados_torneio['Torneio']['max_inscricoes_por_jogador'] <= $second_check+$first_check ) {
			return true;
		}

		return false;		

	}

    public function countSubscriptionsByCategory($categoria_id = null){
        if ( $categoria_id == null ){
            return false;
        }

        return $this->find('count',[
            'conditions' => [
                'TorneioInscricao.torneio_categoria_id' => $categoria_id,
				'not' => [
					'TorneioInscricao.confirmado' => 'R'
				]
            ]
        ]);

    }
}