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
}