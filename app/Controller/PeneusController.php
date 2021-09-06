<?php

class PneusController extends AppController {
    
    public $helpers = array('Html', 'Form');	
    public $components = array('RequestHandler');
    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index() {
        $dados = $this->request->query;
        $dados = json_decode(json_encode($dados), FALSE);

        if ((!isset($dados->token) || $dados->token == "") || (!isset($dados->phone) || $dados->phone == "") || (!isset($dados->placa) || $dados->placa == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->phone);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Veiculo');
        $dados_veiculo = $this->Veiculo->find('first',[
            'conditions' => [
                'Veiculo.placa' => $dados->placa,
                'Veiculo.usuario_id' => $dados_usuario['Usuario']['id']
            ]
        ]);
        
        $this->loadModel('VeiculoEixoPeneu');
        $dados_veiculo_eixo = $this->VeiculoEixoPeneu->find('all',[
            'conditions' => [
                'VeiculoEixoPeneu.veiculo_id' => $dados_veiculo['Veiculo']['id']
            ]
        ]);

        $this->loadModel('Abastecimento');
        $ultimo_abastecimento = $this->Abastecimento->find('first',[
            'conditions' => [
                'Veiculo.id' => $dados_veiculo['Veiculo']['id']
            ],
            'link' => ['Viagem' => 'Veiculo'],
            'order' => [
                'Abastecimento.data_abastecimento DESC'
            ]
        ]);

        $this->loadModel('PeneuHistorico');

        $dados_retorno = ['n_eixos' => count($dados_veiculo_eixo)];
        foreach($dados_veiculo_eixo as $key => $veiculo_eixo) {

            for ( $i = 0; $i < $veiculo_eixo['VeiculoEixoPeneu']['pneus']; $i++ ) {
                
                $dados_peneu = $this->PeneuHistorico->find('first',[
                    'fields' => [
                        'PeneuHistorico.data_pneu',
                        'PeneuHistorico.km',
                        'PeneuHistorico.eixo',
                        'PeneuHistorico.posicao',
                        'Peneu.descricao',
                        'Peneu.marca',
                    ],
                    'conditions' => [
                        'Peneu.veiculo_id' => $dados_veiculo['Veiculo']['id'],
                        'PeneuHistorico.eixo' => $key,
                        'PeneuHistorico.posicao' => $i,
                        'not' => [
                            'PeneuHistorico.eixo' => -1
                        ]
                    ],
                    'order' => ['PeneuHistorico.id DESC'],
                    'link' => ['Peneu']
                ]);
                if ( count($dados_peneu) > 0 && count($ultimo_abastecimento) > 0 ) {
                    if ($dados_peneu['PeneuHistorico']['data_pneu'] >= $ultimo_abastecimento['Abastecimento']['data_abastecimento']) {
                        $dados_peneu['PeneuHistorico']['km'] = $ultimo_abastecimento['Abastecimento']['km']-$dados_peneu['PeneuHistorico']['km'];
                    }
                    $dados_peneu['PeneuHistorico']['km'] = number_format($dados_peneu['PeneuHistorico']['km'],0,',','.');
                    $dados_retorno['dados_pneus'][$key][$i] = $dados_peneu;
                } else {
                    $dados_peneu['PeneuHistorico']['km'] = "-";
                    $dados_retorno['dados_pneus'][$key][$i] = $dados_peneu;
                }
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retorno))));
    }

}