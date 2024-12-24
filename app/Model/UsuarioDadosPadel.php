<?php 
class UsuarioDadosPadel extends AppModel {
    public $useTable = 'usuarios_dados_padel';

    public $name = 'UsuarioDadosPadel';

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
		),
    );

    public $actsAs = array(
		'Upload.Upload' => array(
			'img' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}esportistas",
				'thumbnailSizes' => array(
                    'thumb' => '512x512',
				),
				'pathMethod' => 'flat',
				'nameCallback' => 'rename',
                'keepFilesOnDelete' => true,
			)
		)
	);

    public function rename($field, $currentName, array $data, array $options) {
        $ext = pathinfo($currentName, PATHINFO_EXTENSION);
        $name = md5(uniqid(rand())).'.'.mb_strtolower($ext);
        return $name;
    }

    public function findByUserId($user_id = null) {
		if ( $user_id == null ) {
			return [];
		}

		return $this->find('first',[
			'fields' => [
				'Usuario.nome',
				'UsuarioDadosPadel.*',
				'ClienteCliente.sexo',
				'ClienteCliente.data_nascimento'
			],
			'conditions' => [
			'UsuarioDadosPadel.usuario_id' => $user_id
			],
			'link' => [
				'Usuario' => [
					'ClienteCliente' => [
						'conditions' => [
							'Usuario.id = ClienteCliente.usuario_id',
							'ClienteCliente.cliente_id IS NULL'
						]
					]
				]
			]
		]);

    }

    public $validate = array();

}
