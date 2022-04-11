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

	public function busca_vencedor_por_resultados($resultados = []) {

		if ( count($resultados) == 0 ) {
			return false;
		}

		$time_1_sets = 0;
		$time_2_sets = 0;
		$time_1_games = 0;
		$time_2_games = 0;

		foreach( $resultados as $key => $resultado ){
			$time_1_games += $resultado['TorneioJogoPlacar']['time_1_placar'];
			$time_2_games += $resultado['TorneioJogoPlacar']['time_2_placar'];
			if ( $resultado['TorneioJogoPlacar']['time_1_placar'] > $resultado['TorneioJogoPlacar']['time_2_placar'] ) {
				$time_1_sets++;
			}
			else if ( $resultado['TorneioJogoPlacar']['time_1_placar'] < $resultado['TorneioJogoPlacar']['time_2_placar'] ) {
				$time_2_sets++;
			}
		}

		$vencedor = '';
		if ( $time_1_sets == $time_2_sets ){
			if ( $time_1_games < $time_2_games ) {
				$vencedor = 'time_2';
			}
			else if ( $time_1_games > $time_2_games ) {
				$vencedor = 'time_1';
			} else {
				return false;
			}
		} else if ( $time_1_sets < $time_2_sets ){ 
			$vencedor = 'time_2';

		} else if ( $time_1_sets > $time_2_sets ) {
			$vencedor = 'time_1';
		} else {
			return false;
		}

		return $vencedor;

	}

}