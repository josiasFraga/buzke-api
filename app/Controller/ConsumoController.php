<?php
class ConsumoController extends AppController {

	public function adicionar()
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

		$this->loadModel('Comanda');

		$comanda = $this->Comanda->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->comanda);

		if ( !$comanda ) {
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "msg" => "Essa comanda não existe."))));
		}

		// verifica se a comanda está aberta 
		$this->loadModel('ClienteClienteComanda');
		$comanda_aberta = $this->ClienteClienteComanda->find('first', array(
			'fields' => ['*'],
			'link' => ['Pdv'],
			'conditions' => array(
				'ClienteClienteComanda.comanda_id' => $comanda['Comanda']['id'],
				'ISNULL(ClienteClienteComanda.data_hora_saida)'
			)
		));

		if (!$comanda_aberta) {
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "msg" => "Essa comanda já está fechada."))));
		}

		$this->loadModel('Produto');
		$produto = $this->Produto->buscaPorId($dados_usuario['Usuario']['cliente_id'], $dados->produto_id);

		if (!$produto) {
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "msg" => "Esse produto não existe."))));
		}

		// verifica se a mesa existe
		if (!empty($dados->mesa)) {
			$this->loadModel('Mesa');
			$mesa = $this->Mesa->buscaPorNome($dados_usuario['Usuario']['cliente_id'], $dados->mesa);

			if (!$mesa) {
				return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "msg" => "A mesa escolhida não existe."))));
			}
		}

		// verifica quantidade
		if (empty($dados->quantidade) ||$dados->quantidade == 0) {
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "erro", "msg" => "A quantidade deve ser maior que zero."))));
		}

		$data_hora = date('Y-m-d H:i:s');
		if ($produto['Produto']['cozinha'] == 1) {
			$data_hora_finalizado = NULL;
		} else {
			$data_hora_finalizado = $data_hora;
		}
		
		$valor_venda_produto = (float) $produto['Produto']['valor_venda'];

		$adicionais = array();
		$total_adicionais = 0;
		$salvar_adicionais = array();

		if ( isset($dados->adicionais) && count($dados->adicionais) > 0 ) {
			$this->loadModel('ProdutoAdicional');
			$adicionais = $this->ProdutoAdicional->find('all', array(
				'fields' => array(
					'ProdutoAdicional.id',
					'ProdutoAdicional.valor'
				),
				'conditions' => array(
					'ProdutoAdicional.id' => $dados->adicionais // array
				)
			));

            debug($dados->adicionais);
            debug($adicionais);
            die();
			foreach ($adicionais as $key => $value) {
				$salvar_adicionais[] = array(
					'produto_adicional_id' => $value['ProdutoAdicional']['id'],
					'valor' => $value['ProdutoAdicional']['valor']
				);
				$total_adicionais += $value['ProdutoAdicional']['valor'];
			}
		}
		$valor_total = $valor_venda_produto * intval($this->request->data['quantidade']);

		$valor_final = $valor_total + ($total_adicionais * intval($this->request->data['quantidade']));
		$dados_salvar = array(
			'ClienteComandaProduto' => array(
                'cliente_comanda_pedido_id' => NULL,
				'cliente_comanda_id' => $comanda_aberta['ClienteComanda']['id'],
				'mesa_id' => $this->request->data['mesa'],
				'atendente_id' => $this->Auth->user('id'),
				'vendedor_id' => $this->request->data['vendedor'],
				'atendente_exclusao_id' => NULL,
				'excluido' => 0,
				'produto_id' => $this->request->data['produto'],
				'data_hora_solicitado' => $data_hora,
				'data_hora_finalizado' => $data_hora_finalizado,
				'quantidade' => $this->request->data['quantidade'],
				'valor_unitario' => $valor_venda_produto,
				'valor_total' => $valor_total,
				'desconto' => 0,
				'valor_adicionais' => $total_adicionais,
				'valor_final' => $valor_final,
				'observacoes' => $this->request->data['observacoes'],
                'concluido' => 0
			)
		);
		if ($salvar_adicionais) {
			$dados_salvar['ClienteComandaProdutoAdicional'] = $salvar_adicionais;
		}

		$this->loadModel('ClienteComandaProduto');
		if ($this->ClienteComandaProduto->saveAssociated($dados_salvar)) {
			if ( $comanda_aberta['Pdv']['taxa_adicional'] > 0 ) {
				$this->_calcula_valor_produto_taxa_extra($comanda_aberta['ClienteComanda']['id'], $comanda_aberta['Pdv']['taxa_adicional'] );
			}

			if (isset($this->request->data['finalizar_comanda'])) {
				return $this->finalizar($this->request->data['comanda_numero']);
			} else {
				return new CakeResponse(array('type' => 'json', 'body' => json_encode(array("status" => "ok", "msg" => "Produto salvo com sucesso!"))));
			}
		} else {
			return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => "Ocorreu um erro ao adicionar o produto. Por favor, tente novamente."))));
		}
	}
}