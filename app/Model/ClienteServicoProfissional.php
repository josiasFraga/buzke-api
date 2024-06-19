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
        $hora = date('H:i:s',strtotime($horario));

        $n_agendamentos = $this->find('count',[
            'fields' => [
                'Agendamento.*'
            ],
            'conditions' => [
                'Agendamento.profissional_id' => $usuario_id,
                'Agendamento.cancelado' => 'N',
                'AgendamentoFixoCancelado.id' => null,
                'OR' => [
                    [
                        'Agendamento.horario' => $horario,
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => null
                    ],
                    [
                        'Agendamento.dia_semana' => $dia_semana,
                        'TIME(Agendamento.horario)' => $hora

                    ],
                    [
                        'Agendamento.dia_mes' => $dia_mes,
                        'TIME(Agendamento.horario)' => $hora
                    ]
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
        
        return $n_agendamentos == 0;
    }


}
