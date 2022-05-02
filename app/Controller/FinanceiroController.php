<?php
App::uses('CakeEmail', 'Network/Email');

class FinanceiroController extends AppController {
	
    public $helpers = array('Html', 'Form');
    public $components = array('RequestHandler');
	

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function cadastraClientesNaoCadastrados() {

        $this->loadModel('Cliente');

        if ( $this->ambiente == 1 ) {
            $conditions = [
                'Cliente.asaas_id' => null,
            ];
        }
        else if ( $this->ambiente == 2 ) {
            $conditions = [
                'Cliente.asaas_homologacao_id' => null
            ];
        }


        $clientes_sem_cadastro_no_asaas = $this->Cliente->find('all',[
            'fields' => ['cliente.*', 'Usuario.nome', 'Usuario.email'],
            'conditions' => $conditions,
            'link' => ['Usuario']
        ]);

        if ( count($clientes_sem_cadastro_no_asaas) > 0 ) {
            foreach ( $clientes_sem_cadastro_no_asaas as $key => $cliente ) {

                $asaas_dados = $this->sendClientToAsaas($cliente);

                if ( !$asaas_dados ) {
                    echo 'Erro ao cadastrar o cliente: ' . $cliente['Cliente']['id']."<br />";
                    continue;
                }

                if ( isset($asaas_dados['errors']) ){
                    echo 'Erro ao cadastrar o cliente: ' . $cliente['Cliente']['id'] . " ".$asaas_dados['errors'][0]['description']."<br />";
                    continue;
                }

                $asaas_id = $asaas_dados['id'];

                $dados_salvar = [
                    'id' => $cliente['Cliente']['id'],
                ];
    
                if ( $this->ambiente == 1 ) {
                    $dados_salvar['asaas_id'] = $asaas_id;
                }                
                else if ( $this->ambiente == 2 ) {
                    $dados_salvar['asaas_homologacao_id'] = $asaas_id;
                }

                if ( !$this->Cliente->save($dados_salvar) ){
                    echo 'Erro ao salvar o cliente: ' . $cliente['Cliente']['id']."<br />";
                    continue;
                }


            }
        }

        
        die();

    }

    private function sendClientToAsaas( $cliente = [] ) {

        if ( count($cliente) == 0 ) {
            return false;
        }

        if ( $this->ambiente == 1 ) {
            $asaas_url = $this->asaas_api_url;
            $asaas_token = $this->asaas_api_token;
        }
        else if ( $this->ambiente == 2 ) {
            $asaas_url = $this->asaas_sandbox_url;
            $asaas_token = $this->asaas_sandbox_token;
        }
        

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $asaas_url .'/api/v3/customers',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER=> 0,
        CURLOPT_SSL_VERIFYHOST=> 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
            "company": "'.$cliente['Cliente']['nome'].'",
            "name": "'.$cliente['Usuario']['nome'].'",
            "email": "'.$cliente['Usuario']['email'].'",
            "phone": "'.preg_replace('/[^0-9]/', '', $cliente['Cliente']['telefone']).'",
            "mobilePhone": "'.preg_replace('/[^0-9]/', '', $cliente['Cliente']['wp']).'",
            "cpfCnpj": "'.preg_replace('/[^0-9]/', '', $cliente['Cliente']['cpf'].$cliente['Cliente']['cnpj']).'",
            "postalCode": "'.$cliente['Cliente']['cep'].'",
            "address": "'.$cliente['Cliente']['endereco'].'",
            "addressNumber": "'.$cliente['Cliente']['endereco_n'].'",
            "complement": "",
            "province": "'.$cliente['Cliente']['bairro'].'",
            "notificationDisabled": false,
        }',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'access_token: '.$asaas_token,
            'Cookie: AWSALBTG=Q/GCosyWu6eOIamlJWdj0vib7AnYwdStuaoqseWJCaMgc3I874kmucXg2u5N4eIT1ixBgoFHUOnSJGFGEzHh5psmE9JpwLwaD8nkuBo051w1+mj2ph25I7GDYRA9O8HOtoyqLjeti+sJwp6s9xzVNdfqfm9RKhqWXXLWX41E05oT; AWSALBTGCORS=Q/GCosyWu6eOIamlJWdj0vib7AnYwdStuaoqseWJCaMgc3I874kmucXg2u5N4eIT1ixBgoFHUOnSJGFGEzHh5psmE9JpwLwaD8nkuBo051w1+mj2ph25I7GDYRA9O8HOtoyqLjeti+sJwp6s9xzVNdfqfm9RKhqWXXLWX41E05oT'
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

    public function buscaMetodosPagamento() {
        

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

        $this->loadModel('MetodoPagamento');

    
        $conditions = [
            'MetodoPagamento.ativo' => 'Y',   
        ];

        $metodos_pagamento = $this->MetodoPagamento->find('all',[
            'order' => [
                'MetodoPagamento.nome'
            ],
            'link' => [],
            'conditions' => $conditions,
        ]);
        
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $metodos_pagamento))));

    }

    public function buscaFaturas() {
        
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

        $dados = $this->getPayments($dados_assinatura['ClienteAssinatura']['external_id']);

        if ( !$dados ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao buscar suas faturas. Por favor, tente mais tarde!'))));
        }

        if ( isset($dados['errors']) ){
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao buscar suas faturas. Por favor, tente mais tarde! '.$dados['errors'][0]['description']))));
        }

        $faturas = $dados['data'];

        $asaas_status = [
            'PENDING' => [
                'paga' => false,
                'vencida' => false,
                'msg_usuario' => 'Aguardando pagamento',
            ],
            'RECEIVED' => [
                'paga' => true,
                'vencida' => false,
                'msg_usuario' => 'Paga'
            ],
            'CONFIRMED' => [
                'paga' => true,
                'vencida' => false,
                'msg_usuario' => 'Paga'
            ],
            'OVERDUE' => [
                'paga' => false,
                'vencida' => true,
                'msg_usuario' => 'Atrasada'
            ],
            'REFUNDED' => [
                'paga' => false,
                'vencida' => true,
                'msg_usuario' => 'Estornada',
            ],
            'RECEIVED_IN_CASH' => [
                'paga' => true,
                'vencida' => false,
                'msg_usuario' => 'Paga em dinheiro',
            ],
            'REFUND_REQUESTED' => [
                'paga' => true,
                'vencida' => false,
                'msg_usuario' => 'Estorno Solicitado',
            ],
            'CHARGEBACK_REQUESTED' => [
                'paga' => false,
                'vencida' => false,
                'msg_usuario' => 'Paga chargeback',
            ],
            'CHARGEBACK_DISPUTE' => [
                'paga' => false,
                'vencida' => false,
                'msg_usuario' => 'Em disputa de chargeback',
            ],
            'AWAITING_RISK_ANALYSIS' => [
                'paga' => false,
                'vencida' => false,
                'msg_usuario' => 'Pagamento em Análise',
            ],
            'DUNNING_REQUESTED' => [
                'paga' => false,
                'vencida' => false,
                'msg_usuario' => 'Em processo de negativação',
            ]
        ];

        if ( count($faturas) > 0 ) {
            foreach($faturas as $key => $fatura){
                $faturas[$key]['_valor'] = 'R$ '.number_format($fatura['value'], 2, ',', '.');
                $faturas[$key]['_vencimento'] = date('d/m/Y',strtotime($fatura['dueDate']));
                $faturas[$key]['_status'] = $asaas_status[$fatura['status']];
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $faturas))));

    }
	
}