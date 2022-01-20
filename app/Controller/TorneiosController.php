<?php
class TorneiosController extends AppController {

    public function index($tipo='geral') {

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
                    'or' => [
                        ['TorneioInscricao.cliente_cliente_id' => $meus_ids_de_cliente],
                        ['TorneioInscricao.dupla_id' => $meus_ids_de_cliente],
                    ]
                ]);
            }
        } else {
            $conditions = array_merge($conditions, [
                'Torneio.inicio <=' => date('Y-m-d'),
                'Torneio.fim >=' => date('Y-m-d')
            ]);

        }

        $torneios = $this->Torneio->find('all',[
            'fields' => [
                'Torneio.*', 'Cliente.nome', 'Localidade.loc_no', 'Localidade.ufe_sg', 'Cliente.telefone'
            ],
            'conditions' => $conditions,
            'order' => ['Torneio.inicio'],
            'link' => ['TorneioInscricao', 'Cliente' => ['Localidade']]
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

    public function cadastrar(){
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        //$this->log($dados, 'debug');

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

        if ( !isset($dados->torneio_categoria) || $dados->torneio_categoria == "" || !is_array($dados->torneio_categoria) || count($dados->torneio_categoria) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar ao menos uma categoria para cadastrar um torneio'))));
        }

        if ( !isset($dados->torneio_data) || $dados->torneio_data == "" || !is_array($dados->torneio_data) || count($dados->torneio_data) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar ao menos um período de realização de jogos para cadastrar um torneio'))));
        }

        //categorias do torneio
        foreach( $dados->torneio_categoria as $key => $categoria ){

            if ( ( !isset($categoria->categoria_id) || $categoria->categoria_id == "" ) && ( !isset($categoria->nome) || $categoria->nome == "" ) ) {
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
            if ( !isset($categoria->inscricoes_de) || $categoria->inscricoes_de == "" ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data inicial das inscrições da categoria não informada'))));
            }
            if ( !isset($categoria->inscricoes_ate) || $categoria->inscricoes_ate == "" ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data limite das inscrições da categoria não informada'))));
            }
        }

        //periodos do torneio
        $quadras_periodos = [];
        foreach( $dados->torneio_data as $key => $torneio_data ){

            if ( !isset($torneio_data->data) || $torneio_data->data == ""  ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data do período não informado'))));
            }
            if ( !isset($torneio_data->inicio) || $torneio_data->inicio == ""  ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora início do período não informado'))));
            }
            if ( !isset($torneio_data->fim) || $torneio_data->fim == ""  ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora limite do período não informado'))));
            }
            if ( !isset($torneio_data->duracao_jogos) || $torneio_data->duracao_jogos == ""  ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Duração do jogos do período não informado'))));
            }
            $quadras_periodos[$key]['inicio'] = $torneio_data->data.' '.$torneio_data->inicio;
            $quadras_periodos[$key]['fim'] = $torneio_data->data.' '.$torneio_data->fim;

        }

        //quadras
        $quadras = [];
        if ( isset($dados->torneio_quadras) ) {
    
            if ( is_array($dados->torneio_quadras) )
                $dados->torneio_quadras = (object)$dados->torneio_quadras;
            
            foreach($dados->torneio_quadras as $key => $quadra){
                if($quadra) {
                    list($discard, $id_quadra) = explode('_',$key);
                    $quadras[] = ['servico_id' => $id_quadra, 'confirmado' => 'Y', 'TorneioQuadraPeriodo' => $quadras_periodos];
                }
            }
        }
        if ( isset($dados->torneio_quadras_terceiros) ) {

            if( is_array($dados->torneio_quadras_terceiros) )
                $dados->torneio_quadras_terceiros = (object)$dados->torneio_quadras_terceiros;
            
            foreach($dados->torneio_quadras_terceiros as $key => $quadra){
                $quadras[] = ['nome' => $quadra->nome, 'confirmado' => 'Y', 'TorneioQuadraPeriodo' => $quadras_periodos];
            }
        }

        if ( count($quadras) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve selecionar ao menos uma quadra para cadastrar um torneio.'))));
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_usuario['Usuario']['cliente_id'] == null || $dados_usuario['Usuario']['cliente_id'] == '' ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Torneio');
        $dados_salvar = [
            'Torneio' => [
                'cliente_id' => $dados_usuario['Usuario']['cliente_id'],
                'nome' => $dados->nome,
                'descricao' => $dados->descricao,
                'inicio' => $dados->inicio,
                'fim' => $dados->fim,
            ],
            'TorneioCategoria' => $dados->torneio_categoria,
            'TorneioData' => $dados->torneio_data,
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

    public function inscricao(){
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        //$this->log($dados, 'debug');

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->categoria) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar ao menos uma categoria para realizar sua inscrição em um torneio.'))));
        }

        if ( !isset($dados->torneio_id) || $dados->torneio_id == '' || $dados->torneio_id == null ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve informar o torneio que você deseja fazer a inscrição.'))));
        }
       
        //impedimentos da dupla
        $impedimentos = [];
        foreach( $dados->impedimentos as $key => $impedimento ){

            if ( !isset($impedimento->data) || $impedimento->data == ""  ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Data do impedimento não informado'))));
            }
            if ( !isset($impedimento->inicio) || $impedimento->inicio == ""  ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora início do impedimento não informado'))));
            }
            if ( !isset($impedimento->fim) || $impedimento->fim == ""  ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Hora limite do impedimento não informado'))));
            }
            $impedimentos[$key]['inicio'] = $this->dateBrEn($impedimento->data).' '.$impedimento->inicio;
            $impedimentos[$key]['fim'] = $this->dateBrEn($impedimento->data).' '.$impedimento->fim;

        }

        foreach($dados->categoria as $key => $categoria){
            if($categoria) {
                list($discard, $categoria_id) = explode('_',$key);
            }
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $inscricao_usuario_id = $dados_usuario['Usuario']['id'];
        if ( $dados_usuario['Usuario']['cliente_id'] != null && $dados_usuario['Usuario']['cliente_id'] != '' ) {
            if ( !isset($impedimento->cliente_cliente_id) || $impedimento->cliente_cliente_id == ""  ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Cliente não informado'))));
            }
            $inscricao_usuario_id = $dados->cliente_cliente_id;
        }

        $this->loadModel('ClienteCliente');
        $this->loadModel('TorneioInscricao');
        $this->loadModel('Torneio');
        //$this->loadModel('TorneioCategoria');

        $dados_torneio = $this->Torneio->find('first',[
            'fields' => ['*'],
            'conditions' => [
                'Torneio.id' => $dados->torneio_id,
                'Torneio.inicio <=' => date('Y-m-d'),
                'Torneio.fim >=' => date('Y-m-d'),
                'TorneioCategoria.id' => $categoria_id
            ],
            'link' => ['TorneioCategoria' => ['PadelCategoria']],
        ]);

        if ( count($dados_torneio) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do torneio não encontrados.'))));
        }

        if ( date('Y-m-d H:i:s') < $dados_torneio['TorneioCategoria']['inscricoes_de'] ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O prazo para inscrição ainda não começou.'))));
        }

        if ( date('Y-m-d H:i:s') > $dados_torneio['TorneioCategoria']['inscricoes_ate'] ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O prazo para inscrição já encerrou.'))));
        }

        if ( $dados_torneio['TorneioCategoria']['impedimentos'] < count($impedimentos) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Você só pode selecionar até '.$dados_torneio['TorneioCategoria']['impedimentos'].' impedimentos.'))));
        }
        
        $dados_cliente_cliente = $this->ClienteCliente->buscaDadosSemVinculo($inscricao_usuario_id, true);
        $dados_cliente_cliente = array_values($dados_cliente_cliente);

        $dupla_id = null;
        $dupla_nome = null;
    
        if ( isset($dados->dupla_nome) && $dados->dupla_nome != '' && $dados->dupla_nome != null )
            $dupla_nome = $dados->dupla_nome;

        if ( isset($dados->dupla_id) && $dados->dupla_id != '' && $dados->dupla_id != null ) {
            $dupla_id = $dados->dupla_id;
            $check_inscricao = $this->TorneioInscricao->getByClientClientId($dupla_id, $dados->torneio_id);
        
            if ( count($check_inscricao) > 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A dupla já estava inscrita neste torneio.'))));
            }

            /*if ( $dados_torneio['TorneioCategoria']['categoria_id'] != null ) {
                $check_categoria = $this->
                debug($dados_torneio['PadelCategoria']); die();
            }*/

        }

        $check_inscricao = $this->TorneioInscricao->getByClientClientId($dados_cliente_cliente[0], $dados->torneio_id);
        
        if ( count($check_inscricao) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você já estava inscrito neste torneio.'))));
        }
        
        $dados_salvar = [
            'TorneioInscricao' => [
                'torneio_id' => $dados->torneio_id,
                'cliente_cliente_id' => $dados_cliente_cliente[0],
                'dupla_id' => $dupla_id,
                'torneio_categoria_id' => $categoria_id,
                'dupla_nome' => $dupla_nome,
            ],
        ];

        if ( count($impedimentos) > 0 ) {
            $dados_salvar['TorneioInscricaoImpedimento'] = $impedimentos;
        }

        if ( !$this->TorneioInscricao->saveAssociated($dados_salvar, ['deep' => true]) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao realizar sua inscrição.'))));
        }

        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! Sua inscrição foi cadastrada com sucesso!'))));
    }
}