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

	public function busca_resultados($jogo_id = null, $finalizado = "Y") {

		if ( $jogo_id == null ) {
			return [];
		}

		return $this->find('all', [
			'conditions' => [
				'TorneioJogoPlacar.torneio_jogo_id' => $jogo_id,
				'TorneioJogo.finalizado' => $finalizado
			],
			'link' => [
				'TorneioJogo'
			]
		]);

	}

	public function tipo_set_em_aberto($jogo_id = null) {

		if ( $jogo_id == null ) {
			return null;
		}

		$check_opened_set = $this->find('first', [
			'conditions' => [
				'TorneioJogoPlacar.torneio_jogo_id' => $jogo_id,
				'TorneioJogoPlacar.finalizado' => 'N'
			],
			'link' => [
				'TorneioJogo'
			]
		]);

		if ( count($check_opened_set) == 0 ) {
			return null;
		}

		return $check_opened_set['TorneioJogoPlacar']['tipo'];

	}

	public function conta_vitorias( $resultados = [], $equipe ) {

		if ( count($resultados) == 0 ) {
			return 0;
		}

		$n_vitorias = 0;

		if ( $equipe == 1 ) {

			foreach ( $resultados as $key => $resultado ) { 
				if ( $resultado["TorneioJogoPlacar"]['finalizado'] == "Y" && $resultado["TorneioJogoPlacar"]['time_1_placar'] > $resultado["TorneioJogoPlacar"]['time_2_placar'] ) {
					$n_vitorias++;
				}
			}

		}

		else if ( $equipe == 2 ) {

			foreach ( $resultados as $key => $resultado ) { 
				if ( $resultado["TorneioJogoPlacar"]['finalizado'] == "Y" && $resultado["TorneioJogoPlacar"]['time_2_placar'] > $resultado["TorneioJogoPlacar"]['time_1_placar'] ) {
					$n_vitorias++;
				}
			}

		}

		return $n_vitorias;

	}

	public function busca_games( $resultados = [], $equipe ) {

		if ( count($resultados) == 0 ) {
			return 0;
		}

		$n_games = 0;

		if ( $equipe == 1 ) {

			foreach ( $resultados as $key => $resultado ) {
				if ( $resultado["TorneioJogoPlacar"]['finalizado'] == "N" ) {
					return $resultado["TorneioJogoPlacar"]['time_1_placar'];
				}
			}

		}

		else if ( $equipe == 2 ) {

			foreach ( $resultados as $key => $resultado ) {
				if ( $resultado["TorneioJogoPlacar"]['finalizado'] == "N" ) {
					return $resultado["TorneioJogoPlacar"]['time_2_placar'];
				}
			}

		}

		return $n_games;

	}

	public function busca_vencedor_por_jogo( $torneio_jogo_id = null ) {

		$resultados = $this->busca_resultados($torneio_jogo_id);

		if ( empty($resultados) ) {
			return null;
		}
	
		$vencedor_field = $this->busca_vencedor_por_resultados($resultados);

		if ( empty($vencedor_field) ) {
			return null;
		}

		return $vencedor_field;

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