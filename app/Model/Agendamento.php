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

    public $hasMany = array(
		'AgendamentoFixoCancelado' => array(
			'foreignKey' => 'agendamento_id'
        ),
		'AgendamentoConvite' => array(
			'foreignKey' => 'agendamento_id'
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

        $dia_semana = date('w',strtotime($data));
        $dia_mes = (int)date('d',strtotime($data));

        $n_agendamentos_normais = $this->find('count',[
            'conditions' => [
                'Agendamento.cliente_id' => $cliente_id,
                'Agendamento.horario' => $data.' '.$hora,
                'Agendamento.cancelado' => 'N'
            ]
        ]);

        $n_agendamentos_fixos_semanais = $this->find('count',[
            'conditions' => [
                'Agendamento.cliente_id' => $cliente_id,
                'TIME(Agendamento.horario)' => $hora,
                'Agendamento.cancelado' => 'N',
                'Agendamento.dia_semana' => $dia_semana
            ]
        ]);

        $n_agendamentos_fixos_mensais = $this->find('count',[
            'conditions' => [
                'Agendamento.cliente_id' => $cliente_id,
                'TIME(Agendamento.horario)' => $hora,
                'Agendamento.cancelado' => 'N',
                'Agendamento.dia_mes' => $dia_mes
            ]
        ]);

        return $n_agendamentos_normais+$n_agendamentos_fixos_semanais+$n_agendamentos_fixos_mensais;

    }

    public function buscaAgendamentoUsuario($cliente_cliente_ids = []) {

        $agendamentos_basicos = $this->find('all',[
            'fields' => [     
                'Cliente.id',
                'Cliente.nome',
                'Cliente.telefone',
                'Cliente.wp', 
                'Cliente.estado', 
                'Cliente.logo',
                'Cliente.endereco',
                'Cliente.endereco_n',
                'Cliente.bairro',
                'Cliente.prazo_maximo_para_canelamento',
                'Agendamento.*', 
                'Localidade.loc_no',
                'Localidade.ufe_sg',
                'ClienteServico.nome',
                'ClienteServico.descricao',
                'ClienteServico.valor',
            ],
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

        //debug($agendamentos_basicos);

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

        $agendamentos_de_convites = $this->buscaAgendamentoDeConvite($cliente_cliente_ids);
        if ( count($agendamentos_de_convites) > 0 ) {
            $agendamentos_de_convites = $this->montaArrayHorariosConvite($agendamentos_de_convites);
        }

        return array_merge($agendamentos_basicos, $fixo_semanal, $fixo_mensal, $agendamentos_de_convites);
    }

    public function buscaAgendamentoUsuarioFixoSemanal($cliente_cliente_ids = []) {

        return $this->find('all',[
            'fields' => [
                'Cliente.id',
                'Cliente.nome',
                'Cliente.telefone',
                'Cliente.wp', 
                'Cliente.estado', 
                'Cliente.logo',
                'Cliente.endereco',
                'Cliente.endereco_n',
                'Cliente.bairro',
                'Cliente.prazo_maximo_para_canelamento',
                'Agendamento.*', 
                'Localidade.loc_no',
                'Localidade.ufe_sg',
                'ClienteServico.nome',
                'ClienteServico.descricao',
                'ClienteServico.valor',
            ],
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
            'fields' => [ 
                'Cliente.id',
                'Cliente.nome',
                'Cliente.telefone',
                'Cliente.wp', 
                'Cliente.estado', 
                'Cliente.logo',
                'Cliente.endereco',
                'Cliente.endereco_n',
                'Cliente.bairro',
                'Cliente.prazo_maximo_para_canelamento',
                'Agendamento.*', 
                'Localidade.loc_no',
                'Localidade.ufe_sg',
                'ClienteServico.nome',
                'ClienteServico.descricao',
                'ClienteServico.valor',
            ],
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

    public function buscaAgendamentoDeConvite($cliente_cliente_ids = []) {
        

        return $this->find('all',[
            'fields' => [
                'Cliente.id',
                'Cliente.nome',
                'Cliente.telefone',
                'Cliente.wp', 
                'Cliente.estado', 
                'Cliente.logo', 
                'Cliente.endereco',
                'Cliente.endereco_n',
                'Cliente.bairro',
                'Cliente.prazo_maximo_para_canelamento',
                'Agendamento.*', 
                'AgendamentoConvite.*', 
                'Localidade.loc_no',
                'Localidade.ufe_sg',
                'ClienteServico.nome',
                'ClienteServico.descricao',
                'ClienteServico.valor',
                'ClienteCliente.nome',
                'Usuario.img'
            ],
            'conditions' => [
                'AgendamentoConvite.horario >=' => date('Y-m-d 00:00:00'),
                'AgendamentoConvite.confirmado_usuario' => "Y",
                'AgendamentoConvite.confirmado_convidado' => "Y",
                'AgendamentoConvite.cliente_cliente_id' => $cliente_cliente_ids,
                'AgendamentoConvite.horario_cancelado' => "N",
            ],
            'order' => [
                'AgendamentoConvite.horario'
            ],
            'link' => ['ClienteCliente' => ['Usuario'], 'Cliente' => ['Localidade'], 'ClienteServico', 'AgendamentoConvite']
        ]);

    }

    public function buscaAgendamentoEmpresa($cliente_id, $type, $data, $year_week) {

        
        if ( $type == 1) {
            $conditions = [
                'Agendamento.cliente_id' => $cliente_id,
                'MONTH(Agendamento.horario)' => date('m',strtotime($data)),
                'Agendamento.dia_semana' => null,
                'Agendamento.dia_mes' => null,
                'not' => [
                    'Agendamento.cancelado' => 'Y'
                ]
            ];
        }
        else if ( $type == 2) {
            $conditions = [
                'Agendamento.cliente_id' => $cliente_id,
                'Agendamento.dia_semana' => null,
                'Agendamento.dia_mes' => null,
                'YEARWEEK(Agendamento.horario, 4)' => $year_week,
                'not' => [
                    'Agendamento.cancelado' => 'Y'
                ]
            ];
        }


        $agendamentos_basicos = $this->find('all',[
            'conditions' => $conditions,
            'fields' => [
                'Agendamento.id',
                'Agendamento.horario',
                'Agendamento.duracao',
                'Agendamento.dia_semana',
                'Agendamento.dia_mes',
                'Agendamento.endereco',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.img',
                'ClienteServico.nome',
                'Agendamento.cliente_id'
            ],
            'link' => ['ClienteCliente', 'ClienteServico'],
            'order' => ['Agendamento.horario']
        ]);

        $fixo_semanal = $this->buscaAgendamentoEmpresaFixoSemanal($cliente_id);
        if ( count($fixo_semanal) > 0 ) {
            $data_selecionada = $data;
            $inicio_mes = date('Y-m-01',strtotime($data_selecionada));
            $limitDate = strtotime($inicio_mes." + 1 months");
            $fixo_semanal = $this->montaArrayHorariosFixoSemanal($fixo_semanal, $limitDate, $inicio_mes);
        }

        $fixo_mensal = $this->buscaAgendamentoEmpresaFixoMensal($cliente_id); 
        if ( count($fixo_mensal) > 0 ) {
            $data_selecionada = $data;
            $inicio_mes = date('Y-m-01',strtotime($data_selecionada));
            $limitDate = strtotime(date("Y-m-t", strtotime($data_selecionada)));            
            $fixo_mensal = $this->montaArrayHorariosFixoMensal($fixo_mensal, $limitDate, $inicio_mes);
        }
        return array_merge($agendamentos_basicos, $fixo_semanal, $fixo_mensal);
    }
    
    public function buscaAgendamentoEmpresaFixoSemanal($cliente_id) {

        return $this->find('all',[
            'fields' => [
                'Agendamento.id',
                'Agendamento.dia_semana',
                'Agendamento.horario',
                'Agendamento.duracao',
                'Agendamento.dia_semana',
                'Agendamento.dia_mes',
                'Agendamento.endereco',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.img',
                'ClienteServico.nome',
                'Agendamento.cliente_id'
            ],
            'conditions' => [
                'Agendamento.cliente_id' => $cliente_id,
                'Agendamento.cancelado' => "N",
                'not' => [
                    'Agendamento.dia_semana' => null,
                ]
            ],
            'order' => [
                'Agendamento.horario'
            ],
            'link' => ['ClienteCliente', 'ClienteServico']
        ]);

    }

    public function buscaAgendamentoEmpresaFixoMensal($cliente_id) {

        return $this->find('all',[
            'fields' => [
                'Agendamento.id',
                'Agendamento.dia_mes',
                'Agendamento.horario',
                'Agendamento.duracao',
                'Agendamento.dia_semana',
                'Agendamento.dia_mes',
                'Agendamento.endereco',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.img',
                'ClienteServico.nome',
                'Agendamento.cliente_id'
            ],
            'conditions' => [
                'Agendamento.cliente_id' => $cliente_id,
                'Agendamento.cancelado' => "N",
                'not' => [
                    'Agendamento.dia_mes' => null,
                ]
            ],
            'order' => [
                'Agendamento.horario'
            ],
            'link' => ['ClienteCliente', 'ClienteServico']
        ]);

    }

    public function montaArrayHorariosFixoSemanal($agendamentos = [], $limitDate = null, $start_limit = null) {
        
        if ( count($agendamentos) == 0 || $limitDate == null ) {
            return [];
        }

        if ( $start_limit == null ) {
            $start_limit = date('Y-m-d');
        }
        
        $dados_retornar = [];
        foreach( $agendamentos as $key => $age ) {
            $dia_semana = $age['Agendamento']['dia_semana'];
            list($data,$hora) = explode(' ',$age['Agendamento']['horario']);
            if ( $data > $start_limit) {
                $start_limit = $data;
            }

            for($i = strtotime($this->weekDayNames[$dia_semana], strtotime($start_limit)); $i <= $limitDate; $i = strtotime('+1 week', $i)) {
                $age['Agendamento']['horario'] = date('Y-m-d', $i).' '.$hora;
                $dados_retornar[] = $age;
            }

        }

        return $dados_retornar;
    }

    public function montaArrayHorariosFixoMensal($agendamentos = [], $limitDate = null, $start_limit = null) {
        
        if ( count($agendamentos) == 0 || $limitDate == null ) {
            return [];
        }

        if ( $start_limit == null ) {
            $start_limit = date('Y-m-d');
        }

        $dados_retornar = [];
        foreach( $agendamentos as $key => $age ) {

            $dia_mes = $age['Agendamento']['dia_mes'];
            list($data,$hora) = explode(' ',$age['Agendamento']['horario']);
            list($ano,$mes,$dia) = explode('-',$data);
            if ( $data > $start_limit) {
                $start_limit = $data;
            }

            $months_of_difference = $this->calcMountPeriodDifference($start_limit,date('Y-m-d',$limitDate));
            $mes_checar = $mes;
            $ano_checar = $ano;
   
            for($i = 1; $i < $months_of_difference; $i++) {

                if (checkdate($mes_checar, $dia, $ano_checar)){
                    $age['Agendamento']['horario'] = $ano_checar.'-'.($mes_checar < 10 ? "0".$mes_checar : $mes_checar).'-'.$dia.' '.$hora;
                }
                $mes_checar = $mes_checar + 1;
                if ( $mes_checar > 12 ) {
                    $mes_checar = 1;
                    $ano_checar++;
                }

                if ($age['Agendamento']['horario'] >= date('Y-m-d H:i:s')){
                    $dados_retornar[] = $age;
                   
                }
                
                //$age['Agendamento']['horario'] = date('Y-m-d', $i).' '.$hora;
            }
            

        }

        return $dados_retornar;
    }

    public function montaArrayHorariosConvite($agendamentos = []) {
        
        if ( count($agendamentos) == 0 ) {
            return [];
        }

        $dados_retornar = [];
        foreach( $agendamentos as $key => $age ) {
            $age['Agendamento']['horario'] = $age['AgendamentoConvite']['horario'];
            //unset($age['AgendamentoConvite']);
            $dados_retornar[] = $age;

        }

        return $dados_retornar;
    }

}
