<?php 
class AgendamentoClientecliente extends AppModel {
    public $useTable = 'agendamento_clientes_clientes';

    public $belongsTo = array(
		'Agendamento' => array(
			'foreignKey' => 'agendamento_id'
        ),
    );

    public function add($cliente_cliente_id = null, $agendamento_id = null) {
        $dados_salvar = [
            'agendamento_id' => $agendamento_id,
            'cliente_cliente_id' => $cliente_cliente_id,
        ];

        $this->create();
        $this->save($dados_salvar);
    }
}