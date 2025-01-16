<?php 
class ClienteServicoHorario extends AppModel {
    public $useTable = 'clientes_servicos_horarios';

    public $belongsTo = array(
      'ClienteServico' => array(
        'foreignKey' => 'cliente_servico_id'
      ),
    );
    
    public $validate = array();

    public function listaDiasSemana($servico_id = null) {

        $dias_semana = [
            [
            "name" => "Dom",
            "active" => false
            ],
            [
            "name" => "Seg",
            "active" => false
            ],
            [
            "name" => "Ter",
            "active" => false
            ],
            [
            "name" => "Qua",
            "active" => false
            ],
            [
            "name" => "Qui",
            "active" => false
            ],
            [
            "name" => "Sex",
            "active" => false
            ],
            [
            "name" => "Sáb",
            "active" => false
            ],
        ];

        $dias_servico = array_values($this->find('list',[
        'fields'=> [
            'ClienteServicoHorario.dia_semana',
            'ClienteServicoHorario.dia_semana'
        ],
        'conditions' => [
            'ClienteServicoHorario.cliente_servico_id' => $servico_id
        ],
        'group' => [
            'ClienteServicoHorario.dia_semana'
        ]
        ]));

        foreach( $dias_semana as $key => $dia_semana ){
            if ( in_array($key, $dias_servico) ) {
                $dias_semana[$key]["active"] = true;
            }
	    }

	    return $dias_semana;
        
    }

    public function listaHorarios($servico_id = null, $dia = null) {
        if (is_null($servico_id) || is_null($dia)) {
            return []; // Retorna vazio se algum dos parâmetros não for fornecido
        }

        $dia_semana = (int)date('w', strtotime($dia));

        // Busca os horários para o serviço e o dia da semana correspondente
        $horarios = $this->find('all', [
            'conditions' => [
                'ClienteServicoHorario.cliente_servico_id' => $servico_id,
                'ClienteServicoHorario.dia_semana' => $dia_semana
            ],
            'link' => []
        ]);
    
        $intervalos = [];
        foreach ($horarios as $horario) {
            $inicio = DateTime::createFromFormat('H:i:s', $horario['ClienteServicoHorario']['inicio']);
            $fim = DateTime::createFromFormat('H:i:s', $horario['ClienteServicoHorario']['fim']);
            $duracao = DateTime::createFromFormat('H:i:s', $horario['ClienteServicoHorario']['duracao']);
    
            // Calcula o intervalo de tempo entre o início e o fim adicionando a duração
            while ($inicio <= $fim) {
                $fim_intervalo = clone $inicio;
                $fim_intervalo->add(new DateInterval('PT' . $duracao->format('H') . 'H' . $duracao->format('i') . 'M' . $duracao->format('s') . 'S'));
    
                if ($fim_intervalo <= $fim && $fim_intervalo <= $fim) { // Verifica se não excede o horário de fim

                    $promocao = $this->ClienteServico->PromocaoServico->find('first', [
                        'fields' => [
                            'Promocao.promocao_para_fixos',
                            'Promocao.promocao_para_padrao',
                            'Promocao.promocao_para_padrao',
                            'Promocao.valor_padrao',
                            'Promocao.valor_fixos'
                        ],
                        'conditions' => [
                            'PromocaoServico.servico_id' => $servico_id,
                            'PromocaoDiaSemana.dia_semana' => $dia_semana,
                            'Promocao.finalizada' => 'N',
                            ['OR' => [
                                [
                                    'Promocao.validade_ate_cancelar' => 'Y'
                                ],
                                [
                                    'Promocao.validade_inicio <=' => $dia . ' ' . $inicio->format('H:i:s'),
                                    'Promocao.validade_fim >=' => $dia . ' ' . $inicio->format('H:i:s'),
                                ]
                            ]],
                            ['OR' => [
                                [
                                    'Promocao.horario_inicio' => null,
                                    'Promocao.horario_fim' => null
                                ],
                                [
                                    'Promocao.horario_inicio <=' => $inicio->format('H:i:s'),
                                    'Promocao.horario_fim >=' => $inicio->format('H:i:s')
                                ]
                            ]]
        
                        ],
                        'link' => [
                            'Promocao' => [
                                'PromocaoDiaSemana'
                            ]
                        ],
                        'order' => [
                            'Promocao.id DESC'
                        ]
                    ]);
      
                    $intervalos[] = [
                        'label' => $inicio->format('H:i') . ' - ' . $fim_intervalo->format('H:i'),
                        'active' => true,
                        'default_value_old' => floatval($horario['ClienteServicoHorario']['valor_padrao']),
                        'fixed_value_old' => floatval($horario['ClienteServicoHorario']['valor_fixos']),
                        'default_value' => !empty($promocao['Promocao']) && $promocao['Promocao']['promocao_para_padrao'] === 'Y' ? floatval($promocao['Promocao']['valor_padrao']) : floatval($horario['ClienteServicoHorario']['valor_padrao']),
                        'fixed_value' => !empty($promocao['Promocao']) && $promocao['Promocao']['promocao_para_fixos'] === 'Y' ? floatval($promocao['Promocao']['valor_fixos']) : floatval($horario['ClienteServicoHorario']['valor_fixos']),
                        'time' => $inicio->format('H:i:s'),
                        'duration' => $duracao->format('H:i:s'),
                        //'vacancies_per_time' => $horario['ClienteServicoHorario']['vagas_por_horario'],
                        'at_home' => $horario['ClienteServicoHorario']['a_domicilio'] === '1' ? true : false,
                        'only_at_home' => $horario['ClienteServicoHorario']['apenas_a_domocilio'] === '1' ? true : false,
                        'enable_fixed_scheduling' => $horario['ClienteServicoHorario']['fixos'] === 'Y' ? true : false,
                        'fixed_type' => $horario['ClienteServicoHorario']['fixos_tipo'],
                        'have_promotion' => count($promocao) > 0
                    ];
                }
    
                $inicio = $fim_intervalo; // Move o início para o fim do intervalo atual
            }
        }
    
        return $intervalos; // Retorna os intervalos de horário calculados
    }
    
    public function checkStatus($agendamentos = []) {

        if ( count($agendamentos) == 0 ) {
            return [];
        }

        foreach($agendamentos as $key => $agendamento) {

            //se ja setou o status passa batido
            if ( isset($agendamento['Agendamento']['status']) ) {
                continue;
            }

            //se é um agendamento de torneio passa batido
            if ( isset($agendamento['Agendamento']['torneio_id']) && $agendamento['Agendamento']['torneio_id'] != null ) {
                $agendamentos[$key]['Agendamento']['status'] = 'confirmed';
                $agendamentos[$key]['Agendamento']['motive'] = '';
                continue;
            }

            list($data,$hora) = explode(' ',$agendamento['Agendamento']['horario']);

            $dados_horario_atendimento = $this->find('first',[
                'conditions' => [
                    'ClienteServico.cliente_id' => $agendamento['Agendamento']['cliente_id'],
                    'ClienteServicoHorario.dia_semana' => date('w',strtotime($data)),
                    'ClienteServicoHorario.inicio <=' => $hora,
                    'ClienteServicoHorario.fim >=' => $hora,
                ],
                'link' => ['ClienteServico']
            ]);

            if ( count($dados_horario_atendimento) > 0 ) {
                $agendamentos[$key]['Agendamento']['status'] = 'confirmed';
                $agendamentos[$key]['Agendamento']['motive'] = '';
            } else {
                $agendamentos[$key]['Agendamento']['status'] = 'cancelled';
                $agendamentos[$key]['Agendamento']['motive'] = 'A empresa não atende mais nesse período nesse dia da semana';

            }


        }

        return $agendamentos;

    }

    public function buscaRangeValores($servico_id = null, $dia_semana = null) {
        $conditions = [
            'cliente_servico_id' => $servico_id
        ];

         if ( $dia_semana !== null ) {
            $conditions['dia_semana'] = $dia_semana;
         }

         $result = $this->find('first', array(
            'fields' => array(
                'LEAST(MIN(COALESCE(valor_padrao, 999999999)), MIN(COALESCE(valor_fixos, 999999999))) AS min_valor',
                'GREATEST(MAX(COALESCE(valor_padrao, 0)), MAX(COALESCE(valor_fixos, 0))) AS max_valor',
            ),
            'link' => [],
            'conditions' => $conditions
        ));

        if ( count($result) === 0 ) {
            return null;
        }

        $minValor = $result[0]['min_valor'];
        $maxValor = $result[0]['max_valor'];

        return [$minValor, $maxValor];
    }

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['valor_padrao']) && $this->data[$this->alias]['valor_padrao'] != '') {
            $this->data[$this->alias]['valor_padrao'] = $this->currencyToFloat($this->data[$this->alias]['valor_padrao']);
        }
        if ( isset($this->data[$this->alias]['valor_fixos']) && $this->data[$this->alias]['valor_fixos'] != '') {
            $this->data[$this->alias]['valor_fixos'] = $this->currencyToFloat($this->data[$this->alias]['valor_fixos']);
        }
        return true;
    }

}
