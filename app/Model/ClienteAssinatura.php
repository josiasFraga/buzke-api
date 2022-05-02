<?php 
class ClienteAssinatura extends AppModel {
    public $useTable = 'cliente_assinaturas';

    public $hasMany = array(
    );

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
		'Plano' => array(
			'foreignKey' => 'plano_id'
		),
    );

    public function getLastByClientId($client_id = null) {
        return $this->find('first', [
            'conditions' => [
                'ClienteAssinatura.cliente_id' => $client_id
            ],
            'order' => ['ClienteAssinatura.id DESC'],
            'link' => []
        ]);
    }

    public function getAllSingatures($client_id = null) {
        return $this->find('all', [
            'conditions' => [
                'ClienteAssinatura.cliente_id' => $client_id
            ],
            'order' => ['ClienteAssinatura.id DESC'],
            'link' => []
        ]);
    }

    public function getOnlyIds($signatures = []) {

        if ( count($signatures) == 0 ) {
            return [];
        }

        $arr_retornar = [];

        foreach( $signatures as $key => $signature ){
            $arr_retornar[] = $signature['ClienteAssinatura']['id'];
        }

        return $arr_retornar;
    }

    public function reativaAssinatura($assinatura_id = null) {

        if ( $assinatura_id == null ) {
            return false;
        }

        $dados_salvar = [
            'id' => $assinatura_id,
            'status' => 'ACTIVE',
        ];

        return $this->save($dados_salvar);
    }

    public function setaAssinaturaAtrasada($assinatura_id = null) {

        if ( $assinatura_id == null ) {
            return false;
        }

        $dados_salvar = [
            'id' => $assinatura_id,
            'status' => 'OVERDUE',
        ];

        return $this->save($dados_salvar);
    }
}