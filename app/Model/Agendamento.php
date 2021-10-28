<?php 
class Agendamento extends AppModel {
    public $useTable = 'agendamentos';

    public $name = 'Agendamento';

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
		'ClienteServico' => array(
			'foreignKey' => 'servico_id'
        ),
    );

    public function verificaHorarios($horarios = [], $cliente_id = null, $data = null) {

        if ( count($horarios) == 0) {
            return [];
        }
        if ( $cliente_id == null ) {
            return [];
        }
        if ( $data == null ) {
            return [];
        }

        foreach( $horarios as $key => $horario ){

            $agendamentos_marcados = $this->find('all',[
                'fields' => [
                    'Agendamento.id',
                    'ClienteServico.*'
                ],
                'conditions' => [
                    'Agendamento.cliente_id' => $cliente_id,
                    'Agendamento.horario' => $data.' '.$horario['horario'],
                    'Agendamento.cancelado' => 'N'
                ],
                'Link' => [
                    'ClienteServico'
                ]
            ]);

            $n_agendamentos_marcados = count($agendamentos_marcados);

            if ( $n_agendamentos_marcados >= $horario['vagas'] ) {
                $horarios[$key]['enabled'] = false;
            } else {
                $horarios[$key]['enabled'] = true;
                $horarios[$key]['agendamentos_marcados'] = $agendamentos_marcados;

            }


        }

        return $horarios;

    }

    public function verificaAgendamento($cliente_cliente_id = null, $cliente_id = null, $data = null, $hora = null){
        if ( $cliente_cliente_id == null ) {
            return false;
        }
        if ( $data == null ) {
            return false;
        }
        if ( $hora == null ) {
            return false;
        }
        
        $conditions = [
            'Agendamento.cliente_cliente_id' => $cliente_cliente_id,
            'Agendamento.horario' => $data.' '.$hora,
            'Agendamento.cancelado' => 'N'
        ];

        if ( $cliente_id != null ) {
            $conditions = array_merge($conditions, [
                'Agendamento.cliente_id' => $cliente_id
            ]);
        }

        return $this->find('first',[
            'conditions' => $conditions
        ]);

    }

    public function nAgendamentosCliente($cliente_id = null, $data = null, $hora = null){
        if ( $cliente_id == null ) {
            return false;
        }
        if ( $data == null ) {
            return false;
        }
        if ( $hora == null ) {
            return false;
        }
        
        $conditions = [
            'Agendamento.cliente_id' => $cliente_id,
            'Agendamento.horario' => $data.' '.$hora,
            'Agendamento.cancelado' => 'N'
        ];


        return $this->find('count',[
            'conditions' => $conditions
        ]);

    }

}
