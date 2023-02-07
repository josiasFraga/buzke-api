<?php
class TorneioJogoPonto extends AppModel {
	public $useTable = 'torneio_jogos_pontos';

	public $belongsTo = array(
		'TorneioJogo' => array(
			'foreignKey' => 'torneio_jogo_id'
        ),
	);

	public $hasMany = array(
	);

	public function ultimo_placar($jogo_id = null) {

		if ( $jogo_id == null ) {
			return [];
		}

		return $this->find('first', [
			'conditions' => [
				'TorneioJogoPonto.torneio_jogo_id' => $jogo_id
			],
			'order' => [
				"TorneioJogoPonto.created DESC"
			],
			'link' => []
		]);

	}


}