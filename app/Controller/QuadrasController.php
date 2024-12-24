<?php
class QuadrasController extends AppController {

    public function index() {

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

        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteServicoHorario');

        $conditions = [];
        if ( $dados['tipo'] == 'meus' ) {

            if ( !isset($dados_token['Usuario']) ) {
                throw new BadRequestException('Usuario não logado!', 401);
            }

            if ( $dados_token['Usuario']['cliente_id'] == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
            }

            $conditions = array_merge($conditions, [
                'ClienteServico.cliente_id' => $dados_token['Usuario']['cliente_id']
            ]);
        }

        $order_quadras = ['ClienteServico.nome'];

        if ( $dados_token['Usuario']['cliente_id'] == 55 ) {
            $order_quadras = ['ClienteServico.id'];
        }

        $quadras = $this->ClienteServico->find('all',[
            'fields' => [
                'ClienteServico.*'
            ],
            'conditions' => $conditions,
            'order' => $order_quadras,
            'contain' => [
                'ClienteServicoFoto' => [
                    'fields' => [
                        'imagem',
                        'id'
                    ]
                ]
            ]
        ]);
        
        //debug($conditions); die();

        foreach($quadras as $key => $qua){

            $range_valores = $this->ClienteServicoHorario->buscaRangeValores($qua['ClienteServico']['id']);

            if ( !empty($range_valores) ) {
                $quadras[$key]['ClienteServico']['_valor'] = $range_valores[0] === $range_valores[1] ? number_format($range_valores[0], 2, ',', '.') : number_format($range_valores[0], 2, ',', '.') . ' - ' . number_format($range_valores[1], 2, ',', '.');
            }

            $quadras[$key]["ClienteServico"]["_dias_semana"] = $this->ClienteServicoHorario->listaDiasSemana($qua['ClienteServico']['id']);

            if ( count($qua['ClienteServicoFoto']) === 0 ) {
                $quadras[$key]['ClienteServicoFoto'][0]['imagem'] = "https://buzke-images.s3.sa-east-1.amazonaws.com/services/sem_imagem.jpeg";
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $quadras))));

    }

}
