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

        $email = null;
        if ( isset($dados['email']) && $dados['email'] != "" ) {
            $email = $dados['email'];
        }

        $token = $dados['token'];
        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Cliente');

        $conditions = [
            'Cliente.ativo' => 'Y',
            'Cliente.mostrar' => 'Y'
        ];

        if ( $categoria_id != null ) {
            $conditions = array_merge($conditions, [
                'Categoria.id' => $categoria_id
            ]);
        }

        if ( isset($dados['address']) && $dados['address'] != '' ) {

            if ( isset($dados['address'][1]) && (trim($dados['address'][1]) == "Uruguai" || trim($dados['address'][1]) == "Uruguay") ) {
                $this->loadModel('UruguaiCidade');
                $dados_localidade = $this->UruguaiCidade->findByGoogleAddress($dados['address'][0]);

                $conditions = array_merge($conditions, [
                    'Cliente.ui_cidade' => $dados_localidade['UruguaiCidade']['id'],
                ]);

            } else {
                $this->loadModel('Localidade');
                $dados_localidade = $this->Localidade->findByGoogleAddress($dados['address']);

                $conditions = array_merge($conditions, [
                    'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                    'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
                ]);
            }
        }

        if ( isset($dados['location']) && $dados['location'] != '' ) {

            if ( isset($dados['location'][1]) && (trim($dados['location'][1]) == "Uruguai" || trim($dados['location'][1]) == "Uruguay") ) {
                $this->loadModel('UruguaiCidade');
                $dados_localidade = $this->UruguaiCidade->findByGoogleAddress($dados['location'][0]);

                $conditions = array_merge($conditions, [
                    'Cliente.ui_cidade' => $dados_localidade['UruguaiCidade']['id'],
                ]);

            } else {
                $this->loadModel('Localidade');
                $dados_localidade = $this->Localidade->findByGoogleAddress($dados['location']);

                $conditions = array_merge($conditions, [
                    'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                    'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
                ]);
            }
        }

        $clientes = $this->Cliente->find('all',[
            'fields' => [
                'Cliente.*',
                'Localidade.loc_no',
                'UruguaiCidade.nome',
                'UruguaiDepartamento.nome'
            ],
            'link' => [
                'ClienteSubcategoria' => ['Subcategoria' => ['Categoria']], 'Localidade', "UruguaiCidade", "UruguaiDepartamento"
            ],
            'conditions' => $conditions,
            'group' => [
                'Cliente.id'
            ],
            'order' => ['Cliente.nome']
        ]);
        
        $this->loadModel('ClienteHorarioAtendimento');
        $this->loadModel('ClienteSubcategoria');

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
            $subcategorias = $this->ClienteSubcategoria->find('list',[
                'fields' => [
                    'Subcategoria.nome',
                    'Subcategoria.nome'
                ],
                'conditions' => [
                    'ClienteSubcategoria.cliente_id' => $cliente['Cliente']['id'],
                ],
                'link' => [
                    'Subcategoria'
                ],
                'group' => [
                    'Subcategoria.nome'
                ]
            ]);

            $clientes[$key]['Cliente']['subcategorias_str'] = implode(`,`,$subcategorias);
        }

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

        $email = null;
        if ( isset($dados['email']) && $dados['email'] != "" ) {
            $email = $dados['email'];
        }

        $token = $dados['token'];
    
        $dados_token = $this->verificaValidadeToken($token,$email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $conditions = [
            'Cliente.mostrar' => 'Y',
            'Cliente.ativo' => 'Y',
            'not' => [
                'Categoria.id' => null
            ]
        ];

        if ( isset($dados['address']) && $dados['address'] != '' ) {

            if ( isset($dados['address'][1]) && (trim($dados['address'][1]) == "Uruguai" || trim($dados['address'][1]) == "Uruguay") ) {
                $this->loadModel('UruguaiCidade');
                $dados_localidade = $this->UruguaiCidade->findByGoogleAddress($dados['address'][0]);

                $conditions = array_merge($conditions, [
                    'Cliente.ui_cidade' => $dados_localidade['UruguaiCidade']['id'],
                ]);

            } else {
                $this->loadModel('Localidade');
                $dados_localidade = $this->Localidade->findByGoogleAddress($dados['address']);

                $conditions = array_merge($conditions, [
                    'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                    'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
                ]);
            }
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

        if (!isset($dados->plano) || $dados->plano == '') {
            throw new BadRequestException('Plano não informado', 400);
        }

        if (!isset($dados->metodo_pagamento) || $dados->metodo_pagamento == '') {
            throw new BadRequestException('Método de pagamento não informado', 400);
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
        $this->loadModel('Plano');
        $this->loadModel('MetodoPagamento');
        $this->loadModel('Cliente');

        $dados_cliente = $this->Cliente->find('first',[
            'conditions' => [
                'Cliente.id' => $dados_token['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_cliente) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados de cliente não encontrados!'))));
        }

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

        $dados_plano = $this->Plano->find('first',[
            'conditions' => [
                'Plano.id' => $dados->plano,
                'Plano.ativo' => 'Y',
            ],
            'link' => []
        ]);

        if ( count($dados_plano) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Os dados do plano não foram encontrados!'))));
        }

        $dados_metodo_pagamento = $this->MetodoPagamento->find('first',[
            'conditions' => [
                'MetodoPagamento.id' => $dados->metodo_pagamento,
                'MetodoPagamento.ativo' => 'Y',
            ],
            'link' => []
        ]);

        if ( count($dados_metodo_pagamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Os dados do método de pagamento não foram encontrados!'))));
        }

        if ( $dados_metodo_pagamento['MetodoPagamento']['credit_card'] == 'Y' ){

            if (!isset($dados->cc_number) || $dados->cc_number == '') {
                throw new BadRequestException('Nº do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_name) || $dados->cc_name == '') {
                throw new BadRequestException('Nome impresso no cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_expiry) || $dados->cc_expiry == '') {
                throw new BadRequestException('Data de expiração do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_secure_code) || $dados->cc_secure_code == '') {
                throw new BadRequestException('Código de segurança do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_holder_name) || $dados->cc_holder_name == '') {
                throw new BadRequestException('Nome do titular do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_holder_email) || $dados->cc_holder_email == '') {
                throw new BadRequestException('Email do titular do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_holder_telefone) || $dados->cc_holder_telefone == '') {
                throw new BadRequestException('Telefone do titular do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_holder_cpf) || $dados->cc_holder_cpf == '') {
                throw new BadRequestException('CPF do titular do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_holder_cep) || $dados->cc_holder_cep == '') {
                throw new BadRequestException('CEP do titular do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_holder_bairro) || $dados->cc_holder_bairro == '') {
                throw new BadRequestException('Bairro do titular do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_holder_endereco) || $dados->cc_holder_endereco == '') {
                throw new BadRequestException('Endereço do titular do cartão de crédito não informado.', 400);
            }

            if (!isset($dados->cc_holder_n) || $dados->cc_holder_n == '') {
                throw new BadRequestException('Nº do titular do cartão de crédito não informado.', 400);
            }

        }

        $registrada_em = '';
        if ( !empty($dados_metodo_pagamento['MetodoPagamento']['asaas_key']) ) {
        
            $cria_assinatura = $this->createSignatureApi($dados_cliente, $dados, $dados_plano, $dados_metodo_pagamento);
    
            if ( !$cria_assinatura ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao gerar seus dados de faturamento. Por favor, tente mais tarde!'))));
            }
    
            if ( isset($cria_assinatura['errors']) ){
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao gerar seus dados de faturamento. Por favor, tente mais tarde! '.$asaas_dados['errors'][0]['description']))));
            }

            $registrada_em = 'Asaas';

        } else {
            $cria_assinatura = ['paymentLink' => '', 'status' => 'Active', 'id' => $dados->recibo];
            $registrada_em = 'Apple';
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
            'ClienteAssinatura' => [
                [
                    'plano_id' => $dados->plano,
                    'json_response' => json_encode($cria_assinatura),
                    'link_pagamento' => $cria_assinatura['paymentLink'],
                    'external_id' => $cria_assinatura['id'],
                    'status' => $cria_assinatura['status'],
                    'registrada_em' => $registrada_em,
                ]
            ],
        ];

        if ( isset($cria_assinatura['creditCard']) && is_array($cria_assinatura['creditCard']) && count($cria_assinatura['creditCard']) > 0 ) {
     
 
            $credit_cards_save[] = [
                'bandeira' => $cria_assinatura['creditCard']['creditCardBrand'],
                'ultimos_digitos' => $cria_assinatura['creditCard']['creditCardNumber'],
                'token_asaas' => $cria_assinatura['creditCard']['creditCardToken']
            ];

            $dados_salvar['ClienteCartaoCredito'] = $credit_cards_save;
        }

    
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

    public function renovar_assinatura() {
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

        if (!isset($dados->plano) || $dados->plano == '') {
            throw new BadRequestException('Plano não informado', 400);
        }

        if (!isset($dados->metodo_pagamento) || $dados->metodo_pagamento == '') {
            throw new BadRequestException('Método de pagamento não informado', 400);
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


        $this->loadModel('Plano');
        $this->loadModel('MetodoPagamento');
        $this->loadModel('Cliente');
        $this->loadModel('ClienteCartaoCredito');
        $this->loadModel('ClienteAssinatura');

        $dados_assinatura = $this->ClienteAssinatura->getLastByClientId($dados_token['Usuario']['cliente_id']);

        if ( count($dados_assinatura) > 0 && $dados_assinatura['ClienteAssinatura']['status'] != 'INACTIVE' ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você já tem uma assinatura válida!'))));
        }

        $dados_cliente = $this->Cliente->find('first',[
            'conditions' => [
                'Cliente.id' => $dados_token['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_cliente) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados de cliente não encontrados!'))));
        }

        $dados_plano = $this->Plano->find('first',[
            'conditions' => [
                'Plano.id' => $dados->plano,
                'Plano.ativo' => 'Y',
            ],
            'link' => []
        ]);

        if ( count($dados_plano) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Os dados do plano não foram encontrados!'))));
        }

        $dados_metodo_pagamento = $this->MetodoPagamento->find('first',[
            'conditions' => [
                'MetodoPagamento.id' => $dados->metodo_pagamento,
                'MetodoPagamento.ativo' => 'Y',
            ],
            'link' => []
        ]);

        if ( count($dados_metodo_pagamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Os dados do método de pagamento não foram encontrados!'))));
        }

        $dados_cartao = [];
        if ( $dados_metodo_pagamento['MetodoPagamento']['credit_card'] == 'Y' ){

            if ( isset($dados->cc) && $dados->cc != '' && $dados->cc != '_new' ) {
                $dados_cartao = $this->ClienteCartaoCredito->find('first',[
                    'conditions' => [
                        'ClienteCartaoCredito.id' => $dados->cc,
                        'ClienteCartaoCredito.cliente_id' => $dados_token['Usuario']['cliente_id']
                    ],
                    'link' => []
                ]);

                if ( count($dados_cartao) == 0 ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Os dados do cartão de crédito não foram encontrados!'))));
                }

            } else {

                if (!isset($dados->cc_number) || $dados->cc_number == '') {
                    throw new BadRequestException('Nº do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_name) || $dados->cc_name == '') {
                    throw new BadRequestException('Nome impresso no cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_expiry) || $dados->cc_expiry == '') {
                    throw new BadRequestException('Data de expiração do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_secure_code) || $dados->cc_secure_code == '') {
                    throw new BadRequestException('Código de segurança do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_holder_name) || $dados->cc_holder_name == '') {
                    throw new BadRequestException('Nome do titular do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_holder_email) || $dados->cc_holder_email == '') {
                    throw new BadRequestException('Email do titular do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_holder_telefone) || $dados->cc_holder_telefone == '') {
                    throw new BadRequestException('Telefone do titular do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_holder_cpf) || $dados->cc_holder_cpf == '') {
                    throw new BadRequestException('CPF do titular do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_holder_cep) || $dados->cc_holder_cep == '') {
                    throw new BadRequestException('CEP do titular do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_holder_bairro) || $dados->cc_holder_bairro == '') {
                    throw new BadRequestException('Bairro do titular do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_holder_endereco) || $dados->cc_holder_endereco == '') {
                    throw new BadRequestException('Endereço do titular do cartão de crédito não informado.', 400);
                }
    
                if (!isset($dados->cc_holder_n) || $dados->cc_holder_n == '') {
                    throw new BadRequestException('Nº do titular do cartão de crédito não informado.', 400);
                }

            }

        }

        $atualiza_assinatura = $this->renewSignatureApi($dados_cliente, $dados, $dados_plano, $dados_metodo_pagamento, $dados_cartao);

        if ( !$atualiza_assinatura ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao gerar seus dados de faturamento. Por favor, tente mais tarde!'))));
        }

        if ( isset($atualiza_assinatura['errors']) ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao gerar seus dados de faturamento. Por favor, tente mais tarde! '.$asaas_dados['errors'][0]['description']))));
        }

        $dados_salvar = [
            'Cliente' => [
                'plano_id' => $dados->plano,
                'id' => $dados_token['Usuario']['cliente_id'],
            ],
            'ClienteAssinatura' => [
                [
                    'plano_id' => $dados->plano,
                    'json_response' => json_encode($atualiza_assinatura),
                    'link_pagamento' => $atualiza_assinatura['paymentLink'],
                    'external_id' => $atualiza_assinatura['id'],
                    'status' => $atualiza_assinatura['status'],
                ]
            ],
        ];

        if ( isset($atualiza_assinatura['creditCard']) && is_array($atualiza_assinatura['creditCard']) && count($atualiza_assinatura['creditCard']) > 0 && count($dados_cartao) == 0 ) {
     
 
            $credit_cards_save[] = [
                'bandeira' => $atualiza_assinatura['creditCard']['creditCardBrand'],
                'ultimos_digitos' => $atualiza_assinatura['creditCard']['creditCardNumber'],
                'token_asaas' => $atualiza_assinatura['creditCard']['creditCardToken']
            ];

            $dados_salvar['ClienteCartaoCredito'] = $credit_cards_save;
        }

        $this->loadModel('Cliente');

        $this->Cliente->set($dados_salvar);
        if ($this->Cliente->saveAssociated($dados_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Assinatura atualizada com sucesso!'))));
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

        $email = null;
        if ( isset($dados['email']) && $dados['email'] != "" ) {
            $email = $dados['email'];
        }

        $dados_token = $this->verificaValidadeToken($token, $email);

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
            throw new BadRequestException('Dados de usuário não informados!', 401);
        }
    
        if ( !isset($dados['data']) || $dados['data'] == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }

        $token = $dados['token'];
        $data = $dados['data'];
        $dia_semana = date('w',strtotime($data));

        $email = null;
        if ( isset($dados['email']) && $dados['email'] != "" ) {
            $email = $dados['email'];
        }

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token["Usuario"]["cliente_id"] == null ) {

            if ( !isset($dados['cliente_id']) || $dados['cliente_id'] == "" ) {
                throw new BadRequestException('Dados de cliente não informados!', 401);
            }
        } else {
            $dados['cliente_id'] = $dados_token["Usuario"]["cliente_id"];
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

        $order_cliente_servico = [
            'ClienteServico.nome'
        ];

        if ( isset($dados['cliente_id']) && $dados['cliente_id'] != '' ) {
            $conditions = [
                'ClienteServico.cliente_id' => $dados['cliente_id']
            ];

            if ( $dados['cliente_id'] == 55 ) {
                $order_cliente_servico = [
                    'ClienteServico.id'
                ];
    
            }
        } else {
            $conditions = [
                'ClienteServico.cliente_id' => $dado_usuario['Usuario']['cliente_id']
            ];

            if ( $dado_usuario['Usuario']['cliente_id'] == 55 ) {
                $order_cliente_servico = [
                    'ClienteServico.id'
                ];
    
            }
        }


        $this->loadModel('ClienteServico');
        $servicos = $this->ClienteServico->find('all',[
            'conditions' => $conditions,
            'order' => $order_cliente_servico,
            'link' => []
        ]);

        if ( count($servicos) > 0 ) {
            foreach($servicos as $key => $servico) {
                $servicos[$key]['ClienteServico']['valor'] = 'R$ '.$this->floatEnBr($servico['ClienteServico']['valor']);
                $servicos[$key]["ClienteServico"]["cor"] = $this->services_colors[$key];
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
                'ClienteCliente.nacionalidade',
                'ClienteCliente.nome',
                'ClienteCliente.email',
                'ClienteCliente.pais',
                'ClienteCliente.telefone',
                'ClienteCliente.telefone_ddi',
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
            'order' => ['ClienteCliente.nome'],
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
                $clientes[$key]['ClienteCliente']['_telefone'] = "+" . $clientes[$key]['ClienteCliente']['telefone_ddi'] . " " . $clientes[$key]['ClienteCliente']['telefone'];
                $clientes[$key]['ClienteCliente']['_endereco'] = $clientes[$key]['ClienteCliente']['endereco'] . ", " . $clientes[$key]['ClienteCliente']['endreceo_n'];
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
            'order' => ['ClienteCliente.nome'],
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
            $dados['Cliente']['isPaddleCourt'] = $this->ClienteSubcategoria->checkIsPaddleCourt($dados['Cliente']['id']);
            if ( $dados['Cliente']['prazo_maximo_para_canelamento'] != null && $dados['Cliente']['prazo_maximo_para_canelamento'] != '' )
                $dados['Cliente']['prazo_maximo_para_canelamento'] = substr($dados['Cliente']['prazo_maximo_para_canelamento'], 0, 5);
            
            $dados['Cliente']['tempo_aviso_usuarios'] = substr($dados['Cliente']['tempo_aviso_usuarios'], 0, 5);
                
            $dados['Cliente']['telefone_possui_wp'] = false;
            if ($dados['Cliente']['telefone'] == $dados['Cliente']['wp'])
                $dados['Cliente']['telefone_possui_wp'] = true;
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

        if (!isset($dados->endereco ) || $dados->endereco  == '') {
            throw new BadRequestException('Endereço não informado', 400);
        }

        if (!isset($dados->n ) || $dados->n  == '' || !is_numeric($dados->n) ) {
            throw new BadRequestException('Número não informado', 400);
        }

        if (!isset($dados->telefone) || $dados->telefone == '') {
            throw new BadRequestException('Telefone não informado', 400);
        }

        if (!isset($dados->telefone_ddi) || $dados->telefone_ddi == '') {
            throw new BadRequestException('Telefone DDI não informado', 400);
        }

        if (!isset($dados->avisar_com ) || $dados->avisar_com  == '' || $dados->avisar_com  == '00:00' ) {
            throw new BadRequestException('Tempo para aviso dos usuários não informado', 400);
        }

        $pais = isset($dados->pais) ? $dados->pais : "Brasil";

        if ( $pais == "Brasil" ) {

            if (!isset($dados->localidade) || $dados->localidade == '') {
                throw new BadRequestException('cidade não informada', 400);
            }

            if (!isset($dados->uf) || $dados->uf == '') {
                throw new BadRequestException('UF não informada', 400);
            }

            if (!isset($dados->cep) || $dados->cep == '') {
                throw new BadRequestException('CEP não informada', 400);
            }

            if (!isset($dados->bairro) || $dados->bairro == '') {
                throw new BadRequestException('CEP não informada', 400);
            }

            $estado = $dados->uf;
            $localidade = $dados->localidade;
            $bairro = $dados->bairro;
            $ui_departamento = null;
            $ui_cidade = null;
            $cep = $dados->cep;

            $this->loadModel('Uf');
            $dadosUf = $this->Uf->find('first',[
                'conditions' => [
                    'Uf.ufe_sg' => $estado
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

            $cidade_id = $dadosLocalidade['Localidade']['loc_nu_sequencial'];
        }

        else if ( $pais == "Uruguai" ) {

            if (!isset($dados->ui_departamento) || $dados->ui_departamento == '') {
                throw new BadRequestException('Departamento não informado', 400);
            }

            if (!isset($dados->ui_cidade) || $dados->ui_cidade == '') {
                throw new BadRequestException('Cidade não informada', 400);
            }

            $ui_departamento = $dados->ui_departamento;
            $ui_cidade = $dados->ui_cidade;
            $cidade_id = null;
            $estado = null;
            $bairro = null;
            $cep = null;
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

        $wp = null;
        if (isset($dados->telefone_possui_wp) && $dados->telefone_possui_wp) {
            $wp = $dados->telefone;
            $wp_ddi = $dados->telefone_ddi;
        } else {
            $wp = $dados->wp;
            $wp_ddi = $dados->wp_ddi;
        }

        $telefone = $dados->telefone;
        $telefone_ddi = $dados->telefone_ddi;

        $dados_salvar = [
            'Cliente' => [
                'pais' => $pais,
                'id' => $dados_token['Usuario']['cliente_id'],
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'nome' => $dados->nomeProfissional,
                'telefone_ddi' => $telefone_ddi,
                'telefone' => $telefone,
                'wp_ddi' => $wp_ddi,
                'wp' => $wp,
                'cep' => $dados->cep,
                'endereco' => $dados->endereco,
                'endereco_n' => $dados->n,
                'tipo' => $dados->tipo_cadastro,
                'cidade_id' => $cidade_id,
                'estado' => $estado,
                'ui_departamento' => $ui_departamento,
                'ui_cidade' => $ui_cidade,
                'prazo_maximo_para_canelamento' => $dados->prazo_maximo_para_canelamento != "00:00" && $dados->prazo_maximo_para_canelamento != "" ? $dados->prazo_maximo_para_canelamento : null,
                'tempo_aviso_usuarios' => $dados->avisar_com,
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

    public function setShowToUsers() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        } else {
            $dados = json_decode($dados);
        }

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->email) || $dados->email == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
    
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_usuario['Usuario']['cliente_id'] == '' || $dados_usuario['Usuario']['cliente_id'] == null || $dados_usuario['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Sem permissão de acesso!', 401);
        }

        $value = $dados->value;

        $dados_salvar = [
            'id' => $dados_usuario['Usuario']['cliente_id'],
            'mostrar' => $value,
        ];
        
        $this->loadModel('Cliente');
        $atualiza_dados = $this->Cliente->save($dados_salvar);

        if ( !$atualiza_dados ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao atualizar seus dados. Por favor, tente novamente mais tarde!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Seus dados foram atualizados com sucesso!'))));

    }

    public function cartoes_de_credito() {

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

        $this->loadModel('ClienteCartaoCredito');

        $cartoes = $this->ClienteCartaoCredito->getByClientId($dado_usuario['Usuario']['cliente_id']);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $cartoes))));

    }

    public function checkSignature() {

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

        $this->loadModel('ClienteAssinatura');
        $dados_assinatura = $this->ClienteAssinatura->getLastByClientId($dados_token['Usuario']['cliente_id']);

        if ( count($dados_assinatura) == 0 || $dados_assinatura['ClienteAssinatura']['status'] == 'INACTIVE' ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'no_signature', 'msg' => 'Sua assinatura venceu, clique no botao abaixo para resolver.', 'button_text' => 'Renovar Assinatura'))));
        }

        if ( $dados_assinatura['ClienteAssinatura']['status'] == 'OVERDUE' ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'overdue', 'msg' => 'Você possui pendencias financeiras com o Buzke, clique no botao abaixo para resolver.', 'button_text' => 'Resolver'))));
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok'))));
    }

    private function createSignatureApi($cliente = [], $dados_adicionais, $plano = [], $tipo_pagamento = [] ) {
    
        if ( count($cliente) == 0 || count($plano) == 0 || count($tipo_pagamento) == 0 ) {
            return false;
        }

        $hoje = date('Y-m-d');
        $hoje_time = strtotime($hoje);
        $one_month_more = date("Y-m-d", strtotime("+1 month", $hoje_time));

        if ( $this->ambiente == 1 ) {
            $asaas_url = $this->asaas_api_url;
            $asaas_token = $this->asaas_api_token;            
            $asaas_cliente_id = $cliente['Cliente']['asaas_id'];
        }
        else if ( $this->ambiente == 2 ) {
            $asaas_url = $this->asaas_sandbox_url;
            $asaas_token = $this->asaas_sandbox_token;
            $asaas_cliente_id = $cliente['Cliente']['asaas_homologacao_id'];
        }

        $params = [
            'customer' => $asaas_cliente_id,
            'billingType' => $tipo_pagamento['MetodoPagamento']['asaas_key'],
            'nextDueDate' => $one_month_more,
            'value' => floatval($plano['Plano']['valor_promocional']),
            'cycle' => 'MONTHLY',
            'description' => $plano['Plano']['nome'],
            'maxPayments' => $plano['Plano']['prazo_promocao'],
            //'remoteIp' => ,
        ];

        if ( $tipo_pagamento['MetodoPagamento']['credit_card'] == 'Y' ) {

            list($expiry_month, $expiry_year) = explode('/',$dados_adicionais->cc_expiry);

            $params['creditCard'] = 
            [
              'holderName' => $dados_adicionais->cc_name,
              'number' => $dados_adicionais->cc_number,
              'expiryMonth' => $expiry_month,
              'expiryYear' => '20'.$expiry_year,
              'ccv' => $dados_adicionais->cc_secure_code,
            ];

            $params['creditCardHolderInfo'] = 
            [
              'name' => $dados_adicionais->cc_holder_name,
              'email' => $dados_adicionais->cc_holder_email,
              'cpfCnpj' => preg_replace('/[^0-9]/', '', $dados_adicionais->cc_holder_cpf),
              'postalCode' => $dados_adicionais->cc_holder_cep,
              'addressNumber' => $dados_adicionais->cc_holder_n,
              'addressComplement' => isset($dados_adicionais->cc_holder_complemento) && $dados_adicionais->cc_holder_complemento != '' ? $dados_adicionais->cc_holder_complemento : null,
              'phone' => preg_replace('/[^0-9]/', '', $dados_adicionais->cc_holder_telefone),
              //'mobilePhone' => '47998781877',
            ];

            //$params['creditCardToken'] = $credit_card_token;

        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $asaas_url .'/api/v3/subscriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER=> 0,
            CURLOPT_SSL_VERIFYHOST=> 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'access_token: '.$asaas_token,
            ),
        ));

        $response = curl_exec($curl);

        $errors = curl_error($curl);
        curl_close($curl);

        if ( !empty($errors) ) {
            return false;
        }

        return json_decode($response, true);

    }

    private function renewSignatureApi($cliente = [], $dados_adicionais, $plano = [], $tipo_pagamento = [], $cartao = [] ) {
    
        if ( count($cliente) == 0 || count($plano) == 0 || count($tipo_pagamento) == 0 ) {
            return false;
        }

        $hoje = date('Y-m-d');
        $hoje_time = strtotime($hoje);
        $one_month_more = date("Y-m-d", strtotime("+1 month", $hoje_time));

        if ( $this->ambiente == 1 ) {
            $asaas_url = $this->asaas_api_url;
            $asaas_token = $this->asaas_api_token;            
            $asaas_cliente_id = $cliente['Cliente']['asaas_id'];
        }
        else if ( $this->ambiente == 2 ) {
            $asaas_url = $this->asaas_sandbox_url;
            $asaas_token = $this->asaas_sandbox_token;
            $asaas_cliente_id = $cliente['Cliente']['asaas_homologacao_id'];
        }

        $params = [
            'customer' => $asaas_cliente_id,
            'billingType' => $tipo_pagamento['MetodoPagamento']['asaas_key'],
            'nextDueDate' => $one_month_more,
            'value' => floatval($plano['Plano']['valor']),
            'cycle' => 'MONTHLY',
            'description' => $plano['Plano']['nome'],
            'maxPayments' => $plano['Plano']['prazo_promocao'],
            //'remoteIp' => ,
        ];

        if ( $tipo_pagamento['MetodoPagamento']['credit_card'] == 'Y' ) {

            if ( isset($cartao['ClienteCartaoCredito']['token_asaas']) ) {
                $params['creditCardToken'] = $cartao['ClienteCartaoCredito']['token_asaas'];
            } else {

                list($expiry_month, $expiry_year) = explode('/',$dados_adicionais->cc_expiry);
    
                $params['creditCard'] = 
                [
                  'holderName' => $dados_adicionais->cc_name,
                  'number' => $dados_adicionais->cc_number,
                  'expiryMonth' => $expiry_month,
                  'expiryYear' => '20'.$expiry_year,
                  'ccv' => $dados_adicionais->cc_secure_code,
                ];
    
                $params['creditCardHolderInfo'] = 
                [
                  'name' => $dados_adicionais->cc_holder_name,
                  'email' => $dados_adicionais->cc_holder_email,
                  'cpfCnpj' => preg_replace('/[^0-9]/', '', $dados_adicionais->cc_holder_cpf),
                  'postalCode' => $dados_adicionais->cc_holder_cep,
                  'addressNumber' => $dados_adicionais->cc_holder_n,
                  'addressComplement' => isset($dados_adicionais->cc_holder_complemento) && $dados_adicionais->cc_holder_complemento != '' ? $dados_adicionais->cc_holder_complemento : null,
                  'phone' => preg_replace('/[^0-9]/', '', $dados_adicionais->cc_holder_telefone),
                  //'mobilePhone' => '47998781877',
                ];

            }

        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $asaas_url .'/api/v3/subscriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER=> 0,
            CURLOPT_SSL_VERIFYHOST=> 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'access_token: '.$asaas_token,
            ),
        ));

        $response = curl_exec($curl);

        $errors = curl_error($curl);
        curl_close($curl);

        if ( !empty($errors) ) {
            return false;
        }

        return json_decode($response, true);

    }
}