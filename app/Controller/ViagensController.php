<?php

class ViagensController extends AppController {
    
    public $helpers = array('Html', 'Form');	
    public $components = array('RequestHandler');
    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function viagem_ativa() {
        $dados = $this->request->query;
        $dados = json_decode(json_encode($dados), FALSE);

        if ((!isset($dados->token) || $dados->token == "") || (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        $this->loadModel('Viagem');
        $viagem_ativa = $this->Viagem->find('first', array(
            'conditions' => [
                'Viagem.usuario_id' => $dados_usuario['Usuario']['id'],
                'Viagem.ativo' => 'Y',
                'Viagem.is_finalizada' => 'N'
            ],
            'fields' => [
                'Viagem.*',
                'Veiculo.placa'
            ],
            'link' => ['Veiculo']
        ));

        if ( count($viagem_ativa) > 0 ) {
            $viagem_ativa['Viagem']['duracao_dias'] = $this->calculaDatas('d', $viagem_ativa['Viagem']['data_viagem_ini'], date('Y-m-d H:i:s'));
            $viagem_ativa['Viagem']['duracao_horas'] = $this->calculaDatas('h', $viagem_ativa['Viagem']['data_viagem_ini'], date('Y-m-d H:i:s'));

        }
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $viagem_ativa))));
    }

    public function viagem_iniciar() {
        $dados = $this->request->query;
        $dados = json_decode(json_encode($dados), FALSE);

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        $this->loadModel('Viagem');
        $this->loadModel('Veiculo');
        if ( !$this->Viagem->validaIniciarViagem($dados_usuario['Usuario']['id']) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'A viagem não pode ser iniciada, pois há uma viagem não finalizada!'))));
        }

        if (!isset($dados->valor_frete) || $dados->valor_frete == '') {
            throw new BadRequestException('Valor do frete não informado!', 400);
        }
        if (!isset($dados->valor_adiantamento) || $dados->valor_adiantamento == '') {
            $dados->valor_adiantamento = null;
        }
        // if (!isset($dados->viagem->valor_comissao) || $dados->viagem->valor_comissao == '') {
        //     throw new BadRequestException('Valor de comissão não informado!', 400);
        // }
        if (!isset($dados->destino) || $dados->destino == '') {
            throw new BadRequestException('Destino não informado!', 400);
        }
        if (!isset($dados->veiculo_id) || $dados->veiculo_id == '') {
            throw new BadRequestException('Veículo não informado!', 400);
        }
        if (!isset($dados->km) || $dados->km == '') {
            throw new BadRequestException('KM do caminhão não informado!', 400);
        }
       
        $dados_viagem_salvar = array(
            'Viagem' => array(
                'valor_frete' => $dados->valor_frete,
                'valor_adiantamento' => $dados->valor_adiantamento,
                // 'valor_comissao' => $dados->viagem->valor_comissao,
                'destino' => $dados->destino,
                'veiculo_id' => $dados->veiculo_id,
                'usuario_id' => $dados_usuario['Usuario']['id'],
                'data_viagem_ini' => date('Y-m-d H:i:s'),
                'caminhao_km_inicial' => $dados->km
            )
        );

        
        $dados_veiculo_salvar = array(
            'Veiculo' => array(
                'id' => $dados->veiculo_id,
                'km' => $dados->km,
            )
        );

        $this->Viagem->set($dados_viagem_salvar);
        $this->Veiculo->set($dados_viagem_salvar);
        $this->Veiculo->save($dados_veiculo_salvar);
        if ($this->Viagem->save($dados_viagem_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Viagem Iniciada!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }
    
    public function abastecimento() {
        $dados = json_decode($this->request->data['dados']);

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if (!isset($dados->abastecimento->gps_lat)) {
            throw new BadRequestException('Latitude não informada!', 400);
        }
        if (!isset($dados->abastecimento->gps_lng)) {
            throw new BadRequestException('Longitude não informada!', 400);
        }
        if (!isset($dados->abastecimento->posto) || $dados->abastecimento->posto == '') {
            throw new BadRequestException('Posto não informado!', 400);
        }
        if (!isset($dados->abastecimento->valor) || $dados->abastecimento->valor == '') {
            throw new BadRequestException('Valor não informado!', 400);
        }
        if (!isset($dados->abastecimento->litros) || $dados->abastecimento->litros == '') {
            throw new BadRequestException('Litragem não informada!', 400);
        }
        if (!isset($dados->abastecimento->valor_arla) || $dados->abastecimento->valor_arla == '') {
            $dados->abastecimento->valor_arla = 0;
        }
        if (!isset($dados->abastecimento->litros_arla) || $dados->abastecimento->litros_arla == '') {
            $dados->abastecimento->litros_arla = 0;
        }
        if (!isset($dados->abastecimento->km) || $dados->abastecimento->km == '') {
            throw new BadRequestException('KM não informado!', 400);
        }
        /*if (!isset($dados->abastecimento->media_kml) || $dados->abastecimento->media_kml == '') {
            throw new BadRequestException('Média KM/L não informada!', 400);
        }*/
        if (!isset($dados->viagem_id) || $dados->viagem_id == '') {
            throw new BadRequestException('Viagem não informada!', 400);
        }       
       
        $dados_abastecimento_salvar = array(
            'Abastecimento' => array(
                'data_abastecimento' => date('Y-m-d'),
                'gps_lat' => $dados->abastecimento->gps_lat,
                'gps_lng' => $dados->abastecimento->gps_lng,
                'viagem_id' => $dados->viagem_id,
                'posto' => $dados->abastecimento->posto,
                'valor' => $dados->abastecimento->valor,
                'litragem' => $dados->abastecimento->litros,
                'valor_arla' => $dados->abastecimento->valor_arla,
                'litragem_arla' => $dados->abastecimento->litros_arla,
                'km' => $dados->abastecimento->km,
                //'media_kml' => $dados->abastecimento->media_kml,
            )
        );
        if (isset($this->request->params['form']['anexo'])) {
            $dados_abastecimento_salvar['Abastecimento']['anexo'] = $this->request->params['form']['anexo'];
        }

        $this->loadModel('Abastecimento');
        $this->Abastecimento->set($dados_abastecimento_salvar);
        if ($this->Abastecimento->save($dados_abastecimento_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Registro realizado!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function despesa() {
        $dados = json_decode($this->request->data['dados']);

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        if (!isset($dados->despesa->gps_lat)) {
            throw new BadRequestException('Latitude não informada!', 400);
        }
        if (!isset($dados->despesa->gps_lng)) {
            throw new BadRequestException('Longitude não informada!', 400);
        }
        if (!isset($dados->despesa->titulo) || $dados->despesa->titulo == '') {
            throw new BadRequestException('Titúlo não informado!', 400);
        }
        if (!isset($dados->despesa->valor) || $dados->despesa->valor == '') {
            throw new BadRequestException('Valor não informado!', 400);
        }
        /*if (!isset($dados->despesa->descricao) || $dados->despesa->descricao == '') {
            throw new BadRequestException('Descrição não informada!', 400);
        }*/

        if ( isset($dados->despesa->isManutencao) && $dados->despesa->isManutencao ){
            if (!isset($dados->despesa->km) || $dados->despesa->km == '') {
                throw new BadRequestException('KM não informado!', 400);
            }
            if (isset($this->request->params['form']['anexo'])) {
                $dados_despesa_salvar['Despesa']['anexo'] = $this->request->params['form']['anexo'];
            }

            $dados_manutencao_salvar = array(
                'Manutencao' => array(
                    'data_manutencao' => date('Y-m-d'),
                    'gps_lat' => $dados->despesa->gps_lat,
                    'gps_lng' => $dados->despesa->gps_lng,
                    'viagem_id' => $dados->viagem_id,
                    'titulo' => $dados->despesa->titulo,
                    'valor' => $dados->despesa->valor,
                    'km' => $dados->despesa->km,
                    //'descricao' => $dados->despesa->descricao,
                )
            );

            if (isset($this->request->params['form']['anexo'])) {
                $dados_manutencao_salvar['Manutencao']['anexo'] = $this->request->params['form']['anexo'];
            }

            $this->loadModel('Manutencao');
            $this->Manutencao->set($dados_manutencao_salvar);
            if ($this->Manutencao->save($dados_manutencao_salvar)) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Registro realizado!'))));
            } else {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
            }
        }
    
        $dados_despesa_salvar = array(
            'Despesa' => array(
                'data_despesa' => date('Y-m-d'),
                'gps_lat' => $dados->despesa->gps_lat,
                'gps_lng' => $dados->despesa->gps_lng,
                'viagem_id' => $dados->viagem_id,
                'titulo' => $dados->despesa->titulo,
                'valor' => $dados->despesa->valor,
                //'descricao' => $dados->despesa->descricao,
            )
        );

        if (isset($this->request->params['form']['anexo'])) {
            $dados_despesa_salvar['Despesa']['anexo'] = $this->request->params['form']['anexo'];
        }

        $this->loadModel('Despesa');
        $this->Despesa->set($dados_despesa_salvar);
        if ($this->Despesa->save($dados_despesa_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Registro realizado!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }
    
    public function manutencao() {
        $dados = json_decode($this->request->data['dados']);

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if (!isset($dados->manutencao->gps_lat)) {
            throw new BadRequestException('Latitude não informada!', 400);
        }
        if (!isset($dados->manutencao->gps_lng)) {
            throw new BadRequestException('Longitude não informada!', 400);
        }

        if (!isset($dados->manutencao->titulo) || $dados->manutencao->titulo == '') {
            throw new BadRequestException('Titúlo não informado!', 400);
        }
        /*if (!isset($dados->manutencao->local) || $dados->manutencao->local == '') {
            throw new BadRequestException('Local não informado!', 400);
        }*/
        if (!isset($dados->manutencao->valor) || $dados->manutencao->valor == '') {
            throw new BadRequestException('Valor não informado!', 400);
        }
        if (!isset($dados->manutencao->km) || $dados->manutencao->km == '') {
            throw new BadRequestException('KM não informado!', 400);
        }
        if (!isset($dados->manutencao->descricao) || $dados->manutencao->descricao == '') {
            throw new BadRequestException('Descrição não informado!', 400);
        }
        if (!isset($dados->manutencao->pagamento) || $dados->manutencao->pagamento == '') {
            throw new BadRequestException('Pagamento não informado!', 400);
        }

        $dados_manutencao_salvar = ['Manutencao' => []];
        if (isset($this->request->params['form']['anexo'])) {
            $dados_manutencao_salvar['Manutencao']['anexo'] = $this->request->params['form']['anexo'];
        }

        $dados_manutencao_salvar['Manutencao'] = array_merge(
            $dados_manutencao_salvar['Manutencao'],
            [
                'data_manutencao' => date('Y-m-d'),
                'gps_lat' => $dados->manutencao->gps_lat,
                'gps_lng' => $dados->manutencao->gps_lng,
                'viagem_id' => $dados->viagem_id,
                'titulo' => $dados->manutencao->titulo,
                //'local' => $dados->manutencao->local,
                'valor' => $dados->manutencao->valor,
                'km' => $dados->manutencao->km,
                'descricao' => $dados->manutencao->descricao,
                'pagamento' => $dados->manutencao->pagamento,                
            ]
        );
       
        $this->log($dados_manutencao_salvar,'debug');

        $this->loadModel('Manutencao');
        $this->Manutencao->set($dados_manutencao_salvar);
        if ($this->Manutencao->save($dados_manutencao_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Registro realizado!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function carregamento_descarregamento() {
        $dados = json_decode($this->request->data['dados']);

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if (!isset($dados->carga_descarga->gps_lat)) {
            throw new BadRequestException('Latitude não informada!', 400);
        }
        if (!isset($dados->carga_descarga->gps_lng)) {
            throw new BadRequestException('Longitude não informada!', 400);
        }/*
        if (!isset($dados->carga_descarga->titulo) || $dados->carga_descarga->titulo == '') {
            throw new BadRequestException('Titúlo não informado!', 400);
        }*/
        if (!isset($dados->carga_descarga->cidade) || $dados->carga_descarga->cidade == '') {
            throw new BadRequestException('Cidade não informada!', 400);
        }
        if (!isset($dados->carga_descarga->descricao) || $dados->carga_descarga->descricao == '') {
            throw new BadRequestException('Descrição não informado!', 400);
        }
        if (!isset($dados->carga_descarga->titulo) || $dados->carga_descarga->titulo == '') {
            throw new BadRequestException('Tipo não informado!', 400);
        }

        $is_carregamento = true;
        if ( $dados->carga_descarga->titulo == 2 )
            $is_carregamento = false;

        $dados_carga_descarga_salvar = array(
            'CargaDescarga' => array(
                'data_cd' => date('Y-m-d'),
                'gps_lat' => $dados->carga_descarga->gps_lat,
                'gps_lng' => $dados->carga_descarga->gps_lng,
                'viagem_id' => $dados->viagem_id,
                'titulo' => $dados->carga_descarga->titulo,
                'cidade' => $dados->carga_descarga->cidade,
                'descricao' => $dados->carga_descarga->descricao,
                'is_carregamento' => $is_carregamento,
            )
        );

        $this->loadModel('CargaDescarga');
        $this->CargaDescarga->set($dados_carga_descarga_salvar);
        if ($this->CargaDescarga->save($dados_carga_descarga_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Registro realizado!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function historico_viagem() {
        $dados = $this->request->query;
        $dados = json_decode(json_encode($dados), FALSE);

        
        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $usuario_token = $dados->token;
        $usuario_phone = $dados->phone;

        $dados_usuario = $this->verificaValidadeToken($usuario_token, $usuario_phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        $this->loadModel('Viagem');
        $dados = $this->Viagem->historicoViagensByUserId($dados_usuario['Usuario']['id']);

        if ($dados === false) {
            throw new BadRequestException('Erro ao buscar viagens!', 400);
        }

        if (count($dados) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Nenhuma viagem encontrada!'))));
        }

        $this->loadModel('Abastecimento');
        $this->loadModel('Despesa');
        $this->loadModel('Manutencao');
        $dados_viagem = array();
        $x=0;
        foreach($dados as $viagem) {
            $aba = $this->Abastecimento->calculoValorTotalAbastecimentoByViagemId($viagem['Viagem']['id']);
            $des = $this->Despesa->calculoValorTotalDespesaByViagemId($viagem['Viagem']['id']);
            $man = $this->Manutencao->calculoValorTotalManutencaoByViagemId($viagem['Viagem']['id']);

            $valor_frete = (float) $viagem['Viagem']['valor_frete'];
            $valor_adiantamento = (float) $viagem['Viagem']['valor_adiantamento'];
            $total_despesas = $aba + $des + $man;

            $duracao_dias = $this->calculaDatas('d', $viagem['Viagem']['data_viagem_ini'], date('Y-m-d H:i:s'));
            $duracao_horas = $this->calculaDatas('h', $viagem['Viagem']['data_viagem_ini'], date('Y-m-d H:i:s'));

            $dados_viagem[$x]['Viagem']['id'] = $viagem['Viagem']['id'];
            $dados_viagem[$x]['Viagem']['destino'] = $viagem['Viagem']['destino'];
            $dados_viagem[$x]['Viagem']['abastecimentos'] = $aba;
            $dados_viagem[$x]['Viagem']['valor_frete'] = number_format($valor_frete, 2, ',', '.');
            $dados_viagem[$x]['Viagem']['valor_adiantamento'] = number_format($valor_adiantamento, 2, ',', '.');
            $dados_viagem[$x]['Viagem']['data_viagem'] = $this->dateHourEnBr($viagem['Viagem']['data_viagem_ini'], true, false);
            $dados_viagem[$x]['Viagem']['data_fim_viagem'] = $this->dateHourEnBr($viagem['Viagem']['data_viagem_fim'], true, false);
            $dados_viagem[$x]['Viagem']['despesas'] = number_format($total_despesas, 2, ',', '.');
            $dados_viagem[$x]['Viagem']['saldo_viagem'] = number_format(($valor_frete - $total_despesas), 2, ',', '.');
            $dados_viagem[$x]['Viagem']['_comissao'] = number_format(($valor_frete * 12) / 100, 2, ',', '.');
            $dados_viagem[$x]['Viagem']['km_l'] = $this->calcula_km_l($dados_viagem[$x]['Viagem']['id']);
            $dados_viagem[$x]['Viagem']['duracao'] = $duracao_dias.' dias '.$duracao_horas;
            
            
            $x++;
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_viagem))));

    }

    public function viagem_finalizar() {
        $dados = $this->request->query;
        $dados = json_decode(json_encode($dados), FALSE);

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if (!isset($dados->viagem_id) || $dados->viagem_id == '') {
            throw new BadRequestException('Viagem não informada!', 400);
        }

        if (!isset($dados->km) || $dados->km == '') {
            throw new BadRequestException('KM não informada!', 400);
        }

        $this->loadModel('Viagem');
        $this->loadModel('Veiculo');

        $dados_viagem = $this->Viagem->find('first',[
            'conditions' => [
                'Viagem.id' => $dados->viagem_id,
                'Viagem.usuario_id' => $dados_usuario['Usuario']['id']
            ],
            'link' => []
        ]);

        if ( count($dados_viagem) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos os dados da viagem!'))));
        }

        $dados_veiculo = $this->Veiculo->find('first',[
            'conditions' => [
                'Veiculo.id' => $dados_viagem['Viagem']['veiculo_id']
            ],            
            'link' => []
        ]); 

        if ( count($dados_veiculo) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos os dados do veículo!'))));
        }
    
        $dados_viagem_salvar = array(
            'Viagem' => array(
                'id' => $dados->viagem_id,
                'data_viagem_fim' => date('Y-m-d H:i:s'),
                'is_finalizada' => 'Y',
                'caminhao_km_final' => $dados->km,
            )
        );

        $dados_veiculo_salvar = array(
            'Veiculo' => array(
                'id' => $dados_viagem['Viagem']['veiculo_id'],
                'km' => $dados->km,
            )
        );

        $this->Viagem->set($dados_viagem_salvar);
        $this->Veiculo->set($dados_veiculo_salvar);
        if ($this->Viagem->save($dados_viagem_salvar) && $this->Veiculo->save($dados_veiculo_salvar)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Viagem Finalizada!'))));
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }
    }

    public function historico_manutencao() {
		$dados = json_decode($this->request->data['dados']);
        
        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $usuario_token = $dados->token;
        $usuario_phone = $dados->phone;

        $dados_usuario = $this->verificaValidadeToken($usuario_token, $usuario_phone);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Manutencao');
        $dados = $this->Manutencao->historicoManutencoesByUserId($dados_usuario['Usuario']['id']);

        $dados_manutencoes = array();
        $x=0;
        foreach($dados as $manutencao) {
            $dados_manutencoes[$x]['Manutencao']['id'] = $manutencao['Manutencao']['id'];
            $dados_manutencoes[$x]['Manutencao']['titulo'] = $manutencao['Manutencao']['titulo'];
            $dados_manutencoes[$x]['Manutencao']['local'] = $manutencao['Manutencao']['local'];
            $dados_manutencoes[$x]['Manutencao']['km'] = str_replace('.', ',', $manutencao['Manutencao']['km']);
            $dados_manutencoes[$x]['Manutencao']['valor'] = 'R$ ' . number_format($manutencao['Manutencao']['valor'], 2, ',', '.');
            $x++;
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_manutencoes))));

    }

    public function viagem_info() {
        $dados = $this->request->query;
        $dados = json_decode(json_encode($dados), FALSE);

        if ((!isset($dados->token) || $dados->token == "") ||  (!isset($dados->phone) || $dados->phone == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $usuario_token = $dados->token;
        $usuario_phone = $dados->phone;
        $dados_usuario = $this->verificaValidadeToken($usuario_token, $usuario_phone);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ((!isset($dados->viagem_id) || $dados->viagem_id == "" || !is_numeric($dados->viagem_id))) {
            throw new BadRequestException('Viagem não informada!', 400);
        }

        $viagem_id = $dados->viagem_id;
		$this->loadModel('Viagem');
        $dados = $this->Viagem->viagensInformacoesById($viagem_id);
        if (!$dados) {
            throw new BadRequestException('Dados não encontrados!', 400);
        }

        unset($dados['Viagem']['created']);
        unset($dados['Viagem']['modified']);
        unset($dados['Viagem']['usuario_id']);
        unset($dados['Viagem']['veiculo_id']);

		$this->loadModel('Abastecimento');
		$this->loadModel('Despesa');
		$this->loadModel('Manutencao');
        $aba = $this->Abastecimento->calculoValorTotalAbastecimentoByViagemId($dados['Viagem']['id']);
        $des = $this->Despesa->calculoValorTotalDespesaByViagemId($dados['Viagem']['id']);
        $man = $this->Manutencao->calculoValorTotalManutencaoByViagemId($dados['Viagem']['id']);

        $valor_frete = (float) $dados['Viagem']['valor_frete'];
        $total_despesas = $aba + $des + $man;
        $dados['Viagem']['valor_adiantamento'] = (float) $dados['Viagem']['valor_adiantamento'];

        $dados['Viagem']['total_abastecimentos'] = (float) $aba;
        $dados['Viagem']['total_despesas'] = (float) $des;
        $dados['Viagem']['total_manutencoes'] = (float) $man;
        
        $dados['Viagem']['saldo_viagem'] =(float)  number_format(($valor_frete - $total_despesas), 2, ',', '.');
        $dados['Viagem']['valor_frete'] = (float) $dados['Viagem']['valor_frete'];
        $dados['Viagem']['valor_comissao'] = (float) $dados['Viagem']['valor_comissao'];
        
        $dados['Viagem']['duracao_dias'] = $this->calculaDatas('d', $dados['Viagem']['data_viagem_ini'], date('Y-m-d H:i:s'));
        $dados['Viagem']['duracao_horas'] = $this->calculaDatas('h', $dados['Viagem']['data_viagem_ini'], date('Y-m-d H:i:s'));

        $dados['Viagem']['data_viagem_ini'] = $this->dateHourEnBr($dados['Viagem']['data_viagem_ini'], true, false);
        $dados['Viagem']['data_viagem_fim'] = $this->dateHourEnBr($dados['Viagem']['data_viagem_fim'], true, false);

        for ($x = 0; $x < count($dados['Abastecimento']); $x++) {
            unset($dados['Abastecimento'][$x]['created']);
            unset($dados['Abastecimento'][$x]['modified']);
            $dados['Abastecimento'][$x]['data_abastecimento'] = $this->dateHourEnBr($dados['Abastecimento'][$x]['data_abastecimento'], true, false);
            $dados['Abastecimento'][$x]['titulo'] = $dados['Abastecimento'][$x]['data_abastecimento'] . ' - ' . $dados['Abastecimento'][$x]['posto'];
            $dados['Abastecimento'][$x]['litragem'] = (float) $dados['Abastecimento'][$x]['litragem'];
            $dados['Abastecimento'][$x]['valor'] = (float) $dados['Abastecimento'][$x]['valor'] + (float) $dados['Abastecimento'][$x]['valor_arla'];
            if ( $dados['Abastecimento'][$x]['anexo'] != '' && $dados['Abastecimento'][$x]['anexo'] != null ) {
                $dados['Abastecimento'][$x]['anexo'] = $this->files_path.'/'.$dados['Abastecimento'][$x]['anexo'];
            }
        }

        for ($x = 0; $x < count($dados['CargaDescarga']); $x++) {
            unset($dados['CargaDescarga'][$x]['created']);
            unset($dados['CargaDescarga'][$x]['modified']);
            $dados['CargaDescarga'][$x]['data_cd'] = $this->dateHourEnBr($dados['CargaDescarga'][$x]['data_cd'], true, false);
        }

        for ($x = 0; $x < count($dados['Despesa']); $x++) {
            unset($dados['Despesa'][$x]['created']);
            unset($dados['Despesa'][$x]['modified']);
            $dados['Despesa'][$x]['data_despesa'] = $this->dateHourEnBr($dados['Despesa'][$x]['data_despesa'], true, false);
            $dados['Despesa'][$x]['titulo_despesa'] = $dados['Despesa'][$x]['data_despesa'] . ' - ' . $dados['Despesa'][$x]['titulo'];
            $dados['Despesa'][$x]['valor'] = (float) $dados['Despesa'][$x]['valor'];
            if ( $dados['Despesa'][$x]['anexo'] != '' && $dados['Despesa'][$x]['anexo'] != null ) {
                $dados['Despesa'][$x]['anexo'] = $this->files_path.'/'.$dados['Despesa'][$x]['anexo'];
            }
        }

        for ($x = 0; $x < count($dados['Manutencao']); $x++) {
            unset($dados['Manutencao'][$x]['created']);
            unset($dados['Manutencao'][$x]['modified']);
            $dados['Manutencao'][$x]['data_manutencao'] = $this->dateHourEnBr($dados['Manutencao'][$x]['data_manutencao'], true, false);
            $dados['Manutencao'][$x]['veiculo_placa'] = $dados['Veiculo']['placa'];
            if ( $dados['Manutencao'][$x]['anexo'] != '' && $dados['Manutencao'][$x]['anexo'] != null ) {
                $dados['Manutencao'][$x]['anexo'] = $this->files_path.'/'.$dados['Manutencao'][$x]['anexo'];
            }
        }

        
        $dados['Viagem']['km_l'] = $this->calcula_km_l($dados['Viagem']['id']);

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));

    }
	
	public function veiculos_user() {
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

		$this->loadModel('Veiculo');
        $dados = $this->Veiculo->find('all', array(
			'conditions' => array(
                'Veiculo.usuario_id' => $dados_usuario['Usuario']['id'],
                'Veiculo.tipo_id' => 1
            ),
            'link' => []
		));
		
        if (!$dados) {
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'dados' => 'Nenhum veículo encontrado!'))));
		} else {
            $list = [];
            foreach( $dados as $key => $dado ) {
                $list[] = ['label' => $dado['Veiculo']['placa'], 'value' => $dado['Veiculo']['id']];
            }
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados, 'list' => $list))));
		}
    }

    private function calcula_km_l($viagem_id) {
        return 'DUV';
    }


}