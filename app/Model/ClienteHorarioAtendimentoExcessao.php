<?php 

class ClienteHorarioAtendimentoExcessao extends AppModel {
    public $useTable = 'clientes_horarios_excessoes';

    public $name = 'ClienteHorarioAtendimentoExcessao';

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
        )
    );

    public function findExcessoes($cliente_id) {
        $execoes_abertura = $this->find('list',[
            'fields' => [
                'ClienteHorarioAtendimentoExcessao.id', 'ClienteHorarioAtendimentoExcessao.data'
            ],
            'conditions' => [
                'ClienteHorarioAtendimentoExcessao.cliente_id' => $cliente_id,
                'ClienteHorarioAtendimentoExcessao.data >=' => date('Y-m-d'),
                'ClienteHorarioAtendimentoExcessao.type' => 'A',
            ],
            'link' => []
        ]);
        $execoes_fechamento = $this->find('list',[
            'fields' => [
                'ClienteHorarioAtendimentoExcessao.id', 'ClienteHorarioAtendimentoExcessao.data'
            ],
            'conditions' => [
                'ClienteHorarioAtendimentoExcessao.cliente_id' => $cliente_id,
                'ClienteHorarioAtendimentoExcessao.data >=' => date('Y-m-d'),
                'ClienteHorarioAtendimentoExcessao.type' => 'F',
            ],
            'link' => []
        ]);

        return ['abertura' => array_values($execoes_abertura), 'fechamento' => array_values($execoes_fechamento)];
    }

    public function verificaExcessao($cliente_id, $data, $tipo) {
        return $this->find('first',[
            'conditions' => [
                'ClienteHorarioAtendimentoExcessao.cliente_id' => $cliente_id,
                'ClienteHorarioAtendimentoExcessao.data' => $data,
                'ClienteHorarioAtendimentoExcessao.type' => $tipo,
            ],
            'link' => []
        ]);

    }

    public function checkStatus($agendamentos = []) {

        if ( count($agendamentos) == 0 ) {
            return [];
        }

        $excessoes = $this->find('all',[
            'conditions' => [
                'ClienteHorarioAtendimentoExcessao.cliente_id' => $agendamentos[0]['Agendamento']['cliente_id'],
                'ClienteHorarioAtendimentoExcessao.data >=' => date('Y-m-d')
            ],
            'link' => []
        ]);

        if ( count($excessoes) == 0 ) {
            return $agendamentos;
        } 

        foreach($agendamentos as $key => $agendamento) {
            list($data,$hora) = explode(' ',$agendamento['Agendamento']['horario']);

            foreach( $excessoes as $key_excessao => $excessao) {

                if ( $excessao['ClienteHorarioAtendimentoExcessao']['data'] == $data && $excessao['ClienteHorarioAtendimentoExcessao']['type'] == 'F' ){
                    $agendamentos[$key]['Agendamento']['status'] = 'cancelled';
                    $agendamentos[$key]['Agendamento']['motive'] = 'A empresa não abrirá nesse dia';
                }

                if ( $excessao['ClienteHorarioAtendimentoExcessao']['data'] == $data && $excessao['ClienteHorarioAtendimentoExcessao']['type'] == 'A' ){
                    if ( $excessao['ClienteHorarioAtendimentoExcessao']['abertura'] <= $hora && $excessao['ClienteHorarioAtendimentoExcessao']['fechamento'] <= $hora) {
                        $agendamentos[$key]['Agendamento']['status'] = 'confirmed';
                        $agendamentos[$key]['Agendamento']['motive'] = 'A empresa abrirá excepcionalmente nesse dia';
                    }
                }

            }


        }

        return $agendamentos;

    }

	
}
