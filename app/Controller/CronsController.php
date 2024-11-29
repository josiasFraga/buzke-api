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

    public function avisa_usuarios_promocoes() {

        $hoje = date('Y-m-d');
        $hora = date('H');

        // Não enviar notificação entre 22 e 8
        if ( $hora > 22 || $hora < 8 ) {
            return false;
        }

        $n_avisos = 0;

        $this->loadModel('Promocao');
        $this->loadModel('Usuario');
        $this->loadModel('Token');

        $promocoes = $this->Promocao->find('all',[
            'fields' => [
                'Promocao.id',
                'Cliente.nome',
                'Cliente.estado',
                'Localidade.loc_no',
                'UruguaiCidade.nome',
                'UruguaiDepartamento.nome'
            ],
            'conditions' => [
                'Promocao.avisos_enviados' => 'N',
                'DATE(Promocao.validade_inicio) <=' => $hoje,
                'DATE(Promocao.validade_fim) >=' => $hoje
            ],
            'link' => [
                'Cliente' => [
                    'Localidade',
                    'UruguaiCidade' => [
                        'UruguaiDepartamento'
                    ]
                ]
            ]
        ]);

        if ( count($promocoes) > 0 ) {

            foreach( $promocoes as $key => $promocao ) {
        
                $pais = 'Uruguai';
                $cidade = $promocao['UruguaiCidade']['nome'];
                $estado = $promocao['UruguaiDepartamento']['nome'];
        
                if ( !empty($promocao['Localidade']['loc_no']) ) {
                    $pais = 'Brasil';
                    $cidade = $promocao['Localidade']['loc_no'];
                    $estado = $promocao['Cliente']['estado'];
                }
    
                $usuarios_avisar = $this->Usuario->getByLastLocation($pais, $estado, $cidade);

                foreach( $usuarios_avisar as $key_usu => $usuario ) {

                    $usuario_id = $usuario['Usuario']['id'];
                    $notifications_ids = $this->Token->getIdsNotificationsUsuario($usuario_id);

                    if ( count($notifications_ids) === 0 ) {
                        continue;
                    }

                    $this->sendNotificationNew( 
                        $usuario_id,
                        $notifications_ids, 
                        $promocao['Promocao']['id'],
                        null,
                        'nova_promocao',
                        ["en"=> '$[notif_count] Novas promoções na sua área']
                    );

                    $n_avisos++;
                }

                $dados_promocao_atualizar = [
                    'id' => $promocao['Promocao']['id'],
                    'avisos_enviados' => 'Y'
                ];

                $this->Promocao->save($dados_promocao_atualizar);
            }

        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Usuários avisados com sucesso!', 'dados' => $n_avisos))));
    }

    public function avisa_usuarios_torneios() {

        $hoje = date('Y-m-d');
        $hora = date('H');

        // Não enviar notificação entre 22 e 8
        if ( $hora > 22 || $hora < 8 ) {
            return false;
        }

        $n_avisos = 0;

        $this->loadModel('Torneio');
        $this->loadModel('Usuario');
        $this->loadModel('Token');

        $torneios = $this->Torneio->find('all',[
            'fields' => [
                'Torneio.id',
                'Cliente.nome',
                'Cliente.estado',
                'Localidade.loc_no',
                'UruguaiCidade.nome',
                'UruguaiDepartamento.nome'
            ],
            'conditions' => [
                'Torneio.notificacao_cadastro_enviada' => 'N',
                'DATE(Torneio.data_publicacao) <=' => $hoje,
                'DATE(Torneio.fim) >=' => $hoje
            ],
            'link' => [
                'Cliente' => [
                    'Localidade',
                    'UruguaiCidade' => [
                        'UruguaiDepartamento'
                    ]
                ]
            ]
        ]);

        if ( count($torneios) > 0 ) {

            foreach( $torneios as $key => $torneio ) {
        
                $pais = 'Uruguai';
                $cidade = $torneio['UruguaiCidade']['nome'];
                $estado = $torneio['UruguaiDepartamento']['nome'];
        
                if ( !empty($torneio['Localidade']['loc_no']) ) {
                    $pais = 'Brasil';
                    $cidade = $torneio['Localidade']['loc_no'];
                    $estado = $torneio['Cliente']['estado'];
                }
    
                $usuarios_avisar = $this->Usuario->getByLastLocation($pais, $estado, $cidade);

                foreach( $usuarios_avisar as $key_usu => $usuario ) {

                    $usuario_id = $usuario['Usuario']['id'];
                    $notifications_ids = $this->Token->getIdsNotificationsUsuario($usuario_id);

                    if ( count($notifications_ids) === 0 ) {
                        continue;
                    }

                    $this->sendNotificationNew( 
                        $usuario_id,
                        $notifications_ids, 
                        $torneio['Torneio']['id'],
                        null,
                        'novo_torneio_padel',
                        ["en"=> '$[notif_count] Novos torneios na sua área']
                    );

                    $n_avisos++;
                }

                $dados_torneio_atualizar = [
                    'id' => $torneio['Torneio']['id'],
                    'notificacao_cadastro_enviada' => 'Y'
                ];

                $this->Torneio->save($dados_torneio_atualizar);
            }

        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Usuários avisados com sucesso!', 'dados' => $n_avisos))));
    }

    public function avisa_jogos_torneio_liberados() {

        $hoje = date('Y-m-d');
        $hora = date('H');

        // Não enviar notificação entre 22 e 8
        if ( $hora > 22 || $hora < 8 ) {
            return false;
        }

        $n_avisos = 0;

        $this->loadModel('Usuario');
        $this->loadModel('Token');
        $this->loadModel('Torneio');

        $usuarios = $this->Usuario->find('all',[
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                'Torneio.id'
            ],
            'conditions' => [
                'Torneio.notificacao_jogos_liberados_enviada' => 'N',
                'Torneio.jogos_liberados_ao_publico' => 'Y'
            ],
            'link' => [
                'ClienteCliente' => [
                    'TorneioInscricaoJogador' => [
                        'TorneioInscricao' => [
                            'Torneio'
                        ]
                    ]
                ]
            ],
            'group' => [
                'Torneio.id', 
                'Usuario.id'
            ]
        ]);

        if ( count($usuarios) > 0 ) {

            foreach( $usuarios as $key => $usuario ) {
                
                $usuario_id = $usuario['Usuario']['id'];
                $notifications_ids = $this->Token->getIdsNotificationsUsuario($usuario_id);

                if ( count($notifications_ids) === 0 ) {
                    continue;
                }

                $this->sendNotificationNew( 
                    $usuario_id,
                    $notifications_ids, 
                    $usuario['Torneio']['id'],
                    null,
                    'jogos_torneio_padel_liberados',
                    ["en"=> '$[notif_count] Jogos do torneio liberados']
                );

                $n_avisos++;

                $dados_torneio_atualizar = [
                    'id' => $usuario['Torneio']['id'],
                    'notificacao_jogos_liberados_enviada' => 'Y'
                ];

                $this->Torneio->save($dados_torneio_atualizar);
            }

        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Usuários avisados com sucesso!', 'dados' => $n_avisos))));
    }

    public function envia_lembrete_avaliar_servico() {

        $hora = date('H');

        // Não enviar notificação entre 22 e 8
        if ( $hora > 22 || $hora < 8 ) {
            return false;
        }

        $hoje = date('Y-m-d');
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $dia_ontem  = (int)date('d', strtotime('-1 day'));
        $dia_semana_ontem = (int)date('w',strtotime($ontem));

        $n_avisos = 0;
    
        $this->loadModel('Agendamento');
        $this->loadModel('Token');
        $this->loadModel('AgendamentoAviso');

        $agendamentos = $this->Agendamento->find('all',[
            'fields' => [
                'Agendamento.id',
                'Usuario.id',
                'Usuario.nome',
                'ClienteServico.id',
            ],
            'conditions' => [
                'Agendamento.cancelado' => 'N',
                'ClienteServicoAvaliacao.id' => null,
                'AgendamentoAviso.id' => null,
                'NOT' => [
                    'Usuario.id' => null
                ],
                'OR' => [
                    ['DATE(Agendamento.horario)' => $ontem],
                    ['Agendamento.dia_semana' => $dia_semana_ontem],
                    ['Agendamento.dia_mes' => $dia_ontem]
                ]
            ],
            'link' => [
                'ClienteCliente' => ['Usuario'],
                'ClienteServico' => [
                    'ClienteServicoAvaliacao' => [
                        'conditions' => [
                            'ClienteServico.id = ClienteServicoAvaliacao.cliente_servico_id',
                            'Usuario.id = ClienteServicoAvaliacao.usuario_id'
                        ]
                    ]
                ],
                'AgendamentoAviso' => [
                    'conditions' => [
                        'Agendamento.id = AgendamentoAviso.agendamento_id',
                        'AgendamentoAviso.tipo = "lembrete_avaliar"'
                    ]
                ]
            ],
            'group' => [
                'Usuario.id'
            ]
        ]);

        foreach( $agendamentos as $key => $agendamento ) {
            $usuario_id = $agendamento['Usuario']['id'];
            $notifications_ids = $this->Token->getIdsNotificationsUsuario($usuario_id);

            if ( count($notifications_ids) === 0 ) {
                continue;
            }

            $this->sendNotificationNew( 
                $usuario_id,
                $notifications_ids, 
                $agendamento['ClienteServico']['id'],
                null,
                'lembrete_avaliar',
                ["en"=> '$[notif_count] Agendamentos para avaliar']
            );

            $n_avisos++;

            $dados_aviso_salvar = [
                'agendamento_id' => $agendamento['Agendamento']['id'],
                'avisado_em' => date('Y-m-d H:i:s'),
                'tipo' => 'lembrete_avaliar'
            ];

            $this->AgendamentoAviso->create();
            $this->AgendamentoAviso->save($dados_aviso_salvar);
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Usuários lembrados com sucesso!', 'dados' => $n_avisos))));
    }

}