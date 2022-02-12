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
}