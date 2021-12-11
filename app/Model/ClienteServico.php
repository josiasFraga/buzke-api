<?php 
class ClienteServico extends AppModel {
    public $useTable = 'clientes_servicos';

    public $name = 'ClienteServico';

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );

    public $hasMany = array(
		'Agendamento' => array(
			'foreignKey' => 'servico_id'
		),
    );
    
    public $validate = array();

    public function modaArrayServicosIndisponiveis($horarios = [], $cliente_id = null) {


        if ( count($horarios) == 0 )
            return [];
        
        if ( $cliente_id == null )
            return [];

        foreach( $horarios as $key => $horario ) {
            $horarios[$key]['servicos_desativar'] = [];
            if (isset($horario['agendamentos_marcados']) && count($horario['agendamentos_marcados']) > 0){
                foreach($horario['agendamentos_marcados'] as $key_agendamento => $agendamento) {
                    $horarios[$key]['servicos_desativar'][] = $agendamento['ClienteServico']['id'];

                }
            }
            unset($horarios[$key]['agendamentos_marcados']);

        }

        return $horarios;
         
    }

    public function contaServicos($cliente_id = null) {
        
        if ( $cliente_id == null )
            return 0;

        return $this->find('count',[
            'conditions' => [
                'ClienteServico.cliente_id' => $cliente_id
            ]
        ]);
         
    }

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['valor']) && $this->data[$this->alias]['valor'] != '') {
            $this->data[$this->alias]['valor'] = $this->currencyToFloat($this->data[$this->alias]['valor']);
        }
        return true;
    }

    public function checkServiceIsAvaliableOnDateTime($cliente_id = null, $servico = null, $data_selecionada = null, $hora_selecionada = null) {

        if ( $cliente_id == null ) {
            return false;
        }

        if ( $servico == null ) {
            return false;
        }

        if ( $data_selecionada == null ) {
            return false;
        }

        if ( $hora_selecionada == null ) {
            return false;
        }

        $dia_semana = date('w',strtotime($data_selecionada.' '.$hora_selecionada));
        $dia_mes = (int)date('d',strtotime($data_selecionada.' '.$hora_selecionada));

        $dados_gendamento = $this->find('first',[
            'conditions' => [
                'ClienteServico.cliente_id' => $cliente_id,
                'ClienteServico.id' => $servico,
                'Agendamento.cancelado' => 'N',
                'or' => [
                    [
                        'Agendamento.horario' => $data_selecionada.' '.$hora_selecionada,
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => null,
                    ],
                    [
                        'Agendamento.horario >=' => $data_selecionada.' '.$hora_selecionada,
                        'or' => [
                            'Agendamento.dia_semana' => $dia_semana,
                            'Agendamento.dia_mes' => $dia_mes
                        ]

                    ]
                ]
            ],
            'link' => [
                'Agendamento'
            ]
        ]);

        if ( count($dados_gendamento) == 1 ) {
            return false;
        }
    
        return true;
        
    }



}
