<?php
class Localidade extends AppModel {
	public $useTable = 'log_localidade';
	public $primaryKey = 'loc_nu_sequencial';

	public $hasMany = array(
		'Cliente' => array(
			'foreignKey' => 'cidade_id'
		),
		'ClienteCliente' => array(
			'foreignKey' => 'cidade_id'
		),
	);

	public $belgonsTo = array(
		'Uf' => array(
			'className' => 'Estado',
			'foreignKey' => 'ufe_sg'
		)
	);

	public function getByName($name=null) {
		if ( $name == null )
			return null;

		$dados_localidade = $this->find('first',[
			'conditions' => [
				'Localidade.loc_no' => $name
			]
		]);

		if ( count($dados_localidade) == 0 )
			return null;
		
		return $dados_localidade['Localidade']['loc_nu_sequencial'];

	}

	public function findByGoogleAddress($dados_localidade) {

		if ( is_array($dados_localidade) ) {
			$localidade_nome = trim($dados_localidade[0]);
			$localidade_uf = trim($dados_localidade[1]);
		} else if ( count(explode(',',$dados_localidade)) === 3) {
			list($localidade_nome, $localidade_uf) = explode(',',$dados_localidade); 
		} else {			
			return ['Localidade' => ['loc_nu_sequencial' => -500, 'ufe_sg' => 'RS']];
		}

		if ( $localidade_nome == "Sant'Ana do Livramento" ) {
			$localidade_nome = "Santana do Livramento";
		}

		$dados_localidade = $this->find('first',[
			'conditions' => [
				'Localidade.loc_no' => $localidade_nome,
				'Localidade.ufe_sg' => $localidade_uf,
			],
			'link' => []
		]);

		if ( count($dados_localidade) == 0 ) {
			if ( strpos($localidade_nome, '-') ) {
				list( $localidade_nome ) = explode('-',$localidade_nome);
			}
			if ( strpos($localidade_uf, '-') ) {
				list( $discard, $localidade_uf ) = explode('-',$localidade_uf);
			}
			$dados_localidade = $this->find('first',[
				'conditions' => [
					'Localidade.loc_no' => trim($localidade_nome),
					'Localidade.ufe_sg' => trim($localidade_uf),
				],
				'link' => []
			]);
			if ( count($dados_localidade) == 0 ) {
				return ['Localidade' => ['loc_nu_sequencial' => -500, 'ufe_sg' => 'RS']];
			} else{
				return $dados_localidade;
			}

		} else {
			return $dados_localidade;
		}

	}
}