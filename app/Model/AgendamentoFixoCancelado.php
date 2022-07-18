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

    public function checkStatus($agendamentos = []) {

        if ( count($agendamentos) == 0 ) {
            return [];
        }
        foreach($agendamentos as $key => $agendamento) {

            if ( $agendamento['Agendamento']['dia_semana'] == '' && $agendamento['Agendamento']['dia_mes'] == '' ) {
                continue;
            }

            if ($agendamento['Agendamento']['horario'] < date('Y-m-d H:i:s')) {
                //unset($agendamentos[$key]);
                //continue;
            }

            $checa_cancelamento = $this->find('first',[
                'conditions' => [
                    'AgendamentoFixoCancelado.agendamento_id' => $agendamento['Agendamento']['id'],
                    'AgendamentoFixoCancelado.horario' => $agendamento['Agendamento']['horario'],
                ],
                'link' => []
            ]);

            if ( count($checa_cancelamento) > 0 ) {
                $agendamentos[$key]['Agendamento']['status'] = 'cancelled';
                if ( $checa_cancelamento['AgendamentoFixoCancelado']['cancelado_por'] == 'cliente_cliente' )
                    $agendamentos[$key]['Agendamento']['motive'] = 'Cancelado pelo usuário';
                if ( $checa_cancelamento['AgendamentoFixoCancelado']['cancelado_por'] == 'cliente' )
                    $agendamentos[$key]['Agendamento']['motive'] = 'Cancelado pela empresa';

            }


        }

        return $agendamentos;

    }

    public function nAgendamentosFixosCanceladosCliente($cliente_id = null, $data = null, $hora = null){
        if ( $cliente_id == null ) {
            return 0;
        }
        if ( $data == null ) {
            return 0;
        }
        if ( $hora == null ) {
            return 0;
        }

        return $this->find('count',[
            'conditions' => [
                'Agendamento.cliente_id' => $cliente_id,
                'AgendamentoFixoCancelado.horario' => $data.' '.$hora,
            ],
            'link' => ['Agendamento']
        ]);

    }

    public function cancelSheduling($sheduling_data = [], $cliente_cliente_id = null, $cancelado_por = 'cliente'){

        if ( count($sheduling_data) == 0 || $cliente_cliente_id == null )
            return false;

        $check_cancel = $this->find('first',[
            'conditions' => [
                'agendamento_id' => $sheduling_data['Agendamento']['id'],
                'horario' => $sheduling_data['Agendamento']['horario'],
            ], 
            'link' => []
        ]);

        if ( count($check_cancel) > 0 )
            return true;

        $dados_salvar = [
            'agendamento_id' => $sheduling_data['Agendamento']['id'],
            'cliente_cliente_id' => $cliente_cliente_id,
            'horario' => $sheduling_data['Agendamento']['horario'],
            'cancelado_por' => $cancelado_por,
            'motivo' => 'Torneio neste horário',
        ];

        $this->create();
        return $this->save($dados_salvar);
    }
   
    
}
