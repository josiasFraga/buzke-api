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
}