<?php
App::uses('CakeEmail', 'Network/Email');
class CronsController extends AppController {
    public function avisa_usuarios_agendamentos(){

        $this->layout = 'ajax';
        $this->loadModel('Agendamento');
        $hoje = date('Y-m-d');
        $amanha = date('Y-m-d', strtotime($hoje.' + 1 days'));
        $dia_semana = date('w',strtotime($hoje));
        $dia_mes = (int)date('d',strtotime($hoje));
        $dia_semana_amanha = $dia_semana + 1;
        $avisar_alguem = false;

        if ( $dia_semana_amanha > 6 )
            $dia_semana_amanha = 0;
            
        $dia_mes_amanha = (int)date('d',strtotime($amanha));

        $this->loadModel('Agendamento');
        $this->loadModel('AgendamentoAviso');

        $agendamentos_proximos = $this->Agendamento->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'or' => [
                    [
                        'DATE(Agendamento.horario) >=' => date('Y-m-d'),
                        'DATE(Agendamento.horario) <=' => date('Y-m-d', strtotime($hoje.' + 1 days')),
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => null,
                    ],
                    [
                        'DATE(Agendamento.horario) <=' => date('Y-m-d', strtotime($hoje.' + 1 days')),
                        'Agendamento.dia_semana' => [$dia_semana, $dia_semana_amanha],
                        'Agendamento.dia_mes' => null,
                    ],
                    [
                        'DATE(Agendamento.horario) <=' => date('Y-m-d', strtotime($hoje.' + 1 days')),
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => [$dia_mes, $dia_mes_amanha],
                    ]
                ],
                'Agendamento.cancelado' => 'N',
                'not' => [
                    'Usuario.id' => null,
                ]
            ],
            'link' => ['ClienteCliente' => ['Usuario'], 'Cliente']
        ]);

        $n_avisos = 0;
        if ( count($agendamentos_proximos) > 0 ) {
            foreach($agendamentos_proximos as $key => $agendamento){

                $agendamento_horario = $agendamento['Agendamento']['horario'];
                if ( $agendamento['Agendamento']['dia_semana'] != null ) {
                    if ( $agendamento['Agendamento']['dia_semana'] == $dia_semana) {
                        $agendamento_horario = $hoje.' '.date('H:i:s',strtotime($agendamento['Agendamento']['horario']));
                    }
                    else if ( $agendamento['Agendamento']['dia_semana'] == $dia_semana_amanha) {
                        $agendamento_horario = $amanha.' '.date('H:i:s',strtotime($agendamento['Agendamento']['horario']));
                    }
                }

                if ( $agendamento['Agendamento']['dia_mes'] != null ) {
                    if ( $agendamento['Agendamento']['dia_mes'] == $dia_mes) {
                        $agendamento_horario = $hoje.' '.date('H:i:s',strtotime($agendamento['Agendamento']['horario']));
                    }
                    else if ( (int)$agendamento['Agendamento']['dia_mes'] == (int)$dia_mes_amanha) {
                        $agendamento_horario = $amanha.' '.date('H:i:s',strtotime($agendamento['Agendamento']['horario']));
                    }
                }

                $diff = strtotime($agendamento_horario) - strtotime(date('Y-m-d H:i:s'));
                $minutos = $diff / ( 60 );
                $prazo_minutos_aviso = $this->timeToMinutes($agendamento['Cliente']['tempo_aviso_usuarios']);

                if ( $minutos > $prazo_minutos_aviso ) {
                    continue;
                }

                $v_aviso = $this->AgendamentoAviso->find('first',[
                    'conditions' => [
                        'AgendamentoAviso.agendamento_id' => $agendamento['Agendamento']['id'],
                        'AgendamentoAviso.horario' => $agendamento_horario,
                        #'DATE(AgendamentoAviso.horario) <=' => date('Y-m-d', strtotime($hoje.' + 1 days')),
                    ],
                    'link' => []
                ]);

                if ( count($v_aviso) == 0 ) {

                    $usuarios_ids[] = $agendamento['Usuario']['id'];
                    $this->loadModel('Usuario');
                    $convidados_confirmados = $this->Usuario->getShedulingConfirmedUsers($agendamento['Agendamento']['id'], $agendamento_horario);

                    if ( count($convidados_confirmados) > 0 ) {
                        $usuarios_ids = array_merge($usuarios_ids, $convidados_confirmados);
                    }

                    $dados_salvar = [
                        'agendamento_id' => $agendamento['Agendamento']['id'],
                        'avisado_em' => date('Y-m-d H:i:s'),
                        'horario' => $agendamento_horario,
                    ];

                    $this->AgendamentoAviso->create();
                    $this->AgendamentoAviso->save($dados_salvar);
                    
                    $this->sendShedulingAlertNotification($usuarios_ids, $agendamento, $agendamento_horario);
                    $avisar_alguem = true;
                    $n_avisos++;
                    
                }
                
            }

            /*if ( $avisar_alguem ) {
                $Email = new CakeEmail('smtp_aplicativo');
                $Email->from(array('aplicativo@buzke.com.br' => 'Buzke'));
                $Email->emailFormat('html');
                $Email->to('josiasrs2009@gmail.com');
                $Email->template('sugestao');
                $Email->subject('Sugestao - Buzke');
                $Email->viewVars(array('nome_usuario'=>'notificação de horario', 'sugestao' => $n_avisos ));//variable will be replaced from template
                $Email->send();
            }*/

        }


        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Usuários avisados com sucesso!', 'dados' => $n_avisos))));

    }

}