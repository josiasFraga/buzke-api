<?php 
class AgendamentoConvite extends AppModel {
    public $useTable = 'agendamento_convites';

    public $name = 'AgendamentoConvite';

    public $belongsTo = array(
		'Agendamento' => array(
			'foreignKey' => 'agendamento_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
    );
    
}
