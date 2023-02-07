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

    public $actsAs = array(
		'Upload.Upload' => array(
			'img' => array(
				'path' => "{ROOT}{DS}webroot{DS}img{DS}torneios", // {ONDE ARQ ESTA}{ENTRA}webroot{ENTRA}img{ENTRA}lotes
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

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['inicio']) && $this->data[$this->alias]['inicio'] != '') {
            $this->data[$this->alias]['inicio'] = $this->dateBrEn($this->data[$this->alias]['inicio']);
        }
        if ( isset($this->data[$this->alias]['fim']) && $this->data[$this->alias]['fim'] != '') {
            $this->data[$this->alias]['fim'] = $this->dateBrEn($this->data[$this->alias]['fim']);
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