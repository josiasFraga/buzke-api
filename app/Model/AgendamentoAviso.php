<?php 
class AgendamentoAviso extends AppModel {
    public $useTable = 'agendamento_avisos';

    public $name = 'AgendamentoAviso';

    public $belongsTo = array(
		'Agendamento' => array(
			'foreignKey' => 'agendamento_id'
        ),
    );
}