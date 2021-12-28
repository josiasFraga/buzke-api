<?php
class ToProJogoController extends AppController {

    public function index() {

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

        if ( $dados_token['Usuario']['nivel_id'] != 3 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');

        $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_token['Usuario']['id'], true);

        $this->loadModel('ToProJogo');
        $to_pro_jogo = $this->ToProJogo->find('all',[
            'fields' => ['*'],
            'order' => [
                'ToProJogo.id'
            ],
            'contain' => ['ToProJogoEsporte' => ['Subcategoria'], 'UsuarioLocalizacao'],
            'conditions' => [
                'ToProJogo.cliente_cliente_id' => $meus_ids_de_cliente,
                'or' => [
                    'ToProJogo.data_fim' => null,
                    'ToProJogo.data_fim >=' => date('Y-m-d')
                ]
            ],
        ]);

        foreach($to_pro_jogo as $key => $tpj){
            $to_pro_jogo[$key]['ToProJogo']['_esportes'] = $this->subcategoriasToEsportesStr($tpj['ToProJogoEsporte']);
            if ( $tpj['ToProJogo']['dia_semana'] != null ) {
                $to_pro_jogo[$key]['ToProJogo']['_periodo'] = 'Toda '.$this->dias_semana_str[$tpj['ToProJogo']['dia_semana']];
            } else if( $tpj['ToProJogo']['dia_mes'] != null ) {
                $to_pro_jogo[$key]['ToProJogo']['_periodo'] = 'Todo dia '.$tpj['ToProJogo']['dia_mes'];
            } else {
                $to_pro_jogo[$key]['ToProJogo']['_periodo'] = 
                'De '.date('d/m/Y',strtotime($tpj['ToProJogo']['data_inicio'])).
                ' até '.date('d/m/Y',strtotime($tpj['ToProJogo']['data_fim']));
            }
            $to_pro_jogo[$key]['ToProJogo']['_tempo'] = 'das '.date('H:i',strtotime($tpj['ToProJogo']['hora_inicio']));
            $to_pro_jogo[$key]['ToProJogo']['_tempo'] .= ' as '.date('H:i',strtotime($tpj['ToProJogo']['hora_fim']));
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $to_pro_jogo))));

    }

    public function cadastrar() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        if (!isset($dados->localizacao) || $dados->localizacao == '') {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'info', 'msg' => 'Localização não informada. Você deve selecionar sua localização antes de clicar em cadastrar.'))));
        }

        if (!is_array($dados->localizacao) || count($dados->localizacao) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'info', 'msg' => 'Localização inválida. Você deve selecionar sua localização antes de clicar em cadastrar.'))));
        }

        if ( !isset($dados->localizacao[0]) || !isset($dados->localizacao[1]) || !isset($dados->localizacao[2]) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'info', 'msg' => 'Localização inválida. Você deve selecionar sua localização antes de clicar em cadastrar.'))));
        }

        if (!isset($dados->tipo_cadastro) || $dados->tipo_cadastro == '') {
            throw new BadRequestException('Tipo de cadastro não informado', 400);
        }

        if (!isset($dados->hora_de) || $dados->hora_de == '') {
            throw new BadRequestException('Hora inicial não informada', 400);
        }

        if (!isset($dados->hora_ate) || $dados->hora_ate == '') {
            throw new BadRequestException('Hora final não informada', 400);
        }

        $data_inicio = null;
        $data_fim = null;
        $dia_semana = null;
        $dia_mes = null;
        if ( $dados->tipo_cadastro == 'P' ) {
            
            if (!isset($dados->dia_de) || $dados->dia_de == '') {
                throw new BadRequestException('Dia inicial não informado', 400);
            }
            if (!isset($dados->dia_ate) || $dados->dia_ate == '') {
                throw new BadRequestException('Dia final não informado', 400);
            }

            $data_inicio = $dados->dia_de;
            $data_fim = $dados->dia_ate;

        } else if ($dados->tipo_cadastro == 'S') {

            if (!isset($dados->dia_semana) || $dados->dia_semana == '') {
                throw new BadRequestException('Dia da semana não informado', 400);
            }
            $dia_semana = $dados->dia_semana;

        } else if ($dados->tipo_cadastro == 'M') {

            if (!isset($dados->dia_mes) || $dados->dia_mes == '') {
                throw new BadRequestException('Dia da semana não informado', 400);
            }
            $dia_mes = $dados->dia_mes;

        } else {
            throw new BadRequestException('Tipo de cadastro inválido', 400);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('UsuarioLocalizacao');
        $this->loadModel('ClienteCliente');

        $dados_localizacao_usuario = $this->UsuarioLocalizacao->findByUserIdAndData($dados_token['Usuario']['id'],$dados->localizacao);

        if ( count($dados_localizacao_usuario) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Localização não encontrada. Você deve selecionar sua localização antes de clicar em cadastrar.'))));
        }

        $meus_ids_de_cliente = $this->ClienteCliente->buscaDadosSemVinculo($dados_token['Usuario']['id'], false);

        if ( count($meus_ids_de_cliente) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos seus dados como cliente.'))));
        }

        $this->loadModel('ToProJogo');

        $dados_salvar = array(
            'ToProJogo' => array(
                'cliente_cliente_id' => $meus_ids_de_cliente[0]['ClienteCliente']['id'], 
                'localizacao_id' => $dados_localizacao_usuario['UsuarioLocalizacao']['id'], 
                'data_inicio' => $data_inicio, 
                'data_fim' => $data_fim, 
                //'email' => $dados->email, 
                'hora_inicio' => $dados->hora_de, 
                'hora_fim' => $dados->hora_ate, 
                'dia_semana' => $dia_semana, 
                'dia_mes' => $dia_mes, 
            ),
            'ToProJogoEsporte' => []
        );

        $esportes = [];
        foreach($dados as $key_dado => $dado) {

            if ( strpos($key_dado, 'esporte_') !== false ) {
                list($discart, $subcategoria_id) = explode('esporte_', $key_dado);
                $esportes[] = $subcategoria_id;
            }
        }

        if ( count($esportes) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione ao menos um esporte antes de clicar em salvar!'))));
        }

        foreach($esportes as $key => $esporte) {
            $dados_salvar['ToProJogoEsporte'][] = [
                'subcategoria_id' => $esporte
            ];
        }

        $this->ToProJogo->set($dados_salvar);
        if ($this->ToProJogo->saveAssociated($dados_salvar, ['deep' => true])) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastrado com sucesso!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function alterar() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->id) || $dados->id == '') {
            throw new BadRequestException('ID não informado', 400);
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        if (!isset($dados->tipo_cadastro) || $dados->tipo_cadastro == '') {
            throw new BadRequestException('Tipo de cadastro não informado', 400);
        }

        if (!isset($dados->hora_de) || $dados->hora_de == '') {
            throw new BadRequestException('Hora inicial não informada', 400);
        }

        if (!isset($dados->hora_ate) || $dados->hora_ate == '') {
            throw new BadRequestException('Hora final não informada', 400);
        }

        $data_inicio = null;
        $data_fim = null;
        $dia_semana = null;
        $dia_mes = null;
        if ( $dados->tipo_cadastro == 'P' ) {
            
            if (!isset($dados->dia_de) || $dados->dia_de == '') {
                throw new BadRequestException('Dia inicial não informado', 400);
            }
            if (!isset($dados->dia_ate) || $dados->dia_ate == '') {
                throw new BadRequestException('Dia final não informado', 400);
            }

            $data_inicio = $dados->dia_de;
            $data_fim = $dados->dia_ate;

        } else if ($dados->tipo_cadastro == 'S') {

            if (!isset($dados->dia_semana) || $dados->dia_semana == '') {
                throw new BadRequestException('Dia da semana não informado', 400);
            }
            $dia_semana = $dados->dia_semana;

        } else if ($dados->tipo_cadastro == 'M') {

            if (!isset($dados->dia_mes) || $dados->dia_mes == '') {
                throw new BadRequestException('Dia da semana não informado', 400);
            }
            $dia_mes = $dados->dia_mes;

        } else {
            throw new BadRequestException('Tipo de cadastro inválido', 400);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');
        $this->loadModel('ToProJogo');
        $this->loadModel('ToProJogoEsporte');
        
        $meus_ids_de_cliente = $this->ClienteCliente->buscaDadosSemVinculo($dados_token['Usuario']['id'], false);

        $dados_to_pro_jogo = $this->ToProJogo->find('first',[
            'fields' => [
                'ToProJogo.id', 
            ],
            'conditions' => [
                'id' => $dados->id,
                'cliente_cliente_id' => $meus_ids_de_cliente[0]['ClienteCliente']['id']
            ],
            'link' => []
        ]);

        if ( count($dados_to_pro_jogo) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O Tô Pro Jogo que você está tentando alterar, não existe!'))));
        }

        $dados_salvar = array(
            'ToProJogo' => array(
                'id' => $dados->id, 
                'data_inicio' => $data_inicio, 
                'data_fim' => $data_fim, 
                //'email' => $dados->email, 
                'hora_inicio' => $dados->hora_de, 
                'hora_fim' => $dados->hora_ate, 
                'dia_semana' => $dia_semana, 
                'dia_mes' => $dia_mes, 
            ),
            'ToProJogoEsporte' => []
        );

        $esportes = [];
        foreach($dados as $key_dado => $dado) {
            if ( strpos($key_dado, 'esporte_') !== false ) {
                list($discart, $subcategoria_id) = explode('esporte_', $key_dado);
                $esportes[] = $subcategoria_id;
            }
        }

        if ( count($esportes) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione ao menos um esporte antes de clicar em salvar!'))));
        }

        $this->ToProJogoEsporte->deleteAll(['ToProJogoEsporte.to_pro_jogo_id' => $dados->id], true);

        foreach($esportes as $key => $esporte) {
            $dados_salvar['ToProJogoEsporte'][] = [
                'subcategoria_id' => $esporte
            ];
        }

        $this->ToProJogo->set($dados_salvar);
        if ($this->ToProJogo->saveAssociated($dados_salvar, ['deep' => true])) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Alterado com sucesso!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    private function subcategoriasToEsportesStr($subcategorias = []) {
        if ( count($subcategorias) == 0 ) {
            return '';
        }

        $arr_nomes = [];
        foreach($subcategorias as $key => $subcategoria){
            $arr_nomes[] = $subcategoria['Subcategoria']['esporte_nome'];
        }
        return implode(', ',$arr_nomes);

    }

    public function esportes() {

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

        if ( $dados_token['Usuario']['nivel_id'] != 3 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');
        $this->loadModel('UsuarioDadosPadel');
        $this->loadModel('Subcategoria');

        $usuario_dados_padel = $this->UsuarioDadosPadel->findByUserId($dados_token['Usuario']['id']);

        $esportes = $this->Subcategoria->find('all',[
            'fields' => ['Subcategoria.id', 'Subcategoria.esporte_nome'],
            'order' => [
                'Subcategoria.esporte_nome'
            ],
            'link' => [],
            'conditions' => [
                'Subcategoria.mostrar_no_to_pro_jogo' => 'Y',
            ],
        ]);

        foreach($esportes as $key => $esporte){
            $esportes[$key]['Subcategoria']['enabled'] = true;
            if ( count($usuario_dados_padel) == 0 &&  $esporte['Subcategoria']['id'] == 7 ) {
                $esportes[$key]['Subcategoria']['enabled'] = false;
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $esportes))));

    }

    public function excluir(){
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->id) || $dados->id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');
        $meus_ids_de_cliente = $this->ClienteCliente->buscaDadosSemVinculo($dados_token['Usuario']['id'], false);

        $this->loadModel('ToProJogo');

        $dados_to_pro_jogo = $this->ToProJogo->find('first',[
            'fields' => [
                'ToProJogo.id', 
            ],
            'conditions' => [
                'id' => $dados->id,
                'cliente_cliente_id' => $meus_ids_de_cliente[0]['ClienteCliente']['id']
            ],
            'link' => []
        ]);

        if ( count($dados_to_pro_jogo) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O Tô Pro Jogo que você está tentando exlcuir, não existe!'))));
        }

        if ( !$this->ToProJogo->delete($dados->id) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar excluir o Tô Pro Jogo. Por favor, tente mais tarde!'))));
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tô pro jogo excluído com sucesso!'))));

    }

    public function usuarios() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['horaSelecionada']) || $dados['horaSelecionada'] == "" ) {
            throw new BadRequestException('Hora não informada!', 401);
        }
        if ( !isset($dados['day']) || $dados['day'] == "" ) {
            throw new BadRequestException('Dia não informado!', 401);
        }
        if ( !isset($dados['cliente_id']) || $dados['cliente_id'] == "" ) {
            throw new BadRequestException('Id da empresa não informada!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token['Usuario']['nivel_id'] != 3 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Cliente');
        $dados_cliente = $this->Cliente->find('first',[
            'fields' => ['Cliente.id'],
            'conditions' => [
                'Cliente.id' => $dados['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_cliente) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'error', 'msg' => 'Dados da empresa não encontrados'))));
        }

        $this->loadModel('ClienteSubcategoria');
        $subcategorias = $this->ClienteSubcategoria->getArrIdsSubcategoriaByBusinessId($dados['cliente_id']);

        $hora_selecionada = json_decode($dados['horaSelecionada'], true);
        $day = json_decode($dados['day'], true);

        $this->loadModel('ToProJogo');
        $usuarios = $this->ToProJogo->findUsers($hora_selecionada['horario'], $day['dateString'], $dados_token['Usuario']['id'], $subcategorias);

        if ( count($usuarios) > 0 ) {
            $this->loadModel('UsuarioPadelCategoria');

            foreach($usuarios as $key => $usr) {
                $usuarios[$key]['Usuario']['img'] = $this->images_path.'usuarios/'.$usr['Usuario']['img'];
                $usuarios[$key]['UsuarioDadosPadel']['_categorias'] = $this->padelCategoriasToStr($this->UsuarioPadelCategoria->findByUserId($usr['Usuario']['id']));
            }

        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $usuarios))));

    }

    private function padelCategoriasToStr ($categorias = []) {
        if ($categorias == '' || !$categorias || $categorias == null || count($categorias) == 0 ) {
            return '';
        }

        $categorias_arr = [];
        foreach($categorias as $key => $categoria) {
            $categorias_arr[] = $categoria['PadelCategoria']['titulo'];

        }
        return implode(', ',$categorias_arr);
    }

    public function meus_convites() {

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

        if ( $dados_token['Usuario']['nivel_id'] != 3 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');
        $this->loadModel('AgendamentoConvite');

        $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_token['Usuario']['id'], true);
        $meus_ids_de_cliente = array_values($meus_ids_de_cliente);
  
        $dados = $this->AgendamentoConvite->find('all',[
            'fields' => [
                'AgendamentoConvite.*',
                'Agendamento.horario', 
                'Usuario.id',
                'Usuario.nome',
                'Usuario.img',
                'Agendamento.cliente_cliente_id',
                'Cliente.nome',
                'Cliente.telefone',
                'Cliente.wp',
                'Cliente.endereco',
                'Cliente.endereco_n',
                'Cliente.bairro',
                'Cliente.estado',
                'Cliente.logo',
                'Localidade.loc_no',
                'ClienteServico.nome',
                'ClienteServico.valor'
            ],
            'order' => [
                'AgendamentoConvite.horario'
            ],
            'link' => [
                'Agendamento' => ['Cliente' => ['Localidade', 'ClienteServico']],
                'ClienteCliente' => [
                    'Usuario'
                ]
            ],
            'conditions' => [
                'or' => [
                    'AgendamentoConvite.cliente_cliente_id' => $meus_ids_de_cliente,
                    'Agendamento.cliente_cliente_id' => $meus_ids_de_cliente
                ],
                'AgendamentoConvite.horario >=' => date('Y-m-d h:i:s')
            ],
            'group' => ['AgendamentoConvite.id']
        ]);

        foreach($dados as $key => $tpj){
            $usuario_dono_horario = in_array($tpj['Agendamento']['cliente_cliente_id'], $meus_ids_de_cliente);
            $dados[$key]['Usuario']['img'] = $this->images_path.'usuarios/'.$tpj['Usuario']['img'];
            $dados[$key]['UsuarioMarcante'] = $this->ClienteCliente->finUserData($dados[$key]['Agendamento']['cliente_cliente_id'], ['Usuario.nome', 'Usuario.img']);
            $dados[$key]['Cliente']['logo'] = $this->images_path.'clientes/'.$tpj['Cliente']['logo'];
            $dados[$key]['UsuarioMarcante']['img'] = $this->images_path.'usuarios/'.$dados[$key]['UsuarioMarcante']['img'];
            $dados[$key]['ClienteServico']['valor_br'] = number_format($tpj['ClienteServico']['valor'], 2, ',', '.');
            $dados[$key]['AgendamentoConvite']['_data_desc'] = date('Y-m-d') == date('Y-m-d',strtotime($tpj['AgendamentoConvite']['horario'])) ? 'Hoje' : date('d/m/Y',strtotime($tpj['AgendamentoConvite']['horario']));
            $dados[$key]['AgendamentoConvite']['_hora_desc'] = date('H:i',strtotime($tpj['AgendamentoConvite']['horario']));
            $dados[$key]['_usuarios_confirmados'] = $this->AgendamentoConvite->getConfirmedUsers($dados[$key]['AgendamentoConvite']['agendamento_id'], $this->images_path.'/usuarios/', $tpj['AgendamentoConvite']['horario']);
            if ( $usuario_dono_horario ) {
                $dados[$key]['AgendamentoConvite']['_tipo'] = [
                    'id' => 1,
                    'desc' => 'Enviado'
                ];
                if ( $tpj['AgendamentoConvite']['confirmado_usuario'] == 'R' ) {
                    $dados[$key]['AgendamentoConvite']['_status'] = [
                        'id' => 4,
                        'desc' => 'Recusado por você'
                    ];
                }
                else if ( $tpj['AgendamentoConvite']['confirmado_convidado'] == 'R' ) {
                    $dados[$key]['AgendamentoConvite']['_status'] = [
                        'id' => 6,
                        'desc' => 'Recusado pelo convidado'
                    ];
                }
            } else {
                $dados[$key]['AgendamentoConvite']['_tipo'] = [
                    'id' => 2,
                    'desc' => 'Recebido'
                ];
                if ( $tpj['AgendamentoConvite']['confirmado_usuario'] == 'R' ) {
                    $dados[$key]['AgendamentoConvite']['_status'] = [
                        'id' => 5,
                        'desc' => 'O jogo já fechou :('
                    ];
                }
                else if ( $tpj['AgendamentoConvite']['confirmado_convidado'] == 'R' ) {
                    $dados[$key]['AgendamentoConvite']['_status'] = [
                        'id' => 4,
                        'desc' => 'Recusado por você'
                    ];
                }
            }

     
            if ( $tpj['AgendamentoConvite']['horario_cancelado'] == 'Y' ) {
                $dados[$key]['AgendamentoConvite']['_status'] = [
                    'id' => 11,
                    'desc' => 'Horário Cancelado'
                ];
            }

            if (!isset($dados[$key]['AgendamentoConvite']['_status'])) {
                if ( $usuario_dono_horario ) {

                    if ( $tpj['AgendamentoConvite']['confirmado_convidado'] == 'N' ) {
                        $dados[$key]['AgendamentoConvite']['_status'] = [
                            'id' => 3,
                            'desc' => 'Aguardando confirmação do convidado'
                        ];
                    }
                    else if ( $tpj['AgendamentoConvite']['confirmado_usuario'] == 'N' ) {
                        $dados[$key]['AgendamentoConvite']['_status'] = [
                            'id' => 2,
                            'desc' => 'Aguardando sua confirmação'
                        ];
                    } else {
                        $dados[$key]['AgendamentoConvite']['_status'] = [
                            'id' => 1,
                            'desc' => 'Confirmado'
                        ];
                    }
                } else {
                    if ( $tpj['AgendamentoConvite']['confirmado_convidado'] == 'N' ) {
                        $dados[$key]['AgendamentoConvite']['_status'] = [
                            'id' => 3,
                            'desc' => 'Aguardando sua confirmação'
                        ];
                    }
                    else if ( $tpj['AgendamentoConvite']['confirmado_usuario'] == 'N' ) {
                        $dados[$key]['AgendamentoConvite']['_status'] = [
                            'id' => 2,
                            'desc' => 'Aguardando confirmação do dono do horário'
                        ];
                    } else {
                        $dados[$key]['AgendamentoConvite']['_status'] = [
                            'id' => 1,
                            'desc' => 'Confirmado'
                        ];
                    }
                }
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));

    }

    public function convite_acao() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));

        }else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->invite_id) || $dados->invite_id == '') {
            throw new BadRequestException('ID do convite não informado', 400);
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        if (!isset($dados->action) || $dados->action == '' || !in_array($dados->action, [1,2]) ) {//1 confirmar = Y, 2 recusar = R
            throw new BadRequestException('Ação não informada', 400);
        }

        $acao = $dados->action;

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');
        $this->loadModel('AgendamentoConvite');
        
        $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_token['Usuario']['id'], true);

        $dados_convite = $this->AgendamentoConvite->find('first',[
            'fields' => [
                '*', 
            ],
            'conditions' => [
                'AgendamentoConvite.id' => $dados->invite_id,
                'or' => [
                    'AgendamentoConvite.cliente_cliente_id' => $meus_ids_de_cliente,
                    'Agendamento.cliente_cliente_id' => $meus_ids_de_cliente
                ]
            ],
            'link' => ['Agendamento']
        ]);

        if ( count($dados_convite) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O convite que você está tentando alterar, não existe!'))));
        }

        //verifica se o horário não foi cancelado
        if ( $dados_convite['AgendamentoConvite']['horario_cancelado'] == 'Y' ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O agendamento deste convite foi cancelado!'))));
        }

        //verifica se foi o usuário que convidou ou foi o convidado
        $convidado = false;
        if ( in_array($dados_convite['AgendamentoConvite']['cliente_cliente_id'], $meus_ids_de_cliente) ) {
            $convidado = true;
        }

       //verifica qual o status do convite
       $status = 'aguardando_marcante';
       if ($dados_convite['AgendamentoConvite']['confirmado_usuario'] == 'R' ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O convite foi recusado pelo convidante!'))));
       }
    
       if ($dados_convite['AgendamentoConvite']['confirmado_convidado'] == 'R' ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'O convite foi recusado pelo convidado!'))));
       }

       if ($convidado) {
            if ($acao == 1) {
               $msg = 'O convidado ['.$dados_token['Usuario']['nome'].'] aceitou o convite para o jogo. :)';
               $resposta = 'Y';

            } else {
                $msg = 'O convidado ['.$dados_token['Usuario']['nome'].'] recusou o convite para o jogo. :(';
                $resposta = 'R';
            }

            $dados_salvar = [
                'id' => $dados->invite_id,
                'confirmado_convidado' => $resposta,
            ];

            $salvo = $this->AgendamentoConvite->save($dados_salvar);
            if ( $salvo ) {
                $this->enviaNotificacaoDeAcaoDoConvite($msg, $dados_convite['Agendamento']['cliente_cliente_id'], $dados_convite['Agendamento']['id']);
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Resposta ao convite cadastrada com sucesso!'))));
            } else {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao cadastrar a resposta do convite!'))));
            }

       } else {
            if ($acao == 1) {
                $msg = 'O dono do horário ['.$dados_token['Usuario']['nome'].'] confirmou sua participação no jogo. :)';
                $resposta = 'Y';

            } else {
                $msg = 'O dono do horário ['.$dados_token['Usuario']['nome'].'] informou que o jogo já estava completo. :(';
                $resposta = 'R';
            }

            $dados_salvar = [
                'id' => $dados->invite_id,
                'confirmado_usuario' => $resposta,
            ];

            $salvo = $this->AgendamentoConvite->save($dados_salvar);
            if ( $salvo ) {
                $this->enviaNotificacaoDeAcaoDoConvite($msg, $dados_convite['AgendamentoConvite']['cliente_cliente_id'], $dados_convite['Agendamento']['id']);
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Resposta ao convite cadastrada com sucesso!'))));
            } else {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao cadastrar a resposta do convite!'))));
            }

        }
    
    }

    public function fecharJogo () {
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        //$this->log($dados,'debug');

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->agendamento_id) || $dados->agendamento_id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
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
            'ClienteCliente.usuario_id' => $dados_usuario['Usuario']['id']
        ];

        $dados_agendamento = $this->Agendamento->find('first',[
            'fields' => [
                'Agendamento.id', 
                'Agendamento.horario', 
                'Agendamento.dia_semana', 
                'Agendamento.dia_mes',  
                'Agendamento.horario', 
                'ClienteCliente.*',
                'Cliente.id',
                'Cliente.nome',
                'Usuario.id', 
                'Usuario.nome'
            ],
            'conditions' => $conditions,
            'link' => [
                'ClienteCliente' => ['Usuario'], 'Cliente'
            ]
        ]);
       

        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O agendamento do jogo que voce está tentando exlcuir, não existe!'))));
        }

        $this->loadModel('AgendamentoConvite');
        $convites_nao_confirmados = $this->AgendamentoConvite->getUnconfirmedUsers($dados->agendamento_id, $this->images_path.'/usuarios/', $dados->horario);

        if ( count($convites_nao_confirmados) > 0 ) {
            foreach($convites_nao_confirmados as $key => $convite) {

                $msg = 'O usuário ['.$dados_usuario['Usuario']['nome'].'] informou que o jogo já fechou. :(';
                $resposta = 'R';

                $dados_salvar = [
                    'id' => $convite['AgendamentoConvite']['id'],
                    'confirmado_usuario' => 'R',
                ];
    
                $salvo = $this->AgendamentoConvite->save($dados_salvar);
                if ( $salvo ) {
                    $this->enviaNotificacaoDeAcaoDoConvite($msg, $convite['ClienteCliente']['id'], $convite['AgendamentoConvite']['agendamento_id']);
                    //return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Resposta ao convite cadastrada com sucesso!'))));
                } else {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao cadastrar a resposta do convite!'))));
                }
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Jogo fechado com sucesso!'))));

    }
}