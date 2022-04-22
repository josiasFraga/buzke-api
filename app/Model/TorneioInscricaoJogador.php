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

	public $hasMany = array(
		'TorneioInscricaoJogadorImpedimento' => array(
			'foreignKey' => 'torneio_inscricao_jogador_id'
        ),
	);

    public function checkSubscribed($torneio_id = null, $ids = []){

        if ( count($ids) == 0 ) {
            return false;
        }

        if ( $torneio_id == null ) {
            return false;
        }
        
        return $this->find('count',[
            'conditions' => [
                'TorneioInscricaoJogador.cliente_cliente_id' => $ids,
                'TorneioInscricao.torneio_id' => $torneio_id,
            ],
            'link' => [
                'TorneioInscricao'
            ]
        ]) > 0;
    }

    public function buscaNomeDupla($inscricao_id = null){
        if ($inscricao_id == null ) {
            return '';
        }

        $jogadores = $this->find('all',[
            'fields' => [
                'ClienteCliente.nome'
            ],
            'conditions' => [
                'TorneioInscricaoJogador.torneio_inscricao_id' => $inscricao_id
            ],
            'link' => ['ClienteCliente']
        ]);

        if ( count($jogadores) == 0 ) {
            return "";
        }

        $nomes = [];

        foreach($jogadores as $key => $jogador){
            $nomes[] = $jogador['ClienteCliente']['nome'];
        }

        $nomes_str = implode(' | ',$nomes);
        return $nomes_str;
        
    }    

	public function getBySubscriptionId($id = null) {

		if ( $id == null ){
			return [];
		}

		return $this->find('all',[
            'fields' => [
                'TorneioInscricaoJogador.cliente_cliente_id',
                'ClienteCliente.*'
            ],
			'conditions' => [
				'TorneioInscricaoJogador.torneio_inscricao_id' => $id,
				'not' => [
					'TorneioInscricao.confirmado' => 'R'
				]
			],
			'link' => ['TorneioInscricao', 'ClienteCliente']
		]);
		

	}
}