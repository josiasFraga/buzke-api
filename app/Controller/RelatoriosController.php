<?php
ini_set("pcre.backtrack_limit", "5000000");
class RelatoriosController extends AppController {


    public function ocupacao() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['inicio']) || $dados['inicio'] == "" ) {
            throw new BadRequestException('Início não informado!', 401);
        }

        if ( !isset($dados['fim']) || $dados['fim'] == "" ) {
            throw new BadRequestException('Fim não informado!', 401);
        }

        if ( !isset($dados['nome']) || $dados['nome'] == "" ) {
            throw new BadRequestException('Nome do arquivo não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $nome = trim(strip_tags($dados['nome']));

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( strpos($dados['inicio'], '/') > -1 ) {
            $dados['inicio'] = $this->dateBrEn($dados['inicio']);
        }

        if ( strpos($dados['fim'], '/') > -1 ) {
            $dados['fim'] = $this->dateBrEn($dados['fim']);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');
        $this->loadModel('ClienteHorarioAtendimento');
        $this->loadModel('Cliente');
        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('ClienteServicoHorarioAtendimento');

        $inicio = date('Y-m-d', strtotime($dados['inicio']));
        $fim = date('Y-m-d', strtotime($dados['fim']));

        if ( $inicio > $fim ){
            throw new BadRequestException('Requisição inválida!', 401);
        }
        
        $cliente_id = $dados_token['Usuario']['cliente_id'];

        $dados_cliente = $this->Cliente->find('first',[
            'conditions' => [
                'Cliente.id' => $cliente_id
            ],
            'link'=> []
        ]);

        if ( !$dados_cliente ){
            throw new BadRequestException('Requisição inválida!', 401);
        }

        $is_court = $this->ClienteSubcategoria->checkIsCourt($cliente_id);
    
        $servicos = $this->ClienteServico->getByClientId($cliente_id);

        $arr_datas = [];
        for( $inicio; $inicio <= $fim; $inicio = date('Y-m-d', strtotime($inicio . ' +1 day')) ) {
            $arr_datas[$inicio] = [];

            $dia_semana = date('w', strtotime($inicio));
            $dia_semana_nome = $this->dias_semana_str[$dia_semana];
            
            //verfica se o cliente fechará excepcionalmente nesse dia no dia
            $verificaFechamento = $this->ClienteHorarioAtendimentoExcessao->verificaExcessao($cliente_id, $inicio, 'F');

            if ( count($verificaFechamento) > 0 ) {
                $arr_datas[$inicio]['msg'] = "Excepcionalmente fechado nesse dia";
            } else {
                

                foreach ( $servicos as $key_servico => $servico ) {                    
                    $horarios = $this->quadra_horarios($servico['ClienteServico']['id'], $inicio, $servico['ClienteServico']['fixos']);
                    $arr_datas[$inicio][$servico['ClienteServico']['id']]['servico'] = $servico;
                    $arr_datas[$inicio][$servico['ClienteServico']['id']]['horarios'] = $horarios;             
                }
            }
            
        }

        $titulo = 'Relatório de Ocupação';
        $dias_semana_str = $this->dias_semana_str;
        $meses_str_abrev = $this->meses_abrev;

        $this->set(compact('dados', 'dados_cliente', 'titulo', 'nome', 'arr_datas', 'dias_semana_str', 'meses_str_abrev', 'servicos'));

        $this -> render('ocupacao_quadras');

    }

    public function inscritos_torneio() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['ordenado_por']) || $dados['ordenado_por'] == "" ) {
            throw new BadRequestException('Ordenação!', 401);
        }

        if ( !isset($dados['torneio_id']) || $dados['torneio_id'] == "" ) {
            throw new BadRequestException('Torneio não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $torneio_id = $dados['torneio_id'];
        $agrupado_por = $dados['agrupado_por'];
        $ordenado_por = $dados['ordenado_por'];
        $nome = trim(strip_tags($dados['nome']));

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioInscricao');
        $this->loadModel('TorneioCateogira');
        $this->loadModel('TorneioInscricaoJogador');
        $this->loadModel('Cliente');
        $this->loadModel('Torneio');

        $cliente_id = $dados_token['Usuario']['cliente_id'];

        $dados_cliente = $this->Cliente->find('first',[
            'conditions' => [
                'Cliente.id' => $cliente_id
            ],
            'link'=> []
        ]);

        if ( !$dados_cliente ){
            throw new BadRequestException('Requisição inválida!', 401);
        }

        $dados_torneio = $this->Torneio->find('first',[
            'conditions' => [
                'Torneio.id' => $torneio_id,
                'Torneio.cliente_id' => $cliente_id
            ],
            'link'=> []
        ]);

        if ( !$dados_torneio ){
            throw new BadRequestException('Requisição inválida!', 401);
        }

        $titulo = 'Inscrições do Torneio<br>'.$dados_torneio['Torneio']['nome'];

        $conditions = [];

        $conditions = array_merge($conditions, [
            'TorneioInscricao.torneio_id' => $torneio_id,
            'not' => [
                'TorneioInscricao.confirmado' => 'R',
            ]
        ]);

        $this->TorneioInscricao->virtualFields['_categoria_nome'] = 'CONCAT_WS("", TorneioCategoria.nome, PadelCategoria.titulo)';
        $dados_inscritos = $this->TorneioInscricao->find('all',[
            'fields' => ['*'],
            'conditions' => $conditions,
            'link' => ['TorneioCategoria' => ['PadelCategoria']],
            'group' => ['TorneioInscricao.id'],
            'order' => ['TorneioInscricao._categoria_nome'],
        ]);

        foreach( $dados_inscritos as $key => $dado) {
            $dados_inscritos[$key]['TorneioInscricao']['_nome_dupla'] = $this->TorneioInscricaoJogador->buscaNomeDupla($dado['TorneioInscricao']['id']);
        }

        if ( $agrupado_por == '' ) {
            usort($dados_inscritos, function($a, $b) use ($ordenado_por) {
                $retval = $a['TorneioInscricao'][$ordenado_por] <=> $b['TorneioInscricao'][$ordenado_por];
                return $retval;
            });
        } else {
            $dados_inscritos_temp = [];
            foreach( @$dados_inscritos as $key => $inscrito ){

                $sexo = "Masculina";

                if ( $inscrito['TorneioCategoria']['sexo'] == 'F') {
                    $sexo = "Feminina";
                }

                $categoria_nome = $inscrito['TorneioInscricao']['_categoria_nome'].' '.$sexo;

                $dados_inscritos_temp[$categoria_nome][] = $inscrito;
            }
        
            foreach( @$dados_inscritos_temp as $categoria_nome => $inscritos ){
                usort($inscritos, function($a, $b) use ($ordenado_por) {
                    $retval = $a['TorneioInscricao'][$ordenado_por] <=> $b['TorneioInscricao'][$ordenado_por];
                    return $retval;
                });

                $dados_inscritos_temp[$categoria_nome] = $inscritos;
            }

            $dados_inscritos = $dados_inscritos_temp;

        }

        $this->set(compact('dados', 'dados_cliente', 'titulo', 'nome', 'dados_inscritos'));

        if ( $agrupado_por == 'categoria' ) {
            $this->render('inscritos_torneio_categoria');
        }

    }

    public function jogos_torneio() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['ordenado_por']) || $dados['ordenado_por'] == "" ) {
            throw new BadRequestException('Ordenação!', 401);
        }

        if ( !isset($dados['torneio_id']) || $dados['torneio_id'] == "" ) {
            throw new BadRequestException('Torneio não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $torneio_id = $dados['torneio_id'];
        $agrupado_por = $dados['agrupado_por'];
        $ordenado_por = $dados['ordenado_por'];
        $nome = trim(strip_tags($dados['nome']));

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }


        $this->loadModel('Cliente');
        $this->loadModel('Torneio');
        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioInscricaoJogador');
        $this->loadModel('TorneioJogoPlacar');
        $this->loadModel('TorneioGrupo');

        $cliente_id = $dados_token['Usuario']['cliente_id'];

        $dados_cliente = $this->Cliente->find('first',[
            'conditions' => [
                'Cliente.id' => $cliente_id
            ],
            'link'=> []
        ]);

        if ( !$dados_cliente ){
            throw new BadRequestException('Requisição inválida!', 401);
        }

        $dados_torneio = $this->Torneio->find('first',[
            'conditions' => [
                'Torneio.id' => $torneio_id,
                'Torneio.cliente_id' => $cliente_id
            ],
            'link'=> []
        ]);

        if ( !$dados_torneio ){
            throw new BadRequestException('Requisição inválida!', 401);
        }

        $titulo = 'Jogos do Torneio<br>'.$dados_torneio['Torneio']['nome'];


        $conditions = [
            'Agendamento.torneio_id' => $dados['torneio_id'],
        ];

    
        $this->TorneioJogo->virtualFields['_quadra_nome'] = 'CONCAT_WS("", TorneioQuadra.nome, ClienteServico.nome)';
        $this->TorneioJogo->virtualFields['_categoria_nome'] = 'CONCAT_WS("", TorneioCategoria.nome, PadelCategoria.titulo)';
        $jogos = $this->TorneioJogo->find('all',[
            'fields' => ['Agendamento.horario', 'TorneioJogo.*', 'TorneioCategoria.sexo'],
            'conditions' => $conditions,
            //'order' => ['Agendamento.horario'],
            'link' => [
                'Agendamento' => ['Torneio'],
                'TorneioQuadra' => [
                    'ClienteServico'
                ],
                'TorneioCategoria' => [
                    'PadelCategoria'
                ],
            ],
            'group' => [
                'TorneioJogo.id'
            ]
        ]);

        if ( count($jogos) > 0 ) {
            foreach( $jogos as $key => $jogo ){

                if ( $jogo['TorneioJogo']['time_1'] != null ) 
                    $jogos[$key]['TorneioJogo']['_nome_dupla1'] = $this->TorneioInscricaoJogador->buscaNomeDupla($jogo['TorneioJogo']['time_1']);
                else
                    $jogos[$key]['TorneioJogo']['_nome_dupla1'] = $jogo['TorneioJogo']['time_1_proximas_fases'];

                if ( $jogo['TorneioJogo']['time_2'] != null )
                    $jogos[$key]['TorneioJogo']['_nome_dupla2'] = $this->TorneioInscricaoJogador->buscaNomeDupla($jogo['TorneioJogo']['time_2']);
                else
                    $jogos[$key]['TorneioJogo']['_nome_dupla2'] = $jogo['TorneioJogo']['time_2_proximas_fases'];
                

                $jogos[$key]['TorneioJogo']['_hora'] = date('H:i',strtotime($jogo['Agendamento']['horario']));
                $jogos[$key]['TorneioJogo']['_data'] = date('d/m/Y',strtotime($jogo['Agendamento']['horario']));
                //$jogos[$key]['TorneioJogo']['_resultados'] = $this->TorneioJogoPlacar->busca_resultados($jogo['TorneioJogo']['id']);

            }
        }

        if ( $agrupado_por == '' ) {
            usort($jogos, function($a, $b) use ($ordenado_por) {
                $retval = $a['Agendamento'][$ordenado_por] <=> $b['Agendamento'][$ordenado_por];
                return $retval;
            });
        } else if ( $agrupado_por == 'categoria' ) {
            $jogos_temp = [];
            foreach( @$jogos as $key => $jogo ){

                $sexo = "Masculina";

                if ( $jogo['TorneioCategoria']['sexo'] == 'F') {
                    $sexo = "Feminina";
                }

                $categoria_nome = $jogo['TorneioJogo']['_categoria_nome'].' '.$sexo;

                $jogos_temp[$categoria_nome][] = $jogo;
            }
        
            foreach( @$jogos_temp as $categoria_nome => $inscritos ){
                usort($inscritos, function($a, $b) use ($ordenado_por) {
                    $retval = $a['Agendamento'][$ordenado_por] <=> $b['Agendamento'][$ordenado_por];
                    return $retval;
                });

                $jogos_temp[$categoria_nome] = $inscritos;
            }

            $jogos = $jogos_temp;
        } else if ( $agrupado_por == 'quadra' ) {
            $jogos_temp = [];
            foreach( @$jogos as $key => $jogo ){

                $quadra_nome = $jogo['TorneioJogo']['_quadra_nome'];
                $jogos_temp[$quadra_nome][] = $jogo;
            }
        
            foreach( @$jogos_temp as $categoria_nome => $inscritos ){
                usort($inscritos, function($a, $b) use ($ordenado_por) {
                    $retval = $a['Agendamento'][$ordenado_por] <=> $b['Agendamento'][$ordenado_por];
                    return $retval;
                });

                $jogos_temp[$categoria_nome] = $inscritos;
            }

            $jogos = $jogos_temp;
        } else if ( $agrupado_por == 'data' ) {
            $jogos_temp = [];
            foreach( @$jogos as $key => $jogo ){

                $data = $jogo['TorneioJogo']['_data'];
                $jogos_temp[$data][] = $jogo;
            }
        
            foreach( @$jogos_temp as $categoria_nome => $inscritos ){
                usort($inscritos, function($a, $b) use ($ordenado_por) {
                    $retval = $a['Agendamento'][$ordenado_por] <=> $b['Agendamento'][$ordenado_por];
                    return $retval;
                });

                $jogos_temp[$categoria_nome] = $inscritos;
            }

            $jogos = $jogos_temp;
        }

        $this->set(compact('dados', 'dados_cliente', 'titulo', 'nome', 'jogos'));

        if ( $agrupado_por == 'categoria' ) {
            $this->render('jogos_torneio_categoria');
        }

        if ( $agrupado_por == 'quadra' ) {
            $this->render('jogos_torneio_quadra');
        }

        if ( $agrupado_por == 'data' ) {
            $this->render('jogos_torneio_data');
        }

    }

}