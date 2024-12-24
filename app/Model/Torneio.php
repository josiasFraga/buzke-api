<?php
class Torneio extends AppModel {
	public $useTable = 'torneios';

	public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		)
	);

	public $hasMany = array(
		'TorneioCategoria' => array(
			'foreignKey' => 'torneio_id'
        ),
		'TorneioData' => array(
			'foreignKey' => 'torneio_id'
		),
		'TorneioQuadra' => array(
			'foreignKey' => 'torneio_id'
        ),
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_id'
		),
		'Agendamento' => array(
			'foreignKey' => 'torneio_id'
		)
	);

    // Método que será chamado para fazer o upload da imagem
    public function uploadImage($file) {
        $imageUploader = new ImageUploader();

        // Faz o upload da imagem para o S3
        $imageUrl = $imageUploader->uploadToS3($file, 'tournaments');

        if ($imageUrl) {
            // Armazene a URL da imagem no banco de dados (ou qualquer outra ação)
            $this->data['Torneio']['img'] = $imageUrl;
            return true;
        } else {
            // Retorne um erro caso o upload falhe
            return false;
        }
    }

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['inicio']) && $this->data[$this->alias]['inicio'] != '') {
            $this->data[$this->alias]['inicio'] = $this->dateBrEn($this->data[$this->alias]['inicio']);
        }
        if ( isset($this->data[$this->alias]['fim']) && $this->data[$this->alias]['fim'] != '') {
            $this->data[$this->alias]['fim'] = $this->dateBrEn($this->data[$this->alias]['fim']);
        }
        if ( isset($this->data[$this->alias]['data_publicacao']) && $this->data[$this->alias]['data_publicacao'] != '') {
            $this->data[$this->alias]['data_publicacao'] = $this->dateBrEn($this->data[$this->alias]['data_publicacao']);
        }
        if ( isset($this->data[$this->alias]['inscricoes_de']) && $this->data[$this->alias]['inscricoes_de'] != '') {
            $this->data[$this->alias]['inscricoes_de'] = $this->dateBrEn($this->data[$this->alias]['inscricoes_de']);
        }
        if ( isset($this->data[$this->alias]['inscricoes_ate']) && $this->data[$this->alias]['inscricoes_ate'] != '') {
            $this->data[$this->alias]['inscricoes_ate'] = $this->dateBrEn($this->data[$this->alias]['inscricoes_ate']);
        }
        if ( isset($this->data[$this->alias]['valor_inscricao']) && $this->data[$this->alias]['valor_inscricao'] != '') {
            $this->data[$this->alias]['valor_inscricao'] = $this->currencyToFloat($this->data[$this->alias]['valor_inscricao']);
        }

        // Verifique se há uma imagem enviada
        if (!empty($this->data['Torneio']['img'])) {
            $file = $this->data['Torneio']['img'];
            // Faça o upload da imagem
            $this->uploadImage($file);
        }
        return true;
    }

	public function checkIsSubscriptionsFinished($torneio_id = null) {
		if ( $torneio_id == null ){
			return false;
		}

		return $this->find('count',[
			'conditions' => [
				'Torneio.id' => $torneio_id,
				'Torneio.inscricoes_ate <' => date('Y-m-d'),
			]
		]) > 0;

	}
}