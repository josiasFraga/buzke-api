<?php
App::uses('CombinationsComponent', 'Controller/Component');
class TorneiosController extends AppController {

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['tipo']) || $dados['tipo'] == "" ) {
            throw new BadRequestException('Tipo não informado!', 401);
        }

        $token = $dados['token'];
        $email = null;

        if ( isset($dados['email']) && $dados['email'] != "" ) {
            $email = $dados['email'];
        }

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Torneio');

        $conditions = [];
        $owner = false;
        if ( $dados['tipo'] == 'meus' ) {

            if ( !isset($dados_token['Usuario']) ) {
                throw new BadRequestException('Usuario não logado!', 401);
            }

            if ( $dados_token['Usuario']['cliente_id'] != null ) {
                $conditions = array_merge($conditions, [
                    'Torneio.cliente_id' => $dados_token['Usuario']['cliente_id']
                ]);
                $owner = true;

            } else {
                $this->loadModel('ClienteCliente');
                $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_token['Usuario']['id'], true);
                $conditions = array_merge($conditions, [
                    'TorneioInscricaoJogador.cliente_cliente_id' => $meus_ids_de_cliente,  
                ]);
            }
        } else {
            $conditions = array_merge($conditions, [
                //'Torneio.fim >=' => date('Y-m-d'),
            ]);

        }

        $torneios = $this->Torneio->find('all',[
            'fields' => [
                'Torneio.*', 'Cliente.nome', 'Localidade.loc_no', 'Localidade.ufe_sg', 'Cliente.telefone'
            ],
            'conditions' => $conditions,
            'order' => ['Torneio.inicio'],
            'group' => ['Torneio.id'],
            'link' => ['TorneioInscricao' => ['TorneioInscricaoJogador'], 'Cliente' => ['Localidade']]
        ]);
        
        //debug($conditions); die();

        foreach($torneios as $key => $trn){
            
            $torneios[$key]['Torneio']['_periodo'] = 
                'De '.date('d/m',strtotime($trn['Torneio']['inicio'])).
                ' até '.date('d/m',strtotime($trn['Torneio']['fim']));
            $torneios[$key]['Torneio']['img'] = $this->images_path."torneios/".$trn['Torneio']['img'];
            $torneios[$key]['Torneio']['_owner'] = $owner;
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $torneios))));

    }

    public function dados() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['id']) || $dados['id'] == "" || !is_numeric($dados['id']) ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $token = $dados['token'];
        $email = null;

        if ( isset($dados['email']) && $dados['email'] != "" ) {
            $email = $dados['email'];
        }

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Torneio');
        $this->loadModel('TorneioCategoria');
        $this->loadModel('TorneioData');
        $this->loadModel('TorneioInscricao');
        $this->loadModel('TorneioInscricaoJogador');
        $this->loadModel('ClienteCliente');
        $this->loadModel('TorneioGrupo');
        $this->loadModel('TorneioJogo');

        $conditions = [];

        $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_token['Usuario']['id'], true);

        $conditions = array_merge($conditions, [
            'Torneio.id' => $dados['id'],
        ]);

        $dados = $this->Torneio->find('first',[
            'fields' => [
                'Torneio.*', 'Cliente.nome', 'Cliente.endereco', 'Cliente.endereco_n', 'Cliente.wp', 'Localidade.loc_no', 'Localidade.ufe_sg', 'Cliente.telefone'
            ],
            'conditions' => $conditions,
            'order' => ['Torneio.inicio'],
            'link' => ['TorneioInscricao', 'Cliente' => ['Localidade']]
        ]);

        if ( count($dados) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Dados do torneio não econtrados!'))));
        }

        $owner = isset($dados_token['Usuario']) && $dados_token['Usuario']['cliente_id'] == $dados['Torneio']['cliente_id'];
  
        $dados['Torneio']['_periodo'] = 
            'De '.date('d/m',strtotime($dados['Torneio']['inicio'])).
            ' até '.date('d/m',strtotime($dados['Torneio']['fim']));
        $dados['Torneio']['img'] = $this->images_path."torneios/".$dados['Torneio']['img'];
        $dados['Torneio']['_owner'] = $owner;
        $dados['Torneio']['_periodo_inscricao'] = 
            'de '.
            date('d',strtotime($dados['Torneio']['inscricoes_de'])).
            '/'.$this->meses_abrev[(int)date('m',strtotime($dados['Torneio']['inscricoes_de']))].
            ' até '.
            date('d',strtotime($dados['Torneio']['inscricoes_ate'])).
            '/'.$this->meses_abrev[(int)date('m',strtotime($dados['Torneio']['inscricoes_ate']))];

        $dados['Torneio']['_subscriptions_opened'] = ($dados['Torneio']['inscricoes_de'] <= date('Y-m-d H:i:s') && $dados['Torneio']['inscricoes_ate'] >= date('Y-m-d H:i:s'));
        $dados['Torneio']['_valor_inscricao'] =  'R$ '.number_format($dados['Torneio']['valor_inscricao'],2,',','.');
        $all_group_generated = true;
        $some_group_generated = false;
        $categorias = $this->TorneioCategoria->getByTournamentId($dados['Torneio']['id']);
        if ( count($categorias) > 0 ) {
            foreach($categorias as $key => $cat){

                $categorias[$key]['_inscritos'] = 
                    $this->TorneioInscricao->find('count',[
                        'conditions' => [
                            'TorneioInscricao.torneio_categoria_id' => $cat['id'],
                            'not' => [
                                'TorneioInscricao.confirmado' => 'R',
                            ]
                        ]
                    ]);

                $is_group_generated = $this->TorneioGrupo->find('count',[
                    'conditions' => [
                        'TorneioGrupo.torneio_categoria_id' => $cat['id'],
                        'not' => [
                            'TorneioInscricao.confirmado' => 'R',
                        ]
                    ],
                    'link' => ['TorneioInscricao']
                ]) > 0;

                if ( !$is_group_generated ) {
                    $all_group_generated = false;
                } else if ( $is_group_generated ) {
                    $some_group_generated = true;
                }

                $categorias[$key]['_is_group_generated'] = $is_group_generated;
            }
        }

        $matches_generated = $this->TorneioJogo->find('count',[
            'conditions' => [
                'Agendamento.torneio_id' => $dados['Torneio']['id']
            ], 
            'link' => ['Agendamento']
        ]) > 0;

        $dados['TorneioCategoria'] = $categorias;
        $dados['TorneioData'] = $this->TorneioData->getByTournamentId($dados['Torneio']['id']);
        $dados['Torneio']['_subscribed'] = $this->TorneioInscricaoJogador->checkSubscribed($meus_ids_de_cliente);
        $dados['Torneio']['_subscriptions_finished'] = $this->Torneio->checkIsSubscriptionsFinished($dados['Torneio']['id']);
        $dados['Torneio']['_all_group_generated'] = $all_group_generated;
        $dados['Torneio']['_some_group_generated'] = $some_group_generated;
        $dados['Torneio']['_matches_generated'] = $matches_generated;
        
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));

    }

    public function categorias() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['torneio_id']) || $dados['torneio_id'] == "" || !is_numeric($dados['torneio_id']) ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

    
        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioCategoria');

        $conditions = [];

        $conditions = array_merge($conditions, [
            'TorneioCategoria.torneio_id' => $dados['torneio_id'],
        ]);

        $this->TorneioCategoria->virtualFields['_nome'] = 'CONCAT_WS("", TorneioCategoria.nome, PadelCategoria.titulo)';
        $dados = $this->TorneioCategoria->find('all',[
            
            'conditions' => $conditions,
            'order' => ['CONCAT_WS("", TorneioCategoria.nome, PadelCategoria.titulo)'],
            'link' => ['PadelCategoria'],
            'group' => ['TorneioCategoria.id'],
        ]);

        if ( count($dados) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Categorias do torneio não econtrados!'))));
        }
      
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));

    }

    public function inscritos() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['categoria_id']) || $dados['categoria_id'] == "" || !is_numeric($dados['categoria_id']) ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioInscricao');
        $this->loadModel('TorneioCateogira');
        $this->loadModel('TorneioInscricaoJogador');

        $conditions = [];

        $conditions = array_merge($conditions, [
            'TorneioInscricao.torneio_categoria_id' => $dados['categoria_id'],
            'not' => [
                'TorneioInscricao.confirmado' => 'R',
            ]
        ]);

        $this->TorneioInscricao->virtualFields['_categoria_nome'] = 'CONCAT_WS("", TorneioCategoria.nome, PadelCategoria.titulo)';
        $dados = $this->TorneioInscricao->find('all',[
            'fields' => ['*'],
            'conditions' => $conditions,
            'link' => ['TorneioCategoria' => ['PadelCategoria']],
            'group' => ['TorneioInscricao.id'],
        ]);

        if ( count($dados) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Nenhuma inscrição econtrada!'))));
        }

        $owner = false;

        if ( $dados_token['Usuario']['nivel_id'] == 2 ) {
            $owner = true;
        }
      
        foreach( $dados as $key => $dado) {
            $dados[$key]['TorneioInscricao']['_nome_dupla'] = $this->TorneioInscricaoJogador->buscaNomeDupla($dado['TorneioInscricao']['id']);
            $dados[$key]['TorneioInscricao']['_owner'] = $owner;
        }

        usort($dados, function($a, $b) {
            $retval = $b['TorneioInscricao']['_nome_dupla'] <=> $a['TorneioInscricao']['_nome_dupla'];
            return $retval;
        });
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));

    }

    public function cadastrar(){
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

        if ( !isset($dados->nome) || $dados->nome == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        if ( !isset($dados->descricao) || $dados->descricao == "" ) {
            throw new BadRequestException('Descrição não informada!', 401);
        }

        if ( !isset($dados->inicio) || $dados->inicio == "" ) {
            throw new BadRequestException('Início não informado!', 401);
        }

        if ( !isset($dados->fim) || $dados->fim == "" ) {
            throw new BadRequestException('Fim não informado!', 401);
        }

        //if ( !isset($dados->duracao) || $dados->duracao == "" ) {
        //    throw new BadRequestException('Fim não informado!', 401);
        //}
        
        if ( !isset($dados->inscricoes_de) || $dados->inscricoes_de == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data inicial das inscrições não informada'))));
        }

        if ( !isset($dados->inscricoes_ate) || $dados->inscricoes_ate == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data limite das inscrições não informada'))));
        }

        if ( !isset($dados->valor_inscricao) || $dados->valor_inscricao == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Valor da inscrição não informado'))));
        }

        if ( !isset($dados->torneio_categoria) || $dados->torneio_categoria == "" || !is_array($dados->torneio_categoria) || count($dados->torneio_categoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar ao menos uma categoria para cadastrar um torneio'))));
        }

        if ( !isset($dados->torneio_quadras) || $dados->torneio_quadras == "" || !is_array($dados->torneio_quadras) || count($dados->torneio_quadras) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar ao menos uma quadra para cadastrar um torneio'))));
        }
        

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_usuario['Usuario']['cliente_id'] == null || $dados_usuario['Usuario']['cliente_id'] == '' ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        //if ( !isset($dados->torneio_data) || $dados->torneio_data == "" || !is_array($dados->torneio_data) || count($dados->torneio_data) == 0 ) {
        //    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar ao menos um período de realização de jogos para cadastrar um torneio'))));
        //}

        //categorias do torneio
        foreach( $dados->torneio_categoria as $key => $categoria ){

            if ( ( !isset($categoria->categoria_id) || $categoria->categoria_id == "" || $categoria->categoria_id == "0") && ( !isset($categoria->nome) || $categoria->nome == "" ) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Categoria ou nome não informados'))));
            }
            if ( !isset($categoria->sexo) || $categoria->sexo == "" || !in_array($categoria->sexo, ['M','F','MI']) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Sexo da categoria não informado'))));
            }
            if ( !isset($categoria->limite_duplas) || $categoria->limite_duplas == "" || !is_numeric($categoria->limite_duplas) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Limite de duplas da categoria não informado'))));
            }
        }

        //periodos do torneio
        $quadras = [];
        $periodos = [];
        $periodos_temp = [];
        foreach( $dados->torneio_quadras as $key => $torneio_quadra ){

            if ( !isset($torneio_quadra->quadra_periodos) || !is_array($torneio_quadra->quadra_periodos) || count($torneio_quadra->quadra_periodos) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Ao menos um período de partidas para a quadra deve ser informado'))));
            }

            $quadra_nome = null;
            $quadra_id = null;

            if ( isset($torneio_quadra->nome) && $torneio_quadra->nome != "" ) {
                $quadra_nome = $torneio_quadra->nome;
            }

            if ( isset($torneio_quadra->quadra_id) && $torneio_quadra->quadra_id != "" && $torneio_quadra->quadra_id != "0" ) {
                $quadra_id = $torneio_quadra->quadra_id;
            }

            if ( $quadra_nome  == null && $quadra_id == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Nome ou ID da quadra não informado'))));
            }

            $quadras[$key] = ['nome' => $quadra_nome, 'servico_id' => $quadra_id, 'confirmado' => 'Y'];

            foreach( $torneio_quadra->quadra_periodos as $key_periodo => $periodo ) {

                if ( !isset($periodo->data) || $periodo->data == "" ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data do período não informada.'))));
                }
                if ( !isset($periodo->das) || $periodo->das == "" ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora de início do período não informado.'))));
                }
                if ( !isset($periodo->ate_as) || $periodo->ate_as == "" ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora final do período não informado.'))));
                }
                if ( !isset($periodo->duracao) || $periodo->duracao == "" ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Duração dos jogos na quadra não informados.'))));
                }
                if ( $periodo->das >= $periodo->ate_as) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A hora de início deve ser inferior a data final.'))));
                }

                $quadras[$key]['TorneioQuadraPeriodo'][] = ['inicio' => $periodo->data." ".$periodo->das, 'fim' =>  $periodo->data." ".$periodo->ate_as, 'duracao_jogos' => $periodo->duracao];

                $periodos_temp[$periodo->data][] = ['inicio' => $periodo->das, 'fim' => $periodo->ate_as];
            }

        }
        
        foreach($periodos_temp as $data => $per_temp){
            foreach( $per_temp as $key_item => $item ){
                if ( !isset($periodos[$data]) ) {
                    $periodos[$data][] = ['data' => $data, 'inicio' => $item['inicio'], 'fim' => $item['fim']];
                } else {
                    foreach( $periodos[$data] as $key_per_temp => $item_salvo ){
                        //se o item está entre o salvo
                        if ( $item['inicio'] >= $item_salvo['inicio'] && $item['fim'] <= $item_salvo['inicio']  ) {
                            continue;
                        }
                        //se o item está antes do salvo
                        if ( $item['inicio'] < $item_salvo['inicio'] && $item['fim'] < $item_salvo['inicio']  ) {
                            $periodos[$data][] = ['data' => $data, 'inicio' => $item['inicio'], 'fim' => $item['fim']];
                        }
                        //se o item está depois do salvo
                        else if ( $item['inicio'] > $item_salvo['fim'] && $item['fim'] > $item_salvo['fim'] ) {
                            $periodos[$data][] = ['data' => $data, 'inicio' => $item['inicio'], 'fim' => $item['fim']];
                        }
                        //se o item tem o início entre o salvo
                        else if ( $item['inicio'] > $item_salvo['inicio'] && $item['inicio'] < $item_salvo['fim'] ) {
                            $periodos[$data][$key_per_temp]['fim'] = $item['fim'];
                        }
                        //se o item tem o fim entre o salvo
                        else if ( $item['fim'] > $item_salvo['inicio'] && $item['fim'] < $item_salvo['fim'] ) {
                            $periodos[$data][$key_per_temp]['inicio'] = $item['inicio'];
                        }
                        //se o item tem o final igual ao início do salvo
                        if ( $item['fim'] == $item_salvo['inicio']  ) {
                            $periodos[$data][$key_per_temp]['inicio'] = $item['inicio'];
                        }
                        //se o item tem o início igual ao final do salvo
                        else if ( $item['inicio'] == $item_salvo['fim'] ) {
                            $periodos[$data][$key_per_temp]['fim'] = $item['fim'];
                        }
                    }
                }
            }
        }

        $periodos_salvar = [];
        foreach($periodos as $key => $periodo){
            foreach($periodo as $key_item => $item){
                $periodos_salvar[] = $item;
            }
        }
   
       
        /*if ( isset($dados->torneio_quadras_terceiros) ) {

            if( is_array($dados->torneio_quadras_terceiros) )
                $dados->torneio_quadras_terceiros = (object)$dados->torneio_quadras_terceiros;
            
            foreach($dados->torneio_quadras_terceiros as $key => $quadra){
                $quadras[] = ['nome' => $quadra->nome, 'confirmado' => 'Y', 'TorneioQuadraPeriodo' => $quadras_periodos];
            }
        }*/

        if ( count($quadras) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve selecionar ao menos uma quadra para cadastrar um torneio.'))));
        }

        $this->loadModel('Torneio');
        $dados_salvar = [
            'Torneio' => [
                'cliente_id' => $dados_usuario['Usuario']['cliente_id'],
                'nome' => $dados->nome,
                'descricao' => $dados->descricao,
                'inicio' => $dados->inicio,
                'fim' => $dados->fim,
                'inscricoes_de' => $dados->inscricoes_de,
                'inscricoes_ate' => $dados->inscricoes_ate,
                'impedimentos' => isset($dados->impedimentos) && $dados->impedimentos > 0 ? $dados->impedimentos : 0,
                'valor_inscricao' => $dados->valor_inscricao,
            ],
            'TorneioCategoria' => $dados->torneio_categoria,
            'TorneioData' => $periodos_salvar,
            'TorneioQuadra' => $quadras,
        ];

        if (isset($this->request->params['form']['img']) && $this->request->params['form']['img'] != '' && $this->request->params['form']['img']['error'] == 0) {
            $dados_salvar['Torneio'] = array_merge($dados_salvar['Torneio'],
            [
                'img' => $this->request->params['form']['img']
            ]);
        }

        $dados_torneio = $this->Torneio->saveAssociated($dados_salvar,['deep' => true]);

        if ( !$dados_torneio ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao cadastrar o torneio!'))));
        }

        $this->cancelShcedulingInRanges($quadras);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! O torneio foi cadastrado com sucesso!'))));
    }

    private function cancelShcedulingInRanges($ranges = []) {
        if ( count($ranges) == 0 )
            return true;

        $this->loadModel('Agendamento');
        $this->loadModel('AgendamentoFixoCancelado');

        foreach( $ranges as $key => $range ){

            if ($range['confirmado'] == 'Y' && isset($range['servico_id']) ) {
                if ( isset($range['TorneioQuadraPeriodo']) && is_array($range['TorneioQuadraPeriodo']) ) {
                    foreach( $range['TorneioQuadraPeriodo'] as $key => $periodo ){

                        $conditions = [
                            'Agendamento.cancelado' => 'N',
                            'Agendamento.servico_id' => $range['servico_id'],
                            'or' => [
                                [
                                    'or' => [
                                        'Agendamento.horario >=' => $this->datetimeBrEn($periodo['inicio']),
                                        'ADDTIME(Agendamento.horario, Agendamento.duracao) >=' => $this->datetimeBrEn($periodo['inicio']),
                                    ],
                                    'Agendamento.horario <=' => $this->datetimeBrEn($periodo['fim']),
                                    'Agendamento.dia_semana' => null,
                                    'Agendamento.dia_mes' => null,
                                ],
                                [
                                    'or' => [
                                        'TIME(Agendamento.horario) >=' => date("H:i:s",strtotime($this->datetimeBrEn($periodo['inicio']))),
                                        'TIME(ADDTIME(Agendamento.horario, Agendamento.duracao)) >=' => date("H:i:s",strtotime($this->datetimeBrEn($periodo['inicio']))),
                                    ],
                                    'TIME(Agendamento.horario) <=' => date("H:i:s",strtotime($this->datetimeBrEn($periodo['fim']))),
                                    'Agendamento.dia_semana' => date('w',strtotime($this->datetimeBrEn($periodo['inicio']))),
                                    'Agendamento.dia_mes' => null,
                                ],
                                [
                                    'or' => [
                                        'TIME(Agendamento.horario) >=' => date("H:i:s",strtotime($this->datetimeBrEn($periodo['inicio']))),
                                        'TIME(ADDTIME(Agendamento.horario, Agendamento.duracao)) >=' => date("H:i:s",strtotime($this->datetimeBrEn($periodo['inicio']))),
                                    ],
                                    'TIME(Agendamento.horario) <=' => date("H:i:s",strtotime($this->datetimeBrEn($periodo['fim']))),
                                    'Agendamento.dia_semana' => null,
                                    'Agendamento.dia_mes' => (int)date('d',strtotime($this->datetimeBrEn($periodo['inicio']))),
                                ]
                            ]
                        ];
        
                        $agendamentos = $this->Agendamento->find('all',[
                            'fields' => ['*'],
                            'conditions' => $conditions,
                            'link' => ['Cliente', 'ClienteCliente' => ['Usuario']]
                        ]);

                        if ( count($agendamentos) > 0 ){
                            foreach($agendamentos as $key => $agend) {
    
                                $data_horario = date("Y-m-d", strtotime($this->datetimeBrEn($periodo['inicio'])));
                                $hora_horario = date("H:i:s", strtotime($agend['Agendamento']['horario']));
                                $horario = $data_horario.' '.$hora_horario;
                                $agend['Agendamento']['horario'] = $horario;

                                if ( $agend['Agendamento']['dia_semana'] == null && $agend['Agendamento']['dia_mes'] == null ) {
                                    if ( $this->Agendamento->cancelSheduling($agend['Agendamento']['id']) ) {
                                        $this->sendNotificationShedulingCanceled($agend);
                                    }
                                } else {
                                    if ( $this->AgendamentoFixoCancelado->cancelSheduling($agend, $agend['Agendamento']['cliente_cliente_id']) ) {
                                        $this->sendNotificationShedulingCanceled($agend);                                        
                                    }
                                }

                            } 
                        }
                    }
                }
            }

        }

    }

    private function sendNotificationShedulingCanceled($agendamento = []) {
        if ( count($agendamento) == 0 )
            return true;
        
        $this->avisaConvidadosCancelamento($agendamento, (object)['horario'=> $agendamento['Agendamento']['horario']] );
        if ( isset($agendamento['Usuario']) && isset($agendamento['Usuario']['id']) && $agendamento['Usuario'] != '' && $agendamento['Usuario'] != null ) {
            $this->enviaNotificacaoDeCancelamento('cliente', $agendamento );
        }
    }

    public function cadastrar_inscricao(){
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

        if ( !isset($dados->torneio_id) || $dados->torneio_id == '' || $dados->torneio_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar o torneio que você deseja fazer a inscrição.'))));
        }

        if ( !isset($dados->torneio_categoria_id) || $dados->torneio_categoria_id == '' || $dados->torneio_categoria_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar a categoria para realizar sua inscrição em um torneio.'))));
        }
       
        //impedimentos da dupla
        $impedimentos = [];
        if ( isset($dados->impedimentos) && count($dados->impedimentos) > 0 ) {
            foreach( $dados->impedimentos as $key => $impedimento ){
    
                if ( !isset($impedimento->data) || $impedimento->data == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data do impedimento não informado'))));
                }
                if ( !isset($impedimento->das) || $impedimento->das == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora início do impedimento não informado'))));
                }
                if ( !isset($impedimento->ate_as) || $impedimento->ate_as == ""  ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora limite do impedimento não informado'))));
                }
                $impedimentos[$key]['inicio'] = $this->dateBrEn($impedimento->data).' '.$impedimento->das;
                $impedimentos[$key]['fim'] = $this->dateBrEn($impedimento->data).' '.$impedimento->ate_as;
    
            }

        }

        $this->loadModel('ClienteCliente');
        $this->loadModel('TorneioInscricao');
        $this->loadModel('Torneio');
        $this->loadModel('Usuario');
        //$this->loadModel('TorneioCategoria');

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        $dados_torneio = $this->Torneio->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'Torneio.id' => $dados->torneio_id,
                'Torneio.inscricoes_de <=' => date('Y-m-d'),
                'Torneio.inscricoes_ate >=' => date('Y-m-d'),
            ],
            'link' => [],
        ]);

        if ( count($dados_torneio) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do torneio não encontrados.'))));
        }

        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] == 2 ){

            if ( !isset($dados->jogador_1) || $dados->jogador_1 == '' || $dados->jogador_1 == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O Jogador 1 deve ser informado.'))));
            }
            
            if ( !isset($dados->jogador_2) || $dados->jogador_2 == '' || $dados->jogador_2 == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O Jogador 2 deve ser informado.'))));
            }

            $v_cadastro_jogador_1 = $this->ClienteCliente->buscaDadosClienteCliente($dados->jogador_1, $dados_usuario['Usuario']['cliente_id']);
            if ( count($v_cadastro_jogador_1) == 0 ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O Jogador 1 não foi localizado.'))));
            }

            $v_cadastro_jogador_2 = $this->ClienteCliente->buscaDadosClienteCliente($dados->jogador_2, $dados_usuario['Usuario']['cliente_id']);
            if ( count($v_cadastro_jogador_2) == 2 ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O Jogador 2 não foi localizado.'))));
            }

        //se é um usuário cadastrando
        } else if ( $dados_usuario['Usuario']['nivel_id'] == 3 ){

            if ( !isset($dados->telefone) || $dados->telefone == '' || $dados->telefone == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O seu telefone deve ser informado.'))));
            }
            if ( !isset($dados->nome_dupla) || $dados->nome_dupla == '' || $dados->nome_dupla == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O nome da dupla deve ser informado.'))));
            }
            if ( !isset($dados->telefone_dupla) || $dados->telefone_dupla == '' || $dados->telefone_dupla == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O telefone da dupla deve ser informado.'))));
            }

            $email_dupla = null;
            $usuario_id_dupla = null;

            //verifica se o email da dupla foi stado
            if ( isset($dados->email_dupla) && $dados->email_dupla != '' && $dados->email_dupla != null ) {
                if ( !filter_var($dados->email_dupla, FILTER_VALIDATE_EMAIL) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O email da dupla é inálido.'))));
                }
                $email_dupla = $dados->email_dupla;
            }

            //se o email foi setado, verifica se tem algum usuario cadastrado com este email
            if ( $email_dupla != null ){

                $dados_usuario_dupla = $this->Usuario->getByEmail($email_dupla);
                if ( count($dados_usuario_dupla) > 0 ){
                    $usuario_id_dupla = $dados_usuario['Usuario']['id'];
                    //se tem, verifico se existe os dados da dupla na empresa do torneio
                    $dados_cliente_cliente_dupla = $this->ClienteCliente->buscaDadosUsuarioComoCliente($usuario_id_dupla, $dados_torneio['Torneio']['cliente_id']);
                    if ( count($dados_cliente_cliente_dupla) > 0 ){
    
                        //verifico se o usuário da dupla já nao esta cadastrado no torneio antes de atualizar os dados
                        $check_inscricao = $this->TorneioInscricao->checkSubscription($dados_cliente_cliente_dupla, $dados->torneio_id);
            
                        if ( $check_inscricao !== false ){
                            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O jogador 2 já está inscrito no torneio'))));
                        }

                        $dados_salvar_dupla = [
                            'id' => $dados_cliente_cliente_dupla['ClienteCliente']['id'],
                            'nome' => $dados->nome_dupla,
                            'telefone' => $dados->telefone_dupla,
                            'usuario_id' => $dados_cliente_cliente_dupla['ClienteCliente']['usuario_id'],
                        ];

                    } else {
                        $dados_salvar_dupla = [
                            'usuario_id' => $usuario_id_dupla,
                            'cliente_id' => $dados_torneio['Torneio']['cliente_id'],
                            'nome' => $dados->nome_dupla,
                            'email' => $email_dupla,
                            'telefone' => $dados->telefone_dupla,
                        ];
                    }
                }

            }

            //se ainda não cadastrei/atualizei os dados da dupla
            if ( !isset($dados_salvar_dupla) ){

                if ( $email_dupla != null ) {
                    $dados_cliente_cliente_dupla = $this->ClienteCliente->buscaPorEmail($dados_torneio['Torneio']['cliente_id'], $email_dupla);
                }

                if ( isset($dados_cliente_cliente_dupla) && count($dados_cliente_cliente_dupla) > 0 ){

                    //verifico se o usuário da dupla já nao esta cadastrado no torneio antes de atualizar os dados
                    $check_inscricao = $this->TorneioInscricao->checkSubscription($dados_cliente_cliente_dupla, $dados->torneio_id);
            
                    if ( $check_inscricao !== false ){
                        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O jogador 2 já está inscrito no torneio'))));
                    }

                    $dados_salvar_dupla = [
                        'id' => $dados_cliente_cliente_dupla['ClienteCliente']['id'],
                        'nome' => $dados->nome_dupla,
                        'telefone' => $dados->telefone_dupla,
                        'usuario_id' => $dados_cliente_cliente_dupla['ClienteCliente']['usuario_id'],
                    ];

                } else {
                    $dados_salvar_dupla = [
                        'usuario_id' => null,
                        'cliente_id' => $dados_torneio['Torneio']['cliente_id'],
                        'nome' => $dados->nome_dupla,
                        'email' => $email_dupla,
                        'cpf' => null,
                        'telefone' => $dados->telefone_dupla,
                    ];

                }

            }

            //atualizo o telefone do usuário
            $this->Usuario->atualizaTelefone($dados_usuario['Usuario']['id'], $dados->telefone);

            $v_cadastro_jogador_1 = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], $dados_torneio['Torneio']['cliente_id']);
            if ( count($v_cadastro_jogador_1) == 0 ){
                //se não achei os dados do usuário como cliente da empresa, eu crio
                $v_cadastro_jogador_1 = $this->ClienteCliente->criaDadosComoCliente($dados_usuario['Usuario']['id'], $dados_torneio['Torneio']['cliente_id']);
            }

            if ( count($v_cadastro_jogador_1) == 0 ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar seus dados no torneio.'))));
            }

            $this->ClienteCliente->create();
            $v_cadastro_jogador_2 = $this->ClienteCliente->save($dados_salvar_dupla);

            if ( count($v_cadastro_jogador_2) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar os dados da dupla.'))));
            }

        }

        if ( $dados_torneio['Torneio']['impedimentos'] < count($impedimentos) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Você só pode selecionar até '.$dados_torneio['Torneio']['impedimentos'].' impedimentos.'))));
        }

        $check_inscricao = $this->TorneioInscricao->checkSubscription($v_cadastro_jogador_1, $dados->torneio_id);

        if ( $check_inscricao !== false ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O jogador 1 já está inscrito no torneio'))));
        }

        $check_inscricao = $this->TorneioInscricao->checkSubscription($v_cadastro_jogador_2, $dados->torneio_id);

        if ( $check_inscricao !== false ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O jogador 2 já está inscrito no torneio'))));
        }
        
        //$dados_cliente_cliente = $this->ClienteCliente->buscaDadosSemVinculo($inscricao_usuario_id, true);
        //$dados_cliente_cliente = array_values($dados_cliente_cliente);
        
        $dados_salvar = [
            'TorneioInscricao' => [
                'torneio_id' => $dados->torneio_id,
                'cliente_cliente_id' => $v_cadastro_jogador_1['ClienteCliente']['id'],
                'dupla_id' => $v_cadastro_jogador_2['ClienteCliente']['id'],
                'torneio_categoria_id' => $dados->torneio_categoria_id,
                'confirmado' => 'N',
            ],
            'TorneioInscricaoJogador' => [
                [                    
                    'cliente_cliente_id' => $v_cadastro_jogador_1['ClienteCliente']['id'],
                ],
                [
                    'cliente_cliente_id' => $v_cadastro_jogador_2['ClienteCliente']['id'],
                ]
            ]

        ];

        if ( count($impedimentos) > 0 ) {
            $dados_salvar['TorneioInscricaoImpedimento'] = $impedimentos;
        }

        //debug($dados_salvar); die();

        if ( !$this->TorneioInscricao->saveAssociated($dados_salvar, ['deep' => true]) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao realizar a inscrição.'))));
        }

        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! A inscrição foi cadastrada com sucesso!'))));
    }

    public function cancela_inscricao(){
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

        if ( !isset($dados->subscription_id) || $dados->subscription_id == '' || $dados->subscription_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Inscrição não informada.'))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para cancelar inscrição.'))));
        }

        $this->loadModel('TorneioInscricao');
        $dados_inscricao = $this->TorneioInscricao->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioInscricao.id' => $dados->subscription_id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
            ],
            'link' => ['Torneio'],
        ]);

        if ( count($dados_inscricao) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados da inscrição não encontrados.'))));
        }

        $dados_salvar = [
            'id' => $dados_inscricao['TorneioInscricao']['id'],
            'confirmado' => 'R'
        ];

        if (!$this->TorneioInscricao->save($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao cancelar a inscrição.'))));
        }
       
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! A inscrição foi cancelada com sucesso!'))));
    }

    public function finaliza_inscricoes(){
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

        if ( !isset($dados->torneio_id) || $dados->torneio_id == '' || $dados->torneio_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Torneio não informado.'))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para finalizar as inscrições.'))));
        }

        $this->loadModel('Torneio');
        $dados_torneio = $this->Torneio->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'Torneio.id' => $dados->torneio_id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
            ],
            'link' => [],
        ]);

        if ( count($dados_torneio) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do torneio não encontrados.'))));
        }

        $hoje = date('Y-m-d');
        $ontem = date('d/m/Y', strtotime('-1 day', strtotime($hoje)));
        $dados_salvar = [
            'id' => $dados_torneio['Torneio']['id'],
            'inscricoes_ate' => $ontem
        ];

        if (!$this->Torneio->save($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao finalizar as inscrições.'))));
        }
       
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! As inscrições foram finalizadas com sucesso!'))));
    }

    public function gera_grupos(){
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

        if ( !isset($dados->torneio_id) || $dados->torneio_id == '' || $dados->torneio_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Torneio não informado.'))));
        }

        if ( !isset($dados->n_chaves) || $dados->n_chaves == '' || $dados->n_chaves == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Nº de chaves não informado.'))));
        }

        if ( !isset($dados->n_duplas_p_chave) || $dados->n_duplas_p_chave == '' || $dados->n_duplas_p_chave == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Nº de duplas por chave não informado.'))));
        }

        if ( !isset($dados->torneio_categoria_id) || $dados->torneio_categoria_id == '' || $dados->torneio_categoria_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Categoria não informada.'))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para finalizar as inscrições.'))));
        }

        $this->loadModel('Torneio');
        $this->loadModel('TorneioCategoria');
        $this->loadModel('TorneioInscricao');
        $this->loadModel('TorneioGrupo');

        $dados_torneio = $this->Torneio->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'Torneio.id' => $dados->torneio_id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
            ],
            'link' => [],
        ]);

        if ( count($dados_torneio) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do torneio não encontrados.'))));
        }
        
        if ($dados_torneio['Torneio']['inscricoes_ate'] >= date('Y-m-d') ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'As inscrições do torneio ainda não foram encerradas.'))));
        }
    
        $dados_torneio_categoria = $this->TorneioCategoria->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioCategoria.torneio_id' => $dados->torneio_id,
                'TorneioCategoria.id' => $dados->torneio_categoria_id,
            ],
            'link' => [],
        ]);

        if ( count($dados_torneio_categoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Categoria não encontrada.'))));
        }
    
        $inscricoes = $this->TorneioInscricao->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioInscricao.torneio_id' => $dados->torneio_id,
                'TorneioInscricao.torneio_categoria_id' => $dados->torneio_categoria_id,
                'not' => [
                    'TorneioInscricao.confirmado' => 'R',
                ]
            ],
            'order' => 'rand()',
            'link' => [],
        ]);

        if ( count($inscricoes) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Nenhuma inscrição registrada para esta categoria.'))));
        }

        if ( count($inscricoes) < $dados->n_duplas_p_chave ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Inscrições insuficientes.'))));
        }

        $grupos_salvar = [];
        $alphas = range('A', 'Z');
        for( $i = 0; $i < $dados->n_chaves; $i++ ){
            $letra_grupo = $alphas[$i];
            foreach($inscricoes as $key => $inscricao){

                if (  !isset($grupos_salvar[$letra_grupo]) || count($grupos_salvar[$letra_grupo]) < $dados->n_duplas_p_chave ){

                    $grupos_salvar[$letra_grupo][] = [
                        'torneio_inscricao_id' => $inscricao['TorneioInscricao']['id'],
                        'torneio_categoria_id' => $dados->torneio_categoria_id,
                        'nome' => 'Chave '.$alphas[$i],
                    ];
                    unset($inscricoes[$key]);
                   

                }
            }
        }

        $grupos_salvar = $this->distribui_restante_inscricoes($inscricoes, $grupos_salvar, $dados->torneio_categoria_id);
        $dados_salvar = [];
        foreach($grupos_salvar as $key => $grupos){
            foreach( $grupos as $key_grupo => $grupo ){
                $dados_salvar[] = $grupo;
            }
        }

        $this->TorneioGrupo->deleteAll(['TorneioGrupo.torneio_categoria_id' => $dados->torneio_categoria_id]);

        $dados_categoria_salvar = [
            'id' => $dados->torneio_categoria_id,
            'n_chaves' => $dados_torneio['Torneio']['id'],
        ];

        if (!$this->TorneioGrupo->saveMany($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao gerar os grupos.'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Grupos gerados com sucesso!'))));
    }

    public function grupos(){

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['torneio_categoria_id']) || $dados['torneio_categoria_id'] == "" || !is_numeric($dados['torneio_categoria_id']) ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados['token'], $dados['email']);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioGrupo');
        $this->loadModel('TorneioCategoria');
        $this->loadModel('TorneioInscricao');
        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioInscricaoJogador');
    
        $dados_torneio_categoria = $this->TorneioCategoria->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioCategoria.id' => $dados['torneio_categoria_id'],
            ],
            'link' => ['Torneio'],
        ]);

        if ( count($dados_torneio_categoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Categoria não encontrada.'))));
        }
    
        $grupos = $this->TorneioGrupo->find('list',[
            'fields' => ['TorneioGrupo.nome', 'TorneioGrupo.nome'],
            'conditions' => [
                'TorneioGrupo.torneio_categoria_id' => $dados['torneio_categoria_id'],
            ],
            'order' => 'TorneioGrupo.nome',
            'link' => [],
        ]);

        $grupos_retornar = [];
        if ( count($grupos) > 0 ) {
            foreach( $grupos as $id => $grupo ){
                $integrantes = $this->TorneioInscricao->find('all',[
                    'conditions' => [
                        'TorneioInscricao.torneio_categoria_id' => $dados['torneio_categoria_id'],
                        'TorneioGrupo.nome' => $grupo,
                        'not' => [
                            'TorneioInscricao.confirmado' => 'R',
                        ]
                    ],
                    'link' => ['TorneioGrupo']
                ]);
                
                if ( count($integrantes) > 0 ) {

                    foreach( $integrantes as $key_integrante => $integrante) {
                        $integrantes[$key_integrante]['TorneioInscricao']['_nome_dupla'] = $this->TorneioInscricaoJogador->buscaNomeDupla($integrante['TorneioInscricao']['id']);
                        $integrantes[$key_integrante]['TorneioInscricao']['_vitorias'] = $this->TorneioJogo->buscaNVitorias($integrante['TorneioInscricao']['id'], 1);
                        $integrantes[$key_integrante]['TorneioInscricao']['_games'] = $this->TorneioJogo->buscaNGames($integrante['TorneioInscricao']['id'], 1);
                        //$dados[$key]['TorneioInscricao']['_owner'] = $owner;
                    }

                    $integrantes = $this->ordena_times($integrantes);

                }
    
                $grupos_retornar[] = [
                    '_nome' => $grupo,
                    //'_id' => $id,
                    '_integrantes' => $integrantes,
                ];
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $grupos_retornar))));
    }

    public function busca_grupos_lista(){

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['torneio_categoria_id']) || $dados['torneio_categoria_id'] == "" || !is_numeric($dados['torneio_categoria_id']) ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados['token'], $dados['email']);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioGrupo');
        $this->loadModel('TorneioCategoria');
        $dados_torneio_categoria = $this->TorneioCategoria->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioCategoria.id' => $dados['torneio_categoria_id'],
            ],
            'link' => ['Torneio'],
        ]);

        if ( count($dados_torneio_categoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Categoria não encontrada.'))));
        }
    
        $grupos = $this->TorneioGrupo->find('all',[
            'fields' => ['TorneioGrupo.nome', 'TorneioGrupo.nome'],
            'conditions' => [
                'TorneioGrupo.torneio_categoria_id' => $dados['torneio_categoria_id'],
            ],
            'order' => 'TorneioGrupo.nome',
            'group' => 'TorneioGrupo.nome',
            'link' => [],
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $grupos))));
    }

    private function distribui_restante_inscricoes($inscricoes = [], $grupos_salvar = [], $torneio_categoria_id) {
        if ( count($inscricoes) > 0 ) {

            $grupos_invertidos = array_reverse($grupos_salvar);
            foreach($inscricoes as $key => $inscricao){
                if ( count($grupos_invertidos) > 0 ) {
                    foreach($grupos_invertidos as $key_grupo => $grupo){
                        $grupos_salvar[$key_grupo][] = [
                            'torneio_inscricao_id' => $inscricao['TorneioInscricao']['id'],
                            'torneio_categoria_id' => $torneio_categoria_id,
                            'nome' => 'Chave '.$key_grupo,
                        ];
    
                        unset($inscricoes[$key]);
                        unset($grupos_invertidos[$key_grupo]);
                        break;
                    }

                }
            }

            return $this->distribui_restante_inscricoes($inscricoes, $grupos_salvar, $torneio_categoria_id);

        } else {
            return $grupos_salvar;
        }
        
    }

    public function troca_dupla_grupo(){
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

        if ( !isset($dados->torneio_inscricao_id) || $dados->torneio_inscricao_id == '' || $dados->torneio_inscricao_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Inscrição não informada.'))));
        }

        if ( !isset($dados->grupo_destino) || $dados->grupo_destino == '' || $dados->grupo_destino == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Chave de destino não informada.'))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para trocar inscritos de grupo.'))));
        }

        $this->loadModel('TorneioInscricao');
        $this->loadModel('TorneioGrupo');
 
        $dados_inscricao = $this->TorneioInscricao->find('first',[
            'fields' => ['TorneioInscricao.torneio_categoria_id', 'TorneioGrupo.id', 'TorneioGrupo.nome'],
            'conditions' => [
                'TorneioInscricao.id' => $dados->torneio_inscricao_id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
                'not' => [
                    'TorneioInscricao.confirmado' => 'R',
                    'TorneioGrupo.id' => null
                ]
            ],
            'link' => [
                'Torneio',
                'TorneioGrupo'
            ]
        ]);

        if ( count($dados_inscricao) == 0 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Inscrição não econtrada.'))));
        }

        $n_inscritos_grupo = $this->TorneioGrupo->find('count',[
            'conditions' => [
                'TorneioGrupo.nome' => $dados_inscricao['TorneioGrupo']['nome'],
                'TorneioGrupo.torneio_categoria_id' => $dados_inscricao['TorneioInscricao']['torneio_categoria_id'],
            ]
        ]);

        if ( $n_inscritos_grupo <= 2 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você não pode ter menos que 2 duplas em um grupo.'))));
        }
 
        $dados_grupo = $this->TorneioGrupo->find('first',[
            'conditions' => [
                'TorneioGrupo.nome' => $dados->grupo_destino,
                'TorneioGrupo.torneio_categoria_id' => $dados_inscricao['TorneioInscricao']['torneio_categoria_id'],
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
                'not' => [                    
                    'TorneioInscricao.confirmado' => 'R'
                ]
            ],
            'link' => [
                'TorneioInscricao' => ['Torneio']
            ]
        ]);

        if ( count($dados_grupo) == 0 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Chave não econtrada.'))));
        }

        $dados_salvar = [
            'id' => $dados_inscricao['TorneioGrupo']['id'],
            'nome' => $dados->grupo_destino,            
        ];

        if ( !$this->TorneioGrupo->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao mudar a dupla de grupo'))));
        }
        

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Inscrição trocada de grupo com sucesso!'))));
    }

    public function cancela(){
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

        if ( !isset($dados->torneio_id) || $dados->torneio_id == '' || $dados->torneio_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Torneio não informado.'))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para cancelar torneios.'))));
        }

        $this->loadModel('Torneio');
        $dados_torneio = $this->Torneio->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'Torneio.id' => $dados->torneio_id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
            ],
            'link' => [],
        ]);

        if ( count($dados_torneio) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do torneio não encontrados.'))));
        }


        if (!$this->Torneio->delete($dados->torneio_id, true)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao cancelar o torneio.'))));
        }
       
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! O torneio foi cancelado com sucesso.'))));
    }

    public function gera_jogos(){
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

        if ( !isset($dados->torneio_id) || $dados->torneio_id == '' || $dados->torneio_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Torneio não informado.'))));
        }
        
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para gerar jogos.'))));
        }

        $this->loadModel('Torneio');
        $this->loadModel('TorneioCategoria');
        $this->loadModel('TorneioQuadra');
        $this->loadModel('TorneioGrupo');
        $this->loadModel('TorneioQuadraPeriodo');
        $this->loadModel('Agendamento');

        $dados_torneio = $this->Torneio->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'Torneio.id' => $dados->torneio_id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
            ],
            'link' => [],
        ]);

        if ( count($dados_torneio) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do torneio não encontrados.'))));
        }
        
        if ($dados_torneio['Torneio']['inscricoes_ate'] >= date('Y-m-d') ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'As inscrições do torneio ainda não foram encerradas.'))));
        }

        $verifica_categorias = $this->TorneioCategoria->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioCategoria.torneio_id' => $dados->torneio_id,
                'TorneioInscricao.id' => null,
            ],
            'link' => ['TorneioInscricao'],
        ]);

        if ( count($verifica_categorias) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Ainda existem categorias sem grupos gerados.'))));
        }

        //busca quadras do torneio
        $quadras = $this->TorneioQuadra->find('all',[
            'conditions' => [
                'TorneioQuadra.torneio_id' => $dados->torneio_id
            ],
            'contain' => ['TorneioQuadraPeriodo', 'ClienteServico']
        ]);

        //busca grupos
        $grupos = $this->TorneioGrupo->find('all',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioCategoria.torneio_id' => $dados->torneio_id
            ],
            'link' => ['TorneioInscricao' => ['TorneioCategoria']]
        ]);

        //gera os confrontos por grupos
        $confrontos = $this->geraConfrontos($grupos);

        if ( !$confrontos || count($confrontos) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Impossível gerar os confronto.'))));
        }

        //gera os horatios do torneio
        $horarios = $this->TorneioQuadraPeriodo->getTimeList($dados->torneio_id);

        if ( !$horarios || count($horarios) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Impossível gerar os horários dos confrontos.'))));
        }

        //atribui os horarios gerados aos confrontos
        $confrontos = $this->atribui_horarios_confrontos($confrontos,$horarios);

        if ( !$confrontos || count($confrontos) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Impossível gerar os jogos.'))));
        }

        $dados_salvar = [];
        foreach( $confrontos as $key => $dados_confronto ){
            if ( isset($dados_confronto['confrontos']) && is_array($dados_confronto['confrontos']) && count($dados_confronto['confrontos']) ){

                foreach($dados_confronto['confrontos'] as $key_confronto => $confronto ){
                    $dados_salvar[] = [
                        'Agendamento' => [
                            'cliente_id' => $dados_usuario['Usuario']['cliente_id'],
                            'torneio_id' => $dados->torneio_id,
                            'horario' => $confronto['horario']['horario'],
                            'duracao' => $confronto['horario']['duracao']
                        ],
                        'TorneioJogo' => [
                            [
                                'torneio_categoria_id' => $dados_confronto['torneio_categoria_id'],
                                'torneio_quadra_id' => $confronto['horario']['torneio_quadra_id'],
                                'time_1' => $confronto[0]['inscricao_id'],
                                'time_2' => $confronto[1]['inscricao_id'],
                                'fase' => 1
                            ]
                        ]
                    ];

                }
            }
        }

        $this->Agendamento->deleteAll(['Agendamento.torneio_id' => $dados->torneio_id]);
        if (!$this->Agendamento->saveAll($dados_salvar, ['deep' => true])) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao gerar os jogos.'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Jogos gerados com sucesso!'))));
    }

    private function geraConfrontos ($grupos = []){
        $arr_retornar = [];
        if ( count($grupos) == 0 ) {
            return $arr_retornar;
        }

        $arr_grupos = [];

        foreach($grupos as $key => $grupo){
            $torneio_categoria_id = $grupo['TorneioGrupo']['torneio_categoria_id'];
            $arr_grupos[$torneio_categoria_id][$grupo['TorneioGrupo']['nome']][] = ['grupo' => $grupo['TorneioGrupo']['nome'], 'inscricao_id' => $grupo['TorneioInscricao']['id']];
        }
        
        foreach($arr_grupos as $torneio_categoria_id => $chaveamentos ) {

            foreach($chaveamentos as $grupo_nome => $inscricoes){
                $arr_retornar[] = [
                    'torneio_categoria_id' => $torneio_categoria_id,
                    'grupo_nome' => $grupo_nome,
                    'confrontos' => $this->gera_confrontos($inscricoes),
                ];
            }

        }

        return $arr_retornar;

    }

    private function gera_confrontos($inscricoes = []){
        if ( count($inscricoes) == 0 ){
            return [];
        }

        $Combinations = new CombinationsComponent($inscricoes);
        $combinations = $Combinations->getCombinations(2, false);
        return $combinations;

    }

    private function atribui_horarios_confrontos( $confrontos = [], $horarios = [] ){

        if ( count($confrontos) == 0 || count($horarios) == 0 ){
            return [];
        }
        
        //conto os confrontos
        $n_confrontos = 0;
        foreach( $confrontos as $key => $dados_confronto ){
            if ( count($dados_confronto['confrontos']) > 0 ){
                $n_confrontos += count($dados_confronto['confrontos']);
            }
        }

        //verifica se tem horarios o suficiente pra todos os jogos
        if ( $n_confrontos > count($horarios) ){
            return false;
        }

        //pega os primeiros horarios pra não ter jogos de fase superior antes de jogos de fase anterior
        $horarios = array_slice($horarios, 0, $n_confrontos);
        $horarios = array_values($horarios);

        //embaralha os horarios
        shuffle($horarios);

        //atribui um horario pra cada jogo        
        foreach( $confrontos as $key => $dados_confronto ){
            if ( count($dados_confronto['confrontos']) > 0 ){

                $arr_confrontos = $dados_confronto['confrontos'];

                foreach( $arr_confrontos as $key_confronto => $confronto ){
                    $horario = $horarios[0];
                    $confrontos[$key]['confrontos'][$key_confronto]['horario'] = $horario;
                    unset($horarios[0]);
                    $horarios = array_values($horarios);
                }
            }
        }

        return $confrontos;

    }

    public function busca_jogos(){

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['torneio_id']) || $dados['torneio_id'] == "" || !is_numeric($dados['torneio_id']) ) {
            throw new BadRequestException('Torneio não informado!', 401);
        }

        if ( !isset($dados['torneio_categoria_id']) || $dados['torneio_categoria_id'] == "" || !is_numeric($dados['torneio_categoria_id']) ) {
            throw new BadRequestException('Categoria não informada!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados['token'], $dados['email']);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioInscricaoJogador');
        $this->loadModel('TorneioJogoPlacar');
        $this->loadModel('TorneioGrupo');

        $conditions = [
            'Agendamento.torneio_id' => $dados['torneio_id'],
            'TorneioJogo.torneio_categoria_id' => $dados['torneio_categoria_id'],
        ];
    
        $this->TorneioJogo->virtualFields['_quadra_nome'] = 'CONCAT_WS("", TorneioQuadra.nome, ClienteServico.nome)';

        $jogos = $this->TorneioJogo->find('all',[
            'fields' => ['Agendamento.horario', 'TorneioJogo.*'],
            'conditions' => $conditions,
            'order' => ['Agendamento.horario'],
            'link' => [
                'Agendamento',
                'TorneioQuadra' => [
                    'ClienteServico'
                ],
            ],
            'group' => [
                'TorneioJogo.id'
            ]
        ]);

       $datas = [];
       $datas_retornar = [];

        if ( count($jogos) > 0 ) {
            foreach( $jogos as $key => $jogo ){

                if ( isset($dados['grupo']) && $dados['grupo'] != '' ) {
                    $grupo_time_1 = $this->TorneioGrupo->buscaGrupoByTeam($jogo['TorneioJogo']['time_1']);
                    if ( $grupo_time_1 != $dados['grupo'] ) {
                        unset($jogos[$key]);
                        continue;
                    }
                }
                $jogos[$key]['TorneioJogo']['_nome_dupla1'] = $this->TorneioInscricaoJogador->buscaNomeDupla($jogo['TorneioJogo']['time_1']);
                $jogos[$key]['TorneioJogo']['_nome_dupla2'] = $this->TorneioInscricaoJogador->buscaNomeDupla($jogo['TorneioJogo']['time_2']);
                $jogos[$key]['TorneioJogo']['_hora'] = date('H:i',strtotime($jogo['Agendamento']['horario']));
                $jogos[$key]['TorneioJogo']['_data'] = date('d/m/Y',strtotime($jogo['Agendamento']['horario']));
                $jogos[$key]['TorneioJogo']['_resultados'] = $this->TorneioJogoPlacar->busca_resultados($jogo['TorneioJogo']['id']);
                $datas[] = date('d/m/Y',strtotime($jogo['Agendamento']['horario']));
            }
        }

        $jogos = array_values($jogos);

        if ( count($datas) > 0 ) {

            $datas = array_unique($datas);
            foreach( $datas as $data ){
                $datas_retornar[] = ['data' => $data];
            }

        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $jogos, 'datas' => $datas_retornar))));

    }

    public function salva_resultado(){
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

        if ( !isset($dados->placar) || $dados->placar == "" || !is_array($dados->placar) || count($dados->placar) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar ao menos um resultado antes de clicar em cadastrar'))));
        }

        $placares = [];
        foreach( $dados->placar as $key => $placar ){

            if ( !isset($placar->time_1_placar) || $placar->time_1_placar == '' || $placar->time_1_placar == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Placar da equipe 1 não informado'))));
            }

            if ( !isset($placar->time_2_placar) || $placar->time_2_placar == '' || $placar->time_2_placar == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Placar da equipe 2 não informado'))));
            }

            if ( !isset($placar->tipo) || $placar->tipo == '' || $placar->tipo == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => ' 2 não informado'))));
            }

            $placares[] = [
                'torneio_jogo_id' => $dados->id,
                'time_1_placar' => $placar->time_1_placar,
                'time_2_placar' => $placar->time_2_placar,
                'tipo' => $placar->tipo,
            ];
            
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
        $this->loadModel('TorneioJogoPlacar');

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

        $this->TorneioJogoPlacar->deleteAll(['TorneioJogoPlacar.torneio_jogo_id' => $dados->id]);
 
        if (!$this->TorneioJogoPlacar->saveMany($placares)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar o resultado do jogo.'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Resultado cadastrado com sucesso!'))));
    }

    public function busca_placares(){

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['torneio_jogo_id']) || $dados['torneio_jogo_id'] == "" || !is_numeric($dados['torneio_jogo_id']) ) {
            throw new BadRequestException('Jogo não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados['token'], $dados['email']);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioJogoPlacar');
    
        $placares = $this->TorneioJogoPlacar->find('all',[
            'fields' => ['TorneioJogoPlacar.*'],
            'conditions' => [
                'TorneioJogoPlacar.torneio_jogo_id' => $dados['torneio_jogo_id'],
            ],
            'link' => [],
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $placares))));

    }

    private function ordena_times($teams = [], $orderBy = ['_vitorias' => 'desc', '_games' => 'desc']) {
        usort($teams, function ($a, $b) use ($orderBy) {
            $ReturnValues = [true => -1, false => 1];
            $bIsBigger = true;
            $isAscending = 1;
        
            foreach ($orderBy as $key => $value) {
                $isAscending = ($value === 'asc') ? 1 : -1; //checks whether to go in ascending or descending order
                $bIsBigger = ($a['TorneioInscricao'][$key] < $b['TorneioInscricao'][$key]);  //does the comparing of target key; E.G 'points'
        
                if ($a['TorneioInscricao'][$key] !== $b['TorneioInscricao'][$key]) { //if values do not match
                    return $ReturnValues[$bIsBigger] * $isAscending; //the multiplication is done to create a negative return value incase of descending order
                }
        
            }
        
            return $ReturnValues[$bIsBigger] * $isAscending;
        });

        return $teams;
    }

    public function troca_horario_jogo(){
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

        if ( !isset($dados->torneio_jogo_id) || $dados->torneio_jogo_id == '' || $dados->torneio_jogo_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Jogo não informado.'))));
        }

        if ( !isset($dados->torneio_quadra_id) || $dados->torneio_quadra_id == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Quadra não informada!'))));
        }

        if ( !isset($dados->data) || $dados->data == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data não informada!'))));
        }

        if ( !isset($dados->hora) || $dados->hora == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora não informada!'))));
        }
        
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        //se é uma empresa cadastrando
        if ( $dados_usuario['Usuario']['nivel_id'] != 2 ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Sem permissão para troca de horarios.'))));
        }

        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioJogoPlacar');
        $this->loadModel('Agendamento');
        $this->loadModel('TorneioQuadra');
        $this->loadModel('TorneioQuadraPeriodo');

        $dados_jogo = $this->TorneioJogo->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'TorneioJogo.id' => $dados->torneio_jogo_id,
                'Torneio.cliente_id' => $dados_usuario['Usuario']['cliente_id'],
            ],
            'link' => ['TorneioCategoria' => ['Torneio']],
        ]);

        if ( count($dados_jogo) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do jogo não encontrados.'))));
        }

        $data_hora_en = $this->datetimeBrEn($dados->data . ' ' . $dados->hora);

        $verifica_quadra_horario = $this->Agendamento->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'Agendamento.horario' => $data_hora_en,
                'Agendamento.torneio_id' => $dados_jogo['Torneio']['id'],
                'TorneioJogo.torneio_quadra_id' => $dados_jogo['TorneioJogo']['torneio_quadra_id'],
            ],
            'link' => [
                'TorneioJogo'
            ]
        ]);

        //se há um jogo na quadra e horario selecioando
        if ( count($verifica_quadra_horario) > 0 ) {
            $dados_salvar = [
                [
                    'id' => $dados->torneio_jogo_id,
                    'agendamento_id' => $verifica_quadra_horario['TorneioJogo']['agendamento_id'],
                    'torneio_quadra_id' => $verifica_quadra_horario['TorneioJogo']['torneio_quadra_id'],
                ],
                [
                    'id' => $verifica_quadra_horario['TorneioJogo']['id'],
                    'agendamento_id' => $dados_jogo['TorneioJogo']['agendamento_id'],
                    'torneio_quadra_id' => $dados_jogo['TorneioJogo']['torneio_quadra_id'],
                ]
            ];

            if ( !$this->TorneioJogo->saveMany($dados_salvar) ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar a data/horário do jogo.'))));
            }

            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Data/horário do jogo atualizados com sucesso!'))));

        }

        $dados_quadra = $this->TorneioQuadra->find('first',[
            'conditions' => [
                'TorneioQuadra.id' => $dados->torneio_quadra_id,
                'TorneioQuadra.torneio_id' => $dados_jogo['Torneio']['id'],
                'TorneioQuadraPeriodo.inicio <=' => $data_hora_en,
                'TorneioQuadraPeriodo.fim >=' => $data_hora_en,
            ],
            'link' => ['TorneioQuadraPeriodo']
        ]);

        if ( count($dados_quadra) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados da quadra não encontrados.'))));
        }

        $horarios = $this->TorneioQuadraPeriodo->getTimeList($dados_jogo['Torneio']['id'],$dados->torneio_quadra_id);

        $check_horario = array_filter($horarios,function ($horario) use($data_hora_en) {
            return $horario['horario'] == $data_hora_en;
        });

        if ( count($check_horario) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A quadra não está disponível para o dia e hora informados.'))));
        }

        $check_horario = array_values($check_horario);

        $dados_agendamento_salvar = [
            'cliente_id' => $dados_jogo['Torneio']['cliente_id'],
            'torneio_id' => $dados_jogo['Torneio']['id'],
            'horario' => $data_hora_en,
            'duracao' => $check_horario[0]['duracao'],
        ];

        $dados_agendamento_salvo = $this->Agendamento->save($dados_agendamento_salvar);

        if (!$dados_agendamento_salvo) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar o agendamento.'))));
        }

        $dados_jogo_salvar = [
            'id' => $dados_jogo['TorneioJogo']['id'],
            'agendamento_id' => $dados_agendamento_salvo['Agendamento']['id'],
        ];

 
        if ( !$this->TorneioJogo->save($dados_jogo_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar o horário do jogo.'))));
        }

        $this->Agendamento->delete($dados_jogo['TorneioJogo']['agendamento_id']);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Data/horário do jogo atualizados com sucesso!'))));
    }

    public function quadra_datas() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['torneio_quadra_id']) || $dados['torneio_quadra_id'] == "" || !is_numeric($dados['torneio_quadra_id']) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados['token'], $dados['email']);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioQuadraPeriodo');
    
        $datas = $this->TorneioQuadraPeriodo->find('all',[
            'fields' => ['TorneioQuadraPeriodo.*'],
            'conditions' => [
                'TorneioQuadraPeriodo.torneio_quadra_id' => $dados['torneio_quadra_id'],
            ],
            'group' => ['DATE(TorneioQuadraPeriodo.inicio)'],
            'order' => ['DATE(TorneioQuadraPeriodo.inicio)'],
            'link' => [],
        ]);

        $datas_list = [];
        foreach( $datas as $key => $data ) {
            $datas_list[]['data'] = date('d/m/Y', strtotime($data['TorneioQuadraPeriodo']['inicio']));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $datas_list))));

    }

    public function quadra_data_horarios() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['torneio_quadra_id']) || $dados['torneio_quadra_id'] == "" || !is_numeric($dados['torneio_quadra_id']) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        if ( !isset($dados['data']) || $dados['data'] == "" ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados['token'], $dados['email']);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioQuadraPeriodo');
        $this->loadModel('TorneioJogo');

        $horarios = $this->TorneioQuadraPeriodo->getTimeList(null,$dados['torneio_quadra_id'],$this->dateBrEn($dados['data']));

        $horarios_retornar = [];
        if ( count($horarios) > 0 ) {
            foreach( $horarios as $key => $horario ) {
                $texto_horario = '';
                if ( $this->TorneioJogo->checaJogoNoHorario($dados['torneio_quadra_id'], $horario['horario']) ) {
                    $texto_horario = ' *';
                }
                $horarios_retornar[$key]['horario'] = date('H:i:s',strtotime($horario['horario']));
                $horarios_retornar[$key]['complemento'] = $texto_horario;
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $horarios_retornar))));

    }

    public function busca_quadras(){

        $this->layout = 'ajax';
        $dados = $this->request->query;

        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Email não informado!', 401);
        }

        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados['torneio_id']) || $dados['torneio_id'] == "" || !is_numeric($dados['torneio_id']) ) {
            throw new BadRequestException('ID do torneio nào informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados['token'], $dados['email']);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('TorneioQuadra');
        $this->TorneioQuadra->virtualFields['_quadra_nome'] = 'CONCAT_WS("", TorneioQuadra.nome, ClienteServico.nome)';
        $quadras = $this->TorneioQuadra->find('all',[
            'fields' => ['TorneioQuadra.*', 'ClienteServico.nome'],
            'conditions' => [
                'TorneioQuadra.torneio_id' => $dados['torneio_id'],
            ],
            'link' => ['ClienteServico'],
        ]);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $quadras))));
    }

}