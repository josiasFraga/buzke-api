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

    public $actsAs = array(
		'Upload.Upload' => array(
			'img' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}clientes_clientes", // {ONDE ARQ ESTA}{ENTRA}webroot{ENTRA}img{ENTRA}lotes
				'thumbnailSizes' => array(
                    'thumb' => '512x512',
				),
				'pathMethod' => 'flat',
				'nameCallback' => 'rename'
			)
		)
	);

    public function rename($field, $currentName, array $data, array $options) {
        $ext = pathinfo($currentName, PATHINFO_EXTENSION);
        $name = md5(uniqid(rand())).'.'.mb_strtolower($ext);
        return $name;
    }

    public function buscaDadosUsuarioComoCliente($usuario_id = null, $cliente_id = null) {

		if ( $usuario_id == null || $cliente_id == null )
			return false;

		$dados_cliente = $this->find('first',[
			'conditions' => [
				'ClienteCliente.usuario_id' => $usuario_id,
				'ClienteCliente.cliente_id' => $cliente_id,
			],
			'link' => []
		]);

		if ( count($dados_cliente) == 0 ) {

			$dados_cliente = $this->find('first',[
				'conditions' => [
					'ClienteCliente.usuario_id' => $usuario_id,
					'ISNULL(ClienteCliente.cliente_id)',
				],
				'link' => []
			]);

		}

		if ( count($dados_cliente) == 0 ) {

			$dados_cliente = $this->find('first',[
				'conditions' => [
					'ClienteCliente.usuario_id' => $usuario_id,
				],
				'link' => []
			]);

			
			if ( count($dados_cliente) == 0 ) {
				return false;
			}

			$dados_cliente['ClienteCliente']['cliente_id'] = $cliente_id;

			$dados_cliente = $this->save($dados_cliente);

		}

		return $dados_cliente;
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

}
