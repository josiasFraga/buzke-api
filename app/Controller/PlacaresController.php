<?php
App::uses('CombinationsComponent', 'Controller/Component');
class PlacaresController extends AppController {

    public function torneios() {

        $this->layout = 'ajax';
        $dados = $this->request->query;


        $this->loadModel('Torneio');

        $conditions = [
            "Torneio.inicio <=" => date("Y-m-d"),
            "Torneio.fim >=" => date("Y-m-d")
        ];       

        $torneios = $this->Torneio->find('all',[
            'fields' => [
                'Torneio.id', 
                'Torneio.nome', 
                'Torneio.descricao', 
                'Torneio.img',
                'Torneio.inicio', 
                'Torneio.fim', 
                'Cliente.nome', 
                'Localidade.loc_no', 
                'Localidade.ufe_sg', 
                'Cliente.telefone'
            ],
            'conditions' => $conditions,
            'order' => ['Torneio.nome'],
            'group' => ['Torneio.id'],
            'link' => ['TorneioInscricao' => ['TorneioInscricaoJogador'], 'Cliente' => ['Localidade']]
        ]);
        
        //debug($conditions); die();

        foreach($torneios as $key => $trn){
            
            $torneios[$key]['Torneio']['_periodo'] = 
                'De '.date('d/m',strtotime($trn['Torneio']['inicio'])).
                ' até '.date('d/m',strtotime($trn['Torneio']['fim']));
            $torneios[$key]['Torneio']['img'] = $this->images_path."torneios/".$trn['Torneio']['img'];

        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $torneios))));

    }

    public function jogos($torneio_id = null){

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($torneio_id) || $torneio_id == "" || !$torneio_id ) {
            throw new BadRequestException('Torneio não informado!', 401);
        }

        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioInscricaoJogador');
        $this->loadModel('TorneioJogoPlacar');
        $this->loadModel('TorneioJogoPonto');
        $this->loadModel('TorneioGrupo');

        $conditions = [
            'Agendamento.torneio_id' => $torneio_id,
            'TorneioJogo.finalizado' => "N",
            'ADDTIME(Agendamento.horario, Agendamento.duracao) >=' => date("Y-m-d H:i:s"),
            'not' => [
                'TorneioJogo.time_1' => null,
                'TorneioJogo.time_2' => null,
            ]
        ];

        if ( isset($dados['torneio_categoria_id']) && is_numeric($dados['torneio_categoria_id']) ) {
            $conditions = array_merge($conditions, [
                'TorneioJogo.torneio_categoria_id' => $dados['torneio_categoria_id'],
            ]);
        }

        if ( isset($dados['torneio_quadra_id']) && is_numeric($dados['torneio_quadra_id']) ) {
            $conditions = array_merge($conditions, [
                'TorneioJogo.torneio_quadra_id' => $dados['torneio_quadra_id'],
            ]);
        }
    
        $this->TorneioJogo->virtualFields['_quadra_nome'] = 'CONCAT_WS("", TorneioQuadra.nome, ClienteServico.nome)';
        $this->TorneioJogo->virtualFields['_termino'] = 'ADDTIME(Agendamento.horario, Agendamento.duracao)';

        $jogos = $this->TorneioJogo->find('all',[
            'fields' => [
                'Agendamento.horario', 
                'TorneioJogo.id',
                'TorneioJogo._termino',
                'TorneioJogo._quadra_nome',
                'TorneioJogo.time_1',
                'TorneioJogo.time_2',
                'TorneioJogo.grupo',
                'TorneioJogo.fase_nome',
            ],
            'conditions' => $conditions,
            'order' => ['Agendamento.horario'],
            'link' => [
                'Agendamento' => ['Torneio'],
                'TorneioQuadra' => [
                    'ClienteServico'
                ],
            ],
            'group' => [
                'TorneioJogo.id'
            ],
            'limit' => 15
        ]);


        $now = date("Y-m-d H:i:s");
        if ( count($jogos) > 0 ) {
            foreach( $jogos as $key => $jogo ){

                if ( isset($dados['grupo']) && $dados['grupo'] != '' ) {
                    $grupo_time_1 = $this->TorneioGrupo->buscaGrupoByTeam($jogo['TorneioJogo']['time_1']);
                    if ( $grupo_time_1 != $dados['grupo'] ) {
                        unset($jogos[$key]);
                        continue;
                    }
                }

                $jogos[$key]['TorneioJogo']['_nome_dupla1'] = $this->TorneioInscricaoJogador->buscaPrimeiroNomeDupla($jogo['TorneioJogo']['time_1'], '-');
                $jogos[$key]['TorneioJogo']['_jogador_1_imagem'] = $this->TorneioInscricaoJogador->buscaImagemJogador($jogo['TorneioJogo']['time_1'], 1, $this->images_path);
                $jogos[$key]['TorneioJogo']['_jogador_2_imagem'] = $this->TorneioInscricaoJogador->buscaImagemJogador($jogo['TorneioJogo']['time_1'], 2, $this->images_path);
        
                $jogos[$key]['TorneioJogo']['_nome_dupla2'] = $this->TorneioInscricaoJogador->buscaPrimeiroNomeDupla($jogo['TorneioJogo']['time_2'], '-');
                $jogos[$key]['TorneioJogo']['_jogador_3_imagem'] = $this->TorneioInscricaoJogador->buscaImagemJogador($jogo['TorneioJogo']['time_2'], 1, $this->images_path);
                $jogos[$key]['TorneioJogo']['_jogador_4_imagem'] = $this->TorneioInscricaoJogador->buscaImagemJogador($jogo['TorneioJogo']['time_2'], 2, $this->images_path);
      
                $jogos[$key]['TorneioJogo']['_hora'] = date('H:i',strtotime($jogo['Agendamento']['horario']));
                $jogos[$key]['TorneioJogo']['_data'] = date('d/m/Y',strtotime($jogo['Agendamento']['horario']));
                $resultados = $this->TorneioJogoPlacar->busca_resultados($jogo['TorneioJogo']['id'], "N");

                $jogos[$key]['TorneioJogo']['_equipe_1_vitorias'] = $this->TorneioJogoPlacar->conta_vitorias($resultados, 1);
                $jogos[$key]['TorneioJogo']['_equipe_2_vitorias'] = $this->TorneioJogoPlacar->conta_vitorias($resultados, 2);

                $jogos[$key]['TorneioJogo']['_equipe_1_games'] = $this->TorneioJogoPlacar->busca_games($resultados, 1);
                $jogos[$key]['TorneioJogo']['_equipe_2_games'] = $this->TorneioJogoPlacar->busca_games($resultados, 2);
            
                $ultimo_placar = $this->TorneioJogoPonto->ultimo_placar($jogo['TorneioJogo']['id']);
    
                $jogos[$key]['TorneioJogo']['_tipo'] = "Set";
    
                $tipo_set_em_aberto = $this->TorneioJogoPlacar->tipo_set_em_aberto($jogo['TorneioJogo']['id']);

                if ( $tipo_set_em_aberto != null ){ 
                    $jogos[$key]['TorneioJogo']['_tipo'] = $tipo_set_em_aberto;
                }
                
                if ( count($ultimo_placar) == 0 ) {
                    $jogos[$key]['TorneioJogo']['_equipe_1_pontos'] = 0;
                    $jogos[$key]['TorneioJogo']['_equipe_2_pontos'] = 0;
                    $jogos[$key]['TorneioJogo']['_saque'] = 1;
                } else {          
                    //achou o ultimo placar mas era do set anterior
                    if ( $ultimo_placar["TorneioJogoPonto"]["equipe_1_pontos"] > 40 || $ultimo_placar["TorneioJogoPonto"]["equipe_2_pontos"] > 40 ) {
                        $jogos[$key]['TorneioJogo']['_equipe_1_pontos'] = 0;
                        $jogos[$key]['TorneioJogo']['_equipe_2_pontos'] = 0;
                        $jogos[$key]['TorneioJogo']['_saque'] = $ultimo_placar["TorneioJogoPonto"]["saque"] == 1 ? 2 : 1;

                    } else {
                        $jogos[$key]['TorneioJogo']['_equipe_1_pontos'] = $ultimo_placar["TorneioJogoPonto"]["equipe_1_pontos"];
                        $jogos[$key]['TorneioJogo']['_equipe_2_pontos'] = $ultimo_placar["TorneioJogoPonto"]["equipe_2_pontos"];
                        $jogos[$key]['TorneioJogo']['_saque'] = $ultimo_placar["TorneioJogoPonto"]["saque"];

                    }
                }
                //$ultimo_ponto = $this->TorneioJogoPonto->busca_ultimo_ponto($jogo['TorneioJogo']['id']);

                $jogos[$key]['TorneioJogo']['status'] = "upcoming";
                if ( $jogo['Agendamento']['horario'] >= $now && $jogo['TorneioJogo']['_termino'] <= $now ) {
                    $jogos[$key]['TorneioJogo']['status'] = "in_progress";
                }

            }
        }

        $jogos = array_values($jogos);


        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $jogos))));

    }

    public function seta_saque(){
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

        if ( !isset($dados->id) || $dados->id == '' || $dados->id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Jogo não informado.'))));
        }

        if ( !isset($dados->saque_equipe) || $dados->saque_equipe == '' || $dados->saque_equipe == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Equipe do saque não informada.'))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para informar resultados.'))));
        }

        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioJogoPonto');

        $dados_jogo = $this->TorneioJogo->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioJogo.id' => $dados->id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
            ],
            'link' => ['TorneioCategoria' => ['Torneio']],
        ]);

        if ( count($dados_jogo) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do jogo não encontrados.'))));
        }

        $ultimo_placar = $this->TorneioJogoPonto->ultimo_placar($dados->id);

        if ( count($ultimo_placar) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Saque setado com sucesso!'))));
        }

        $dados_placar_atualizar = [
            'id' => $ultimo_placar["TorneioJogoPonto"]["id"],
            'saque' => $dados->saque_equipe,
        ];

        if ( !$this->TorneioJogoPonto->save($dados_placar_atualizar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao setar a equipe do saque.'))));
        }


        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Saque setado com sucesso!'))));
    }

    public function salva_ponto(){
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

        if ( !isset($dados->id) || $dados->id == '' || $dados->id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Jogo não informado.'))));
        }

        if ( !isset($dados->equipe_1_pontos) || $dados->equipe_1_pontos === '' || $dados->equipe_1_pontos === null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Pontos da equipe 1 não informados.'))));
        }

        if ( !isset($dados->equipe_2_pontos) || $dados->equipe_2_pontos === '' || $dados->equipe_2_pontos === null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Pontos da equipe 2 não informados.'))));
        }

        if ( !isset($dados->saque_equipe) || $dados->saque_equipe == '' || $dados->saque_equipe == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Equipe do saque não informada.'))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para informar resultados.'))));
        }

        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioJogoPonto');
        $this->loadModel('TorneioJogoPlacar');
        $this->loadModel('TorneioInscricao');

        $dados_jogo = $this->TorneioJogo->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioJogo.id' => $dados->id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
            ],
            'link' => ['TorneioCategoria' => ['Torneio']],
        ]);

        if ( count($dados_jogo) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do jogo não encontrados.'))));
        }

        $dados_ponto_salvar = [
            'torneio_jogo_id' => $dados->id,
            'equipe_1_pontos' => $dados->equipe_1_pontos,
            'equipe_2_pontos' => $dados->equipe_2_pontos,
            'saque' => $dados->saque_equipe,
        ];

        if ( !$this->TorneioJogoPonto->save($dados_ponto_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar o ponto.'))));
        }

        if ( isset($dados->set_game) && $dados->set_game ) {

            if ( $dados->equipe_1_pontos == $dados->equipe_2_pontos ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Jogo empatado, impossível determinar o vencedor.'))));
            }

            $dados_placar = $this->TorneioJogoPlacar->find('first',[
                'fields' => ['*'],
                'conditions' => [
                    'TorneioJogoPlacar.torneio_jogo_id' => $dados->id,
                    'TorneioJogoPlacar.finalizado' => "N",
                ],
                'link' => [],
            ]);

            $dados_placar_salvar = [
                'torneio_jogo_id' => $dados->id,
                'finalizado' => 'N'
            ];

            $equipe_1_add_game = $dados->equipe_1_pontos > $dados->equipe_2_pontos ? 1 : 0;
            $equipe_2_add_game = $dados->equipe_2_pontos > $dados->equipe_1_pontos ? 1 : 0;
    
            if ( count($dados_placar) > 0 ) {
                $dados_placar_salvar['id'] = $dados_placar['TorneioJogoPlacar']['id'];
                $dados_placar_salvar['time_1_placar'] = $dados_placar['TorneioJogoPlacar']['time_1_placar'] + $equipe_1_add_game;
                $dados_placar_salvar['time_2_placar'] = $dados_placar['TorneioJogoPlacar']['time_2_placar'] + $equipe_2_add_game;
            } else {
                $dados_placar_salvar['time_1_placar'] = $equipe_1_add_game;
                $dados_placar_salvar['time_2_placar'] = $equipe_2_add_game;
            }

            if ( !$this->TorneioJogoPlacar->save($dados_placar_salvar) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Erro ao salvar o set.'))));
            }


        }

        if ( isset($dados->end_set) && $dados->end_set ) {

            if ( $dados->equipe_1_pontos == $dados->equipe_2_pontos ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Jogo empatado, impossível determinar o vencedor.'))));
            }

            $dados_placar = $this->TorneioJogoPlacar->find('first',[
                'fields' => ['*'],
                'conditions' => [
                    'TorneioJogoPlacar.torneio_jogo_id' => $dados->id,
                    'TorneioJogoPlacar.finalizado' => "N",
                ],
                'link' => [],
            ]);

            if ( count($dados_placar) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Placar não encontrado.'))));
            }

            $dados_placar_salvar = [
                'id' => $dados_placar['TorneioJogoPlacar']['id'],
                'torneio_jogo_id' => $dados->id,
                'finalizado' => 'Y'
            ];

            if ( !$this->TorneioJogoPlacar->save($dados_placar_salvar) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Erro ao finalizar o set.'))));
            }


        }

        if ( isset($dados->end_match) && $dados->end_match ) {

            if ( $dados->equipe_1_pontos == $dados->equipe_2_pontos ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Jogo empatado, impossível determinar o vencedor.'))));
            }

            $dados_jogo_salvar = [
                'id' => $dados->id,
                'finalizado' => 'Y'
            ];

            if ( !$this->TorneioJogo->save($dados_jogo_salvar) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Erro ao finalizar o jogo.'))));
            }
            
            $fase_jogo = $dados_jogo['TorneioJogo']['fase'];

            //se ta na fase de grupos
            if ( $fase_jogo == 1 ) {
                
                $grupo = $dados_jogo['TorneioJogo']['grupo'];
                $jogos_sem_resultados = $this->TorneioJogo->getMatchesWithoutScore($dados_jogo['Torneio']['id'], $dados_jogo['TorneioJogo']['torneio_categoria_id'], $grupo);
    
                //não existe partida sem resultado lançado
                if ( count($jogos_sem_resultados) == 0 ) {
                    
                    $integrantes = $this->TorneioInscricao->find('all',[
                        'conditions' => [
                            'TorneioInscricao.torneio_categoria_id' => $dados_jogo['TorneioJogo']['torneio_categoria_id'],
                            'TorneioGrupo.nome' => $grupo,
                            'not' => [
                                'TorneioInscricao.confirmado' => 'R',
                            ]
                        ],
                        'link' => ['TorneioGrupo']
                    ]);
                    
                    if ( count($integrantes) == 0 ) {
                        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Não foram encontrados integrantes no grupo.'))));
                    }
    
                    foreach( $integrantes as $key_integrante => $integrante) {
                        $integrantes[$key_integrante]['TorneioInscricao']['_nome_dupla'] = $this->TorneioInscricaoJogador->buscaNomeDupla($integrante['TorneioInscricao']['id']);
                        $integrantes[$key_integrante]['TorneioInscricao']['_vitorias'] = $this->TorneioJogo->buscaNVitorias($integrante['TorneioInscricao']['id'], 1);
                        $integrantes[$key_integrante]['TorneioInscricao']['_sets'] = $this->TorneioJogo->buscaNSets($integrante['TorneioInscricao']['id'], 1);
                        $integrantes[$key_integrante]['TorneioInscricao']['_games'] = $this->TorneioJogo->buscaNGames($integrante['TorneioInscricao']['id'], 1);
                    }
    
                    $integrantes = $this->ordena_times($integrantes);
                    $grupo_letra = substr($grupo, -1);
                    $alphabet = range('A', 'Z');
                    $letter_number = array_search($grupo_letra, $alphabet);
                    $grupo_id = $letter_number + 1;
    
                    $seta_times = $this->TorneioJogo->setTeams($dados_jogo['Torneio']['id'], $dados_jogo['TorneioJogo']['torneio_categoria_id'], $grupo_id, null, $integrantes);
    
                    if ( !$seta_times ) {
                        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Ocorreu um erro ao gerar as próximas fases.'))));
                    }
                }
            } else {
    
                $resultados = $this->TorneioJogoPlacar->busca_resultados($dados_jogo['TorneioJogo']['id']);
    
                if ( count($resultados) == 0 ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao buscar os resultados.'))));
                }
    
                $vencedor = $this->TorneioJogoPlacar->busca_vencedor_por_resultados($resultados);
    
                if ( !$vencedor ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Impossível definir o vencedor.'))));
                }
    
                $seta_times = $this->TorneioJogo->setTeams($dados_jogo['Torneio']['id'], $dados_jogo['TorneioJogo']['torneio_categoria_id'], null, $dados_jogo['TorneioJogo']['_id'], [], $dados_jogo['TorneioJogo'][$vencedor]);
    
                if ( !$seta_times ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Ocorreu um erro ao gerar as próximas fases.'))));
                }
    
            }


        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Ação registrada com sucesso!'))));
    }
}