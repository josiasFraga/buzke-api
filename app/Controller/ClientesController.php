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

        $subcategorias = [];
        $dados_turnos = [];
        foreach($dados as $key_dado => $dado) {

            if ( strpos($key_dado, 'item_') !== false ) {
                list($discart, $subcategoria_id) = explode('item_', $key_dado);
                $subcategorias[] = $subcategoria_id;
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

        $subcategorias_salvar = [];
        foreach( $subcategorias as $key => $sbc) {
            $subcategorias_salvar[]['subcategoria_id'] = $sbc;
        }

        $turnos_salvar = [];
        $index = 0;
        foreach( $dados_turnos as $dia_semana_abrev => $turnos) {
            $dia_semana_n = array_search($dia_semana_abrev, $this->dias_semana_abrev);
            foreach($turnos as $key_turno => $turno) {
                $turnos_salvar[$index]['horario_dia_semana'] = $dia_semana_n;
                $turnos_salvar[$index]['abertura'] = $turno['abertura'];
                $turnos_salvar[$index]['fechamento'] = $turno['fechamento'];
                $turnos_salvar[$index]['vagas_por_horario'] = $turno['vagas'];
                $turnos_salvar[$index]['intervalo_horarios'] = $turno['intervalo'];
                if ( isset($turno['domicilio']) ) {
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
            'ClienteSubcategoria' => $subcategorias_salvar,
            'ClienteHorarioAtendimento' => $turnos_salvar
        ];
        

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
            'link' => []
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

        $lista_horarios_atendimento = $this->ClienteHorarioAtendimento->generateListHorarios($horarios_atendimento, $model_horario);

        $this->loadModel('Agendamento');
        $horarios_verificados = $this->Agendamento->verificaHorarios($lista_horarios_atendimento, $dados['cliente_id'], $data);
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
        $horarios_atendimento = $this->ClienteHorarioAtendimento->find('all',[
            'conditions' => [
                'ClienteHorarioAtendimento.cliente_id' => $dado_usuario['Usuario']['cliente_id']
            ],
            'order' => [
                'ClienteHorarioAtendimento.horario_dia_semana'
            ],
            'link' => []
        ]);


        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $horarios_atendimento))));

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
            ],
            'link' => [],
            //'contain' => ['Localidade', 'Uf', 'Agendamento' => ['conditions' => ['Agendamento.cliente_id' => $dados_token['Usuario']['cliente_id']]], 'Usuario', 'ClienteClienteDadosPadel', 'ClienteClientePadelCategoria'],
            'group' => ['ClienteCliente.id']
        ]);
        
        $clientes_retornar = [];
        if  ( count($clientes) > 0 ) {
            foreach($clientes as $key => $cliente){
                $clientes_retornar[] = ['label' => $cliente['ClienteCliente']['nome']." - ".$cliente['ClienteCliente']['telefone'], 'value' => $cliente['ClienteCliente']['id']];
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $clientes_retornar))));

    }
}