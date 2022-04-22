<?php
class TorneioQuadra extends AppModel {
	public $useTable = 'torneio_quadras';

	public $belongsTo = array(
		'Torneio' => array(
			'foreignKey' => 'torneio_id'
        ),
		'ClienteServico' => array(
			'foreignKey' => 'servico_id'
		)
	);

	public $hasMany = array(
		'TorneioQuadraPeriodo' => array(
			'foreignKey' => 'torneio_quadra_id'
        ),
		'TorneioJogo' => array(
			'foreignKey' => 'torneio_quadra_id'
        ),
	);

	public function getByTournamentId($torneio_id = null) {
		if ( $torneio_id == null ){
			return [];
		}

		$this->virtualFields['_quadra_nome'] = 'CONCAT_WS("", TorneioQuadra.nome, ClienteServico.nome)';

		$quadras = $this->find('all',[
			'fields' => [
				'TorneioQuadra.*',
			],
			'conditions' => [
				'TorneioQuadra.torneio_id' => $torneio_id,
				'not'=> [
					'TorneioQuadra.confirmado' => 'R'
				]
			],
			'link' => [
				'ClienteServico'
			]
		]);

		$quadras_retornar = [];

		if ( count($quadras) > 0 ) {

			foreach( $quadras as $key => $quadra){
				$quadras_retornar[] = $quadra['TorneioQuadra'];
			}

		}

		return $quadras_retornar;
	}
}