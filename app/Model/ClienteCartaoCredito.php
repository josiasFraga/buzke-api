<?php 
class ClienteCartaoCredito extends AppModel {
    public $useTable = 'cliente_cartoes';

    public $hasMany = array(
    );

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );

    public function getByClientId($cliente_id = null) {

      return $this->find('all', [
        'fields' => [
          'ClienteCartaoCredito.id',
          'ClienteCartaoCredito.bandeira',
          'ClienteCartaoCredito.ultimos_digitos',
        ],
        'conditions' => [
          'ClienteCartaoCredito.cliente_id' => $cliente_id
        ]
      ]);
    }
}