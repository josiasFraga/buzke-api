<?php
class ComandasController extends AppController {

    public function index()
    {

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

        $conditions = [];
        if ( isset($dados['searchText']) && $dados['searchText'] != '' ) {
            $searchText = $dados['searchText'];
            $conditions = array_merge($conditions, [
                'or' => [
                    ['Comanda.descricao LIKE' => "%".$searchText."%"]
                ]
            ]);
        }

        if ( isset($dados['descricao']) && $dados['descricao'] != "" ) {
            $conditions = array_merge($conditions, [
                'Comanda.descricao' => $dados['descricao']              
            ]);
        }


        $this->loadModel('Comanda');
        $data = $this->Comanda->listar($dados_token['Usuario']['cliente_id'], $conditions);

        // Define o tipo de resposta como JSON
        $this->autoRender = false;
        $this->response->type('json');

        $response = array(
            'status' => 'ok',
            'data' => $data
        );

        // Converte os dados em formato JSON
        $json = json_encode($response);

        // Retorna a resposta JSON
        $this->response->body($json);
    }

    public function cadastrar()
    {
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

        if ( !isset($dados->descricao) || $dados->descricao == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $dados_salvar['Comanda']['cliente_id'] = $dados_usuario['Usuario']['cliente_id'];
        $dados_salvar['Comanda']['descricao'] = $dados->descricao;

        $this->loadModel('Comanda');

        $verifica_por_nome = $this->Comanda->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->descricao);

        if ( count($verifica_por_nome) > 0 && (!isset($dados->confirma) || $dados->confirma == 0 ) )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'waning', 'message' => 'Já existe uma comanda cadastrada com esse nome.'))));

        if ( !$this->Comanda->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao salvar os dados da comanda'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Comanda cadastrada com sucesso!'))));

    }

    public function alterar()
    {
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

        if ( !isset($dados->descricao) || $dados->descricao == "" ) {
            throw new BadRequestException('Nome não informado!', 401);
        }

        if ( !isset($dados->id) || $dados->id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Comanda');

        $vComanda = $this->Comanda->find('first',[
            'conditions' => [
                'Comanda.id' => $dados->id,
                'Comanda.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($vComanda) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Não encontramos os dados da comanda.'))));
        }

        $dados_salvar['Comanda']['id'] = $dados->id;
        $dados_salvar['Comanda']['descricao'] = $dados->descricao;

        $verifica_por_nome = $this->Comanda->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->descricao, $dados->id);

        if ( count($verifica_por_nome) > 0 && (!isset($dados->confirma) || $dados->confirma == 0 ) )
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'message' => 'Já existe uma comanda cadastrada com esse nome.'))));


        if ( !$this->Comanda->saveAssociated($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao salvar os dados da comanda'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Comanda alterada com sucesso!'))));

    }

    public function excluir()
    {
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

        if ( !isset($dados->id) || $dados->id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Comanda');
        $dados_mesa = $this->Comanda->find('first',[
            'conditions' => [
                'Comanda.id' => $dados->id,
                'Comanda.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($dados_mesa) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'A comanda que você está tentando exlcuir, não existe!'))));
        }

        if ( !$this->Comanda->deleteAll(['Comanda.id' => $dados->id]) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Ocorreu um erro ao tentar exluir a comanda. Por favor, tente mais tarde!'))));
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'message' => 'Comanda excluída com sucesso!'))));

    }

    public function verificaAberta() 
    {
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

        $this->loadModel('Comanda');
        $this->loadModel('ClienteClienteComanda');

        //se passou o nome da comanda
        if ( isset($dados['descricao']) && $dados['descricao'] != "" ) {

            $dados_comanda = $this->Comanda->find('first',[
                'conditions' => [
                    'Comanda.cliente_id' => $dados_token['Usuario']['cliente_id'],
                    'Comanda.descricao' => $dados['descricao']
                ],
                'link' => []
            ]);

            if ( count($dados_comanda) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "message" => "Comanda não econtrada!"))));
            }

            // verifica se a comanda está aberta 
            $comanda_aberta = $this->ClienteClienteComanda->find('first', array(
                'fields' => array(
                    'ClienteClienteComanda.comanda_id',
                    'ClienteClienteComanda.data_hora_entrada', 
                    'ClienteClienteComanda.cliente_cliente_id', 
                    'ClienteClienteComanda.cliente_endereco', 
                    'ClienteCliente.nome', 
                    'ClienteCliente.cpf'
                ),
                'link' => array('ClienteCliente'),
                'conditions' => array(
                    'ClienteClienteComanda.comanda_id' => $dados_comanda['Comanda']['id'],
                    'ISNULL(ClienteClienteComanda.data_hora_saida)'
                )
            ));

        }

        //se passou o cliente
        if ( isset($dados['client_client_id']) && $dados['client_client_id'] != "" ) {

            // verifica se a comanda está aberta 
            $comanda_aberta = $this->ClienteClienteComanda->find('first', array(
                'fields' => array(
                    'ClienteClienteComanda.comanda_id',
                    'ClienteClienteComanda.data_hora_entrada', 
                    'ClienteClienteComanda.cliente_cliente_id', 
                    'ClienteClienteComanda.cliente_endereco', 
                    'Comanda.descricao',
                    'ClienteCliente.nome', 
                    'ClienteCliente.cpf'
                ),
                'link' => array('ClienteCliente', 'Comanda'),
                'conditions' => array(
                    'ClienteClienteComanda.cliente_cliente_id' => $dados['client_client_id'],
                    'ISNULL(ClienteClienteComanda.data_hora_saida)'
                )
            ));

        }
    
        if ( count($comanda_aberta) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "ok", "reponse" => "comanda_fechada"))));
        } else {
            return new CakeResponse(array(
                'type' => 'json', 
                'body' => json_encode(array(
                    "status" => "ok", 
                    "reponse" => "comanda_aberta", 
                    "cliente" => $comanda_aberta['ClienteClienteComanda']['cliente_cliente_id'], 
                    "comanda" => isset($comanda_aberta['Comanda']) ? $comanda_aberta['Comanda']['descricao'] : $dados['descricao'], 
                    "endereco" => $comanda_aberta['ClienteClienteComanda']['cliente_endereco'] == null ? "" : $comanda_aberta['ClienteClienteComanda']['cliente_endereco']
                ))
            ));
        }

    }

    public function iniciarPedido()
    {
        $this->layout = 'ajax';
        $dados = json_decode(json_encode($this->request->data['dados']));

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;
    
        if ( !isset($dados->token) || $dados->token == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados->email) || $dados->email == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados->token;
        $email = $dados->email;

        $dados_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

		$comanda_numero = $dados->comanda;
		$cliente = $dados->client_client_id;
		$mesa = $dados->mesa;
		$endereco = $dados->endereco;
		$pdv_id  = $dados->pdv_id;

		$this->loadModel('Comanda');
        $this->loadModel('Mesa');
        $this->loadModel('ClienteCliente');
        $this->loadModel('Pdv');

        // verifica se existe a comanda
        $vComanda = $this->Comanda->find('first',[
            'conditions' => [
                'Comanda.descricao' => $comanda_numero,
                'Comanda.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($vComanda) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Não encontramos os dados da comanda.'))));
        }

        // verifica se existe o PDV
        $vPdv = $this->Pdv->find('first',[
            'conditions' => [
                'Pdv.id' => $pdv_id,
                'Pdv.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ],
            'link' => []
        ]);

        if ( count($vPdv) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Não encontramos os dados do pdv.'))));
        }
        
		$delivery   = $vPdv['Pdv']['delivery'];

        if ( $delivery == 'Y' ) {
            if ( empty($cliente) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Cliente não informado'))));
            }

        } else {

            if ( empty($mesa) ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Mesa não informada'))));
            }
        }

        if ( !empty($cliente) ) {

            $vCliente = $this->ClienteCliente->find('first',[
                'conditions' => [
                    'ClienteCliente.id' => $cliente,
                    'ClienteCliente.cliente_id' => $dados_usuario['Usuario']['cliente_id']
                ],
                'link' => []
            ]);
    
            if ( count($vCliente) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Não encontramos os dados do cliente.'))));
            }
        }

        if ( !empty($mesa) ) {

            $vMesa = $this->Mesa->find('first',[
                'conditions' => [
                    'Mesa.descricao' => $mesa,
                    'Mesa.cliente_id' => $dados_usuario['Usuario']['cliente_id']
                ],
                'link' => []
            ]);
    
            if ( count($vMesa) == 0 ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'message' => 'Não encontramos os dados da mesa.'))));
            }
        }

        // verifica se a comanda está aberta 
		$this->loadModel('ClienteClienteComanda');
		$comanda_aberta = $this->ClienteClienteComanda->find('first', array(
			'fields' => array(
                'ClienteClienteComanda.*', 
                'ClienteClienteComanda.cliente_cliente_id',
                'ClienteCliente.nome', 
                'ClienteCliente.cpf', 
                'ClienteCliente.id'
            ),
			'link' => array('ClienteCliente'),
			'conditions' => array(
				'ClienteClienteComanda.comanda_id' => $vComanda['Comanda']['id'],
				'ISNULL(ClienteClienteComanda.data_hora_saida)'
			)
		));
    
        if ( count($comanda_aberta) > 0 ) {
            
		    // Se a comanda já está aberta por MESA
			if (is_null($comanda_aberta['ClienteClienteComanda']['cliente_cliente_id'])) {
				if (!empty($cliente)) {
					// se escolheu algum cliente
					// Diz que a comanda já está aberta por mesa
					return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "message" => "A comanda já está aberta em uma mesa, verifique."))));	

				}
    
			} else {
				// Se a comanda está aberta por um CLIENTE
				if (!empty($cliente)) {
					// se escolheu algum cliente agora, verifica se e diferente
					if ($cliente != $comanda_aberta['ClienteClienteComanda']['cliente_cliente_id']) {
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "message" => "Essa comanda está aberta por um cliente diferente, verifique."))));
					}
				}
			}
        
        } else {

            if ( $vCliente ) {

				// se escolheu cliente, verificar se ele possui comanda aberta
				$comanda_aberta_cliente = $this->ClienteClienteComanda->find('first', array(
                    'fields' => array(
                        'ClienteClienteComanda.comanda_id',
                        'ClienteClienteComanda.data_hora_entrada',
                        'ClienteCliente.nome', 
                        'ClienteCliente.cpf', 
                        'ClienteCliente.id'
                    ),
                    'link' => array('ClienteCliente'),
                    'conditions' => array(
                        'ClienteClienteComanda.cliente_cliente_id' => $cliente,
                        'ISNULL(ClienteClienteComanda.data_hora_saida)'
                    )
                ));

                if ( count($comanda_aberta_cliente) > 0 ) {

					// Comanda nova, cliente com comanda aberta, verifica se é diferente
					if ( $comanda_aberta_cliente['ClienteClienteComanda']['comanda_id'] != $vComanda['Comanda']['id'] ) {
						return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "message" => "Esse cliente possui outra comanda aberta, verifique."))));	
					}
				}

            }

        }

		if (!$comanda_aberta) {
			if (!$this->_abrirComanda($vComanda['Comanda']['id'], $cliente, $endereco, $pdv_id)) {
				return new CakeResponse(array(
                    'type' => 'json',
                     'body' => json_encode(array(
                        "status" => "erro", 
                        "message" => "Ocorreu um erro ao abrir a comanda, tente novamente."
                    ))
                ));	
			}
		}

		return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "ok"))));

    }

	private function _abrirComanda($comanda_id, $cliente, $endereco, $pdv_id) 
    {

		$cliente_id = NULL;
		if (!empty($cliente)) {
			$cliente_id = $cliente;
		}

		$data_hora_entrada = date('Y-m-d H:i:s');
		$comanda_salvar = array(
			'ClienteClienteComanda' => array(
				'tipo_entrada' => NULL,
				'data_hora_entrada' => $data_hora_entrada,
				'cliente_endereco' => $endereco,
				'pdv_id' => $pdv_id,
				'cliente_cliente_id' => $cliente_id,
				'data_hora_saida' => NULL,
				'comanda_id' => $comanda_id,
				'bloqueada' => 0
			)
		);

		if (!$this->ClienteClienteComanda->saveAssociated($comanda_salvar)) {
			return false;
		}

		return true;
	}
}