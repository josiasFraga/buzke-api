<?php 
class ClienteCliente extends AppModel {
    public $useTable = 'clientes_clientes';

    public $name = 'ClienteCliente';

    public $hasMany = array(
		'ClienteClientePadelCategoria' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'Agendamento' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'AgendamentoFixoCancelado' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'ToProJogo' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'AgendamentoConvite' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'TorneioInscricaoJogador' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'ClienteClienteComanda' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'AgendamentoClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
		'TorneioInscricaoJogadorTimeUm' => [
			'foreignKey' => 'cliente_cliente_id'
		],
		'TorneioInscricaoJogadorTimeDois' => [
			'foreignKey' => 'cliente_cliente_id'
		]
		
    );

    public $hasOne = array(
		'ClienteClienteDadosPadel' => array(
			'foreignKey' => 'cliente_cliente_id'
		),
    );

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
		'Localidade' => array(
			'foreignKey' => 'cidade_id'
		),
		'Uf' => array(
			'foreignKey' => 'estado_id'
		),
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
		),
    );
    
    public $validate = array();


    public function buscaDadosUsuarioComoCliente($usuario_id = null, $cliente_id = null) {

		if ( $usuario_id == null )
			return [];

		$conditions = [
			'ClienteCliente.usuario_id' => $usuario_id,
			'ClienteCliente.cliente_id' => null,
		];

		if ( $cliente_id != null ) {
			$conditions = array_merge($conditions, [
				'ClienteCliente.cliente_id' => $cliente_id,
			]);
		}

		$dados_cliente = $this->find('first',[
			'conditions' => $conditions,
			'link' => []
		]);

		
		return $dados_cliente;
    }

	public function criaDadosComoCliente($usuario_id = null, $cliente_id = null) {
		if ( $usuario_id == null || $cliente_id == null)
			return [];

		$dados = $this->buscaDadosUsuarioComoCliente($usuario_id);

		if ( count($dados) == 0 ){
			return [];
		}

		$dados['ClienteCliente']['cliente_id'] = $cliente_id;
		unset($dados['ClienteCliente']['id']);
		$this->create();
		$dados_retornar = $this->save($dados);
		return $dados_retornar;
		
	}

    public function buscaDadosClienteCliente($cliente_cliente_id = null, $cliente_id = null) {

		if ( $cliente_cliente_id == null || $cliente_id == null )
			return false;

		$dados_cliente = $this->find('first',[
			'conditions' => [
				'ClienteCliente.id' => $cliente_cliente_id,
				'ClienteCliente.cliente_id' => $cliente_id,
			],
			'link' => []
		]);

		return $dados_cliente;
    }

	public function buscaPorNome($cliente_id, $nome, $id=null) {
		$conditions = [
			'ClienteCliente.cliente_id' => $cliente_id,
			'ClienteCliente.nome' => $nome
		];

		if ( $id != null ) {
			$conditions = array_merge([
				'not' => [
					'ClienteCliente.id' => $id
				]
			],
			$conditions);
		}

		return $this->find('first',[
			'conditions' => $conditions
		]);
	}

	public function buscaPorEmail($cliente_id, $email) {
		return $this->find('first',[
			'conditions' => [
				'ClienteCliente.cliente_id' => $cliente_id,
				'ClienteCliente.email' => $email
			]
		]);
	}

    public function buscaTodosDadosUsuarioComoCliente($usuario_id = null, $only_ids = false) {

		if ( $usuario_id == null )
			return [];

		if ( !$only_ids ) {
			$dados_cliente = $this->find('all',[
				'conditions' => [
					'ClienteCliente.usuario_id' => $usuario_id,
				],
				'link' => []
			]);
		} else {
			$dados_cliente = $this->find('list',[
				'fields' => ['ClienteCliente.id', 'ClienteCliente.id'],
				'conditions' => [
					'ClienteCliente.usuario_id' => $usuario_id,
				],
				'link' => []
			]);
		}

		return $dados_cliente;
    }

    public function buscaDadosSemVinculo($usuario_id = null, $only_ids = false) {

		if ( $usuario_id == null )
			return [];

		if ( !$only_ids ) {
			$dados_cliente = $this->find('all',[
				'conditions' => [
					'ClienteCliente.usuario_id' => $usuario_id,
					'ClienteCliente.cliente_id' => null,
				],
				'link' => []
			]);
		} else {
			$dados_cliente = $this->find('list',[
				'fields' => ['ClienteCliente.id', 'ClienteCliente.id'],
				'conditions' => [
					'ClienteCliente.usuario_id' => $usuario_id,
					'ClienteCliente.cliente_id' => null,
				],
				'link' => []
			]);
		}

		return $dados_cliente;
    }

	public function getUsersIdsFromClienteCliente($cliente_cliente_ids = []) {

		if ( count($cliente_cliente_ids) == 0 ) {
			return [];
		}

		return $this->find('list',[
			'conditions' => [
				'ClienteCliente.id' => $cliente_cliente_ids,
				'not' => [
					'ClienteCliente.usuario_id' => null
				]
			],
			'fields' => ['ClienteCliente.usuario_id','ClienteCliente.usuario_id'],
			'group' => ['ClienteCliente.usuario_id']
		]);

	}

	public function finUserData($cliente_cliente_id = null, $fields = ['Usuario.nome']) {
		if ($cliente_cliente_id == null) {
			return [];
		}

		$dados_usuario =  $this->find('first',[
			'fields' => $fields,
			'conditions' => [
				'ClienteCliente.id' => $cliente_cliente_id
			],
			'link' => ['Usuario']
		]);

		if ( count($dados_usuario) == 0 ) {
			return [];
		} else {
			return $dados_usuario['Usuario'];
		}
	}

    // Método que será chamado para fazer o upload da imagem
    public function uploadImage($file) {
        $imageUploader = new ImageUploader();

        // Faz o upload da imagem para o S3
        $imageUrl = $imageUploader->uploadToS3($file, 'client');

        if ($imageUrl) {
            // Armazene a URL da imagem no banco de dados (ou qualquer outra ação)
            $this->data['ClienteCliente']['img'] = $imageUrl;
            return true;
        } else {
            // Retorne um erro caso o upload falhe
            return false;
        }
    }
	
    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['data_nascimento']) && $this->data[$this->alias]['data_nascimento'] != '') {
            $this->data[$this->alias]['data_nascimento'] = $this->dateBrEn($this->data[$this->alias]['data_nascimento']);
        }

        // Verifique se há uma imagem enviada
        if (!empty($this->data['ClienteCliente']['img'])) {
            $file = $this->data['ClienteCliente']['img'];
            // Faça o upload da imagem
            $this->uploadImage($file);
        }
        return true;
    }

}
