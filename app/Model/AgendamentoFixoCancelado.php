<?php 
class AgendamentoFixoCancelado extends AppModel {
    public $useTable = 'agendamentos_fixos_cancelados';

    public $name = 'AgendamentoFixoCancelado';

    public $belongsTo = array(
		'Agendamento' => array(
			'foreignKey' => 'agendamento_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
    );

   
    
}
