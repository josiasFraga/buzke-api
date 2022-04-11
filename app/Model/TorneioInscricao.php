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
	);

	public function checkSubscription($dados_cliente_cliente = [], $dados_torneio = [], $categoria_id = null) {

		if ( count($dados_cliente_cliente) == 0 || count($dados_torneio) == 0 || $categoria_id == null ){
			return false;
		}

		$torneio_id = $dados_torneio['Torneio']['id'];

		$first_check = $this->find('all',[
			'conditions' => [
				'TorneioInscricao.torneio_id' => $torneio_id,
				'TorneioInscricaoJogador.cliente_cliente_id' => $dados_cliente_cliente['ClienteCliente']['id'],
				'not' => [
					'TorneioInscricao.confirmado' => 'R'
				]
			],
			'link' => ['TorneioInscricaoJogador']
		]);

		if ( count($first_check) > 0 ) {

			foreach( $first_check as $key => $inscricao ) {

				if ( $inscricao['TorneioInscricao']['torneio_categoria_id'] == $categoria_id ) {
					return $inscricao;
				}

			}

			if ( $dados_torneio['Torneio']['max_inscricoes_por_jogador'] <= count($first_check) ) {
				return $first_check[0];
			}
		}

		if ($dados_cliente_cliente['ClienteCliente']['usuario_id'] == null) 
			return false;

		$second_check = $this->find('all',[
			'conditions' => [
				'TorneioInscricao.torneio_id' => $torneio_id,
				'ClienteCliente.usuario_id' => $dados_cliente_cliente['ClienteCliente']['usuario_id'],
				'not' => [
					'TorneioInscricao.confirmado' => 'R'
				]
			],
			'link' => ['TorneioInscricaoJogador' => ['ClienteCliente']]
		]);

		if ( count($second_check) > 0 ) {

			foreach( $second_check as $key => $inscricao ) {

				if ( $inscricao['TorneioInscricao']['torneio_categoria_id'] == $categoria_id ) {
					return $inscricao;
				}

			}

			if ( $dados_torneio['Torneio']['max_inscricoes_por_jogador'] <= count($second_check) ) {
				return $second_check[0];
			}

		}

		if ( count($second_check) > 0 )
			return $second_check;

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