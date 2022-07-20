<?php
class ClientesClientesController extends AppController {

    public function cadastrar(){
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = json_decode(json_encode($this->request->data['dados']));

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->nome) || $dados->nome == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $pais = 'Brasil';
        $telefone_ddi = '55';

        if ( isset($dados->pais) && !empty($dados->pais) ) {
            $pais = $dados->pais;
            $telefone_ddi = $this->phone_ddi[$pais];
        }

        $dados_salvar['ClienteCliente']['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
        $dados_salvar['ClienteCliente']['nome'] = $dados->nome;
        $dados_salvar['ClienteCliente']['pais'] = $pais;
        $dados_salvar['ClienteCliente']['telefone_ddi'] = $telefone_ddi;
        
        $dados_salvar['ClienteCliente']['sexo'] = isset($dados->sexo) && $dados->sexo != null ? $dados->sexo : null;
        $dados_salvar['ClienteCliente']['cpf'] = isset($dados->cpf) && $dados->cpf != null ? $dados->cpf : null;
        $dados_salvar['ClienteCliente']['email'] = isset($dados->email_cliente) && $dados->email_cliente != null ? $dados->email_cliente : null;
        $dados_salvar['ClienteCliente']['telefone'] = isset($dados->telefone) && $dados->telefone != null ? $dados->telefone : null;        
        $dados_salvar['ClienteCliente']['bairro'] = isset($dados->bairro) && $dados->bairro != null ? $dados->bairro : null;
        $dados_salvar['ClienteCliente']['endereco'] = isset($dados->endereco) && $dados->endereco != null ? $dados->endereco : null;
        $dados_salvar['ClienteCliente']['endreceo_n'] = isset($dados->n) && $dados->n != null ? $dados->n : null;
        $dados_salvar['ClienteCliente']['cep'] = isset($dados->cep) && $dados->cep != null ? $dados->cep : null;
        $dados_salvar['ClienteCliente']['estado_id'] = isset($dados->uf) && $dados->uf != null ? $dados->uf : null;

        if ( isset($dados->padel) )
            $dados_salvar['ClienteClienteDadosPadel'][]['lado'] = isset($dados->padel->lado) && $dados->padel->lado != null ? $dados->padel->lado : null;

        $this->loadModel('ClienteCliente');

        $verifica_por_nome = $this->ClienteCliente->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->nome);
        //$verifica_por_email = $this->ClienteCliente->buscaPorEmail($dados_usuario['Usuario']['cliente_id'], $dados->email_cliente);

        if ( count($verifica_por_nome) > 0 && (!isset($dados->confirma) || $dados->confirma == 0 ) )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'confirm', 'msg' => 'Já existe um cliente cadastrado com esse nome. Deseja cadastrar mesmo assim?'))));

        if ( isset($dados->email_cliente) && $dados->email_cliente != '') {
            $this->loadModel('Usuario');
            $dados_usuario = $this->Usuario->find('first',[
                'fields' => [
                    'Usuario.id'
                ],
                'conditions' => [
                    'Usuario.email' => $dados->email_cliente,
                    'Usuario.nivel_id' => 3,
                ],
                'link' => []
            ]);
            
            $dados_salvar['ClienteCliente']['usuario_id'] = count($dados_usuario) > 0 ? $dados_usuario['Usuario']['id'] : null;
        }

        if ( isset($dados->localidade) && $dados->localidade != '' ) {
            $this->loadModel('Localidade');
            $dados_salvar['ClienteCliente']['cidade_id'] = $this->Localidade->getByName($dados->localidade);
        }

        if ( isset($dados->dados_padelista) && $dados->dados_padelista == 1 ) {
            foreach($dados as $key_dado => $dado) {
    
                if ( strpos($key_dado, 'item_') !== false ) {
                    list($discart, $subcategoria_id) = explode('item_', $key_dado);
                    $dados_salvar['ClienteClientePadelCategoria'][]['categoria_id'] = $subcategoria_id;
                }
            }

            $dados_salvar['ClienteClienteDadosPadel']['lado'] = $dados->lado;

        }

        if ( !$this->ClienteCliente->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar os dados do cliente'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cliente cadastrado com sucesso!'))));

    }

    public function alterar(){
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = json_decode(json_encode($this->request->data['dados']));

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;


        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->nome) || $dados->nome == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        if ( !isset($dados->id) || $dados->id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');

        $vCliente = $this->ClienteCliente->find('first',[
            'conditions' => [
                'ClienteCliente.id' => $dados->id,
                'ClienteCliente.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($vCliente) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Não encontramos os dados do cliente.'))));
        }

        $pais = 'Brasil';
        $telefone_ddi = '55';

        if ( isset($dados->pais) && !empty($dados->pais) ) {
            $pais = $dados->pais;
            $telefone_ddi = $this->phone_ddi[$pais];
        }

        $dados_salvar['ClienteCliente']['id'] = $dados->id;
        $dados_salvar['ClienteCliente']['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
        $dados_salvar['ClienteCliente']['nome'] = $dados->nome;
        $dados_salvar['ClienteCliente']['sexo'] = isset($dados->sexo) && $dados->sexo != null ? $dados->sexo : null;
        $dados_salvar['ClienteCliente']['bairro'] = isset($dados->bairro) && $dados->bairro != null ? $dados->bairro : null;
        $dados_salvar['ClienteCliente']['cpf'] = isset($dados->cpf) && $dados->cpf != null ? $dados->cpf : null;
        $dados_salvar['ClienteCliente']['email'] = isset($dados->email_cliente) && $dados->email_cliente != null ? $dados->email_cliente : null;
        $dados_salvar['ClienteCliente']['telefone'] = isset($dados->telefone) && $dados->telefone != null ? $dados->telefone : null;
        $dados_salvar['ClienteCliente']['endereco'] = isset($dados->endereco) && $dados->endereco != null ? $dados->endereco : null;
        
        $dados_salvar['ClienteCliente']['pais'] = $pais;
        $dados_salvar['ClienteCliente']['telefone_ddi'] = $telefone_ddi;
        
        $dados_salvar['ClienteCliente']['endreceo_n'] = isset($dados->n) && $dados->n != null ? $dados->n : null;
        $dados_salvar['ClienteCliente']['cep'] = isset($dados->cep) && $dados->cep != null ? $dados->cep : null;
        $dados_salvar['ClienteCliente']['estado_id'] = isset($dados->uf) && $dados->uf != null ? $dados->uf : null;

        if ( $dados_salvar['ClienteCliente']['estado_id'] == null ) {
            unset($dados_salvar['ClienteCliente']['estado_id']);
        }

        if ( isset($dados->padel) )
            $dados_salvar['ClienteClienteDadosPadel'][]['lado'] = isset($dados->padel->lado) && $dados->padel->lado != null ? $dados->padel->lado : null;



        $verifica_por_nome = $this->ClienteCliente->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->nome, $dados->id);
        //$verifica_por_email = $this->ClienteCliente->buscaPorEmail($dados_usuario['Usuario']['cliente_id'], $dados->email_cliente);

        if ( count($verifica_por_nome) > 0 && (!isset($dados->confirma) || $dados->confirma == 0 ) )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'confirm', 'msg' => 'Já existe um cliente cadastrado com esse nome. Deseja cadastrar mesmo assim?'))));

        if ( isset($dados->email_cliente) && $dados->email_cliente != '') {
            $this->loadModel('Usuario');
            $dados_usuario = $this->Usuario->find('first',[
                'fields' => [
                    'Usuario.id'
                ],
                'conditions' => [
                    'Usuario.email' => $dados->email_cliente,
                    'Usuario.nivel_id' => 3,
                ],
                'link' => []
            ]);
            
            $dados_salvar['ClienteCliente']['usuario_id'] = count($dados_usuario) > 0 ? $dados_usuario['Usuario']['id'] : null;
        }

        if ( isset($dados->localidade) && $dados->localidade != '' ) {
            $this->loadModel('Localidade');
            $dados_salvar['ClienteCliente']['cidade_id'] = $this->Localidade->getByName($dados->localidade);
        }


        $this->loadModel('ClienteClienteDadosPadel');
        $this->ClienteClienteDadosPadel->deleteAll(['ClienteClienteDadosPadel.cliente_cliente_id' => $dados->id]);
        if ( isset($dados->dados_padelista) && $dados->dados_padelista == 1 ) {

            foreach($dados as $key_dado => $dado) {
    
                if ( strpos($key_dado, 'item_') !== false ) {
                    if ( $dado == 1){
                        list($discart, $subcategoria_id) = explode('item_', $key_dado);
                        $dados_salvar['ClienteClientePadelCategoria'][]['categoria_id'] = $subcategoria_id;
                    }
                }
            }

            $dados_salvar['ClienteClienteDadosPadel']['lado'] = $dados->lado;
            

        }

        $this->loadModel('ClienteClientePadelCategoria');
        $this->ClienteClientePadelCategoria->deleteAll(['ClienteClientePadelCategoria.cliente_cliente_id' => $dados->id]);

        if ( !$this->ClienteCliente->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao salvar os dados do cliente'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cliente cadastrado com sucesso!'))));

    }

    public function excluir(){
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = json_decode(json_encode($this->request->data['dados']));

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        $this->log($dados, 'debug');

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->id) || $dados->id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('ClienteCliente');
        $dados_cliente = $this->ClienteCliente->find('first',[
            'conditions' => [
                'ClienteCliente.id' => $dados->id,
                'ClienteCliente.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_cliente) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O cliente que você está tentando exlcuir, não existe!'))));
        }

        if ( !$this->ClienteCliente->deleteAll(['ClienteCliente.id' => $dados->id]) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar exluir o cliente. Por favor, tente mais tarde!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Cliente excluído com sucesso!'))));

    }
}