<?php
class TorneioJogoPlacar extends AppModel {
	public $useTable = 'torneio_jogos_placares';

	public $belongsTo = array(
		'TorneioJogo' => array(
			'foreignKey' => 'torneio_jogo_id'
        ),
	);

	public $hasMany = array(
	);

	public function busca_resultados($jogo_id = null) {

		if ( $jogo_id == null ) {
			return [];
		}

		return $this->find('all', [
			'conditions' => [
				'TorneioJogoPlacar.torneio_jogo_id' => $jogo_id
			],
			'link' => []
		]);

	}

}