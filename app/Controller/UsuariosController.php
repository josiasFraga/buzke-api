<?php
App::uses('AuthComponent', 'Controller/Component');
App::import("Vendor", "FacebookAuto", array("file" => "facebook/src/Facebook/autoload.php"));
App::uses('CakeEmail', 'Network/Email');
use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
class UsuariosController extends AppController {
	
    public $helpers = array('Html', 'Form');
    public $components = array('RequestHandler');
    public $app_id = '2032200327066593';
	public $app_secret = '60563e998768a114156c1fad1134796e';
	

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");

    }

    public function senha($senha) {
        echo AuthComponent::password($senha); die();
    }

    public function index() {
        $this->layout = 'ajax';
        die('teste');
	}
	
    public function login() {
        $this->layout = 'ajax';
        
        $dados = $this->request->data['dados'];
        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

		if ( !isset($dados->email) || !filter_var($dados->email, FILTER_VALIDATE_EMAIL) ) {
			throw new BadRequestException('Email inválido!', 400);
		}
    
        if (!isset($dados->password) || $dados->password == '') {
            throw new BadRequestException('Senha não informada', 400);
        }
		
		if ( !isset($dados->notifications_id) || $dados->notifications_id == '' ) {
			$dados->notifications_id = null;
		}

        $this->log($dados,'debug');


        $email = $dados->email;
        $senha = $dados->password;

        $this->loadModel('Usuario');
        $usuario = $this->Usuario->find('first', array(
            'conditions' => array(
                'Usuario.email' => $email,
                'Usuario.senha' => AuthComponent::password($senha),
                'Usuario.nivel_id' => [2,3],
            ),
            'link' => array(
                'Token', 'Cliente',
            ),
            'fields' => array(
                'Usuario.*',
                'Token.id',
                'Cliente.*'
            )
        ));


        if (count($usuario) == 0) {
            throw new BadRequestException('Login e/ou Senha não conferem.', 401);
        }

        if ( $usuario['Usuario']['ativo'] == 'N' ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Seu cadastro ainda está inativado.'))));
        }

        $cadastro_horarios_ok = 'false';
        if ( $usuario['Usuario']['nivel_id'] == 2 ) {
            $this->loadModel('ClienteHorarioAtendimento');
            $cadastro_horarios_ok = $this->ClienteHorarioAtendimento->find('count',[
                'conditions' => [
                    'ClienteHorarioAtendimento.cliente_id' => $usuario['Usuario']['cliente_id']
                ]
            ]) > 0;
        }

        $cadastro_categorias_ok = 'false';
        $business_is_court = false;
        $business_is_paddle_court = false;
        if ( $usuario['Usuario']['nivel_id'] == 2 ) {
            $this->loadModel('ClienteSubcategoria');
            $subcategorias = $this->ClienteSubcategoria->find('all',[
                'fields' => ['*'],
                'conditions' => [
                    'ClienteSubcategoria.cliente_id' => $usuario['Usuario']['cliente_id'],
                    'ClienteSubcategoria.cliente_id' => $usuario['Usuario']['cliente_id']
                ],
                'link' => ['Subcategoria']
            ]);

            if ( count($subcategorias) > 0 ) {
                foreach($subcategorias as $key_subc => $subc) {
                    if ($subc['Subcategoria']['categoria_id'] == 4){
                        $business_is_paddle_court = true;
                    }
                }
            }

            $usuario['Cliente']['is_paddle_court'] = $business_is_court;
            $usuario['Cliente']['is_court'] = $business_is_court ? true : $this->ClienteSubcategoria->checkIsCourt($usuario['Usuario']['cliente_id']);

            $cadastro_categorias_ok = count($subcategorias) > 0;
        }

        unset($usuario['Usuario']['senha']);

        $this->loadModel('Token');
        $dados_salvar = [];
        if ( isset($dados->token) && $dados->token != null && $dados->token != '' ) {
            $dados_token = $this->Token->find('first',[
                'conditions' => [
                    'Token.token' => $dados->token,
                    'Token.data_validade >=' => date('Y-m-d'),
                    'or' => [
                        'ISNULL(Token.usuario_id)',
                        'Token.usuario_id' => $usuario['Usuario']['id']
                    ]
                ],
                'link' => []
            ]);

            if ( count($dados_token) > 0 ) {
                $token = $dados_token['Token']['token'];

                $dados_salvar = array_merge($dados_salvar, [    
                    'id' => $dados_token['Token']['id'], 
                ]
                );
            } else {
                $token = md5(uniqid($email, true));
            }

        } else {
            $token = md5(uniqid($email, true));
        }

        $dados_salvar = array_merge($dados_salvar, [
                'token' => $token, 
                'data_validade' => date('Y-m-d', strtotime(date("Y-m-d") . ' + 30 days')), 
                'usuario_id' => $usuario['Usuario']['id'],
                'notification_id' => $dados->notifications_id
            ]
        );


        $this->Token->create();
        $this->Token->set($dados_salvar);
        $dados_token = $this->Token->save($dados_salvar);
        if ($dados_token) {
            if ( $usuario['Usuario']['img'] == '' || $usuario['Usuario']['img'] == null ) {
                $usuario['Usuario']['img'] = $this->images_path."usuarios/default.png";
            } else if ( !strpos($usuario['Usuario']['img'], 'facebook') ) {
                $usuario['Usuario']['img'] = $this->images_path."usuarios/".$usuario['Usuario']['img'];
            }
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => array_merge($usuario, $dados_token, ['cadastro_horarios_ok' => $cadastro_horarios_ok, 'cadastro_categorias_ok' => $cadastro_categorias_ok])))));
        } else {
            throw new BadRequestException('Erro ao salvar o Token', 500);
        }
    }
	
    public function entrarVisitante() {
        $this->layout = 'ajax';
        
        
        $dados = json_decode($this->request->data['dados']);
		
		if ( !isset($dados->notifications_id) || $dados->notifications_id == '' ) {
			$dados->notifications_id = null;
        }

        $token = md5(uniqid(date('YmdHis'), true));
        $notification_id = $dados->notifications_id;

        $dados_salvar = array(
            'Token' => array(
                'token' => $token, 
                'data_validade' => date('Y-m-d', strtotime(date("Y-m-d") . ' + 30 days')), 
                'notification_id' => $notification_id
            )
        );

        $this->loadModel('Token');
        $this->Token->create();
        $this->Token->set($dados_salvar);
        $this->Token->save($dados_salvar);
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => array_merge($dados_salvar)))));
 
    }

    private function updateNotificationId($notification_id = null, $usuario_id = null) {
        if ($usuario_id == null) return false;
        $this->loadModel('Usuario');
        if ($notification_id == "" || $notification_id == null) {
            $this->Usuario->updateAll(array('Usuario.notifications_id' => null), array('Usuario.notifications_id' => $notification_id));
        }
        $dados_usuario_salvar = array('Usuario' => array('id' => $usuario_id, 'notifications_id' => $notification_id));
        $this->Usuario->set($dados_usuario_salvar);
        if (!$this->Usuario->save()) {
            return false;
        }
        return true;
    }

    public function cadastrar() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        //$this->log($dados,'debug');

        if (!isset($dados->nome) || $dados->nome == '') {
            throw new BadRequestException('Nome não informado', 400);
        }
        /* (!isset($dados->cpf) || $dados->cpf == '') {
            throw new BadRequestException('CPF não informado', 400);
        }*/
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
        if (!isset($dados->notifications_id)) {
            $dados->notifications_id = null;
        }

        $nome = $dados->nome;
        $email = $dados->email;
        $telefone = $dados->telefone;
        $senha = $dados->senha;
        //$cpf = $dados->cpf;
        $notifications_id = $dados->notifications_id;

        $this->loadModel('Usuario');
        $ja_existe = ($this->Usuario->find('count', array('conditions' => array('Usuario.email' => $email))) > 0);

        if ($ja_existe) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Já existe um usuário cadastrado com este email. Por favor, informe outro.'))));
        }

        $this->loadModel('ClienteCliente');
        //busca se extiste algum cadastro de cliente de empresa com esse email
        $dados_cliente_cliente = $this->ClienteCliente->find('all',[
            'conditions' => [
                'ClienteCliente.email' => $email,
                'ISNULL(ClienteCliente.usuario_id)'
            ],
            'link' => []
        ]);

        $token = md5(uniqid($telefone, true));

        if ( count($dados_cliente_cliente) == 0){
            $dados_salvar = array(
                'Usuario' => array(
                    'nome' => $nome, 
                    'email' => $email, 
                    'telefone' => $telefone, 
                    //'email' => $dados->email, 
                    'senha' => $senha, 
                    'nivel_id' => 3
                ), 
                'Token' => array(
                    array(
                        'token' => $token, 
                        'data_validade' => date('Y-m-d', strtotime(date("Y-m-d") . ' + 30 days')),
                        'notifications_id' => $notifications_id,
                    )
                ), 
                'ClienteCliente' => array(
                    array(
                        'nome' => $nome, 
                        'email' => $email, 
                        'telefone' => $telefone, 
                        //'cpf' => $cpf, 
                    )
                )
            );
        } else {

            $arr_cadastros_atualizar = [
                [
                    'nome' => $nome, 
                    'email' => $email, 
                    'telefone' => $telefone, 
                    //'cpf' => $cpf, 
                ]                
            ];
            foreach( $dados_cliente_cliente as $key => $d_cliente ){
                unset($d_cliente['ClienteCliente']['img']);
                unset($d_cliente['ClienteCliente']['usuario_id']);
                $arr_cadastros_atualizar[$key+1] = $d_cliente['ClienteCliente'];
                $arr_cadastros_atualizar[$key+1]['nome'] = $nome;
            }
            $dados_salvar = array(
                'Usuario' => array(
                    'nome' => $nome, 
                    'email' => $email, 
                    'telefone' => $telefone, 
                    //'email' => $dados->email, 
                    'senha' => $senha, 
                    'nivel_id' => 3
                ), 
                'Token' => array(
                    array(
                        'token' => $token, 
                        'data_validade' => date('Y-m-d', strtotime(date("Y-m-d") . ' + 30 days')),
                        'notifications_id' => $notifications_id,
                    )
                ), 
                'ClienteCliente' => $arr_cadastros_atualizar
            );
        }

        //debug($dados_salvar); die();

        $this->Usuario->set($dados_salvar);
        if ($this->Usuario->saveAssociated($dados_salvar, ['deep' => true])) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastrado com sucesso!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function save_location() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Nome não informado', 400);
        }

        $token = $dados->token;
        $dados_token = $this->verificaValidadeToken($token);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados_address = json_decode($dados->dados_address);

        $dados_salvar = [
            'token_id' => $dados_token['Token']['id'],
            'location_data' => $dados->dados_address,
            'description' => $dados_address->description
        ];

        if ($dados_token['Token']['usuario_id'] != null) {
            $dados_salvar = array_merge($dados_salvar,[
                'usuario_id' => $dados_token['Token']['usuario_id']
            ]);

        }

        $this->loadModel('UsuarioLocalizacao');


        $this->UsuarioLocalizacao->set($dados_salvar);
        if ($this->UsuarioLocalizacao->save($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastrado com sucesso!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function cadastrarEmpresa() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        /*$this->log($dados,'debug');
        die();*/

        if (!isset($dados->uf) || $dados->uf == '') {
            throw new BadRequestException('UF não informada', 400);
        }

        if (!isset($dados->localidade ) || $dados->localidade  == '') {
            throw new BadRequestException('Localidade não informada', 400);
        }

        if (!isset($dados->tipo_cadastro) || $dados->tipo_cadastro   == '') {
            throw new BadRequestException('Tipo de Cadastro não informado', 400);
        }

        if ( $dados->tipo_cadastro == 'F' ) {

            if (!isset($dados->cpf) || $dados->cpf   == '') {
                throw new BadRequestException('CPF não informado', 400);
            }

            $cpf = $dados->cpf;
            $cnpj = null;

        } else if ( $dados->tipo_cadastro == 'J' ) {

            if (!isset($dados->cnpj) || $dados->cnpj   == '') {
                throw new BadRequestException('CNPJ não informado', 400);
            }

            $cpf = null;
            $cnpj = $dados->cnpj;

        } else {
            throw new BadRequestException('Tipo de Cadastro desconhecido', 400);
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


        if (!isset($dados->nome) || $dados->nome == '') {
            throw new BadRequestException('Nome não informado', 400);
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

        if (!isset($dados->notifications_id ) || $dados->notifications_id  == '' ) {
            $dados->notifications_id = null;
        }

        $tipo = $dados->tipo_cadastro;
        $uf = $dados->uf;
        $localidade = $dados->localidade;
        $nome_rofissional = $dados->nomeProfissional;
        $cep = $dados->cep;
        $bairro = $dados->bairro;
        $endereco = $dados->endereco;
        $n = $dados->n;

        $nome = $dados->nome;
        $email = $dados->email;
        $telefone = $dados->telefone;
        $senha = $dados->senha;
        $notifications_id = $dados->notifications_id;

        $this->loadModel('Usuario');
        $ja_existe = ($this->Usuario->find('count', array('conditions' => array('Usuario.email' => $email))) > 0);

        if ($ja_existe) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Já existe um usuário cadastrado com este email. Por favor, informe outro.'))));
        }

        $this->loadModel('Cliente');

        if ( $dados->tipo_cadastro == 'F' ) {
            $ja_existe = ($this->Cliente->find('count', array('conditions' => array('Cliente.cpf' => $cpf))) > 0);

            if ($ja_existe) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Já existe um usuário cadastrado com este CPF. Por favor, informe outro.'))));
            }
        } else if ( $dados->tipo_cadastro == 'J' ) {
            $ja_existe = ($this->Cliente->find('count', array('conditions' => array('Cliente.cnpj' => $cnpj))) > 0);

            if ($ja_existe) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Já existe um usuário cadastrado com este CNPJ. Por favor, informe outro.'))));
            }
        }

        $this->loadModel('Uf');
        $dadosUf = $this->Uf->find('first',[
            'conditions' => [
                'Uf.ufe_sg' => $uf
            ]
        ]);

    
        if (count($dadosUf) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do Estado não encontrados.'))));
        }

        $this->loadModel('Localidade');
        $dadosLocalidade = $this->Localidade->find('first',[
            'conditions' => [
                'Localidade.loc_no' => $localidade
            ]
        ]);
    
        if (count($dadosLocalidade) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados da cidade não encontrados.'))));
        }

        $wp = null;
        if (isset($dados->telefone_possui_wp) && $dados->telefone_possui_wp) {
            $wp = $telefone;
        } else {
            if (isset($dados->wp) && $dados->wp != '') {
                $wp = $dados->wp;
            }
        }

        $token = md5(uniqid($telefone, true));

        $dados_salvar = array(
            'Usuario' => array(
                'nome' => $nome, 
                'email' => $email, 
                'telefone' => $telefone, 
                //'email' => $dados->email, 
                'senha' => $senha, 
                'nivel_id' => 2
            ), 
            'Token' => array(
                array(
                    'token' => $token, 
                    'data_validade' => date('Y-m-d', strtotime(date("Y-m-d") . ' + 30 days')),
                    'notifications_id' => $notifications_id,
                )
            ),
            'Cliente' => array(
                'tipo' => $tipo,
                'nome' => $nome_rofissional,
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'telefone' => $telefone,
                'wp' => $wp,
                'cidade_id' => $dadosLocalidade['Localidade']['loc_nu_sequencial'],
                'estado' => $uf,
                'cep' => $cep,
                'endereco' => $endereco,
                'endereco_n' => $n,
                'bairro' => $bairro,
            ), 
        );

        $this->Usuario->set($dados_salvar);
        if ($this->Usuario->saveAssociated($dados_salvar)) {
            unset($dados_salvar['Usuario']['senha']);
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastrado com sucesso!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function usuario_informacoes() {
        $this->layout = 'ajax';
        
        $dados = $this->request->query;

        
        if ((!isset($dados['token']) || $dados['token'] == "") ||  (!isset($dados['phone']) || $dados['phone'] == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $usuario_token = $dados['token'];
        $usuario_phone = $dados['phone'];

        $dados_usuario = $this->verificaValidadeToken($usuario_token, $usuario_phone);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Usuario');
        $dados = $this->Usuario->findById($dados_usuario['Usuario']['id']);
        unset($dados['Usuario']['senha']);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));

    }

    public function dados_padelista() {
        $this->layout = 'ajax';
        
        $dados = $this->request->query;

        
        if ((!isset($dados['token']) || $dados['token'] == "") ||  (!isset($dados['email']) || $dados['email'] == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('UsuarioDadosPadel');
        $dados = $this->UsuarioDadosPadel->findByUserId($dados_usuario['Usuario']['id']);
        $this->loadModel('UsuarioPadelCategoria');
        $categorias = $this->UsuarioPadelCategoria->findByUserId($dados_usuario['Usuario']['id']);
        $this->loadModel('ClienteCliente');
        $dados_como_cliente = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_usuario['Usuario']['id']);
        //debug($dados_como_cliente); die();

        if ( count($dados) > 0 ) {
            $dados['UsuarioDadosPadel']['sexo'] = $dados_como_cliente['ClienteCliente']['sexo'];
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados, 'categorias' => $categorias))));

    }

    public function altera_dados_padelista() {
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

        if (!isset($dados->sexo) || $dados->sexo == '') {
            throw new BadRequestException('Sexo não informado', 400);
        }

        if (!isset($dados->lado) || $dados->lado == '') {
            throw new BadRequestException('Lado de jogo não informado', 400);
        }

        $categorias = [];
        foreach($dados as $key_dado => $dado) {
    
            if ( strpos($key_dado, 'item_') !== false && $dado == 1 ) {
                list($discart, $categoria_id) = explode('item_', $key_dado);
                $categorias[] = $categoria_id;
            }
        }


        if ( count($categorias) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione ao menos uma categoria antes de clicar em "Atualizar Dados"'))));
        }

        if ( count($categorias) > 2 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione no máximo 2 categorias'))));
        }


        $this->loadModel('UsuarioDadosPadel');
		$dataSource = $this->UsuarioDadosPadel->getDataSource();
		$dataSource->begin();

        $dados_padel = $this->UsuarioDadosPadel->findByUserId($dados_usuario['Usuario']['id']);
        $dados_salvar = [];
        if ( count($dados_padel) > 0 ) {
            $dados_salvar = array_merge(
                $dados_salvar,
                [
                    'id' => $dados_padel['UsuarioDadosPadel']['id']
                ]
            );
        }

        
        $dados_salvar = array_merge(
            $dados_salvar,
            [
                'lado' => $dados->lado,
                'usuario_id' => $dados_usuario['Usuario']['id']
            ]
        );

        $save_padelist_data = $this->UsuarioDadosPadel->save($dados_salvar);

        $this->loadModel('UsuarioPadelCategoria');
		$dataSourcePadelCategoria = $this->UsuarioPadelCategoria->getDataSource();
		$dataSourcePadelCategoria->begin();

        $daodos_categorias = $this->UsuarioPadelCategoria->findByUserId($dados_usuario['Usuario']['id']);
        $dados_salvar_categorias = [];
        if ( count($daodos_categorias) > 0 ) {
           $this->UsuarioPadelCategoria->deleteAll(['UsuarioPadelCategoria.usuario_id' => $dados_usuario['Usuario']['id']]);
        }

        foreach( $categorias as $key => $cat) {
            $dados_salvar_categorias = array_merge(
                $dados_salvar_categorias,
                [[
                    'categoria_id' => $cat,
                    'usuario_id' => $dados_usuario['Usuario']['id']
                ]]
            );
        }
    
        $save_padelist_categories = $this->UsuarioPadelCategoria->saveMany($dados_salvar_categorias);

        
        $this->loadModel('ClienteCliente');
        $dados_como_cliente = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_usuario['Usuario']['id']);
        unset($dados_como_cliente['ClienteCliente']['img']);
        $dados_como_cliente['ClienteCliente']['sexo'] = $dados->sexo;
        
		$dataSourceClienteCliente = $this->ClienteCliente->getDataSource();
		$dataSourceClienteCliente->begin();

        $usuario_cliente_cliente = $this->ClienteCliente->save(
            [
                'id' => $dados_como_cliente['ClienteCliente']['id'],
                'sexo' => $dados->sexo,
            ]
        );


        if ($usuario_cliente_cliente && $save_padelist_categories && $save_padelist_data ) {
            $dataSource->commit();
            $dataSourcePadelCategoria->commit();
            $dataSourceClienteCliente->commit();
            

            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastro alterado!', 'padelist_data' => $save_padelist_data, 'padel_categories' => $save_padelist_categories, 'updated_user_sex' => $dados->sexo))));
        } else {
            $dataSource->rollback();
            $dataSourcePadelCategoria->rollback();
            $dataSourceClienteCliente->rollback();
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function usuario_alterar() {
        $this->layout = 'ajax';
        $dados = $this->request->input('json_decode');

        if ((!isset($dados->auth->token) || $dados->auth->token == "") ||  (!isset($dados->auth->phone) || $dados->auth->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->auth->token, $dados->auth->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if (!isset($dados->usuario->nome) || $dados->usuario->nome == '') {
            throw new BadRequestException('Nome não informado', 400);
        }
        if (!isset($dados->usuario->sobrenome) || $dados->usuario->sobrenome == '') {
            throw new BadRequestException('Sobrenome não informado', 400);
        }
        if (!isset($dados->usuario->telefone) || $dados->usuario->telefone == '') {
            throw new BadRequestException('Telefone não informado', 400);
        }
        // if (!isset($dados->usuario->email) || !filter_var($dados->usuario->email, FILTER_VALIDATE_EMAIL)) {
        //     $this->log($dados->usuario->email, 'debug');
        //     return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        // }
        if (!isset($dados->usuario->endereco) || $dados->usuario->endereco == '') {
            throw new BadRequestException('Endereço não informado', 400);
        }
        if (!isset($dados->usuario->notifications_id)) {
            $dados->usuario->notifications_id = null;
        }

        $dados_salvar = array(
            'Usuario' => array(
                'id' => $dados_usuario['Usuario']['id'], 
                'nome' => $dados->usuario->nome, 
                'sobrenome' => $dados->usuario->sobrenome, 
                'telefone' => $dados->usuario->telefone, 
                // 'email' => $dados->usuario->email, 
                'endereco' => $dados->usuario->endereco, 
                'nivel' => 'usuario', 
                'notifications_id' => $dados->usuario->notifications_id,
            )
        );

        if (isset($dados->usuario->altera_senha) && $dados->usuario->altera_senha == 'Y') {
            if (!isset($dados->usuario->senha) || $dados->usuario->senha == '') {
                throw new BadRequestException('Senha não informada', 400);
            }
            $senha = $dados->usuario->senha;
            $senha = AuthComponent::password($senha);
            $dados_salvar['Usuario'] = array_merge($dados_salvar['Usuario'], array('senha' => $senha));
        }

        $this->loadModel('Usuario');
        $this->Usuario->set($dados_salvar);
        if ($this->Usuario->save($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastro alterado!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function altera_senha() {
        $this->layout = 'ajax';
        $dados = json_decode($this->request->data['dados']);
        
        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->email) || $dados->email == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if (!isset($dados->password) || $dados->password == '') {
            throw new BadRequestException('Senha não informada', 400);
        }

        $dados_salvar = array(
            'Usuario' => array(
                'id' => $dados_usuario['Usuario']['id'], 
                'senha' => $dados->password, 
            )
        );

        $this->loadModel('Usuario');
        $this->Usuario->set($dados_salvar);
        if ($this->Usuario->save($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastro alterado!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }
    
    public function alteraFoto() {
        $this->layout = 'ajax';

        $dados = $this->request->data['dados'];
        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if (!isset($this->request->params['form']['foto']) || $this->request->params['form']['foto'] == '' || $this->request->params['form']['foto']['error'] != 0) {
            throw new BadRequestException('Foto não informada', 400);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token,$dados->usuario);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados_salvar = array(
            'Usuario' => array(
                'id' => $dados_usuario['Usuario']['id'], 
                'img' => $this->request->params['form']['foto'], 
            )
        );

        $this->loadModel('Usuario');
        $this->Usuario->set($dados_salvar);
        if ($this->Usuario->save($dados_salvar)) {
            //busca os dados com a foto atualizada
            $usuario = $this->verificaValidadeToken($dados->token, $dados->usuario);
            $this->log($usuario,'debug');
            if ( $usuario['Usuario']['img'] == '' || $usuario['Usuario']['img'] == null ) {
                $usuario['Usuario']['img'] = $this->images_path."usuarios/default.png";
            } else if ( !strpos($usuario['Usuario']['img'], 'facebook') ) {
                $usuario['Usuario']['img'] = $this->images_path."usuarios/".$usuario['Usuario']['img'];
            }
            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastro alterado!', 'dados' => $usuario))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function reset_password($acao=null) {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if ($acao == null) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ação não informada!'))));
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('Email não informado', 400);
        }
        
        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }
        
        $email = $dados->email;
        $this->loadModel('Usuario');
        $dados_usuario = $this->Usuario->find('first', [            
			'fields' => ['Usuario.id','Usuario.nome','Usuario.email'],
            'conditions' => [
                'Usuario.email' => $email
            ],
            'link' => []
        ]);

        if ( $acao == 'sendcode' ) {
            return $this->sendCode($dados_usuario);
        }

        if ( $acao == 'checkcode' ) {
            if (!isset($dados->codigo) || $dados->codigo == '') {
                throw new BadRequestException('Código não informado', 400);
            }
            
            $checkCodeReturn = $this->checkCode($dados_usuario, $dados->codigo);

            if ( !$checkCodeReturn )
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Código inválido'))));
            
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Código validado'))));
        }

        if ( $acao == 'changepassword' ) {

            if (!isset($dados->codigo) || $dados->codigo == '') {
                throw new BadRequestException('Código não informado', 400);
            }

            if (!isset($dados->nova_senha) || trim($dados->nova_senha) == '') {
                throw new BadRequestException('Senha não informada', 400);
            }

            $checkCodeReturn = $this->checkCode($dados_usuario, $dados->codigo);

            if ( !$checkCodeReturn )
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Código inválido'))));

            return $this->changePassword($dados_usuario, $dados->nova_senha);
        }

    }

    private function sendCode($dados_usuario) {

        if ( count($dados_usuario) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok'))));
        }

        $this->loadModel('UsuarioResetSenha');

        $v_code = $this->UsuarioResetSenha->find('first', [
            'fields' => [
                'UsuarioResetSenha.id',
                'UsuarioResetSenha.codigo',
            ],
            'conditions' => [
                'UsuarioResetSenha.validade >=' => date('Y-m-d H:i:s'),
                'UsuarioResetSenha.usuario_id' => $dados_usuario['Usuario']['id']
            ],
            'link' => [],
        ]);

        if ( count($v_code) > 0 ) {

            $dados_salvar = [
                'id' => $v_code['UsuarioResetSenha']['id'],
                'usuario_id' => $dados_usuario['Usuario']['id'],
                'validade' => date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' + 2 hours')),
                'codigo' => mt_rand(100000,999999),
            ];

        } else {

            $dados_salvar = [
                'usuario_id' => $dados_usuario['Usuario']['id'],
                'validade' => date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . ' + 2 hours')),
                'codigo' => mt_rand(100000,999999),
            ];

        }

        if ( $this->UsuarioResetSenha->save($dados_salvar) ) {            
            return $this->sendEmail($dados_usuario, $dados_salvar);
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao gerar o código de recuperação. Por favor, tente mais tarde!'))));
        }


    }

    private function sendEmail($dados_usuario, $dados_salvar){
        $Email = new CakeEmail('smtp');
        $Email->from(array('naoresponder@buzke.com.br' => 'Buzke'));
        $Email->emailFormat('html');
        $Email->to($dados_usuario['Usuario']['email']);
        $Email->template('recuperar_senha');
        $Email->subject('Resetar Senha - Buzke');
        $Email->viewVars(array('usuario_nome'=>$dados_usuario['Usuario']['nome'], 'codigo' => $dados_salvar['codigo'] ));//variable will be replaced from template
        if ( !$Email->send() ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao enviar um e-mail com o código de verificação. Por favor, tente novamente.'))));				
        }
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Código enviado com sucesso!'))));
    }

    private function checkCode($dados_usuario=null, $codigo=null) {

        if ( count($dados_usuario) == 0 || $dados_usuario == null || $codigo == null ) {
            return false;
        }

        $this->loadModel('UsuarioResetSenha');

        $v_code = $this->UsuarioResetSenha->find('first', [
            'fields' => [
                'UsuarioResetSenha.id',
                'UsuarioResetSenha.codigo',
            ],
            'conditions' => [
                'UsuarioResetSenha.validade >=' => date('Y-m-d H:i:s'),
                'UsuarioResetSenha.usuario_id' => $dados_usuario['Usuario']['id'],
                'UsuarioResetSenha.codigo' => $codigo
            ],
            'link' => [],
        ]);

        if ( count($v_code) == 0 ) {
            return false;
        }

        return true;
        

    }

    private function changePassword($dados_usuario, $senha) {

        $dados_usuario['Usuario'] = array_merge($dados_usuario['Usuario'], ['senha' => $senha]);
        $this->loadModel('Usuario');

        if ( !$this->Usuario->save($dados_usuario) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao alterar sua senha. Por favor, tente novamente mais tarde.'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Senha alterada com sucesso!'))));

    }
    
	
}