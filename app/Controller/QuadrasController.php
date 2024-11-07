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

            $quadras[$key]['ClienteServico']['_valor'] = number_format($qua['ClienteServico']['valor'],2,',','.');
            $quadras[$key]["ClienteServico"]["_dias_semana"] = $this->ClienteServicoHorario->listaDiasSemana($qua['ClienteServico']['id']);

            if ( count($qua['ClienteServicoFoto']) > 0 ) {
                foreach( $qua['ClienteServicoFoto'] as $key_imagem => $imagem){
                    $quadras[$key]['ClienteServicoFoto'][$key_imagem]['imagem'] = $this->images_path . "/servicos/" . $imagem['imagem'];
                }
            } else {
                $quadras[$key]['ClienteServicoFoto'][0]['imagem'] = $this->images_path . "/servicos/sem_imagem.jpeg";
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $quadras))));

    }

}
