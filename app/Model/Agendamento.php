<?php 
class Agendamento extends AppModel {
    public $useTable = 'agendamentos';

    public $name = 'Agendamento';

    public $weekDayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
		'ClienteServico' => array(
			'foreignKey' => 'servico_id'
        ),
    );

    public function verificaHorarios($horarios = [], $cliente_id = null, $data = null) {

        if ( count($horarios) == 0) {
            return [];
        }
        if ( $cliente_id == null ) {
            return [];
        }
        if ( $data == null ) {
            return [];
        }

        foreach( $horarios as $key => $horario ){

            $agendamentos_marcados = $this->find('all',[
                'fields' => [
                    'Agendamento.id',
                    'ClienteServico.*'
                ],
                'conditions' => [
                    'Agendamento.cliente_id' => $cliente_id,
                    'Agendamento.horario' => $data.' '.$horario['horario'],
                    'Agendamento.cancelado' => 'N'
                ],
                'Link' => [
                    'ClienteServico'
                ]
            ]);

            $n_agendamentos_marcados = count($agendamentos_marcados);

            if ( $n_agendamentos_marcados >= $horario['vagas'] ) {
                $horarios[$key]['enabled'] = false;
            } else {
                $horarios[$key]['enabled'] = true;
                $horarios[$key]['agendamentos_marcados'] = $agendamentos_marcados;

            }


        }

        return $horarios;

    }

    public function verificaAgendamento($cliente_cliente_id = null, $cliente_id = null, $data = null, $hora = null){
        if ( $cliente_cliente_id == null ) {
            return false;
        }
        if ( $data == null ) {
            return false;
        }
        if ( $hora == null ) {
            return false;
        }
        
        $conditions = [
            'Agendamento.cliente_cliente_id' => $cliente_cliente_id,
            'Agendamento.horario' => $data.' '.$hora,
            'Agendamento.cancelado' => 'N'
        ];

        if ( $cliente_id != null ) {
            $conditions = array_merge($conditions, [
                'Agendamento.cliente_id' => $cliente_id
            ]);
        }

        return $this->find('first',[
            'conditions' => $conditions
        ]);

    }

    public function nAgendamentosCliente($cliente_id = null, $data = null, $hora = null){
        if ( $cliente_id == null ) {
            return false;
        }
        if ( $data == null ) {
            return false;
        }
        if ( $hora == null ) {
            return false;
        }
        
        $conditions = [
            'Agendamento.cliente_id' => $cliente_id,
            'Agendamento.horario' => $data.' '.$hora,
            'Agendamento.cancelado' => 'N'
        ];


        return $this->find('count',[
            'conditions' => $conditions
        ]);

    }

    public function buscaAgendamentoUsuario($cliente_cliente_ids = []) {

        $agendamentos_basicos = $this->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'Agendamento.horario >=' => date('Y-m-d 00:00:00'),
                'Agendamento.cliente_cliente_id' => $cliente_cliente_ids,
                'Agendamento.dia_semana' => null,
                'Agendamento.cancelado' => "N",
                'Agendamento.dia_mes' => null,
            ],
            'order' => [
                'Agendamento.horario'
            ],
            'link' => ['Cliente' => ['Localidade'], 'ClienteServico']
        ]);

        $fixo_semanal = $this->buscaAgendamentoUsuarioFixoSemanal($cliente_cliente_ids);
        if ( count($fixo_semanal) > 0 ) {
            $hoje = date('Y-m-d');
            $limitDate = strtotime($hoje." + 11 months");
            $fixo_semanal = $this->montaArrayHorariosFixoSemanal($fixo_semanal, $limitDate);
        }

        $fixo_mensal = $this->buscaAgendamentoUsuarioFixoMensal($cliente_cliente_ids);
        if ( count($fixo_mensal) > 0 ) {
            $hoje = date('Y-m-d');
            $limitDate = strtotime($hoje." + 11 months");
            $fixo_mensal = $this->montaArrayHorariosFixoMensal($fixo_mensal, $limitDate);
        }

        return array_merge($agendamentos_basicos, $fixo_semanal, $fixo_mensal);
    }

    public function buscaAgendamentoUsuarioFixoSemanal($cliente_cliente_ids = []) {

        return $this->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'Agendamento.cliente_cliente_id' => $cliente_cliente_ids,
                'Agendamento.cancelado' => "N",
                'not' => [
                    'Agendamento.dia_semana' => null,
                ]
            ],
            'order' => [
                'Agendamento.horario'
            ],
            'link' => ['Cliente' => ['Localidade'], 'ClienteServico']
        ]);

    }

    public function buscaAgendamentoUsuarioFixoMensal($cliente_cliente_ids = []) {

        return $this->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'Agendamento.cliente_cliente_id' => $cliente_cliente_ids,
                'Agendamento.cancelado' => "N",
                'not' => [
                    'Agendamento.dia_mes' => null,
                ]
            ],
            'order' => [
                'Agendamento.horario'
            ],
            'link' => ['Cliente' => ['Localidade'], 'ClienteServico']
        ]);

    }

    public function montaArrayHorariosFixoSemanal($agendamentos = [], $limitDate = null) {
        
        if ( count($agendamentos) == 0 || $limitDate == null ) {
            return [];
        }
        $hoje = date('Y-m-d');

        $dados_retornar = [];
        foreach( $agendamentos as $key => $age ) {
            $dia_semana = $age['Agendamento']['dia_semana'];
            list($data,$hora) = explode(' ',$age['Agendamento']['horario']);

            for($i = strtotime($this->weekDayNames[$dia_semana], strtotime($hoje)); $i <= $limitDate; $i = strtotime('+1 week', $i)) {
                
                $age['Agendamento']['horario'] = date('Y-m-d', $i).' '.$hora;
                $dados_retornar[] = $age;
            }
            

        }

        return $dados_retornar;
    }

    public function montaArrayHorariosFixoMensal($agendamentos = [], $limitDate = null) {
        
        if ( count($agendamentos) == 0 || $limitDate == null ) {
            return [];
        }
        $hoje = date('Y-m-d');

        $dados_retornar = [];
        foreach( $agendamentos as $key => $age ) {

            $dia_mes = $age['Agendamento']['dia_mes'];
            list($data,$hora) = explode(' ',$age['Agendamento']['horario']);
            list($ano,$mes,$dia) = explode('-',$data);

            $months_of_difference = $this->calcMountPeriodDifference($hoje,date('Y-m-d',$limitDate));
            $mes_checar = $mes;
            $ano_checar = $ano;
   
            for($i = 1; $i <= $months_of_difference; $i++) {

                if (checkdate($mes_checar, $dia, $ano_checar)){
                    $age['Agendamento']['horario'] = $ano_checar.'-'.($mes_checar < 10 ? "0".$mes_checar : $mes_checar).'-'.$dia.' '.$hora;
                }
                $mes_checar = $mes_checar + 1;
                if ( $mes_checar > 12 ) {
                    $mes_checar = 1;
                    $ano_checar++;
                }
                
                //$age['Agendamento']['horario'] = date('Y-m-d', $i).' '.$hora;
                $dados_retornar[] = $age;
            }
            

        }

        return $dados_retornar;
    }

}
