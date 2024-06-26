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
    
        // Busca os horários para o serviço e o dia da semana correspondente
        $horarios = $this->find('all', [
            'conditions' => [
                'ClienteServicoHorario.cliente_servico_id' => $servico_id,
                'ClienteServicoHorario.dia_semana' => (int)date('w', strtotime($dia))
            ]
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
                    $intervalos[] = [
                        'label' => $inicio->format('H:i') . ' - ' . $fim_intervalo->format('H:i'),
                        'active' => true,
                        'time' => $inicio->format('H:i:s'),
                        'duration' => $duracao->format('H:i:s'),
                        //'vacancies_per_time' => $horario['ClienteServicoHorario']['vagas_por_horario'],
                        'at_home' => $horario['ClienteServicoHorario']['a_domicilio'] === '1' ? true : false,
                        'only_at_home' => $horario['ClienteServicoHorario']['apenas_a_domocilio'] === '1' ? true : false
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

}
