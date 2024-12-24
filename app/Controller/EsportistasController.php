<?php
class EsportistasController extends AppController {

    public $components = array('RequestHandler');

    public function busca_perfil() {
        
        $dados = $this->request->query;
        
        if ((!isset($dados['token']) || $dados['token'] == "") ||  (!isset($dados['email']) || $dados['email'] == "")) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        
        if ( empty($dados['tipo']) ) {
            throw new BadRequestException('Tipo não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $tipo = $dados['tipo'];

        $dados_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados = [];
        if ( $tipo === 'padel' ) {
            $dados = $this->buscaDadosPadel(!empty($dados['usuario_id']) ? $dados['usuario_id'] : $dados_usuario['Usuario']['id']);
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados))));

    }

    private function buscaDadosPadel($usuario_id = null) {

        $this->loadModel('UsuarioDadosPadel');
        $this->loadModel('UsuarioPadelCategoria');

        $dados = $this->UsuarioDadosPadel->findByUserId($usuario_id);

        $dados_retornar = $dados['UsuarioDadosPadel'];
        $dados_retornar['sexo'] = $dados['ClienteCliente']['sexo'];
        $dados_retornar['data_nascimento'] = $dados['ClienteCliente']['data_nascimento'];
        $dados_retornar['nome'] = $dados['Usuario']['nome'];
        $categorias = $this->UsuarioPadelCategoria->findByUserId($usuario_id);

        $categorias_retornar = [];

        foreach( $categorias as $key => $categoria ) {
            $categorias_retornar[] = (int)$categoria['PadelCategoria']['id'];
        }

        $dados_retornar['categorias'] = $categorias_retornar;
     

        return $dados_retornar;
    }

    public function atualiza_perfil() {
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        }else {
            $dados = json_decode($dados);
        }

        if ( empty($dados->token) ||  empty($dados->email) ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
    
        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
    
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( empty($dados->tipo) ) {
            throw new BadRequestException('Tipo não informado', 400);
        }

        $tipo = $dados->tipo;

        if ( $tipo === 'padel' ) {
            return $this->atualizaDadosPadel($dados_usuario['Usuario']['id'], $dados);
        }

    }

    private function atualizaDadosPadel( $usuario_id, $dados ) {

        $this->loadModel('UsuarioDadosPadel');
        $this->loadModel('ClienteCliente');
        $this->loadModel('UsuarioPadelCategoria');
    
        $dados_padelista_atualizar = [];
        $dados_cliente_cliente_atualizar = [];

        $categorias = $dados->categorias;
        
        if ( empty($categorias) && empty($dados->img)  &&  empty($dados->img_capa) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione ao menos uma categoria antes de clicar em "Atualizar Dados"'))));
        }

        if ( !empty($categorias) && count($categorias) > 2 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione no máximo 2 categorias'))));
        }

        if ( !empty($dados->lado) ) {
            $dados_padelista_atualizar['lado'] = $dados->lado;
        }

        if ( !empty($dados->img) ) {
            $dados_padelista_atualizar['img'] = $dados->img;
        }

        if ( !empty($dados->img_capa) ) {
            $dados_padelista_atualizar['img_capa'] = $dados->img_capa;
        }

        if ( !empty($dados->sexo) ) {
            $dados_cliente_cliente_atualizar['ClienteCliente.sexo'] = "'".$dados->sexo."'";
        }

        if ( !empty($dados->data_nascimento) ) {
            $dados_cliente_cliente_atualizar['ClienteCliente.data_nascimento'] = $dados->data_nascimento;
        }

        $dados_padelista = $this->UsuarioDadosPadel->find('first',[
            'fields' => [
                'id',
                'usuario_id'
            ],
            'conditions' => [
                'UsuarioDadosPadel.usuario_id' => $usuario_id
            ],
            'link' => []
        ]);
 
        // Atualiza os dados de padelista
        if ( !empty($dados_padelista_atualizar) ) {

            if ( !empty($dados_padelista) ) {
                $dados_padelista_atualizar['id'] = $dados_padelista['UsuarioDadosPadel']['id'];
            } else {
                $this->UsuarioDadosPadel->create();
                $dados_padelista_atualizar['usuario_id'] = $usuario_id;
            }
    
            $dados_padelista = $this->UsuarioDadosPadel->save($dados_padelista_atualizar);

            if ( !$dados_padelista ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
            }

        }


        // Atualiza os dados da clientes_clientes
        if ( !empty($dados_cliente_cliente_atualizar) ) {
            $this->ClienteCliente->updateAll($dados_cliente_cliente_atualizar, ['usuario_id' => $usuario_id]);
        }


        // Atualiza as categorias do usuário
        if ( !empty($categorias) ) {
            // Remove as categorias que não vieram no post
            $this->UsuarioPadelCategoria->deleteAll([
                'usuario_id' => $usuario_id,
                'NOT' => [
                    'categoria_id' => $categorias
                ]
            ]);

            // Salva as categorias novas
            foreach( $categorias as $key => $categoria ) {

                $check_categoria = $this->UsuarioPadelCategoria->find('count',[
                    'conditions' => [
                        'usuario_id' => $usuario_id,
                        'categoria_id' => $categoria
                    ],
                    'link' => []
                ]);

                if ( $check_categoria === 0 ) {
                    $dados_salvar = [
                        'usuario_id' => $usuario_id,
                        'categoria_id' => $categoria                        
                    ];

                    $this->UsuarioPadelCategoria->create();

                    if ( $this->UsuarioPadelCategoria->save($dados_salvar) ) {
                        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
                    }
                }

            }
        }
    
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Seus dados de padelista foram atualizados!'))));
        
    }
}