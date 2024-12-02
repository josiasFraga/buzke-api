<?php
class AgendamentosController extends AppController {
    
    public $helpers = array('Html', 'Form');
    public $components = array('RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['agendamento_id']) || $dados['agendamento_id'] == "" ) {
            throw new BadRequestException('Agendamento não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $agendamento_id = $dados['agendamento_id'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteSubcategoria');

        $conditions = [
            'Agendamento.id' => $agendamento_id,
            'not' => [
                'Agendamento.cancelado' => 'Y'
            ]
        ];

        $agendamento = $this->Agendamento->find('first',[
            'conditions' => $conditions,
            'fields' => [
                'Agendamento.*',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.telefone',
                'ClienteCliente.telefone_ddi',
                'ClienteCliente.pais',
                'Cliente.id',
                'Cliente.nome',
                'ClienteCliente.img',
                'ClienteCliente.endereco',
                'ClienteCliente.endreceo_n',
                'Localidade.loc_no',
                'ClienteServico.id',
                'ClienteServico.nome',
                'Usuario.nome'
            ],
            'link' => ['ClienteCliente' => ['Localidade'], 'Cliente', 'ClienteServico', 'Usuario']
        ]);

        if ( count($agendamento) > 0 ) {
            $agendamento['Agendamento']['tipo'] = 'padrao';

            if ( $agendamento['Agendamento']['dia_semana'] != '' ) {
                if (!isset($dados['horario'])) {
                    $agendamento = [];
                } else {
                    $dia_semana_horario_informado = date("w", strtotime($dados['horario']));
                    if ( $dia_semana_horario_informado == $agendamento['Agendamento']['dia_semana'] ) {
                        $agendamento['Agendamento']['horario'] = $dados['horario'];
                        $agendamento['Agendamento']['tipo'] = 'fixo';
                    } else {
                        $agendamento = [];
                    }
                }
            }
            else if ( $agendamento['Agendamento']['dia_mes'] != '' ) {
                if (!isset($dados['horario'])) {
                    $agendamento = [];
                } else {
                    $dia_mes_horario_informado = date("d", strtotime($dados['horario']));
                    if ( $dia_mes_horario_informado == $agendamento['Agendamento']['dia_mes'] ) {
                        $agendamento['Agendamento']['horario'] = $dados['horario'];
                        $agendamento['Agendamento']['tipo'] = 'fixo';
                    } else {
                        $agendamento = [];
                    }
                }
            }
        }

        if ( count($agendamento) > 0 ) {
            $agendamento['ClienteCliente']['img'] = $this->images_path . 'clientes_clientes/' . $agendamento['ClienteCliente']['img'];
            $agendamento['Agendamento']['horario_str'] = date('d/m',strtotime($agendamento['Agendamento']['horario']))." às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
            $data_agendamento = date('Y-m-d',strtotime($agendamento['Agendamento']['horario']));
            
            $agendamento['Agendamento']['valor_br'] = number_format($agendamento['Agendamento']['valor'], 2, ',', '.');
            $agendamento['Cliente']['isCourt'] = $this->ClienteSubcategoria->checkIsCourt($agendamento['Cliente']['id']);

            if ( $data_agendamento == date('Y-m-d') ) {
                $agendamento['Agendamento']['horario_str'] = "Hoje às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
            }
        }
        
        
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $agendamento))));
    }

    public function agendamentos() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }


        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');
        $this->loadModel('AgendamentoClienteCliente');

        $cancelable = null;
     
        if ( $dados_token['Usuario']['cliente_id'] != '' && $dados_token['Usuario']['cliente_id'] != null ) {

            if ( !isset($dados['cliente_cliente_id']) || $dados['cliente_cliente_id'] == "" ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
            }

            $meus_ids_de_cliente = [$dados['cliente_cliente_id']];
            $cancelable = true;

        } else {            
            $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_token['Usuario']['id'], true);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteServicoHorario');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');
        $this->loadModel('AgendamentoFixoCancelado');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('AgendamentoConvite');
        $this->loadModel('Usuario');

        $agendamentos = $this->Agendamento->buscaAgendamentoUsuario($meus_ids_de_cliente);
        $agendamentos = $this->ClienteHorarioAtendimentoExcessao->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->ClienteServicoHorario->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->AgendamentoFixoCancelado->checkStatus($agendamentos);

        if ( count($agendamentos) > 0 ) {
            usort($agendamentos, function($a, $b) {
                return $a['Agendamento']['horario'] <=> $b['Agendamento']['horario'];
            });

            foreach($agendamentos as $key => $agendamento){
                
                $agendamentos[$key]['Agendamento']['horario_str'] = date('d/m',strtotime($agendamento['Agendamento']['horario']))." às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
                $agendamentos[$key]['Cliente']['logo'] = $this->images_path.'clientes/'.$agendamento['Cliente']['logo'];
                $agendamentos[$key]['Agendamento']['data'] = date('d/m/Y',strtotime($agendamento['Agendamento']['horario']));
                $agendamentos[$key]['Agendamento']['hora'] = date('H:i',strtotime($agendamento['Agendamento']['horario']));
                $agendamentos[$key]['Agendamento']['tipo'] = 'padrao';

                $horario = $agendamento['Agendamento']['horario'];
                $duracao = $agendamento['Agendamento']['duracao'];

                $dateTime = new DateTime($horario);
                list($hours, $minutes, $seconds) = explode(':', $duracao);
                $interval = new DateInterval("PT{$hours}H{$minutes}M{$seconds}S");
                $dateTime->add($interval);;

                $fim_agendamento = $dateTime->format('H:i');

                $agendamentos[$key]['Agendamento']['fim_agendamento'] = $fim_agendamento;
                
                if ( isset($agendamento['Agendamento']['torneio_id']) && $agendamento['Agendamento']['torneio_id'] != null ) 
                    $agendamentos[$key]['Agendamento']['tipo'] = 'tournament';

                if ( isset($agendamentos[$key]['Agendamento']['valor']) )
                    $agendamentos[$key]['Agendamento']['valor_br'] = number_format($agendamentos[$key]['Agendamento']['valor'], 2, ',', '.');
                else
                    $agendamentos[$key]['Agendamento']['valor_br'] = number_format(0, 2, ',', '.');
                $agendamentos[$key]['Cliente']['isCourt'] = $this->ClienteSubcategoria->checkIsCourt($agendamento['Cliente']['id']);

                if ( isset($agendamento['AgendamentoConvite']) ) {
                    $agendamentos[$key]['Agendamento']['tipo'] = 'convidado';
                    $agendamentos[$key]['Agendamento']['convidado_por'] = [
                        'nome' => $agendamento['ClienteCliente']['nome'],
                        'foto' => $this->images_path.'usuarios/'.$agendamento['Usuario']['img'],
                    ];
                }
                else if ( $agendamento['Agendamento']['dia_semana'] != '' || $agendamento['Agendamento']['dia_mes'] != '' ) {
                    $agendamentos[$key]['Agendamento']['tipo'] = 'fixo';
                }

                $agendamentos[$key]['Agendamento']['_usuarios_confirmados'] = $this->AgendamentoConvite->getConfirmedUsers($agendamento['Agendamento']['id'], $this->images_path.'/usuarios/', $agendamento['Agendamento']['horario']);

                if ($cancelable === null) {
                    if ( $agendamentos[$key]['Agendamento']['tipo'] == 'tournament' ) 
                        $cancelable_return = false;
                    else 
                        $cancelable_return = $this->checkIsCancelable($agendamento['Agendamento']['horario'], $agendamento['Cliente']['prazo_maximo_para_canelamento'],$agendamentos[$key]['Agendamento']['tipo']);
                } else {
                    if ( $agendamentos[$key]['Agendamento']['tipo'] == 'tournament' ) 
                        $cancelable_return = false;
                    else
                        $cancelable_return = true;
                }

                $agendamentos[$key]['Agendamento']['_profissional'] = null;

                if ( !empty($agendamento['Agendamento']['profissional_id']) ) {
                    $dados_profissional = $this->Usuario->find('first', [
                        'fields' => ['Usuario.nome', 'Usuario.img'],
                        'conditions' => [
                            'Usuario.id' => $agendamento['Agendamento']['profissional_id']
                        ],
                        'link' => []
                    ]);

                    $agendamentos[$key]['Agendamento']['_profissional'] = [
                        'nome' => $dados_profissional['Usuario']['nome'],
                        'foto' => $this->images_path . 'usuarios/' . $dados_profissional['Usuario']['img']
                    ];

                }


                $agendamentos[$key]['Agendamento']['cancelable'] = $cancelable_return;
                unset($agendamentos[$key]['AgendamentoConvite']);

                $dados_agenda = $this->AgendamentoClienteCliente->find('first',[
                    'fields' => [
                        'AgendamentoClienteCliente.id_sync_google',
                        'AgendamentoClienteCliente.id_sync_ios',
                        'AgendamentoClienteCliente.data_sync_google',
                        'AgendamentoClienteCliente.data_sync_ios'
                    ],
                    'conditions' => [
                        'AgendamentoClienteCliente.agendamento_id' => $agendamentos[$key]['Agendamento']['id'],
                        'AgendamentoClienteCliente.cliente_cliente_id' => $meus_ids_de_cliente
                    ],
                    'link' => []
                ]);

                $agendamentos[$key]['Agendamento']['_dados_agenda'] = count($dados_agenda) > 0 ? $dados_agenda['AgendamentoClienteCliente'] : [];
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $agendamentos))));
    }

    public function view($id = null) {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( empty($id) ) {
            throw new BadRequestException('Agendamento não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $agendamento_id = $id;

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('ClienteServicoFoto');
        $this->loadModel('Localidade');
        $this->loadModel('UruguaiCidade');
        $this->loadModel('Usuario');
        $this->loadModel('ClienteCliente');
        $this->loadModel('ClienteServico');
        $this->loadModel('AgendamentoFixoCancelado');

        $conditions = [
            'Agendamento.id' => $agendamento_id
        ];

        // é usuário de empresa
        if ( $dados_token['Usuario']['nivel_id'] == 2 ) {
            $conditions['Agendamento.cliente_id'] = $dados_token['Usuario']['cliente_id'];
        }

        // é usuário final
        if ( $dados_token['Usuario']['nivel_id'] == 3 ) {
            $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_token['Usuario']['id'], true);
            
            $conditions['OR'] = [
                ['Agendamento.cliente_cliente_id' => $meus_ids_de_cliente],
                ['AgendamentoClienteCliente.cliente_cliente_id' => $meus_ids_de_cliente],
                ['TorneioInscricaoJogadorTimeUm.cliente_cliente_id' => $meus_ids_de_cliente],
                ['TorneioInscricaoJogadorTimeDois.cliente_cliente_id' => $meus_ids_de_cliente]
            ];
        }

        $agendamento = $this->Agendamento->find('first',[
            'conditions' => $conditions,
            'fields' => [
                'Agendamento.*',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.telefone',
                'ClienteCliente.telefone_ddi',
                'ClienteCliente.pais',
                'Cliente.id',
                'Cliente.nome',
                'Cliente.logo',
                'Cliente.cidade_id',
                'Cliente.ui_cidade',
                'Cliente.estado',
                'Cliente.pais',
                'Cliente.endereco',
                'Cliente.endereco_n',
                'Cliente.bairro',
                'Cliente.telefone_ddi',
                'Cliente.telefone',
                'Cliente.wp_ddi',
                'Cliente.wp',
                'Cliente.prazo_maximo_para_canelamento',
                'ClienteCliente.img',
                'ClienteCliente.endereco',
                'ClienteCliente.endreceo_n',
                'Localidade.loc_no',
                'ClienteServico.id',
                'ClienteServico.nome',
                'ClienteServico.tipo',
                'ClienteServico.descricao',
                'Torneio.nome',
                'Torneio.descricao',
                'Torneio.inicio',
                'Torneio.fim',
                'Torneio.img',
                'TorneioJogo.time_1',
                'TorneioJogo.time_2',
                'TorneioJogo.fase_nome',
                'TorneioCategoria.nome',
                'TorneioCategoria.sexo',
                'PadelCategoria.titulo',
                'TorneioQuadra.servico_id',
                'TorneioQuadra.nome',
                '(SELECT AVG(ClienteServicoAvaliacao.avaliacao) 
                FROM cliente_servico_avaliacoes ClienteServicoAvaliacao 
                WHERE ClienteServicoAvaliacao.cliente_servico_id = ClienteServico.id) AS avg_avaliacao',
                '(SELECT AVG(avaliacao) FROM cliente_servico_avaliacoes WHERE cliente_servico_avaliacoes.cliente_servico_id IN (SELECT id FROM clientes_servicos WHERE clientes_servicos.cliente_id = Cliente.id)) as cliente_avg_avaliacao',
                'Usuario.nome',
                'Usuario.created',
                'Usuario.img',
                'ClienteCliente.created'
            ],
            'link' => [
                'ClienteCliente' => [
                    'Localidade',
                    'Usuario'
                ], 
                'AgendamentoClienteCliente',
                'Cliente', 
                'ClienteServico',  
                'Torneio',
                'TorneioJogo' => [
                    'TorneioCategoria' => [
                        'PadelCategoria'
                    ],
                    'TorneioQuadra',
                    'TorneioJogoTimeUm' => [
                        'TorneioInscricaoJogadorTimeUm'
                    ],
                    'TorneioJogoTimeDois' => [
                        'TorneioInscricaoJogadorTimeDois'
                    ]
                ]
            ]
        ]);

        if ( count($agendamento) === 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        $agendamento['Agendamento']['tipo'] = 'padrao';

        if ( $agendamento['Agendamento']['dia_semana'] != '' ) {
            if (!isset($dados['horario'])) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
            } else {
                $dia_semana_horario_informado = date("w", strtotime($dados['horario']));
                if ( $dia_semana_horario_informado == $agendamento['Agendamento']['dia_semana'] ) {
                    $agendamento['Agendamento']['horario'] = $dados['horario'];
                    $agendamento['Agendamento']['tipo'] = 'fixo';
                } else {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
                }
            }
        }
        else if ( $agendamento['Agendamento']['dia_mes'] != '' ) {
            if (!isset($dados['horario'])) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
            } else {
                $dia_mes_horario_informado = date("d", strtotime($dados['horario']));
                if ( $dia_mes_horario_informado == $agendamento['Agendamento']['dia_mes'] ) {
                    $agendamento['Agendamento']['horario'] = $dados['horario'];
                    $agendamento['Agendamento']['tipo'] = 'fixo';
                } else {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
                }
            }
        }

        if ( !empty($agendamento['Agendamento']['dia_semana']) || !empty($agendamento['Agendamento']['dia_mes'])) {
            $cancelamento = $this->AgendamentoFixoCancelado->find('first',[
                'conditions' => [
                    'AgendamentoFixoCancelado.agendamento_id' => $agendamento['Agendamento']['id'],
                    'AgendamentoFixoCancelado.horario' => $agendamento['Agendamento']['horario']
                ],
                'link' => []
            ]);
        }

        // Agendamento de torneio
        if ( !empty($agendamento['Agendamento']['torneio_id']) ) {
            $agendamento['Agendamento']['tipo'] = 'torneio';
    
            $agendamento['Agendamento']['_can_cancell'] = false;
            $agendamento['Agendamento']['_cannot_cancell_motive'] = 'Agendamento de torneio';
            $agendamento['Agendamento']['_can_invite_players'] = false;
            $agendamento['Agendamento']['_cannot_invite_players_motive'] = 'Agendamento de torneio';

            if ( !empty($agendamento['TorneioQuadra']['servico_id']) ) {
                $dados_servico = $this->ClienteServico->find('first',[
                    'fields' => [
                        'ClienteServico.id',
                        'ClienteServico.nome',
                        'ClienteServico.tipo',
                        'ClienteServico.descricao',
                        'ClienteServico.avg_avaliacao',
                    ],
                    'conditions' => [
                        'ClienteServico.id' => $agendamento['TorneioQuadra']['servico_id']
                    ],
                    'link' => []
                ]);
                $agendamento['ClienteServico'] = $dados_servico['ClienteServico'];
                $agendamento[0]['avg_avaliacao'] = $dados_servico['ClienteServico']['avg_avaliacao'];
            } else {
                $agendamento['ClienteServico']['nome'] = $agendamento['TorneioQuadra']['nome'];
                $agendamento['ClienteServico']['tipo'] = 'Quadra';
            }

            $this->loadModel('TorneioInscricaoJogador');
        
            $time_1 = $this->TorneioInscricaoJogador->buscaJogadoresComFoto($agendamento['TorneioJogo']['time_1'], $this->images_path);
            $time_2 = $this->TorneioInscricaoJogador->buscaJogadoresComFoto($agendamento['TorneioJogo']['time_2'], $this->images_path);

            $agendamento['TorneioJogo']['time_1'] = $time_1;
            $agendamento['TorneioJogo']['time_2'] = $time_2;

            if ( !empty($agendamento['Torneio']['img']) ) {
                $agendamento['Torneio']['img'] = $this->images_path . 'torneios/' . $agendamento['Torneio']['img'];
            }

        } else {

            // Verificação de permissões
            $agendamento['Agendamento']['_can_cancell'] = true;
            $agendamento['Agendamento']['_cannot_cancell_motive'] = null;
            $agendamento['Agendamento']['_can_invite_players'] = true;
            $agendamento['Agendamento']['_cannot_invite_players_motive'] = null;
    
            if ( $agendamento['ClienteServico']['tipo'] != 'Quadra'){
                $agendamento['Agendamento']['_can_invite_players'] = false;
                $agendamento['Agendamento']['_cannot_invite_players_motive'] = 'O serviço não é uma quadra.';
            } else if ( $dados_token['Usuario']['nivel_id'] != 3 || !in_array($agendamento['Agendamento']['cliente_cliente_id'], $meus_ids_de_cliente) ) {
                $agendamento['Agendamento']['_can_invite_players'] = false;
                $agendamento['Agendamento']['_cannot_invite_players_motive'] = 'Somente o titular pode convidar jogadores';
            }

            $horario_agendamento = strtotime($agendamento['Agendamento']['horario']);
            $prazo_max_cancelamento = $agendamento['Cliente']['prazo_maximo_para_canelamento'];
            list($horas, $minutos, $segundos) = explode(':', $prazo_max_cancelamento);
            $prazo_max_cancelamento_minutos = ($horas * 60) + $minutos;
    
            $limite_cancelamento = strtotime("-{$prazo_max_cancelamento_minutos} minutes", $horario_agendamento);
    
            $agendamento['Agendamento']['_cancellable_until'] = date('Y-m-d H:i:s', $limite_cancelamento);
    
            if ( !empty($cancelamento) ) {
                $agendamento['Agendamento']['_can_cancell'] = false;
                $agendamento['Agendamento']['_cannot_cancell_motive'] = 'Agendamento cancelado';
                $agendamento['Agendamento']['_cancelled_by_name'] = null;
                $agendamento['Agendamento']['_cancelled_date'] = date('d/m', strtotime($cancelamento['AgendamentoFixoCancelado']['created']));
                $agendamento['Agendamento']['_cancelled_time'] = date('H:i', strtotime($cancelamento['AgendamentoFixoCancelado']['created']));
                $agendamento['Agendamento']['cancelado'] = "Y";
        
                if ( !empty($cancelamento['AgendamentoFixoCancelado']['cancelado_por_id']) ) {
                    $usuario_cancelou = $this->Usuario->getById($cancelamento['AgendamentoFixoCancelado']['cancelado_por_id']);
                    $agendamento['Agendamento']['_cancelled_by_name'] = $usuario_cancelou['Usuario']['nome'];
                }

                if ( !empty($cancelamento['AgendamentoFixoCancelado']['motivo']) ) {
                    $agendamento['Agendamento']['_cancelled_by_name'] = $agendamento['Agendamento']['_cancelled_by_name']." ".$cancelamento['AgendamentoFixoCancelado']['motivo'];
                }

            }
            else if ( $agendamento['Agendamento']['cancelado'] === 'Y' ) {
                $agendamento['Agendamento']['_can_cancell'] = false;
                $agendamento['Agendamento']['_cannot_cancell_motive'] = 'Agendamento cancelado';
                $agendamento['Agendamento']['_cancelled_by_name'] = null;
                $agendamento['Agendamento']['_cancelled_date'] = date('d/m', strtotime($agendamento['Agendamento']['updated']));
                $agendamento['Agendamento']['_cancelled_time'] = date('H:i', strtotime($agendamento['Agendamento']['updated']));
        
                if ( !empty($agendamento['Agendamento']['cancelado_por_id']) ) {
                    $usuario_cancelou = $this->Usuario->getById($agendamento['Agendamento']['cancelado_por_id']);
                    $agendamento['Agendamento']['_cancelled_by_name'] = $usuario_cancelou['Usuario']['nome'];
                }
            } else if ( $agendamento['Agendamento']['horario'] < date('Y-m-d H:i:s') ) {
                $agendamento['Agendamento']['_can_cancell'] = false;
                $agendamento['Agendamento']['_cannot_cancell_motive'] = 'O agendamento já passou';
            } else if ( $dados_token['Usuario']['nivel_id'] == 3 && !in_array($agendamento['Agendamento']['cliente_cliente_id'], $meus_ids_de_cliente) ) {
                $agendamento['Agendamento']['tipo'] = 'convidado';    
                $agendamento['Agendamento']['_can_cancell'] = false;
                $agendamento['Agendamento']['_cannot_cancell_motive'] = 'Somente o titular do horário pode cancelar.';                
            } 
            else if (time() > $limite_cancelamento && $dados_token['Usuario']['nivel_id'] != 2 ) {
                $agendamento['Agendamento']['_can_cancell'] = false;
                $agendamento['Agendamento']['_cannot_cancell_motive'] = 'O prazo para cancelamento já expirou.';
            }

            // Dados do titular do horário
            if ( !empty($agendamento['Usuario']['img']) ) {
                $agendamento['ClienteCliente']['img'] = $this->images_path . 'usuarios/' . $agendamento['Usuario']['img'];
                $agendamento['ClienteCliente']['created'] = $agendamento['Usuario']['created'];
            } else {
                $agendamento['ClienteCliente']['img'] = $this->images_path . 'clientes_clientes/' . $agendamento['ClienteCliente']['img'];
                $agendamento['ClienteCliente']['created'] = null;
            }
        
            // Dados do profissional, se tiver
            $agendamento['_profissional'] = [];
            if ( !empty($agendamento['Agendamento']['profissional_id']) ) {
    
                $profissional = $this->Usuario->find('first', [
                    'fields' => [
                        'Usuario.img',
                        'Usuario.nome',
                        'Usuario.email'
                    ],
                    'conditions' => [
                        'Usuario.id' => $agendamento['Agendamento']['profissional_id']
                    ],
                    'link' => []
                ]);
    
                if ( count($profissional) > 0 ) {
                    $profissional['Usuario']['img'] = $this->images_path . 'usuarios/' . $profissional['Usuario']['img'];
                }
    
                $agendamento['_profissional'] = $profissional;
    
            }

        }

        $agendamento['Cliente']['logo'] = $this->images_path . 'clientes/' . $agendamento['Cliente']['logo'];
        $agendamento['Agendamento']['horario_str'] = date('d/m',strtotime($agendamento['Agendamento']['horario']))." às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
        $data_agendamento = date('Y-m-d',strtotime($agendamento['Agendamento']['horario']));
            
        $agendamento['Agendamento']['valor_br'] = number_format($agendamento['Agendamento']['valor'], 2, ',', '.');
        $agendamento['Cliente']['isCourt'] = $this->ClienteSubcategoria->checkIsCourt($agendamento['Cliente']['id']);

        if ( $data_agendamento == date('Y-m-d') ) {
            $agendamento['Agendamento']['horario_str'] = "Hoje às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
        }

        // Fotos do serviço
        $fotos = $this->ClienteServicoFoto->find('list', [
            'fields' => [
                'ClienteServicoFoto.id',
                'ClienteServicoFoto.imagem'
            ],
            'conditions' => [
                'ClienteServicoFoto.cliente_servico_id' => $agendamento['Agendamento']['servico_id']
            ],
            'link' => [],
        ]);

        foreach( $fotos as $key_foto => $foto ) {
            $fotos[$key_foto] = $this->images_path . 'servicos/' . $foto;
        }
        
        $agendamento['ClienteServico']['_fotos'] = $fotos;

        // Localidade da empresa
        if ( $agendamento['Cliente']['pais'] === 'Brasil' ) {
            $dados_localidade = $this->Localidade->find('first',[
                'fields' => [
                    'Localidade.loc_no'
                ],
                'conditions' => [
                    'Localidade.loc_nu_sequencial' => $agendamento['Cliente']['cidade_id']
                ],
                'link' => []
            ]);

            $agendamento['Cliente']['_cidade'] = $dados_localidade['Localidade']['loc_no'];

        } else {
            $dados_localidade = $this->UruguaiCidade->find('first',[
                'fields' => [
                    'UruguaiCidade.nome'
                ],
                'conditions' => [
                    'UruguaiCidade.id' => $agendamento['Cliente']['ui_cidade']
                ],
                'link' => []
            ]);

            $agendamento['Cliente']['_cidade'] = $dados_localidade['UruguaiCidade']['nome'];

        }

        if ( $agendamento['ClienteServico']['tipo'] === 'Quadra' ) {
            $this->loadModel('ClienteCliente');
            $cliente_cliente = $this->ClienteCliente->find('all',[
                'fields' => [
                    'Usuario.nome',
                    'Usuario.img',
                    'ClienteCliente.id',
                    'ClienteCliente.img',
                    'ClienteCliente.nome',
                    'AgendamentoConvite.id',
                    'Agendamento.cliente_cliente_id',
                    'ClienteCliente.id'
                ],
                'conditions' => [
                    'OR' => [
                        [
                            'Agendamento.id' => $agendamento['Agendamento']['id']
                        ],
                        [
                            'AgendamentoClienteCliente.agendamento_id' => $agendamento['Agendamento']['id']
                        ],
                    ]
                ],
                'link' => [
                    'Usuario',
                    'Agendamento' => [
                        'AgendamentoConvite' => [
                            'conditions' => [
                                'AgendamentoConvite.cliente_cliente_id = ClienteCliente.id',
                                'AgendamentoConvite.agendamento_id' => $agendamento['Agendamento']['id'],
                                'AgendamentoConvite.horario' => $agendamento['Agendamento']['horario']
                            ]
                        ]
                    ],
                    'AgendamentoClienteCliente'
                ],
                'group' => [
                    'ClienteCliente.id'
                ]
            ]);

            $jogadores_confirmados = [];
            foreach( $cliente_cliente as $cli ) {

                if ( $cli['ClienteCliente']['id'] != $cli['Agendamento']['cliente_cliente_id'] && empty($cli['AgendamentoConvite']['id']) ) {
                    continue;
                }
        
                $jogadores_confirmados[] = [
                    'img' => !empty($cli['Usuario']['img']) ? $this->images_path.'usuarios/'.$cli['Usuario']['img'] : $this->images_path.'cliente_cliente/'.$cli['ClienteCliente']['img'],
                    'nome' => !empty($cli['Usuario']['nome']) ? $cli['Usuario']['nome'] : $cli['ClienteCliente']['nome'],
                    'convite_id' => !empty($cli['AgendamentoConvite']['id']) ? $cli['AgendamentoConvite']['id'] : null,
                ]; 
            }

            $agendamento['_confirmed_players'] = $jogadores_confirmados;
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $agendamento))));
    }

    public function empresa() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['data']) || $dados['data'] == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }
        if ( !isset($dados['type']) || $dados['type'] == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $data = $dados['data'];
        $type = $dados['type'];
        $year_week = date('oW',strtotime($data. ' +1 day'));

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteHorarioAtendimento');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');
        $this->loadModel('AgendamentoFixoCancelado');
        $this->loadModel('ClienteServicoHorario');

        $aditional_conditions = [];

        if ( isset($dados["cliente_cliente_id"]) && !empty($dados["cliente_cliente_id"]) ) {
            $aditional_conditions["Agendamento.cliente_cliente_id"] = $dados["cliente_cliente_id"];
        }

        if ( isset($dados["services_ids"]) ) {
            $aditional_conditions["Agendamento.servico_id"] = $dados["services_ids"];
        }

        if ( isset($dados["servicos"]) && is_array($dados["servicos"]) && count($dados["servicos"]) > 0 ) {
            $aditional_conditions["Agendamento.servico_id"] = $dados["servicos"];
        }

        if ( isset($dados["profissionais"]) && is_array($dados["profissionais"]) && count($dados["profissionais"]) > 0 ) {
            $aditional_conditions["Agendamento.profissional_id"] = $dados["profissionais"];
        }

        $agendamentos = $this->Agendamento->buscaAgendamentoEmpresa($dados_token['Usuario']['cliente_id'],$type,$data,$year_week,$aditional_conditions);
        $agendamentos = $this->ClienteHorarioAtendimentoExcessao->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->ClienteServicoHorario->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->AgendamentoFixoCancelado->checkStatus($agendamentos);

        if ( count($agendamentos) > 0 ) {
            usort($agendamentos, function($a, $b) {
                return $a['Agendamento']['horario'] <=> $b['Agendamento']['horario'];
            });
        }
    

        $dados_retornar = $this->formataAgendamentos($agendamentos, $data, $type);
        //$dados_retornar = json_encode($dados_retornar, true);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));
    }

    public function proximo() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');
        $this->loadModel('ClienteServicoHorario');
        $this->loadModel('AgendamentoFixoCancelado');
    
        $data = date('Y-m-d');
        $year_week = date('oW',strtotime($data. ' +1 day'));

        $agendamentos = $this->Agendamento->buscaAgendamentoEmpresa($dados_token['Usuario']['cliente_id'],1,$data,$year_week,[]);
        $agendamentos = $this->ClienteHorarioAtendimentoExcessao->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->ClienteServicoHorario->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->AgendamentoFixoCancelado->checkStatus($agendamentos);

        if ( count($agendamentos) > 0 ) {
            usort($agendamentos, function($a, $b) {
                return $a['Agendamento']['horario'] <=> $b['Agendamento']['horario'];
            });
        }
    

        $dados_retornar = $this->formataAgendamentos($agendamentos, $data, 1);

        $proximo_agendamento = [];
        foreach( $dados_retornar as $agendamento_data => $agendamento_agendamentos ){

            if ( $agendamento_data >= date('Y-m-d') && count($agendamento_agendamentos) > 0 && count($proximo_agendamento) === 0 ) {

                foreach( $agendamento_agendamentos as $key_ag => $agendamento ){

                    if ( $agendamento['name'].":00" >= date('H:i:s') && $agendamento['status'] == 'confirmed' && count($proximo_agendamento) == 0 ) {

                        $proximo_agendamento = $agendamento;
                        break;

                    }

                }
            }

        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $proximo_agendamento))));
    }

    private function formataAgendamentos($agendamentos = [], $data = '', $type = '') {

        if ( $data == '' )
            return [];
        if ( $type == '' )
            return [];

        $php_date = new DateTime($data);
        $day_of_week = $php_date->format("w");
        $php_date->modify("-".($day_of_week)." days");
        $primeiro_dia_semana = $php_date->format('Y-m-d');
        $primeiro_dia_mes = date('Y-m-01',strtotime($data));
        $ultimo_dia_mes = date("t-m-Y", strtotime($data));

        $arr_retornar = [];

        if ($type == 1) {
            $proxima_data = $primeiro_dia_mes;
            while (strtotime($proxima_data) <= strtotime($ultimo_dia_mes)) {
                $arr_retornar[$proxima_data] = [];
                $proxima_data = date('Y-m-d',strtotime($proxima_data." + 1 days"));
            }

        } else if ( $type == 2 ) {
            
            $proxima_data = $primeiro_dia_semana;
            for ($i = 0; $i <= 6; $i++) {
                $arr_retornar[$proxima_data] = [];
                $proxima_data = date('Y-m-d',strtotime($proxima_data." + 1 days"));
            }
        }

        $last_data = '';
        $count = -1;
        if ( count($agendamentos) > 0 ) {
            $ultimo_horario = "";
            $cor = $this->list_even_color;
            foreach( $agendamentos as $key => $agend) {
                $hora = date('H:i',strtotime($agend['Agendamento']['horario']));
                $data = date('Y-m-d',strtotime($agend['Agendamento']['horario']));
                $duracao = $agend['Agendamento']['duracao'];

                if ( $duracao != '') {
                    $timeBase = new DateTime($agend['Agendamento']['horario']);
                    list($hours,$minutes,$seconds) = explode(':',$duracao);
                    $timeToAdd = new DateInterval('PT'.$hours.'H'.$minutes.'M'.$seconds.'S'); 
                    $timeBase->add($timeToAdd);
                    $duracao = $timeBase->format('H:i');
                }

                $tipo = "Padrão";
                $imagem = $this->images_path;
    
                if ( $agend['Agendamento']['dia_semana'] != null ||  $agend['Agendamento']['dia_mes'] != null ) {
                    $tipo = "Fixo";
                }

                if ( isset($agend['Agendamento']['torneio_id']) && $agend['Agendamento']['torneio_id'] != null ) {
                    $tipo = "Torneio";
                    $agend['ClienteCliente']['nome'] = "Jogo de Torneio";
                    $imagem .= "torneios/".$agend['Torneio']['img'];
                } else {
                    if ( isset($agend['Usuario']['img']) && !empty($agend['Usuario']['img']) ) {
                        $imagem .= 'usuarios/'.$agend['Usuario']['img'];
                    } else {
                        $imagem .= 'clientes_clientes/'.$agend['ClienteCliente']['img'];
                    }
                }

                if ( $ultimo_horario != $agend['Agendamento']['horario'] ) {
                    $ultimo_horario = $agend['Agendamento']['horario'];
                    $cor = ($cor == $this->list_odd_color) ? $this->list_even_color : $this->list_odd_color;
                }

                $arr_dados = [
                    'name' => $hora, 
                    'admin_id' => isset($agend['ClienteServico']['id']) ?  $agend['ClienteServico']['id'] : $agend['Agendamento']['cliente_id'], 
                    'height' => $agend['Agendamento']['endereco'] == '' || $agend['Agendamento']['endereco'] == '' ? 100 : 120, 
                    "bg_color" => $cor,
                    'usuario' => $agend['ClienteCliente']['nome'], 
                    'id' => $agend['Agendamento']['id'], 
                    'termino' => $duracao,
                    'img' => $imagem,
                    'servico' => $agend['ClienteServico']['nome'], 
                    'status' => $agend['Agendamento']['status'], 
                    'motive' => $agend['Agendamento']['motive'], 
                    'horario' => $agend['Agendamento']['horario'], 
                    'endereco' => $agend['Agendamento']['endereco'], 
                    'valor' => floatval($agend['Agendamento']['valor']), 
                    'tipo_str' => $tipo,
                ];

                if ( $data != $last_data ) {
                    $count++;
                    $arr_retornar[$data][] = $arr_dados;
                    $last_data = $data;
                } else {
                    $arr_retornar[$data][] = $arr_dados;
                }
            }
        }

        return $arr_retornar;

    }

    private function checkIsCancelable($horario, $prazo_maximo,$tipo) {
        
        if ( $tipo == 'convidado' ) {//se o agendamento é originado de um convite, não é possível cancelar
            return false;
        }

        if ($prazo_maximo == null || $prazo_maximo == '') {//se a empresa nào setou prazo para cancelamento, é possível cancelar
            return true;
        }

        list($horas,$minutos,$segundos) = explode(':',$prazo_maximo);

        $hs_in_unix = 0;
        if ($horas > 0) {
            $hs_in_unix = $horas * 60 * 60;
        }

        $min_in_unix = 0;
        if ($minutos > 0) {
            $min_in_unix = $minutos * 60;
        }

        $horario_unix = strtotime($horario);
        $horario_maximo_unix = $horario_unix-$hs_in_unix-$min_in_unix;
        $now_unix = strtotime(date('Y-m-d H:i:s'));
        /*debug($horario);
        debug(date('d/m/Y H:i',$horario_maximo_unix));
        debug(date('d/m/Y H:i',$now_unix));
        echo 'unix_max = '.$horario_maximo_unix.'<br>';
        echo 'agora ='.$now_unix.'<br>';*/

        return $horario_maximo_unix >= $now_unix;

    }

    public function add(){

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        } elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->day) || $dados->day == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }

        if ( !isset($dados->time) || $dados->time == "" ) {
            throw new BadRequestException('Hora não informada!', 401);
        }

        if ( !isset($dados->servico_id) || $dados->servico_id == "" || !is_numeric($dados->servico_id) ) {
            throw new BadRequestException('Serviço não informado!', 401);
        }

        $data_selecionada = $dados->day;
        $horario_selecionado = $dados->time;

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteCliente');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');
        $this->loadModel('Cliente');        
        $this->loadModel('Token');
        $this->loadModel('ClienteServico');
        $this->loadModel('AgendamentoFixoCancelado');
        $this->loadModel('AgendamentoClienteCliente');

        $dados_servico = $this->ClienteServico->find("first",[
            "conditions" => [
                "ClienteServico.id" => $dados->servico_id,
                "ClienteServico.ativo" => 'Y'
            ],
            'link' => []
        ]);

        if ( count($dados_servico) === 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do serviço não encontrados.'))));
        }

        //verifico quem está tentando salvar o agendamento, se é uma empresa ou um usuário
        if ( $dados_usuario['Usuario']['cliente_id'] != '' && $dados_usuario['Usuario']['cliente_id'] != null ) {

            if ( !isset($dados->cliente_cliente_id) || $dados->cliente_cliente_id == "" ) {
                throw new BadRequestException('Cliente não informado!', 401);
            }
    
            $cliente_id = $dados_usuario['Usuario']['cliente_id'];
            $cliente_cliente_id = $dados->cliente_cliente_id;
            $cadastrado_por = 'cliente';
            $dados_cliente_cliente = $this->ClienteCliente->buscaDadosClienteCliente($cliente_cliente_id, $cliente_id);
    
            if ( !$dados_cliente_cliente || count($dados_cliente_cliente) == 0) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Lamentamos. Não conseguimos encontrar os dados do cliente! ;('))));
            }

        } else {
            $cadastrado_por = 'cliente_cliente';

            if ( !isset($dados->cliente_id) || $dados->cliente_id == "" || !is_numeric($dados->cliente_id) ) {
                throw new BadRequestException('Dados da empresa não informada!', 401);
            }

            //busca os dados do usuário do agendamento como cliente
            $dados_usuario_como_cliente = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], $dados->cliente_id);
    
            if ( !$dados_usuario_como_cliente || count($dados_usuario_como_cliente) == 0) {
                $dados_usuario_como_cliente = $this->ClienteCliente->criaDadosComoCliente($dados_usuario['Usuario']['id'], $dados->cliente_id);
                if ( !$dados_usuario_como_cliente || count($dados_usuario_como_cliente) == 0) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Lamentamos. Não conseguimos encontrar seus dados! ;('))));
                }

            }

            $cliente_id = $dados->cliente_id;
            $cliente_cliente_id = $dados_usuario_como_cliente['ClienteCliente']['id'];
    
        }

        if ( isset($dados->domicilio) && $dados->domicilio == true ) {
            if (!isset($dados->endereco) || $dados->endereco == '') {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Endereço de atendimento não informado.'))));
            }
        }

        //busca os dados da empresa
        $dados_cliente = $this->Cliente->find('first',[
            'fields' => ['Cliente.id', 'Localidade.loc_no', 'Localidade.ufe_sg'],
            'conditions' => [
                'Cliente.id' => $cliente_id,
                'Cliente.ativo' => 'Y'
            ],
            'link' => ['Localidade']
        ]);

        if (count($dados_cliente) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Empresa não encontrada!'))));
        }

        //verfica se o cliente fechará excepcionalmente nesse dia no dia
        $verificaFechamento = $this->ClienteHorarioAtendimentoExcessao->verificaExcessao($cliente_id, $data_selecionada, 'F');
        if ( count($verificaFechamento) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A empresa não atenderá no dia e horário escolhido!'))));
        }

        //verifica se o usuário já não possui um agendamento pro mesmo dia e horário que está tentando
        $verificaAgendamento = $this->Agendamento->verificaAgendamento($cliente_cliente_id, null, $data_selecionada, $horario_selecionado);
        if ( $verificaAgendamento !== false && count($verificaAgendamento) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você já tem um agendamento neste dia e hora!'))));
        }

        // Busca os horários do serviço disponíveis para o dia selecionado
        $horarios = $this->quadra_horarios($dados->servico_id, $data_selecionada);

        if ( count($horarios) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. A empresa não informou os horários de atendimento deste serviço nesse dia! ;('))));
        }

        $horario_x_horario_selecionado = [];
        foreach( $horarios as $key => $horario ){
            if ( $horario['time'] === $horario_selecionado ) {
                $horario_x_horario_selecionado = $horario;
            }
        }

        if ( count($horario_x_horario_selecionado) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. Não encontramos os dados do horário selecionado! ;('))));
        }

        // Se o horário selecionado não está dispnível
        if ( !$horario_x_horario_selecionado['active'] ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. ' . $horario_x_horario_selecionado['motivo'] . ' ;('))));
        }
        
        $agendamento_dia_semana = null;
        $agendamento_dia_mes = null;

        $complement_msg = "";
        $fixos_cancelar = [];

        $valor_agendamento = $horario_x_horario_selecionado['default_value'];

        //verifica se o usuário/empresa está tentando salvar um agendamento fixo
        if ( isset($dados->fixo) && $dados->fixo == true ) {

            // Se o agendamento fixo não está disponível para o horário
            if (!$horario_x_horario_selecionado['enable_fixed_scheduling'] ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. Esse horário fixo já pertence a outro usuário ou não aceita agendamentos fixos! ;('))));
            }

            // Verifica se tem algum agendamento padrão depois dess fixo
            $agendamentos_padrao = $this->Agendamento->find('all',[
                'conditions' => [
                    'Agendamento.servico_id' => $dados->servico_id,
                    'Agendamento.dia_semana' => null,
                    'Agendamento.dia_mes' => null,
                    'Agendamento.horario >=' => $dados->day . ' ' . $dados->time,
                    'Agendamento.cancelado' => 'N'
                ],
                'link' => []
            ]);

            if ( count($agendamentos_padrao) > 0 ) {
                $complement_msg .= " Existem agendamentos não fixos nos próximos dias no seu horario, dia(s): ";

                $arr_dias = [];
                foreach( $agendamentos_padrao as $key => $ag ) {
                    $arr_dias[] = date('d/m', strtotime($ag['Agendamento']['horario']));
                    $fixos_cancelar[] = $ag['Agendamento']['horario'];
                }

                $complement_msg .= implode(', ', $arr_dias);
            }

            if ( $horario_x_horario_selecionado['fixed_type'] === 'Semanal' ) {
                $agendamento_dia_semana = date('w',strtotime($data_selecionada.' '.$horario_selecionado));
            }
            else if ( $$horario_x_horario_selecionado['fixed_type'] === 'Mensal' ) {
                $agendamento_dia_mes = (int)date('d',strtotime($data_selecionada.' '.$horario_selecionado));
            }

            $valor_agendamento = $horario_x_horario_selecionado['fixed_value'];
        }

        $profissional_id = null;
        if ( $dados_servico["ClienteServico"]["tipo"] === "Serviço" ) {
            if ( !isset($dados->profissional_id) || empty($dados->profissional_id) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Profissional não informado.'))));
            }

            $profissional_id = $dados->profissional_id;
        }

        $dados_salvar = [
            'cliente_id' => $cliente_id,
            'cliente_cliente_id' => $cliente_cliente_id,
            'servico_id' => $dados->servico_id,
            'horario' => $data_selecionada.' '.$horario_selecionado,
            'domicilio' => !$dados->domicilio ? 'N' : 'Y',
            'endereco' => $dados->endereco,
            'dia_semana' => $agendamento_dia_semana,
            'dia_mes' => $agendamento_dia_mes,
            'duracao' => $horario_x_horario_selecionado['duration'],
            'profissional_id' => $profissional_id,
            'valor' => $valor_agendamento
        ];

        if ( isset($dados->convites_tpj) && is_array($dados->convites_tpj)) {
            $dados->convites_tpj = (object)$dados->convites_tpj;
        }

        if ( isset($dados->convites_grl) && is_array($dados->convites_grl)) {
            $dados->convites_grl = (object)$dados->convites_grl;
        }

        $this->Agendamento->create();
        $this->Agendamento->set($dados_salvar);
        $dados_agendamento_salvo = $this->Agendamento->save($dados_salvar);

        //$dados_agendamento_salvo = true;
        if ( !$dados_agendamento_salvo ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cadastrar seu agendamento!'))));
        }

        // Cria um registro na tabela de sincronização
        $this->AgendamentoClienteCliente->add($dados_agendamento_salvo['Agendamento']['cliente_cliente_id'], $dados_agendamento_salvo['Agendamento']['id']);

        if ( count($fixos_cancelar) > 0 ) {

            $dados_cancelamentos_salvar = [];
            foreach( $fixos_cancelar as $key => $fx_cancel ){
                $dados_cancelamentos_salvar[] = [
                    'agendamento_id' => $dados_agendamento_salvo['Agendamento']['id'],
                    'cliente_cliente_id' => $dados_agendamento_salvo['Agendamento']['cliente_cliente_id'],
                    'horario' => $fx_cancel,
                    'cancelado_por' => $cadastrado_por,
                    'cancelado_por_id' => $dados_usuario['Usuario']['id']
                ];

            }

            $this->AgendamentoFixoCancelado->saveMany($dados_cancelamentos_salvar);

        }

        //busca os ids do onesignal do usuário a ser notificado do cadastro do horário
        $usuario_id = null;
        if ( $cadastrado_por == 'cliente' ) { // Agendamento feito pela empresa
            $usuario_id = $dados_cliente_cliente['ClienteCliente']['usuario_id'];
            $notifications_ids = $this->Token->getIdsNotificationsUsuario($dados_cliente_cliente['ClienteCliente']['usuario_id']);
            $cadastrado_por = $this->Cliente->findEmpresaNomeById($cliente_id);
            $notificacao_motivo = 'agendamento_empresa';
        } else {
            $notifications_ids = $this->Token->getIdsNotificationsEmpresa($cliente_id);
            $cadastrado_por = $dados_usuario['Usuario']['nome'];
            $notificacao_motivo = 'agendamento_usuario';
        }

        if ( count($notifications_ids) > 0 ) {
    
            $agendamento_data = date('Y-m-d',strtotime($dados_agendamento_salvo['Agendamento']['horario']));

            $this->sendNotificationNew( 
                $usuario_id,
                $notifications_ids, 
                $dados_agendamento_salvo['Agendamento']['id'],
                $agendamento_data,
                $notificacao_motivo,
                ["en"=> '$[notif_count] Novos Agendamentos']
            );
        }

        $this->enviaConvites($dados, $dados_agendamento_salvo, $dados_cliente['Localidade']);

        //$this->Agendamento->delete($dados_agendamento_salvo['Agendamento']['id']);
        
        return new CakeResponse([
            'type' => 'json', 
            'body' => json_encode(
                [
                    'status' => 'ok', 
                    'msg' => 'Tudo certo, Seu agendamento foi cadastrado com sucesso!'.$complement_msg,
                    'cliente_cliente_id' => $cliente_cliente_id
                ]
            )
        ]);
    }

    public function usuarios_verificar() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');

        $dataLimite = date('Y-m-d H:i:s', strtotime('-2 weeks'));
        //$dataLimite = date('Y-m-d H:i:s', strtotime('-4 years'));

        $usuarios_verificar = $this->Agendamento->find('all',[
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                'Usuario.img',
                'Usuario.created',
                'Usuario.telefone_ddi',
                'Usuario.telefone',
                'Usuario.email',
                'ClienteCliente.endereco',

                'ClienteCliente.id',
                'ClienteCliente.nacionalidade',
                'ClienteCliente.pais',
                'ClienteCliente.bairro',
                'ClienteCliente.endreceo_n',
                'ClienteCliente.cep',
                'Localidade.loc_no',
                'Uf.ufe_sg',
            ],
            'conditions' => [
                'Agendamento.cliente_id' => $dados_token['Usuario']['cliente_id'],
                'Usuario.created >=' => $dataLimite
            ],
            'link' => [
                'ClienteCliente' => [
                    'Usuario',
                    'Localidade',
                    'Uf'
                ],
                'ClienteServico'
            ],
            'group' => [
                'Usuario.id'
            ]
        ]);


        foreach( $usuarios_verificar as $key => $usuario ){ 
            $usuarios_verificar[$key]['ClienteCliente']['telefone_ddi'] = $usuarios_verificar[$key]['Usuario']['telefone_ddi'];
            $usuarios_verificar[$key]['ClienteCliente']['telefone'] = $usuarios_verificar[$key]['Usuario']['telefone'];
            $usuarios_verificar[$key]['ClienteCliente']['telefone'] = $usuarios_verificar[$key]['Usuario']['telefone'];
            $usuarios_verificar[$key]['ClienteCliente']['nome'] = $usuarios_verificar[$key]['Usuario']['nome'];
            $usuarios_verificar[$key]['ClienteCliente']['email'] = $usuarios_verificar[$key]['Usuario']['email'];
            $usuarios_verificar[$key]['ClienteCliente']['img'] = $this->images_path.'usuarios/'.$usuario['Usuario']['img'];
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $usuarios_verificar))));
    }

    public function convitesAdicionais(){
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }


        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->cliente_id) || $dados->cliente_id == "" || !is_numeric($dados->cliente_id) ) {
            throw new BadRequestException('Dados da empresa não informada!', 401);
        }

        if ( !isset($dados->horaSelecionada) || $dados->horaSelecionada == "" ) {
            throw new BadRequestException('Hora não informada!', 401);
        }

        if ( gettype($dados->horaSelecionada) === 'string' ) {
            list($data_selecionada, $horario_selecionado) = explode(' ',$dados->horaSelecionada);
        } else {
            list($data_selecionada, $horario_selecionado) = explode(' ',$dados->horaSelecionada->horario);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        $this->loadModel('ClienteCliente');
        $this->loadModel('Agendamento');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('Cliente');

        //busca os dados da empresa
        $dados_cliente = $this->Cliente->find('first',[
            'fields' => ['Cliente.id', 'Localidade.loc_no', 'Localidade.ufe_sg'],
            'conditions' => [
                'Cliente.id' => $dados->cliente_id,
                'Cliente.ativo' => 'Y'
            ],
            'link' => ['Localidade']
        ]);

        if (count($dados_cliente) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Empresa não encontrada!'))));
        }

        $agendamento_dia_semana = date('w',strtotime($data_selecionada.' '.$horario_selecionado));
        $agendamento_dia_mes = (int)date('d',strtotime($data_selecionada.' '.$horario_selecionado));

        //verifica se a empresa é uma quadra, se não for, nào sào permitidos convites
        $isCourt = $this->ClienteSubcategoria->checkIsCourt($dados->cliente_id);

        if (!$isCourt) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O agendamento não pertence a uma quadra!'))));
        }
        
        //busca os dados do usuário do agendamento como cliente
        $dados_usuario_como_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], true);
        $dados_agendamento = $this->Agendamento->find('first',[
            'conditions' => [
                'Agendamento.cliente_id' => $dados->cliente_id,
                'Agendamento.cliente_cliente_id' => $dados_usuario_como_cliente,
                'TIME(Agendamento.horario)' => $horario_selecionado,
                'Agendamento.cancelado' => 'N',
                'or' => [
                    [
                        'DATE(Agendamento.horario)' => $data_selecionada,
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => null,
                    ],[
                        'Agendamento.dia_semana' => $agendamento_dia_semana,
                    ],[
                        'Agendamento.dia_mes' => $agendamento_dia_mes,
                    ]
                ]
            ],
            'link' => []
        ]);

        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Agendamento não encontrado!'))));
        }

        if ( isset($dados->convites_tpj) && is_array($dados->convites_tpj)) {
            $dados->convites_tpj = (object)$dados->convites_tpj;
        }

        if ( isset($dados->convites_grl) && is_array($dados->convites_grl)) {
            $dados->convites_grl = (object)$dados->convites_grl;
        }

        if ( gettype($dados->horaSelecionada) === 'string' ) {
            $dados_agendamento['Agendamento']['horario'] = $dados->horaSelecionada;
        } else {
            $dados_agendamento['Agendamento']['horario'] = $dados->horaSelecionada->horario;
        }

        $this->enviaConvites($dados, $dados_agendamento, $dados_cliente['Localidade']);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! Convites enviados com sucesso!'))));
    }

    private function enviaConvites ($dados, $dados_agendamento_salvo, $cliente_localizacao) {

        $this->loadModel('Usuario');
        $this->loadModel('UsuarioLocalizacao');
    
        $clientes_clientes_ids_convidados = [];

        //convites do to pro jogo
        if (isset($dados->convites_tpj) && count(get_object_vars($dados->convites_tpj)) > 0) {
            foreach($dados->convites_tpj as $key => $convite){
                if($convite) {
                    list($discard, $id_convidado) = explode('_',$key);
                    $clientes_clientes_ids_convidados[] = $id_convidado;
                }
            }
        }

        //convites geral
        if (isset($dados->convites_grl) && count(get_object_vars($dados->convites_grl)) > 0) {
            $usuarios_perfil_convite = $this->Usuario->getClientDataByPadelistProfile($dados->convites_grl);
            $usuarios_perfil_convite = $this->UsuarioLocalizacao->filterByLastLocation($usuarios_perfil_convite, $cliente_localizacao);
            $clientes_clientes_ids_convidados = array_merge($clientes_clientes_ids_convidados, $usuarios_perfil_convite);
            $clientes_clientes_ids_convidados = array_values($clientes_clientes_ids_convidados);
        }

        if ( count($clientes_clientes_ids_convidados) > 0 ) {
            $this->saveInvitesAndSendNotification($clientes_clientes_ids_convidados, $dados_agendamento_salvo['Agendamento']);
        }

    }

    public function excluir(){
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->agendamento_id) || $dados->agendamento_id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        if ( !isset($dados->tipo) || $dados->tipo == "" ) {
            throw new BadRequestException('Tipo não informado!', 401);
        }

        if ( !isset($dados->horario) || $dados->horario == "" ) {
            throw new BadRequestException('Horário não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');

        $conditions = [
            'Agendamento.id' => $dados->agendamento_id,
        ];

        $cancelado_por = 'cliente';
        if ( $dados_usuario['Usuario']['cliente_id'] != '' ) {
            $conditions = array_merge($conditions, [
                'Agendamento.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ]);
        } else {
            $conditions = array_merge($conditions, [
                'ClienteCliente.usuario_id' => $dados_usuario['Usuario']['id']
            ]);
            $cancelado_por = 'cliente_cliente';
        }

        $dados_agendamento = $this->Agendamento->find('first',[
            'fields' => [
                'Agendamento.id', 
                'Agendamento.horario', 
                'Agendamento.dia_semana', 
                'Agendamento.dia_mes',  
                'ClienteCliente.*',
                'Cliente.id',
                'Cliente.nome',
                'Usuario.id', 
                'Usuario.nome'
            ],
            'conditions' => $conditions,
            'link' => [
                'ClienteCliente' => [
                    'Usuario'
                ], 
                'Cliente'
            ]
        ]);
       
        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O agendamento que você está tentando exlcuir, não existe!'))));
        }

        if ( $dados_agendamento['Agendamento']['dia_semana'] != '' || $dados_agendamento['Agendamento']['dia_mes'] != '' ) {
            if ( $dados->tipo == 1 ) {
                $this->loadModel('AgendamentoFixoCancelado');
                $dados_salvar = [
                    'agendamento_id' => $dados_agendamento['Agendamento']['id'],
                    'cliente_cliente_id' => $dados_agendamento['ClienteCliente']['id'],
                    'horario' => $dados->horario,
                    'cancelado_por' => $cancelado_por,
                    'cancelado_por_id' => $dados_usuario['Usuario']['id'],
                ];

                if ( !$this->AgendamentoFixoCancelado->save($dados_salvar) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cancelar o agendamento. Por favor, tente novamente mais tarde!'))));
                }

                // Seta o convite como agendamento cancelado e envia notificação aos usuários
                $this->avisaConvidadosCancelamento($dados->horario, $dados_agendamento['Agendamento']['id']);

                // Envia notificação de cancelamento para o usuário titular ou para a empresa
                $this->enviaNotificacaoDeCancelamento($cancelado_por, $dados->horario, $dados_agendamento);

                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Agendamento cancelado com sucesso!'))));

            } 
        }

        $dados_salvar['Agendamento']['id'] = $dados_agendamento['Agendamento']['id'];
        $dados_salvar['Agendamento']['cancelado'] = 'Y';        
        $dados_salvar['Agendamento']['cancelado_por_id'] = $dados_usuario['Usuario']['id'];

        if ( !$this->Agendamento->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cancelar o agendamento. Por favor, tente mais tarde!'))));
        }
        
        // Seta o convite como agendamento cancelado e envia notificação aos usuários
        $this->avisaConvidadosCancelamento($dados->horario, $dados_agendamento['Agendamento']['id']);

        // Envia notificação de cancelamento para o usuário titular ou para a empresa
        $this->enviaNotificacaoDeCancelamento($cancelado_por, $dados->horario, $dados_agendamento);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Agendamento cancelado com sucesso!'))));

    }

    public function setSyncId() {

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        } elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteCliente');
        $this->loadModel('AgendamentoClienteCliente');
    
        //busca os dados do usuário do agendamento como cliente
        $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], true);

        $dados_agendamento = $this->Agendamento->find('first',[
            'fields' => [
                'Agendamento.id',
                'AgendamentoClienteCliente.id',
                'Agendamento.cliente_cliente_id'
            ],
            'conditions' => [
                'Agendamento.id' => $dados->id,
                'OR' => [
                    'AgendamentoClienteCliente.cliente_cliente_id' => $meus_ids_de_cliente,
                    'Agendamento.cliente_cliente_id' => $meus_ids_de_cliente
                ]
            ],
            'link' => [
                'AgendamentoClienteCliente'
            ]
        ]);

        if ( count($dados_agendamento) === 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Agendamento não encontrado!'))));
        }

        $dados_salvar = [
            'agendamento_id' => $dados->id,
            'cliente_cliente_id' => $dados_agendamento['Agendamento']['cliente_cliente_id']
        ];

        if ( !empty($dados_agendamento['AgendamentoClienteCliente']['id']) ) {
            $dados_salvar['id'] = $dados_agendamento['AgendamentoClienteCliente']['id'];
        } else {
            $this->AgendamentoClienteCliente->create();
        }

        if ( strtolower($dados->plataforma) === 'ios' ) {
            $dados_salvar['id_sync_ios'] = $dados->id_sync;
            $dados_salvar['data_sync_ios'] = date('Y-m-d H:i:d');
        }  else {
            $dados_salvar['id_sync_google'] = $dados->id_sync;
            $dados_salvar['data_sync_google'] = date('Y-m-d H:i:d');
        }

        if ( !$this->AgendamentoClienteCliente->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao sincronizar o agendamento!'))));
        }
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Item sincronizado com sucesso!'))));

    }
}