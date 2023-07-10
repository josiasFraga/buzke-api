<?php
class Pdv extends AppModel {
	public $useTable = 'pdvs';
	public $hasMany = array(
		'ClienteClienteComanda' => array(
			'foreignKey' => 'pdv_id'
		)
	);
	public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		)
	);

	public function listar($cliente_id = null) {
		return $this->find('all', array(
			'fields' => array(
				'Pdv.*',
			),
			'order' => 'Pdv.id',
            'conditions' => [
                'Pdv.cliente_id' => $cliente_id
            ],
            'link' => []
		));
	}
}