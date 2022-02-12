<?php
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
            }
        }
        $dados['TorneioCategoria'] = $categorias;
        $dados['TorneioData'] = $this->TorneioData->getByTournamentId($dados['Torneio']['id']);
        $dados['Torneio']['_subscribed'] = $this->TorneioInscricaoJogador->checkSubscribed($meus_ids_de_cliente);
        $dados['Torneio']['_subscriptions_finished'] = $this->Torneio->checkIsSubscriptionsFinished($dados['Torneio']['id']);
        
        
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

        if ( !isset($dados->duracao) || $dados->duracao == "" ) {
            throw new BadRequestException('Fim não informado!', 401);
        }
        
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
            if ( !isset($categoria->n_chaves) || $categoria->n_chaves == "" || !is_numeric($categoria->n_chaves) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Nº de chaves da categoria não informado'))));
            }
            if ( !isset($categoria->n_duplas_p_chave) || $categoria->n_duplas_p_chave == "" || !is_numeric($categoria->n_duplas_p_chave) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Nº de duplas por chave da categoria não informado'))));
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
                if ( $periodo->das >= $periodo->ate_as) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A hora de início deve ser inferior a data final.'))));
                }

                $quadras[$key]['TorneioQuadraPeriodo'][] = ['inicio' => $periodo->data." ".$periodo->das, 'fim' =>  $periodo->data." ".$periodo->ate_as];

                $periodos_temp[$periodo->data][] = ['inicio' => $periodo->das, 'fim' => $periodo->ate_as];
            }

        }
        
        foreach($periodos_temp as $data => $per_temp){
            foreach( $per_temp as $key_item => $item ){
                if ( !isset($periodos[$data]) ) {
                    $periodos[$data][] = ['data' => $data, 'inicio' => $item['inicio'], 'fim' => $item['fim'], 'duracao_jogos' => $dados->duracao];
                } else {
                    foreach( $periodos[$data] as $key_per_temp => $item_salvo ){
                        //se o item está entre o salvo
                        if ( $item['inicio'] >= $item_salvo['inicio'] && $item['fim'] <= $item_salvo['inicio']  ) {
                            continue;
                        }
                        //se o item está antes do salvo
                        if ( $item['inicio'] < $item_salvo['inicio'] && $item['fim'] < $item_salvo['inicio']  ) {
                            $periodos[$data][] = ['data' => $data, 'inicio' => $item['inicio'], 'fim' => $item['fim'], 'duracao_jogos' => $dados->duracao];
                        }
                        //se o item está depois do salvo
                        else if ( $item['inicio'] > $item_salvo['fim'] && $item['fim'] > $item_salvo['fim'] ) {
                            $periodos[$data][] = ['data' => $data, 'inicio' => $item['inicio'], 'fim' => $item['fim'], 'duracao_jogos' => $dados->duracao];
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

}