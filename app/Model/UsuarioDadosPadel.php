<?php 
class UsuarioDadosPadel extends AppModel {
    public $useTable = 'usuarios_dados_padel';

    public $name = 'UsuarioDadosPadel';

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
		),
    );

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

    // Método que será chamado para fazer o upload da imagem
    public function uploadImage($file, $directory) {
        $imageUploader = new ImageUploader();

        // Faz o upload da imagem para o S3
        $imageUrl = $imageUploader->uploadToS3($file, $directory);

        if ($imageUrl) {
            // Armazene a URL da imagem no banco de dados (ou qualquer outra ação)
            return $imageUrl;
        } else {
            // Retorne um erro caso o upload falhe
            return null;
        }
    }

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['senha']) && $this->data[$this->alias]['senha'] != '') {
            $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        }

        if (!empty($this->data['UsuarioDadosPadel']['img'])) {
            $file = $this->data['UsuarioDadosPadel']['img'];
            // Faça o upload da imagem
            $this->data['UsuarioDadosPadel']['img'] = $this->uploadImage($file, 'athletes/profiles');
        }

        if (!empty($this->data['UsuarioDadosPadel']['img_capa'])) {
            $file = $this->data['UsuarioDadosPadel']['img_capa'];
            // Faça o upload da imagem
            $this->data['UsuarioDadosPadel']['img_capa'] = $this->uploadImage($file, 'athletes/covers');
        }
        return true;
    }

}
