<?php
class TorneioJogo extends AppModel {
	public $useTable = 'torneio_jogos';

	public $belongsTo = array(
		'Agendamento' => array(
			'foreignKey' => 'agendamento_id'
        ),
		'TorneioCategoria' => array(
			'foreignKey' => 'torneio_categoria_id'
        ),
		'TorneioQuadra' => array(
			'foreignKey' => 'torneio_quadra_id'
        ),
		'TorneioJogoTimeUm' => array(
			'foreignKey' => 'time_1'
        ),
		'TorneioJogoTimeDois' => array(
			'foreignKey' => 'time_2'
        ),
	);

	public $hasMany = array(
		'TorneioJogoPlacar' => array(
			'foreignKey' => 'torneio_jogo_id'
        ),
		'TorneioJogoPonto' => array(
			'foreignKey' => 'torneio_jogo_id'
        ),
	);

	public function buscaNVitorias($inscricao_id = null, $fase = null) {
		if ($inscricao_id == null) {
			return 0;
		}
	
		$conditions = [
			'OR' => [
				'TorneioJogo.time_1' => $inscricao_id,
				'TorneioJogo.time_2' => $inscricao_id
			],
			'TorneioJogoPlacar.tipo' => ['Set', 'Tiebreak']
		];
	
		if ($fase !== null) {
			$conditions['TorneioJogo.fase'] = $fase;
		}
	
		$jogos = $this->find('all', [
			'conditions' => $conditions,
			'fields' => [
				'TorneioJogo.id',
				'TorneioJogo.time_1',
				'TorneioJogo.time_2',
				'TorneioJogoPlacar.tipo',
				'TorneioJogoPlacar.time_1_placar',
				'TorneioJogoPlacar.time_2_placar'
			],
			'link' => ['TorneioJogoPlacar']
		]);
	
		$resultados = [];
	
		// Processar cada jogo para contar sets e tiebreaks
		foreach ($jogos as $jogo) {
			$idJogo = $jogo['TorneioJogo']['id'];
			$tipo = $jogo['TorneioJogoPlacar']['tipo'];
			$placarTime1 = $jogo['TorneioJogoPlacar']['time_1_placar'];
			$placarTime2 = $jogo['TorneioJogoPlacar']['time_2_placar'];
	
			if (!isset($resultados[$idJogo])) {
				$resultados[$idJogo] = [
					'sets_time_1' => 0, 
					'sets_time_2' => 0, 
					'tiebreak_vencedor' => null, 
					'time_1' => $jogo['TorneioJogo']['time_1'], 
					'time_2' => $jogo['TorneioJogo']['time_2']
				];
			}
	
			if ($tipo === 'Set') {
				if ($placarTime1 > $placarTime2) {
					$resultados[$idJogo]['sets_time_1']++;
				} else if ($placarTime2 > $placarTime1) {
					$resultados[$idJogo]['sets_time_2']++;
				}
			} else if ($tipo === 'Tiebreak') {
				$resultados[$idJogo]['tiebreak_vencedor'] = $placarTime1 > $placarTime2 ? 'time_1' : 'time_2';
			}
		}
	
		$vitorias = 0;
	
		// Avaliar os resultados acumulados para contar vitórias
		foreach ($resultados as $idJogo => $resultado) {
			$totalSetsTime1 = $resultado['sets_time_1'];
			$totalSetsTime2 = $resultado['sets_time_2'];
			$tiebreakVencedor = $resultado['tiebreak_vencedor'];
			$time_1 = $resultado['time_1'];
			$time_2 = $resultado['time_2'];
	
			if ($totalSetsTime1 == $totalSetsTime2 && $tiebreakVencedor !== null) {
				// Empate nos sets, decide pelo tiebreak
				if (($tiebreakVencedor === 'time_1' && $time_1 == $inscricao_id) || 
					($tiebreakVencedor === 'time_2' && $time_2 == $inscricao_id)) {
					$vitorias++;
				}
			} else {
				// Não houve empate nos sets, decide pelo maior número de sets vencidos
				if ($totalSetsTime1 > $totalSetsTime2 && $time_1 == $inscricao_id) {
					$vitorias++;
				} else if ($totalSetsTime2 > $totalSetsTime1 && $time_2 == $inscricao_id) {
					$vitorias++;
				}
			}
		}
	
		return $vitorias;
	}
	

	public function buscaSaldoSets($inscricao_id = null, $fase = null) {
		if ($inscricao_id == null) {
			return 0;
		}
	
		// Preparar condições base que serão utilizadas em ambas as consultas
		$baseConditions = [
			'TorneioJogoPlacar.tipo' => ['Set']
		];
	
		if ($fase != null) {
			$baseConditions['TorneioJogo.fase'] = $fase;
		}
	
		// Consulta única para contar tanto vitórias quanto derrotas
		$conditionsVitoria = array_merge($baseConditions, [
			'OR' => [
				['TorneioJogo.time_1' => $inscricao_id, 'TorneioJogoPlacar.time_1_placar > TorneioJogoPlacar.time_2_placar'],
				['TorneioJogo.time_2' => $inscricao_id, 'TorneioJogoPlacar.time_2_placar > TorneioJogoPlacar.time_1_placar']
			]
		]);
	
		$conditionsDerrota = array_merge($baseConditions,[
			'OR' => [
				['TorneioJogo.time_1' => $inscricao_id, 'TorneioJogoPlacar.time_1_placar < TorneioJogoPlacar.time_2_placar'],
				['TorneioJogo.time_2' => $inscricao_id, 'TorneioJogoPlacar.time_2_placar < TorneioJogoPlacar.time_1_placar']
			]
		]);
	
		// Realizar as consultas
		$vitorias = $this->find('count', ['conditions' => $conditionsVitoria, 'link' => ['TorneioJogoPlacar']]);
		$derrotas = $this->find('count', ['conditions' => $conditionsDerrota, 'link' => ['TorneioJogoPlacar']]);
	
		// Calculando o saldo de sets
		$saldoSets = $vitorias - $derrotas;
	
		return $saldoSets;
	}

	public function buscaNGames($inscricao_id = null, $fase = null) {
		if ($inscricao_id == null) {
			return 0;
		}
	
		// Define condições base e inclui a lógica da fase, se aplicável
		$baseConditions = ['TorneioJogoPlacar.tipo' => 'Set'];
		if ($fase !== null) {
			$baseConditions['TorneioJogo.fase'] = $fase;
		}
	
		// Prepara o campo virtual para calcular o saldo de games como mandante e visitante
		$this->virtualFields['_saldo_games_mandante'] = 'SUM(CASE WHEN TorneioJogo.time_1 = ' . $inscricao_id . ' THEN TorneioJogoPlacar.time_1_placar - TorneioJogoPlacar.time_2_placar ELSE 0 END)';
		$this->virtualFields['_saldo_games_visitante'] = 'SUM(CASE WHEN TorneioJogo.time_2 = ' . $inscricao_id . ' THEN TorneioJogoPlacar.time_2_placar - TorneioJogoPlacar.time_1_placar ELSE 0 END)';
	
		// Realiza a consulta agregando os resultados para mandante e visitante
		$resultado = $this->find('first', [
			'fields' => [
				'_saldo_games_mandante',
				'_saldo_games_visitante'
			],
			'conditions' => [
				$baseConditions,
				'OR' => [
					'TorneioJogo.time_1' => $inscricao_id,
					'TorneioJogo.time_2' => $inscricao_id,
				],
			],
			'link' => ['TorneioJogoPlacar']
		]);
	
		unset($this->virtualFields['_saldo_games_mandante'], $this->virtualFields['_saldo_games_visitante']);
	
		// Calcula e retorna o saldo total de games
		$saldoTotal = 0;
		if (!empty($resultado)) {
			$saldoTotal += $resultado['TorneioJogo']['_saldo_games_mandante'] + $resultado['TorneioJogo']['_saldo_games_visitante'];
		}
		
		return $saldoTotal;
	}

	public function checaJogoNoHorario($quadra_id = null, $horario = null) {
		if ( $quadra_id == null || $horario == null ) {
			return [];
		}

		$dados_jogo = $this->find('first',[
			'conditions' => [
				'Agendamento.horario' => $horario,
				'TorneioJogo.torneio_quadra_id' => $quadra_id
			],
			'link' => ['Agendamento']
		]);

		if ( count($dados_jogo) > 0 ) {
			return true;
		}
		
		return false;
	}
	
	public function getMatchesWithoutScore( $torneio_id = null, $torneio_categoria_id = null, $grupo = null ){

		if ( $torneio_id == null || $torneio_categoria_id == null ){
			return false;
		}

		$conditions = [
			'TorneioJogoPlacar.id' => null,
			'TorneioQuadra.torneio_id' => $torneio_id,
			'TorneioJogo.torneio_categoria_id' => $torneio_categoria_id
		];

		if ( $grupo != null ) {
			$conditions = array_merge($conditions, [
				'TorneioJogo.grupo' => $grupo
			]);
		}
		
		return $this->find('all',[
			'conditions' => $conditions,
			'link' => [
				'TorneioJogoPlacar',
				'TorneioQuadra'
			]
		]);
	}

	public function setTeams( $torneio_id = null, $torneio_categoria_id = null, $grupo_id = null, $jogo_id = null, $teams = [], $vencedor = null) {

		if ( $torneio_id == null || $torneio_categoria_id == null ) {
			return false;
		}

		if ( $grupo_id != null ) {

			if ( count($teams) == 0 ) {
				return false;
			}

			foreach( $teams as $key => $team ){
				$posicao = ($key+1);
				$dados_jogo = $this->find('first', [
					'conditions' => [
						'TorneioJogo.torneio_categoria_id' => $torneio_categoria_id,
						'TorneioQuadra.torneio_id' => $torneio_id,
						'or' => [
							[
								'TorneioJogo.time_1_grupo' => $grupo_id,
								'TorneioJogo.time_1_posicao' => $posicao,
							],
							[
								'TorneioJogo.time_2_grupo' => $grupo_id,
								'TorneioJogo.time_2_posicao' => $posicao,
							]
						]
					],
					'link' => ['TorneioQuadra']
				]);

				if ( count($dados_jogo) > 0 ) {

					if ( $dados_jogo['TorneioJogo']['time_2_grupo'] == $grupo_id && $dados_jogo['TorneioJogo']['time_2_posicao'] == $posicao ) {
						$dados_salvar = [
							'id' => $dados_jogo['TorneioJogo']['id'],
							'time_2' => $team['TorneioInscricao']['id'],
						];
					} else {
						$dados_salvar = [
							'id' => $dados_jogo['TorneioJogo']['id'],
							'time_1' => $team['TorneioInscricao']['id'],
						];
					}

					$this->set($dados_salvar);
					$this->save($dados_salvar);
				}
	
			}

			return true;

		} else {

			if ( $vencedor == null || $jogo_id == null ) {
				return false;
			}

			$dados_jogo = $this->find('first', [
				'conditions' => [
					'TorneioJogo.torneio_categoria_id' => $torneio_categoria_id,
					'TorneioQuadra.torneio_id' => $torneio_id,
					'or' => [
						'TorneioJogo.time_1_jogo' => $jogo_id,
						'TorneioJogo.time_2_jogo' => $jogo_id,
					]
				],
				'link' => ['TorneioQuadra']
			]);
			

			if ( count($dados_jogo) > 0 ) {

				if ( $dados_jogo['TorneioJogo']['time_1_jogo'] == $jogo_id ) {
					$dados_salvar = [
						'id' => $dados_jogo['TorneioJogo']['id'],
						'time_1' => $vencedor,
					];
				} else {
					$dados_salvar = [
						'id' => $dados_jogo['TorneioJogo']['id'],
						'time_2' => $vencedor,
					];
				}

				$this->set($dados_salvar);
				$this->save($dados_salvar);
			}

			return true;
		}

		return false;
	}

	public function getBySchedulingId($scheduling_id = null){

		if ( $scheduling_id == null ) {
			return [];
		}
	
		$this->virtualFields['_quadra_nome'] = 'CONCAT_WS("", TorneioQuadra.nome, ClienteServico.nome)';
		return $this->find('first',[
			'fields' => ['*'],
			'conditions' => [
				'TorneioJogo.agendamento_id' => $scheduling_id,
			],
			'link'=> ['TorneioQuadra' => ['ClienteServico']],
			'group'=> ['TorneioJogo.id']
		]);

	}

}