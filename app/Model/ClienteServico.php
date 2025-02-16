<?php 
class ClienteServico extends AppModel {
    public $useTable = 'clientes_servicos';

    public $name = 'ClienteServico';
    
    public $virtualFields = array(
        'avg_avaliacao' => 'SELECT AVG(ClienteServicoAvaliacao.avaliacao) FROM cliente_servico_avaliacoes ClienteServicoAvaliacao WHERE ClienteServicoAvaliacao.cliente_servico_id = ClienteServico.id'
    );

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );

    public $hasMany = [
		'Agendamento' => [
			'foreignKey' => 'servico_id'
		],
		'TorneioQuadra' => [
			'foreignKey' => 'servico_id'
		],
		'ClienteServicoFoto' => [
			'foreignKey' => 'cliente_servico_id'
		],
		'ClienteServicoHorario' => [
			'foreignKey' => 'cliente_servico_id'
		],
		'ClienteServicoProfissional' => [
			'foreignKey' => 'cliente_servico_id'
		],
        'ClienteServicoAvaliacao' => [
			'foreignKey' => 'cliente_servico_id'
        ],
        'PromocaoServico' => [
			'foreignKey' => 'servico_id'
        ],
        'PromocaoVisita' => [
			'foreignKey' => 'servico_id'
        ],
        'ClienteServicoSubcategoria' => [
			'foreignKey' => 'cliente_servico_id'
        ]
    ];
    
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

        //agendamentos normais
        $dados_gendamento = $this->find('first',[
            'conditions' => [
                'ClienteServico.cliente_id' => $cliente_id,
                'ClienteServico.id' => $servico,
                'Agendamento.cancelado' => 'N',
                'Agendamento.torneio_id' => null,
                'Agendamento.horario' => $data_selecionada.' '.$hora_selecionada,
                'Agendamento.dia_semana' => null,
                'Agendamento.dia_mes' => null,
            ],
            'link' => [
                'Agendamento'
            ]
        ]);

        if ( count($dados_gendamento) >= 1 ) {
            return false;
        }

        //agendamentos fixos
        $ids_agendamentos_fixos_cancelados = $this->find('list',[
            'fields' => ['Agendamento.id', 'Agendamento.id'],
            'conditions' => [
                'ClienteServico.cliente_id' => $cliente_id,
                'ClienteServico.id' => $servico,
                'Agendamento.cancelado' => 'N',
                'Agendamento.torneio_id' => null,
                'Agendamento.horario <=' => $data_selecionada.' '.$hora_selecionada,
                'TIME(Agendamento.horario)' => $hora_selecionada,
                'or' => [
                    'Agendamento.dia_semana' => $dia_semana,
                    'Agendamento.dia_mes' => $dia_mes
                ],
                'AgendamentoFixoCancelado.horario' => $data_selecionada.' '.$hora_selecionada,
            ],
            'link' => [
                'Agendamento' => ['AgendamentoFixoCancelado']
            ]
        ]);

        $dados_gendamento = $this->find('first',[
            'conditions' => [
                'ClienteServico.cliente_id' => $cliente_id,
                'ClienteServico.id' => $servico,
                'Agendamento.cancelado' => 'N',
                'Agendamento.torneio_id' => null,
                'Agendamento.horario <=' => $data_selecionada.' '.$hora_selecionada,
                'TIME(Agendamento.horario)' => $hora_selecionada,
                'or' => [
                    'Agendamento.dia_semana' => $dia_semana,
                    'Agendamento.dia_mes' => $dia_mes
                ],
                'not' => [
                    'Agendamento.id' => $ids_agendamentos_fixos_cancelados,
                ]
            ],
            'link' => [
                'Agendamento'
            ]
        ]);

        /*if ( $data_selecionada == '2022-04-08' && $hora_selecionada == '17:00:00' && $servico == 50 ) {
            debug($ids_agendamentos_fixos_cancelados);
            debug($dados_gendamento);
            die();
        }*/

        if ( count($dados_gendamento) >= 1 ) {
            return false;
        }

        $dados_gendamento = $this->find('first',[
            'conditions' => [
                'ClienteServico.cliente_id' => $cliente_id,
                'ClienteServico.id' => $servico,
                'Agendamento.cancelado' => 'N',
                'Agendamento.horario' => $data_selecionada.' '.$hora_selecionada,
                'not' => [
                    'Agendamento.torneio_id' => null,
                ]
            ],
            'link' => [
                'TorneioQuadra' => [
                    'TorneioJogo' => [
                        'Agendamento'
                    ]
                ]
            ]
        ]);

        if ( count($dados_gendamento) >= 1 ) {
            return false;
        }
    
        return true;
        
    }

    public function getByClientId($cliente_id = null) {
        
        if ( $cliente_id == null )
            return [];
        
        $order = [
            'ClienteServico.nome'
        ];

        if ( $cliente_id == 55 ) {
            $order = [
                'ClienteServico.id'
            ];

        }

        return $this->find('all',[
            'conditions' => [
                'ClienteServico.cliente_id' => $cliente_id
            ],
            'order' => $order,
            'link' => []
        ]);
         
    }


}
