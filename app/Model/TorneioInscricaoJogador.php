<?php
class TorneioInscricaoJogador extends AppModel {
	public $useTable = 'torneio_inscricao_jogadores';

	public $belongsTo = array(
		'TorneioInscricao' => array(
			'foreignKey' => 'torneio_inscricao_id'
        ),
		'ClienteCliente' => array(
			'foreignKey' => 'cliente_cliente_id'
        ),
	);

    public function checkSubscribed($ids = []){
        if ( count($ids) == 0 ) {
            return false;
        }
        
        return $this->find('count',[
            'conditions' => [
                'TorneioInscricaoJogador.cliente_cliente_id' => $ids
            ]
        ]) > 0;
    }
}