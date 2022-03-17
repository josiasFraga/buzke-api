<?php
class TorneioGrupo extends AppModel {
	public $useTable = 'torneio_grupos';

	public $belongsTo = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
	);

	public $hasMany = array(
	);

	public function buscaGrupoByTeam( $team = null ) {

		if ( $team == null ) {
			return '';
		}

		$grupo = $this->find('first',[
			'conditions' => [
				'TorneioGrupo.torneio_inscricao_id' => $team
			]
		]);

		if ( count($grupo) == 0 ) {
			return '';
		}

		return $grupo['TorneioGrupo']['nome'];
		
	}
}