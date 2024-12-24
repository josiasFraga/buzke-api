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
            throw new BadRequestException('Sexo não informado', 400);
        }
        

        $tipo = $dados->tipo;

        if ( $tipo === 'padel' ) {
            return $this->atualizaDadosPadel($dados_usuario['Usuario']['id'], $dados);
        }

    }

    private function atualizaDadosPadel( $usuario_id, $dados ) {

        $this->loadModel('UsuarioDadosPadel');
    
        $dados_padelista_atualizar = [];
        $dados_cliente_cliente_atualizar = [];

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
            $dados_cliente_cliente_atualizar['sexo'] = $dados->sexo;
        }

        if ( !empty($dados->data_nascimento) ) {
            $dados_cliente_cliente_atualizar['sexo'] = $dados->data_nascimento;
        }

        $dados_padelista = $this->UsuarioDadosPadel->find('first',[
            'fields' => [
                'Usuario.id'
            ],
            'conditions' => [
                'UsuarioDadosPadel.usuario_id' => $usuario_id
            ],
            'link' => []
        ]);
 
        if ( !empty($dados_padelista_atualizar) ) {

            if ( !empty($dados_padelista) ) {
                $dados_padelista_atualizar['id'] = $dados_padelista['UsuarioDadosPadel']['id'];
            } else {
                $this->UsuarioDadosPadel->create();
                $dados_padelista = $this->UsuarioDadosPadel->save($dados_padelista_atualizar);
            }

        }
    

    
        /*

        $categorias = [];
        foreach($dados as $key_dado => $dado) {
    
            if ( strpos($key_dado, 'item_') !== false && $dado == 1 ) {
                list($discart, $categoria_id) = explode('item_', $key_dado);
                $categorias[] = $categoria_id;
            }
        }


        if ( count($categorias) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione ao menos uma categoria antes de clicar em "Atualizar Dados"'))));
        }

        if ( count($categorias) > 2 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Selecione no máximo 2 categorias'))));
        }


        $this->loadModel('UsuarioDadosPadel');
		$dataSource = $this->UsuarioDadosPadel->getDataSource();
		$dataSource->begin();

        $dados_padel = $this->UsuarioDadosPadel->findByUserId($dados_usuario['Usuario']['id']);
        $dados_salvar = [];
        if ( count($dados_padel) > 0 ) {
            $dados_salvar = array_merge(
                $dados_salvar,
                [
                    'id' => $dados_padel['UsuarioDadosPadel']['id']
                ]
            );
        }

        
        $dados_salvar = array_merge(
            $dados_salvar,
            [
                'lado' => $dados->lado,
                'usuario_id' => $dados_usuario['Usuario']['id']
            ]
        );

        $save_padelist_data = $this->UsuarioDadosPadel->save($dados_salvar);

        $this->loadModel('UsuarioPadelCategoria');
		$dataSourcePadelCategoria = $this->UsuarioPadelCategoria->getDataSource();
		$dataSourcePadelCategoria->begin();

        $daodos_categorias = $this->UsuarioPadelCategoria->findByUserId($dados_usuario['Usuario']['id']);
        $dados_salvar_categorias = [];
        if ( count($daodos_categorias) > 0 ) {
           $this->UsuarioPadelCategoria->deleteAll(['UsuarioPadelCategoria.usuario_id' => $dados_usuario['Usuario']['id']]);
        }

        foreach( $categorias as $key => $cat) {
            $dados_salvar_categorias = array_merge(
                $dados_salvar_categorias,
                [[
                    'categoria_id' => $cat,
                    'usuario_id' => $dados_usuario['Usuario']['id']
                ]]
            );
        }
    
        $save_padelist_categories = $this->UsuarioPadelCategoria->saveMany($dados_salvar_categorias);

        
        $this->loadModel('ClienteCliente');
        $dados_como_cliente = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_usuario['Usuario']['id']);
        unset($dados_como_cliente['ClienteCliente']['img']);
        $dados_como_cliente['ClienteCliente']['sexo'] = $dados->sexo;
        
		$dataSourceClienteCliente = $this->ClienteCliente->getDataSource();
		$dataSourceClienteCliente->begin();

        $usuario_cliente_cliente = $this->ClienteCliente->save(
            [
                'id' => $dados_como_cliente['ClienteCliente']['id'],
                'sexo' => $dados->sexo,
            ]
        );


        if ($usuario_cliente_cliente && $save_padelist_categories && $save_padelist_data ) {
            $dataSource->commit();
            $dataSourcePadelCategoria->commit();
            $dataSourceClienteCliente->commit();
            

            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cadastro alterado!', 'padelist_data' => $save_padelist_data, 'padel_categories' => $save_padelist_categories, 'updated_user_sex' => $dados->sexo))));
        } else {
            $dataSource->rollback();
            $dataSourcePadelCategoria->rollback();
            $dataSourceClienteCliente->rollback();
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro em nosso servidor. Por favor, tente mais tarde!'))));
        }*/
    }
}