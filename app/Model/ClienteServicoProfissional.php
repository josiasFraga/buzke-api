<?php 
class ClienteServicoProfissional extends AppModel {
    public $useTable = 'clientes_servicos_profissionais';

    public $belongsTo = [
        'ClienteServico' => [
            'foreignKey' => 'cliente_servico_id'
        ],
        'Usuario' => [
            'foreignKey' => 'usuario_id'
        ]
    ];
    
    public $validate = [
        
    ];

    public function verifica_disponibilidade($usuario_id, $horario) {

        $dia_semana = date('w',strtotime($horario));
        $dia_mes = (int)date('d',strtotime($horario));

        $agendamentos = $this->find('all',[
            'fields' => [
                '*'
            ],
            'conditions' => [
                'Agendamento.profissional_id' => $usuario_id,
                'Agendamento.cancelado' => 'N',
                'AgendamentoFixoCancelado.id' => null,
                'OR' => [
                    'Agendamento.horario' => $horario,
                    'Agendamento.dia_semana' => $dia_semana,
                    'Agendamento.dia_mes' => $dia_mes,
                ]
                
            ],
            'link' => [
                'Usuario' => [
                    'Agendamento' => [
                        'AgendamentoFixoCancelado'
                    ]
                ]
            ]
        ]);
        
        return count($agendamentos) == 0;
    }


}
