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
	);

	public $hasMany = array(
		'TorneioJogoPlacar' => array(
			'foreignKey' => 'torneio_jogo_id'
        ),
		'TorneioJogoPonto' => array(
			'foreignKey' => 'torneio_jogo_id'
        ),
	);

	public function buscaNVitorias($inscricao_id =  null, $fase = null) {
 
		if ( $inscricao_id == null ){
			return 0;
		}

		$conditions = [
			'TorneioJogo.time_1' => $inscricao_id,
			'TorneioJogoPlacar.time_1_placar > TorneioJogoPlacar.time_2_placar',
			'TorneioJogoPlacar.tipo' => ['Set']
		];

		if ( $fase != null ) {
			$conditions = array_merge($conditions, [
				'TorneioJogo.fase' => $fase
			]);
		}

		$vitorias_como_mandante = $this->find('count',[
			'conditions' => $conditions,
			'group' => ['TorneioJogoPlacar.torneio_jogo_id'],
			'link' => ['TorneioJogoPlacar']
		]);

		$conditions = [
			'TorneioJogo.time_2' => $inscricao_id,
			'TorneioJogoPlacar.time_2_placar > TorneioJogoPlacar.time_1_placar',
			'TorneioJogoPlacar.tipo' => ['Set']
		];

		if ( $fase != null ) {
			$conditions = array_merge($conditions, [
				'TorneioJogo.fase' => $fase
			]);
		}

		$vitorias_como_visitante = $this->find('count',[
			'conditions' => $conditions,
			'group' => ['TorneioJogoPlacar.torneio_jogo_id'],
			'link' => ['TorneioJogoPlacar']
		]);

		return $vitorias_como_mandante + $vitorias_como_visitante;

	}

	public function buscaNSets($inscricao_id =  null, $fase = null) {
 
		if ( $inscricao_id == null ){
			return 0;
		}

		$conditions = [
			'TorneioJogo.time_1' => $inscricao_id,
			'TorneioJogoPlacar.time_1_placar > TorneioJogoPlacar.time_2_placar',
			'TorneioJogoPlacar.tipo' => ['Set']
		];

		if ( $fase != null ) {
			$conditions = array_merge($conditions, [
				'TorneioJogo.fase' => $fase
			]);
		}

		$vitorias_como_mandante = $this->find('count',[
			'conditions' => $conditions,
			//'group' => ['TorneioJogoPlacar.torneio_jogo_id'],
			'link' => ['TorneioJogoPlacar']
		]);

		$conditions = [
			'TorneioJogo.time_2' => $inscricao_id,
			'TorneioJogoPlacar.time_2_placar > TorneioJogoPlacar.time_1_placar',
			'TorneioJogoPlacar.tipo' => ['Set']
		];

		if ( $fase != null ) {
			$conditions = array_merge($conditions, [
				'TorneioJogo.fase' => $fase
			]);
		}

		$vitorias_como_visitante = $this->find('count',[
			'conditions' => $conditions,
			//'group' => ['TorneioJogoPlacar.torneio_jogo_id'],
			'link' => ['TorneioJogoPlacar']
		]);

		return $vitorias_como_mandante + $vitorias_como_visitante;

	}

	public function buscaNGames($inscricao_id =  null, $fase = null) {
 
		if ( $inscricao_id == null ){
			return 0;
		}

		$conditions = [
			'TorneioJogo.time_1' => $inscricao_id,
			'TorneioJogoPlacar.tipo' => 'Set',
		];

		if ( $fase != null ) {
			$conditions = array_merge($conditions, [
				'TorneioJogo.fase' => $fase
			]);
		}

		$this->virtualFields['_saldo_games'] = '(SUM(TorneioJogoPlacar.time_1_placar) - SUM(TorneioJogoPlacar.time_2_placar))';
		$saldo_como_mandante = $this->find('first',[
			'fields' => ['TorneioJogo._saldo_games'],
			'conditions' => $conditions,
			'group' => [
				'TorneioJogo.time_1'
			],
			'link' => ['TorneioJogoPlacar']
		]);

		$conditions = [
			'TorneioJogo.time_2' => $inscricao_id,
			'TorneioJogoPlacar.tipo' => 'Set',
		];

		if ( $fase != null ) {
			$conditions = array_merge($conditions, [
				'TorneioJogo.fase' => $fase
			]);
		}
		unset($this->virtualFields['_saldo_games']);

		$this->virtualFields['_saldo_games'] = '(SUM(TorneioJogoPlacar.time_2_placar) - SUM(TorneioJogoPlacar.time_1_placar))';
		$saldo_como_visitante = $this->find('first',[
			'fields' => ['TorneioJogo._saldo_games'],
			'conditions' => $conditions,
			'group' => [
				'TorneioJogo.time_2'
			],
			'link' => ['TorneioJogoPlacar']
		]);


		unset($this->virtualFields['_saldo_games']);

		$saldo_como_mandante = isset($saldo_como_mandante['TorneioJogo']) && isset($saldo_como_mandante['TorneioJogo']['_saldo_games']) ? $saldo_como_mandante['TorneioJogo']['_saldo_games'] : 0;
		$saldo_como_visitante = isset($saldo_como_visitante['TorneioJogo']) && isset($saldo_como_visitante['TorneioJogo']['_saldo_games']) ? $saldo_como_visitante['TorneioJogo']['_saldo_games'] : 0;

		return $saldo_como_mandante + $saldo_como_visitante;

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