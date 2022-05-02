<?php

class PlanosController extends AppController {

    public function buscaPlanosDisponiveis() {

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

        $this->loadModel('Subcategoria');
        $ids_quadras = $this->Subcategoria->buscaSubcategoriasQuadras(true);

        $isCourt = false;
        $subcategorias = [];
        foreach($dados as $key_dado => $dado) {

            if ( strpos($key_dado, 'item_') !== false ) {
                list($discart, $subcateogria_id) = explode('item_', $key_dado);
                $subcategorias[] = $subcateogria_id;
                if ( in_array($subcateogria_id, $ids_quadras) )
                    $isCourt = true;
            }

        }

        if ( count($subcategorias) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione ao menos uma subcategoria antes de clicar em próximo.'))));
        }

        $this->loadModel('Plano');

    
        $conditions = [
            'Plano.ativo' => 'Y',   
        ];
        
        

        if ($isCourt) {
            $conditions = array_merge($conditions,[
                'Plano.id' => 2
            ]);
        } else {
            $conditions = array_merge($conditions,[
                'Plano.id' => 1
            ]);
        }


        $planos = $this->Plano->find('all',[
            'order' => [
                'Plano.valor'
            ],
            'link' => [],
            'conditions' => $conditions,
        ]);

        if ( count($planos) > 0 ) {
            foreach( $planos as $key => $plano ){
                $planos[$key]['Plano']['valor'] = $planos[$key]['Plano']['valor_promocional'] != null ? $planos[$key]['Plano']['valor_promocional'] : $planos[$key]['Plano']['valor'];
                $planos[$key]['Plano']['valor_br'] = number_format($planos[$key]['Plano']['valor'],2,',','.');

            }
        }
        
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $planos))));

    }

    public function buscaPlanosDisponiveisJaRegistrado() {

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

        $this->loadModel('ClienteSubcategoria');
        $isCourt = $this->ClienteSubcategoria->checkIsCourt($dados_token['Usuario']['cliente_id']);

        $this->loadModel('Plano');

        $conditions = [
            'Plano.ativo' => 'Y',   
        ];

        if ($isCourt) {
            $conditions = array_merge($conditions,[
                'Plano.id' => 2
            ]);
        } else {
            $conditions = array_merge($conditions,[
                'Plano.id' => 1
            ]);
        }

        $planos = $this->Plano->find('all',[
            'order' => [
                'Plano.valor'
            ],
            'link' => [],
            'conditions' => $conditions,
        ]);

        if ( count($planos) > 0 ) {
            foreach( $planos as $key => $plano ){
                $planos[$key]['Plano']['valor_br'] = number_format($planos[$key]['Plano']['valor'],2,',','.');
            }
        }
        
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $planos))));

    }
}