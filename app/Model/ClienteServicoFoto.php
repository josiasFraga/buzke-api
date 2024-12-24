<?php 
class ClienteServicoFoto extends AppModel {
    public $useTable = 'cliente_servico_fotos';

    public $belongsTo = array(
      'ClienteServico' => array(
        'foreignKey' => 'cliente_servico_id'
      ),
    );
    
    public $validate = array();

    // Método que será chamado para fazer o upload da imagem
    public function uploadImage($file) {
        $imageUploader = new ImageUploader();

        // Faz o upload da imagem para o S3
        $imageUrl = $imageUploader->uploadToS3($file, 'services');

        if ($imageUrl) {
            // Armazene a URL da imagem no banco de dados (ou qualquer outra ação)
            $this->data['ClienteServicoFoto']['imagem'] = $imageUrl;
            return true;
        } else {
            // Retorne um erro caso o upload falhe
            return false;
        }
    }

    public function beforeSave($options = array()) {

        // Verifique se há uma imagem enviada
        if (!empty($this->data['ClienteServicoFoto']['imagem'])) {
            $file = $this->data['ClienteServicoFoto']['imagem'];
            // Faça o upload da imagem
            $this->uploadImage($file);
        }
        return true;
    }

}
