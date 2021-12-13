<?php
App::uses('AuthComponent', 'Controller/Component');
App::import("Vendor", "FacebookAuto", array("file" => "facebook/src/Facebook/autoload.php"));
App::uses('CakeEmail', 'Network/Email');

class ClientesController extends AppController {

    public function index($categoria_id = null) {
        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];

        $dados_token = $this->verificaValidadeToken($token);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Cliente');

        $conditions = [
            'Cliente.ativo' => 'Y'
        ];

        if ( $categoria_id != null ) {
            $conditions = array_merge($conditions, [
                'Categoria.id' => $categoria_id
            ]);
        }

        $clientes = $this->Cliente->find('all',[
            'fields' => [
                'Cliente.*',
                'Localidade.loc_no'
            ],
            'link' => [
                'ClienteSubcategoria' => ['Subcategoria' => ['Categoria']], 'Localidade'
            ],
            'conditions' => $conditions,
            'group' => [
                'Cliente.id'
            ],
            'order' => ['Cliente.nome']
        ]);
        
        $this->loadModel('ClienteHorarioAtendimento');
        $arr_clientes_ids = [];
        foreach($clientes as $key => $cliente) {
            $arr_clientes_ids[] = $cliente['Cliente']['id'];
            $clientes[$key]['Cliente']['logo'] = $this->images_path.'clientes/'.$clientes[$key]['Cliente']['logo'];
            $clientes[$key]['Horarios'] = $this->ClienteHorarioAtendimento->find('all',[
                'conditions' => [
                    'ClienteHorarioAtendimento.cliente_id' => $cliente['Cliente']['id']
                ],
                'link' => []
            ]);

            $clientes[$key]['Cliente']['atendimento_hoje'] = $this->procuraHorariosHoje($clientes[$key]['Horarios']);
        }

        $this->loadModel('ClienteSubcategoria');
        $subcategorias = $this->ClienteSubcategoria->find('all',[
            'fields' => [
                'Subcategoria.*'
            ],
            'conditions' => [
                'ClienteSubcategoria.cliente_id' => $arr_clientes_ids
            ],
            'link' => ['Subcategoria'],
            'order' => ['Subcategoria.nome'],
            'group' => [
                'Subcategoria.id'
            ]
        ]);
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $clientes, 'subcategorias' => $subcategorias))));


    }

    private function procuraHorariosHoje($horarios = null) {

        if ( $horarios == null || count($horarios) == 0) {
            return "Não atende hoje";
        }

        $retorno = "Não atende hoje";
        foreach($horarios  as $key => $horario){
            if( $horario['ClienteHorarioAtendimento']['horario_dia_semana'] == date('w',strtotime('Y-m-d')) ) {
                if ( $retorno == 'Não atende hoje' )
                    $retorno = "das ".substr($horario['ClienteHorarioAtendimento']['abertura'], 0, 5).' até '.substr($horario['ClienteHorarioAtendimento']['fechamento'],0, 5);
                else
                    $retorno .= " | das ".substr($horario['ClienteHorarioAtendimento']['abertura'], 0, 5).' até '.substr($horario['ClienteHorarioAtendimento']['fechamento'],0, 5);
  
            }
        }
        
        return $retorno;

    }

    public function buscaCategorias() {
        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $dados_token = $this->verificaValidadeToken($token);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $conditions = [
            'Cliente.ativo' => 'Y',
            'not' => [
                'Categoria.id' => null
            ]
        ];

        if ( isset($dados['address']) && $dados['address'] != '' ) {
            $this->loadModel('Localidade');
            $dados_localidade = $this->Localidade->findByGoogleAddress($dados['address']);

            $conditions = array_merge($conditions, [
                'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
            ]);
        }

        $this->loadModel('Cliente');

        $categorias = $this->Cliente->find('all',[
            'fields' => [
                'Categoria.*'
            ],
            'link' => [
                'ClienteSubcategoria' => ['Subcategoria' => ['Categoria']]
            ],
            'conditions' => $conditions,
            'group' => [
                'Categoria.id'
            ]
        ]);
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $categorias))));


    }

    public function cadastrar() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->nome) || $dados->nome == '') {
            throw new BadRequestException('Nome não informado', 400);
        }
        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }
        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }
        if (!isset($dados->telefone) || $dados->telefone == '') {
            throw new BadRequestException('Telefone não informado', 400);
        }
        if (!isset($dados->senha) || $dados->senha == '') {
            throw new BadRequestException('Senha não informada', 400);
        }
        if (!isset($dados->tipo) || $dados->tipo == '') {
            throw new BadRequestException('tipo não informado', 400);
        }
        if (!isset($dados->subcategoria_id) || $dados->subcategoria_id == '' || !is_array($dados->subcategoria_id)) {
            throw new BadRequestException('subcategoria não informada', 400);
        }

        $nome = $dados->nome;
        $email = $dados->email;
        $telefone = $dados->telefone;
        $senha = $dados->senha;
        $tipo = $dados->tipo;
        $subcategoria_id = $dados->subcategoria_id;
        $cidade_id = $dados->cidade_id;
        $estado = $dados->estado;
        $cep = $dados->cep;
        $endereco = $dados->endereco;
        $endereco_n = $dados->endereco_n;

        $this->loadModel('Usuario');
        $ja_existe = ($this->Usuario->find('count', array('conditions' => array('Usuario.email' => $email))) > 0);

        if ($ja_existe) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Já existe um usuário cadastrado com este email. Por favor, informe outro.'))));
        }

        $this->loadModel('Cliente');
        if ( $dados->tipo == "F" ) {
            if (!isset($dados->cpf) || $dados->cpf == '') {
                throw new BadRequestException('CPF não informado', 400);
            }
            
            if ( !$this->validar_cpf($dados->cpf) ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'CPF inválido!'))));
            }

            $ja_existe = ($this->Cliente->find('count', array('conditions' => array('Cliente.cpf' => $dados->cpf))) > 0);

            if ($ja_existe) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Já existe um cliente com este CPF. Por favor, informe outro.'))));
            }

            $cpf = $dados->cpf;
            $cnpj = null;
            
        }
        else if ( $dados->tipo == "J" ) {
            if (!isset($dados->cnpj) || $dados->cnpj == '') {
                throw new BadRequestException('CNPJ não informado', 400);
            }
            
            if ( !$this->validar_cnpj($dados->cnpj) ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'CNPJ inválido!'))));
            }

            $ja_existe = ($this->Cliente->find('count', array('conditions' => array('Cliente.cnpj' => $dados->cnpj))) > 0);

            if ($ja_existe) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Já existe um cliente com este CNPJ. Por favor, informe outro.'))));
            }

            $cpf = null;
            $cnpj = $dados->cnpj;
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Tipo inválido.'))));
        }

        $token = md5(uniqid($email, true));

        $dados_salvar = array(
            'Cliente' => [
                'nome' => $nome,
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'tipo' => $tipo,
                'telefone' => $telefone,
                'cidade_id' => $cidade_id,
                'estado' => $estado,
                'cep' => $cep,
                'endereco' => $endereco,
                'endereco_n' => $endereco_n,
            ],
            'Usuario' => array(
                [
                    'nome' => $nome, 
                    'email' => $email, 
                    'telefone' => $telefone, 
                    //'email' => $dados->email, 
                    'senha' => $senha, 
                    'nivel_id' => 2
                ]
            ), 
            'Token' => array(
                array(
                    'token' => $token, 
                    'data_validade' => date('Y-m-d', strtotime(date("Y-m-d") . ' + 30 days')),
                    'notifications_id' => $dados->notifications_id,
                )
            ),
        );

        foreach( $dados->subcategoria_id as $subcategoria ) {
            $dados_salvar['ClienteSubcategoria'][]['subcategoria_id'] = $subcategoria;
        }

        $this->Cliente->set($dados_salvar);
        if ($this->Cliente->saveAssociated($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastrado com sucesso!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function registerComplementData() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('Email não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }
        
        $token = $dados->token;
        $email = $dados->email;

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Subcategoria');

        $subcategorias = [];
        $dados_turnos = [];
        $ids_quadras = $this->Subcategoria->buscaSubcategoriasQuadras(true);
        $isCourt = false;
        foreach($dados as $key_dado => $dado) {

            if ( strpos($key_dado, 'item_') !== false ) {
                list($discart, $subcategoria_id) = explode('item_', $key_dado);
                $subcategorias[] = $subcategoria_id;
                if ( in_array($subcategoria_id, $ids_quadras) )
                    $isCourt = true;
            }

            if ( strpos($key_dado, 'turnos_') !== false ) {
                list($discart, $dia_semana_abrev) = explode('turnos_', $key_dado);
                $dados_turnos[$dia_semana_abrev] = $dado;
            }

        }

        if ( count($subcategorias) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Selecione a subcategoria do seu negócio antes de clicar em salvar!'))));
        }

        if ( count($dados_turnos) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Você deve adicionar ao menos um turno antes de clicar em salvar!'))));
        }

        $subcategorias = json_decode(json_encode($subcategorias), true);
        $dados_turnos = json_decode(json_encode($dados_turnos), true);
        $dados_servicos = isset($dados->servicos) ? json_decode(json_encode($dados->servicos), true) : [];

        if ( $isCourt ) {
            if ( count($dados_servicos) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Você deve adicionar pelo menos uma quadra antes de clicar em salvar!'))));
            }
        } else {
            $dados_servicos = [];
        }

        $subcategorias_salvar = [];
        foreach( $subcategorias as $key => $sbc) {
            $subcategorias_salvar[]['subcategoria_id'] = $sbc;
        }

        $turnos_salvar = [];
        $index = 0;
        foreach( $dados_turnos as $dia_semana_abrev => $turnos) {
            $dia_semana_n = array_search($dia_semana_abrev, $this->dias_semana_abrev);
            foreach($turnos as $key_turno => $turno) {
                if ( isset($dados->item_7) && $dados->item_7 ) {
                    $turno['vagas'] = count($dados_servicos);
                }
                if ( !isset($turno['abertura']) || $turno['abertura'] == "" || !isset($turno['fechamento']) || $turno['fechamento'] == "" || !isset($turno['vagas']) || $turno['vagas'] == "" || !isset($turno['intervalo']) || $turno['intervalo'] == "") {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deixou um campo obrigatório em branco em lagum turno de '.$dia_semana_abrev.'!'))));
                }
                $turnos_salvar[$index]['horario_dia_semana'] = $dia_semana_n;
                $turnos_salvar[$index]['abertura'] = $turno['abertura'];
                $turnos_salvar[$index]['fechamento'] = $turno['fechamento'];
                $turnos_salvar[$index]['vagas_por_horario'] = $turno['vagas'];
                $turnos_salvar[$index]['intervalo_horarios'] = $turno['intervalo'];
                if ( isset($turno['domicilio']) && $turno['domicilio'] ) {
                    $turnos_salvar[$index]['a_domicilio'] = 1;
                }
                $index++;
            }
        }


        $dados_salvar = [
            'Cliente' => [
                'plano_id' => $dados->plano,
                'id' => $dados_token['Usuario']['cliente_id'],
            ],
            'ClienteConfiguracao' => [
                'horario_fixo' => (isset($dados->agendamentos_fixos) && ($dados->agendamentos_fixos == 1 || $dados->agendamentos_fixos) ? 'Y' : 'N'),
                'fixo_tipo' => (!isset($dados->agendamento_fixo_tipo) || $dados->agendamento_fixo_tipo == '' ? 'Nenhum' : $dados->agendamento_fixo_tipo),
            ],
            'ClienteSubcategoria' => $subcategorias_salvar,
            'ClienteHorarioAtendimento' => $turnos_salvar,
        ];

    
        if (isset($this->request->params['form']['logo']) && $this->request->params['form']['logo'] != '' && $this->request->params['form']['logo']['error'] == 0) {
            $dados_salvar['Cliente'] = array_merge($dados_salvar['Cliente'],
            [
                'logo' => $this->request->params['form']['logo']
            ]);
        }

        
        if ( isset($dados_servicos) && count($dados_servicos) > 0 ) {
            $dados_salvar = array_merge($dados_salvar,[
                'ClienteServico' => $dados_servicos
            ]);
        }

        $this->loadModel('Cliente');

        $this->Cliente->set($dados_salvar);
        if ($this->Cliente->saveAssociated($dados_salvar)) {

  
            $this->loadModel('ClienteHorarioAtendimento');
            $cadastro_horarios_ok = $this->ClienteHorarioAtendimento->find('count',[
                'conditions' => [
                    'ClienteHorarioAtendimento.cliente_id' => $dados_token['Usuario']['cliente_id']
                ]
            ]) > 0;

            $this->loadModel('ClienteSubcategoria');
            $cadastro_categorias_ok = $this->ClienteSubcategoria->find('count',[
                'conditions' => [
                    'ClienteSubcategoria.cliente_id' => $dados_token['Usuario']['cliente_id']
                ]
            ]) > 0;

            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastrado com sucesso!', 'cadastro_horarios_ok' => $cadastro_horarios_ok, 'cadastro_categorias_ok' => $cadastro_categorias_ok))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function dadosCalendario() {
        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['cliente_id']) || $dados['cliente_id'] == "" ) {
            throw new BadRequestException('Dados de cliente não informados!', 401);
        }
        $token = $dados['token'];

        $dados_token = $this->verificaValidadeToken($token);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Cliente');
        $dados_cliente = $this->Cliente->find('first',[
            'conditions' => [
                'Cliente.id' => $dados['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_cliente) == 0 )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados da empresa não econtrados!'))));
        
        $this->loadModel('ClienteHorarioAtendimento');
        $dias_semana_desativar = $this->ClienteHorarioAtendimento->diasSemanaDesativar($dados['cliente_id']);

        $this->LoadModel('ClienteHorarioAtendimentoExcessao');
        $excessoes = $this->ClienteHorarioAtendimentoExcessao->findExcessoes($dados['cliente_id']);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dias_seamana_desativar' => $dias_semana_desativar, 'excessoes_abertura' => $excessoes['abertura'], 'excessoes_fechamento' => $excessoes['fechamento']))));

    }

    public function horariosDisponiveis() {
        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['cliente_id']) || $dados['cliente_id'] == "" ) {
            throw new BadRequestException('Dados de cliente não informados!', 401);
        }
        if ( !isset($dados['data']) || $dados['data'] == "" ) {
            throw new BadRequestException('Dados de cliente não informados!', 401);
        }

        $token = $dados['token'];
        $data = $dados['data'];
        $dia_semana = date('w',strtotime($data));

        $dados_token = $this->verificaValidadeToken($token);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Cliente');
        $dados_cliente = $this->Cliente->find('first',[
            'conditions' => [
                'Cliente.id' => $dados['cliente_id']
            ],
            'contain' => []
        ]);

        $this->loadModel('ClienteHorarioAtendimento');
        $horarios_atendimento = $this->ClienteHorarioAtendimento->find('all',[
            'conditions' => [
                'ClienteHorarioAtendimento.cliente_id' => $dados['cliente_id'],
                'ClienteHorarioAtendimento.horario_dia_semana' => $dia_semana
            ],
            'link' => []
        ]);


        if ( count($horarios_atendimento) == 0 ) {
            $this->loadModel('ClienteHorarioAtendimentoExcessao');
            $horarios_atendimento = $this->ClienteHorarioAtendimentoExcessao->find('all',[
                'conditions' => [
                    'ClienteHorarioAtendimentoExcessao.cliente_id' => $dados['cliente_id'],
                    'ClienteHorarioAtendimentoExcessao.data' => $data,
                    'ClienteHorarioAtendimentoExcessao.type' => 'A'
                ],
                'link' => []
            ]);

            if ( count($horarios_atendimento) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Não atende em '.date('d/m/Y',strtotime($data)).'!'))));
            }

            $model_horario = 'ClienteHorarioAtendimentoExcessao';
        } else {
            $model_horario = 'ClienteHorarioAtendimento';
        }

        //busca uma lista de horários de atendimento conforme os turnos informados pelo estabelecimento
        $lista_horarios_atendimento = $this->ClienteHorarioAtendimento->generateListHorarios($horarios_atendimento, $model_horario);

        //verifica na listagem de horário se resta alguma vaga disponível, se não tiver ele desabilita o horário
        $this->loadModel('Agendamento');
        $horarios_verificados = $this->Agendamento->verificaHorarios($lista_horarios_atendimento, $dados['cliente_id'], $data);

        $this->loadModel('ClienteSubcategoria');
        $isCourt = $this->ClienteSubcategoria->checkIsCourt($dados['cliente_id']);

        if ($isCourt) {
            //busca as quadras disponíveis para os horários
            $this->loadModel('ClienteServico');
            $horarios_verificados = $this->ClienteServico->modaArrayServicosIndisponiveis($horarios_verificados, $dados['cliente_id']);
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $horarios_verificados))));

    }

    public function horarios_atendimento() {

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

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dado_usuario['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }


        $this->loadModel('Cliente');
        $dados_cliente = $this->Cliente->find('first',[
            'conditions' => [
                'Cliente.id' => $dado_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);


        $this->loadModel('ClienteHorarioAtendimento');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('ClienteServico');
        $horarios_atendimento = $this->ClienteHorarioAtendimento->find('all',[
            'conditions' => [
                'ClienteHorarioAtendimento.cliente_id' => $dado_usuario['Usuario']['cliente_id']
            ],
            'order' => [
                'ClienteHorarioAtendimento.horario_dia_semana'
            ],
            'link' => []
        ]);

        $isCourt = $this->ClienteSubcategoria->checkIsCourt($dado_usuario['Usuario']['cliente_id']);
        $nServicos = $this->ClienteServico->contaServicos($dado_usuario['Usuario']['cliente_id']);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $horarios_atendimento, 'isCourt' => $isCourt, 'nServicos' => $nServicos))));

    }

    public function altera_horarios_atendimento() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }


        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->email) || $dados->email == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados_turnos = [];
        foreach($dados as $key_dado => $dado) {

            if ( strpos($key_dado, 'turnos_') !== false ) {
                list($discart, $dia_semana_abrev) = explode('turnos_', $key_dado);
                $dados_turnos[$dia_semana_abrev] = $dado;
            }

        }

        if ( count($dados_turnos) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deve adicionar ao menos um turno antes de clicar em salvar!'))));
        }

        
        $dados_turnos = json_decode(json_encode($dados_turnos), true);


        $turnos_salvar = [];
        $index = 0;
        foreach( $dados_turnos as $dia_semana_abrev => $turnos) {
            $dia_semana_n = array_search($dia_semana_abrev, $this->dias_semana_abrev);
            foreach($turnos as $key_turno => $turno) {
                if ( !isset($turno['abertura']) || $turno['abertura'] == "" || !isset($turno['fechamento']) || $turno['fechamento'] == "" || !isset($turno['vagas']) || $turno['vagas'] == "" || !isset($turno['intervalo']) || $turno['intervalo'] == "") {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você deixou um campo obrigatório em branco em lagum turno de '.$dia_semana_abrev.'!'))));
                }
                $turnos_salvar[$index]['horario_dia_semana'] = $dia_semana_n;
                $turnos_salvar[$index]['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
                $turnos_salvar[$index]['abertura'] = $turno['abertura'];
                $turnos_salvar[$index]['fechamento'] = $turno['fechamento'];
                $turnos_salvar[$index]['vagas_por_horario'] = $turno['vagas'];
                $turnos_salvar[$index]['intervalo_horarios'] = $turno['intervalo'];
                if ( isset($turno['domicilio']) && $turno['domicilio']) {
                    $turnos_salvar[$index]['a_domicilio'] = 1;
                }
                $index++;
            }
        }

        $this->LoadModel('ClienteHorarioAtendimento');
        $this->ClienteHorarioAtendimento->deleteAll(['ClienteHorarioAtendimento.cliente_id' => $dados_usuario['Usuario']['cliente_id']]);
        if ( !$this->ClienteHorarioAtendimento->saveMany($turnos_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao atualizar seus horários de atendimento. Por favor, tente novamente mais tarde!'))));
        }
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Seus dados de atendimento foram atualizados com sucesso!'))));

    }

    public function servicos() {

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

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }


        if ( $dado_usuario['Usuario']['nivel_id'] != 2 && !isset($dados['cliente_id']) ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( isset($dados['cliente_id']) && $dados['cliente_id'] != '' ) {
            $conditions = [
                'ClienteServico.cliente_id' => $dados['cliente_id']
            ];
        } else {
            $conditions = [
                'ClienteServico.cliente_id' => $dado_usuario['Usuario']['cliente_id']
            ];
        }


        $this->loadModel('ClienteServico');
        $servicos = $this->ClienteServico->find('all',[
            'conditions' => $conditions,
            'order' => [
                'ClienteServico.nome'
            ],
            'link' => []
        ]);

        if ( count($servicos) > 0 ) {
            foreach($servicos as $key => $servico) {
                $servicos[$key]['ClienteServico']['valor'] = 'R$ '.$this->floatEnBr($servico['ClienteServico']['valor']);
            }
        }


        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $servicos))));

    }

    public function altera_servicos() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->email) || $dados->email == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados_servicos = isset($dados->servicos) ? json_decode(json_encode($dados->servicos), true) : [];

        if ( count($dados_servicos) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Você deve adicionar pelo menos um serviço antes de clicar em salvar!'))));
        }

        $ids_setados = [];

        foreach( $dados_servicos as $key => $servico) {
            $dados_servicos[$key]['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
            if ( isset($servico['id']) && $servico['id'] != '' )
                $ids_setados[] = $servico['id'];
        }
        
        $this->loadModel('ClienteServico');

        $this->ClienteServico->deleteAll(['ClienteServico.cliente_id' => $dados_usuario['Usuario']['cliente_id'], 'not' => ['ClienteServico.id' => $ids_setados]], true);

        if ( !$this->ClienteServico->saveMany($dados_servicos) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao atualizar seus horários de atendimento. Por favor, tente novamente mais tarde!'))));
        }

        $this->LoadModel('ClienteHorarioAtendimento');
        $this->ClienteHorarioAtendimento->updateAll(
            array('ClienteHorarioAtendimento.vagas_por_horario' => count($dados_servicos)),
            array('ClienteHorarioAtendimento.cliente_id' => $dados_usuario['Usuario']['cliente_id'])
        );
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Seus dados de atendimento foram atualizados com sucesso!'))));

    }

    public function clientes() {

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

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $conditions = [
            'ClienteCliente.cliente_id' => $dados_token['Usuario']['cliente_id']
        ];

        if ( isset($dados['searchText']) && $dados['searchText'] != '' ) {
            $searchText = $dados['searchText'];
            $conditions = array_merge($conditions, [
                'or' => [
                    ['ClienteCliente.nome LIKE' => "%".$searchText."%"],
                    ['ClienteCliente.endereco LIKE' => "%".$searchText."%"],
                    ['ClienteCliente.telefone LIKE' => "%".$searchText."%"],
                    ['ClienteCliente.email LIKE' => "%".$searchText."%"]
                ]
            ]);
        }

        $this->loadModel('ClienteCliente');
        $clientes = $this->ClienteCliente->find('all',[
            'conditions' => $conditions,
            'fields' => [
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.email',
                'ClienteCliente.telefone',
                'ClienteCliente.endereco',
                'ClienteCliente.bairro',
                'ClienteCliente.endreceo_n',
                'ClienteCliente.cep',
                'ClienteCliente.img',
                'ClienteCliente.sexo',
                'Localidade.loc_no',
                'ClienteCliente.cpf',
                'Uf.ufe_sg',
                'ClienteClienteDadosPadel.*'
            ],
            'contain' => ['Localidade', 'Uf', 'Agendamento' => ['conditions' => ['Agendamento.cliente_id' => $dados_token['Usuario']['cliente_id']]], 'Usuario', 'ClienteClienteDadosPadel', 'ClienteClientePadelCategoria'],
            'group' => ['ClienteCliente.id']
        ]);

        if  ( count($clientes) > 0 ) {
            foreach($clientes as $key => $cliente){
                $clientes[$key]['ClienteCliente']['img'] = $this->images_path.'/clientes_clientes/'.$clientes[$key]['ClienteCliente']['img'];
                $clientes[$key]['ClienteCliente']['email_cliente'] = $clientes[$key]['ClienteCliente']['email'];
                $clientes[$key]['ClienteCliente']['n'] = $clientes[$key]['ClienteCliente']['endreceo_n'];
                if ( isset($clientes[$key]['ClienteClienteDadosPadel']['id']) && $clientes[$key]['ClienteClienteDadosPadel']['id'] != '') {
                    $clientes[$key]['ClienteCliente']['dados_padelista'] = true;
                    $clientes[$key]['ClienteCliente']['lado'] = $clientes[$key]['ClienteClienteDadosPadel']['lado'];
                }
                if ( isset($clientes[$key]['ClienteClientePadelCategoria']) && count($clientes[$key]['ClienteClientePadelCategoria']) > 0) {
                    $categorias = array_map(function ($item){
                        return 'item_'.$item['categoria_id'];
                    },$clientes[$key]['ClienteClientePadelCategoria']);
                    foreach($categorias as $key_categoria => $cat){
                        $clientes[$key]['ClienteCliente'][$cat] = true;

                    }
                }
                unset($clientes[$key]['ClienteClientePadelCategoria']);
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $clientes))));

    }

    public function clientes_list() {

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

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $conditions = [
            'ClienteCliente.cliente_id' => $dados_token['Usuario']['cliente_id']
        ];

        /*if ( isset($dados['searchText']) && $dados['searchText'] != '' ) {
            $searchText = $dados['searchText'];
            $conditions = array_merge($conditions, [
                'or' => [
                    ['ClienteCliente.nome LIKE' => "%".$searchText."%"],
                    ['ClienteCliente.endereco LIKE' => "%".$searchText."%"],
                    ['ClienteCliente.telefone LIKE' => "%".$searchText."%"],
                    ['ClienteCliente.email LIKE' => "%".$searchText."%"]
                ]
            ]);
        }*/

        $this->loadModel('ClienteCliente');
        $clientes = $this->ClienteCliente->find('all',[
            'conditions' => $conditions,
            'fields' => [
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.telefone',
                'ClienteCliente.endereco',
                'ClienteCliente.endreceo_n',
                'ClienteCliente.bairro',
            ],
            'link' => [],
            //'contain' => ['Localidade', 'Uf', 'Agendamento' => ['conditions' => ['Agendamento.cliente_id' => $dados_token['Usuario']['cliente_id']]], 'Usuario', 'ClienteClienteDadosPadel', 'ClienteClientePadelCategoria'],
            'group' => ['ClienteCliente.id']
        ]);
        
        $clientes_retornar = [];
        if  ( count($clientes) > 0 ) {
            $count = 0;
            foreach($clientes as $key => $cliente){
                $clientes_retornar[] = ['label' => $cliente['ClienteCliente']['nome']." - ".$cliente['ClienteCliente']['telefone'], 'value' => $cliente['ClienteCliente']['id'], 'endereco' => $this->bdToStringAddres($cliente)];
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $clientes_retornar))));

    }

    public function dados() {

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

        $dado_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dado_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dado_usuario['Usuario']['nivel_id'] != 2 && !isset($dados['cliente_id']) ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        $cliente_id = null;
        if ($dado_usuario['Usuario']['cliente_id'] != '') {
            $cliente_id = $dado_usuario['Usuario']['cliente_id'];
        } else {
            $cliente_id = $dados['cliente_id'];
        }

        $this->loadModel('Cliente');
        $this->loadModel('ClienteSubcategoria');
        $dados = $this->Cliente->find('first',[
            'fields' => ['*'],
            'conditions' => ['Cliente.id' =>  $cliente_id],
            'link' => [
                'ClienteConfiguracao'
            ]
        ]);

        if ( count($dados) > 0 ) {
            $dados['Cliente']['logo'] = $this->images_path.'clientes/'.$dados['Cliente']['logo'];
            $dados['Cliente']['isCourt'] = $this->ClienteSubcategoria->checkIsCourt($dados['Cliente']['id']);
            if ( $dados['Cliente']['prazo_maximo_para_canelamento'] != null && $dados['Cliente']['prazo_maximo_para_canelamento'] != '' )
                $dados['Cliente']['prazo_maximo_para_canelamento'] = substr($dados['Cliente']['prazo_maximo_para_canelamento'], 0, 5);
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));

    }

    public function muda_dados() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('Email não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->tipo_cadastro) || $dados->tipo_cadastro == '' || ($dados->tipo_cadastro != 'F' && $dados->tipo_cadastro != 'J')) {
            throw new BadRequestException('Tipo de cadastro não informado', 400);
        }

        if (!isset($dados->nomeProfissional) || $dados->nomeProfissional == '') {
            throw new BadRequestException('Nome business não informado', 400);
        }

        if (!isset($dados->cep) || $dados->cep == '') {
            throw new BadRequestException('CEP não informado', 400);
        }

        if (!isset($dados->bairro) || $dados->bairro == '') {
            throw new BadRequestException('Bairro não informado', 400);
        }

        if (!isset($dados->endereco ) || $dados->endereco  == '') {
            throw new BadRequestException('Endereço não informado', 400);
        }

        if (!isset($dados->n ) || $dados->n  == '' || !is_numeric($dados->n) ) {
            throw new BadRequestException('Número não informado', 400);
        }

        if (!isset($dados->telefone) || $dados->telefone == '') {
            throw new BadRequestException('Telefone não informado', 400);
        }

        if (!isset($dados->uf) || $dados->uf == '') {
            throw new BadRequestException('UF não informada', 400);
        }

        if (!isset($dados->localidade ) || $dados->localidade  == '') {
            throw new BadRequestException('Localidade não informada', 400);
        }
        
        $token = $dados->token;
        $email = $dados->email;

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Uf');
        $dadosUf = $this->Uf->find('first',[
            'conditions' => [
                'Uf.ufe_sg' => $dados->uf
            ]
        ]);

        if (count($dadosUf) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do Estado não encontrados.'))));
        }

        $this->loadModel('Localidade');
        $dadosLocalidade = $this->Localidade->find('first',[
            'conditions' => [
                'Localidade.loc_no' => $dados->localidade
            ]
        ]);
    
        if (count($dadosLocalidade) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados da cidade não encontrados.'))));
        }

        $this->loadModel('Cliente');

        $cpf = null;
        $cnpj = null;

        if ($dados->tipo_cadastro == 'F') {
            $cpf = $dados->cpf;
            if ( !$this->validar_cpf($cpf) ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'CPF inválido!'))));
            }
            if ( count($this->Cliente->findByCpf($cpf,$dados_token['Usuario']['cliente_id'])) > 0 ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Já existe um parceiro com este CPF!'))));
            }
        }

        if ($dados->tipo_cadastro == 'J') {
            $cnpj = $dados->cnpj;
            if ( !$this->validar_cnpj($cnpj) ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'CNPJ inválido!'))));
            }
            if ( count($this->Cliente->findByCnpj($cnpj,$dados_token['Usuario']['cliente_id'])) > 0 ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Já existe um parceiro com este CNPJ!'))));
            }
        }

        $dados_salvar = [
            'Cliente' => [
                'id' => $dados_token['Usuario']['cliente_id'],
                'cpf' => $cpf,
                'cpnj' => $cnpj,
                'nome' => $dados->nomeProfissional,
                'telefone' => $dados->telefone,
                'cep' => $dados->cep,
                'endereco' => $dados->endereco,
                'endereco_n' => $dados->n,
                'tipo' => $dados->tipo_cadastro,
                'cidade_id' => $dadosLocalidade['Localidade']['loc_nu_sequencial'],
                'estado' => $dados->uf,
                'prazo_maximo_para_canelamento' => $dados->prazo_maximo_para_canelamento != "00:00" && $dados->prazo_maximo_para_canelamento != "" ? $dados->prazo_maximo_para_canelamento : null
            ],
            'ClienteConfiguracao' => [
                //'id' => $dados->configuracoes_id,
                'horario_fixo' => (isset($dados->agendamentos_fixos) && ($dados->agendamentos_fixos == 1 || $dados->agendamentos_fixos) ? 'Y' : 'N'),
                'fixo_tipo' => (!isset($dados->agendamento_fixo_tipo) || $dados->agendamento_fixo_tipo == '' ? 'Nenhum' : $dados->agendamento_fixo_tipo),
            ],
        ];

        if (isset($this->request->params['form']['logo']) && $this->request->params['form']['logo'] != '' && $this->request->params['form']['logo']['error'] == 0) {
            $dados_salvar['Cliente'] = array_merge($dados_salvar['Cliente'],
            [
                'logo' => $this->request->params['form']['logo']
            ]);
        }

        if (isset($dados->configuracoes_id) && $dados->configuracoes_id != '') {
            $dados_salvar['ClienteConfiguracao'] = array_merge($dados_salvar['ClienteConfiguracao'],
            [
                'id' => $dados->configuracoes_id
            ]);
        }

        $this->Cliente->set($dados_salvar);
        if ($this->Cliente->saveAssociated($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Atualizado com sucesso!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    private function bdToStringAddres($cliente_cliente) {

        if ($cliente_cliente['ClienteCliente']['endereco'] != '') {
            return $cliente_cliente['ClienteCliente']['endereco'].", ".$cliente_cliente['ClienteCliente']['endreceo_n'].", ".$cliente_cliente['ClienteCliente']['bairro'];
        }

        return "";

    }
}