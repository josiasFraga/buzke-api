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

	
}
