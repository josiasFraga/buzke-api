<?php
class TorneioInscricaoJogadorImpedimento extends AppModel {
	public $useTable = 'torneio_inscricao_jogador_impedimento';

	public $belongsTo = array(
		'TorneioInscricaoJogador' => array(
			'foreignKey' => 'torneio_inscricao_jogador_id'
        ),
	);

	public function countByPlayer( $player_id = null, $torneio_id = null ){
		if ( $player_id == null || $torneio_id == null )
			return 0;

		return $this->find('count',[
			'conditions' => [
				'TorneioInscricaoJogador.cliente_cliente_id' => $player_id,
				'TorneioInscricao.torneio_id' => $torneio_id,
			],
			'link' => ['TorneioInscricaoJogador' => ['TorneioInscricao']]
		]);
	}

	public function countByPlayerOtherSubscriptions( $player_id = null, $torneio_id = null, $inscricao_id = null ){
		if ( $player_id == null || $torneio_id == null )
			return 0;

		return $this->find('count',[
			'conditions' => [
				'TorneioInscricaoJogador.cliente_cliente_id' => $player_id,
				'TorneioInscricao.torneio_id' => $torneio_id,
				'not' => [
					'TorneioInscricao.id' => $inscricao_id
				]
			],
			'link' => ['TorneioInscricaoJogador' => ['TorneioInscricao']]
		]);
	}
}