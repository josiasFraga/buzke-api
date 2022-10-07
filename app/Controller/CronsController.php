<?php
App::uses('CakeEmail', 'Network/Email');
class CronsController extends AppController {
    public function avisa_usuarios_agendamentos(){

        $this->layout = 'ajax';
        $this->loadModel('Agendamento');
        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioInscricaoJogador');
        $this->loadModel('Agendamento');
        $this->loadModel('AgendamentoAviso');

        $hoje = date('Y-m-d');
        $amanha = date('Y-m-d', strtotime($hoje.' + 1 days'));
        $dia_semana = date('w',strtotime($hoje));
        $dia_mes = (int)date('d',strtotime($hoje));
        $dia_semana_amanha = $dia_semana + 1;
        $avisar_alguem = false;

        if ( $dia_semana_amanha > 6 )
            $dia_semana_amanha = 0;
            
        $dia_mes_amanha = (int)date('d',strtotime($amanha));

        //agendamentos padrão
        $agendamentos_proximos = $this->Agendamento->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'or' => [
                    [
                        'DATE(Agendamento.horario) >=' => $hoje,
                        'DATE(Agendamento.horario) <=' => $amanha,
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => null,
                    ],
                    [
                        'DATE(Agendamento.horario) <=' => $amanha,
                        'Agendamento.dia_semana' => [$dia_semana, $dia_semana_amanha],
                        'Agendamento.dia_mes' => null,
                    ],
                    [
                        'DATE(Agendamento.horario) <=' => $amanha,
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => [$dia_mes, $dia_mes_amanha],
                    ]
                ],
                'Agendamento.cancelado' => 'N',
                'not' => [
                    'Usuario.id' => null,
                    'Cliente.tempo_aviso_usuarios' => null,
                ]
            ],
            'link' => ['ClienteCliente' => ['Usuario'], 'Cliente']
        ]);

        $n_avisos = 0;
        if ( count($agendamentos_proximos) > 0 ) {
            foreach($agendamentos_proximos as $key => $agendamento){

                $agendamento_horario = $agendamento['Agendamento']['horario'];

                //agendamento fixo semanal
                if ( $agendamento['Agendamento']['dia_semana'] != null ) {
                    if ( $agendamento['Agendamento']['dia_semana'] == $dia_semana) {
                        $agendamento_horario = $hoje.' '.date('H:i:s',strtotime($agendamento['Agendamento']['horario']));
                    }
                    else if ( $agendamento['Agendamento']['dia_semana'] == $dia_semana_amanha) {
                        $agendamento_horario = $amanha.' '.date('H:i:s',strtotime($agendamento['Agendamento']['horario']));
                    }
                }

                //agendamento fixo mensal
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

                //verifica se esse agendamento já foi emitido o aviso
                $v_aviso = $this->AgendamentoAviso->find('first',[
                    'conditions' => [
                        'AgendamentoAviso.agendamento_id' => $agendamento['Agendamento']['id'],
                        'AgendamentoAviso.horario' => $agendamento_horario,
                    ],
                    'link' => []
                ]);

                //se ainda nao foi avisado
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

        }

        //agendamentos de torneio
        //OBS: só aviso quando as 2 duplas do jogo foram definidas
        $agendamentos_proximos = $this->Agendamento->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'DATE(Agendamento.horario) >=' => $hoje,
                'DATE(Agendamento.horario) <=' => $amanha,  
                'Agendamento.cancelado' => 'N',
                'Torneio.jogos_liberados_ao_publico' => 'Y',
                'not' => [
                    'Agendamento.torneio_id' => null,
                    'Cliente.tempo_aviso_usuarios' => null,
                ]
            ],
            'link' => ['Torneio', 'Cliente']
        ]);

        if ( count($agendamentos_proximos) > 0 ) {
            foreach($agendamentos_proximos as $key => $agendamento) {
    
                //prazo para aviso definido pelo cliente
                $prazo_minutos_aviso = $this->timeToMinutes($agendamento['Cliente']['tempo_aviso_usuarios']);

                $agendamento_horario = $agendamento['Agendamento']['horario'];
                $diff = strtotime($agendamento_horario) - strtotime(date('Y-m-d H:i:s'));
                $minutos = $diff / ( 60 );

                //vrifico se já é hora de avisar
                if ( $minutos > $prazo_minutos_aviso ) {
                    continue;
                }

                //verifico se já avisei
                $v_aviso = $this->AgendamentoAviso->find('first',[
                    'conditions' => [
                        'AgendamentoAviso.agendamento_id' => $agendamento['Agendamento']['id'],
                        'AgendamentoAviso.horario' => $agendamento_horario,
                    ],
                    'link' => []
                ]);

                if ( count($v_aviso) > 0 ) {
                    continue;
                }

                //busca os dados do jogo
                $dados_jogo = $this->TorneioJogo->getBySchedulingId($agendamento['Agendamento']['id']);

                //se as 2 duplas ainda não foram definidas, eu não aviso
                if ( count($dados_jogo) == 0 || $dados_jogo['TorneioJogo']['time_1'] == null || $dados_jogo['TorneioJogo']['time_2'] == null ) {
                    continue;
                }

                //busca os dados dos jogadores inscritos
                $dados_jogadores = $this->TorneioInscricaoJogador->getBySubscriptionId([$dados_jogo['TorneioJogo']['time_1'], $dados_jogo['TorneioJogo']['time_2']]);

                if ( count($dados_jogadores) == 0 ) {
                    continue;
                }

                $avisar_alguem = false;
                foreach( $dados_jogadores as $key_jogador => $jogador ){

                    if ( $jogador['ClienteCliente']['usuario_id'] != null ) {

                        $usuarios_ids[] = $jogador['ClienteCliente']['usuario_id'];
                        $this->sendTorunamentShedulingAlertNotification($usuarios_ids, $agendamento, $agendamento_horario, $dados_jogo);
                        $avisar_alguem = true;
                        $n_avisos++;

                    }

                }

                if ( $avisar_alguem ) {
                    
    
                    $dados_salvar = [
                        'agendamento_id' => $agendamento['Agendamento']['id'],
                        'avisado_em' => date('Y-m-d H:i:s'),
                        'horario' => $agendamento_horario,
                    ];

                    $this->AgendamentoAviso->create();
                    $this->AgendamentoAviso->save($dados_salvar);
                }
            }
        }

        /*if ( count($n_avisos) > 0 ) {
            $Email = new CakeEmail('smtp_aplicativo');
            $Email->from(array('aplicativo@buzke.com.br' => 'Buzke'));
            $Email->emailFormat('html');
            $Email->to('josiasrs2009@gmail.com');
            $Email->template('sugestao');
            $Email->subject('Sugestao - Buzke');
            $Email->viewVars(array('nome_usuario'=>'notificação de horario', 'sugestao' => $n_avisos ));//variable will be replaced from template
            $Email->send();
        }*/


        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Usuários avisados com sucesso!', 'dados' => $n_avisos))));

    }

}