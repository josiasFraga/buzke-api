<?php 
class ClienteServicoFoto extends AppModel {
    public $useTable = 'cliente_servico_fotos';

    public $belongsTo = array(
      'ClienteServico' => array(
        'foreignKey' => 'cliente_servico_id'
      ),
    );
    
    public $validate = array();

    public $actsAs = array(
		'Upload.Upload' => array(
			'imagem' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}servicos", // {ONDE ARQ ESTA}{ENTRA}webroot{ENTRA}img{ENTRA}lotes
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

}
