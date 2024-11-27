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

    public function getUserNSubscriptions($torneio_id = null, $ids = []){

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
        ]);
    }

    public function buscaNomeDupla($inscricao_id = null, $separator = ' | '){
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

        $nomes_str = implode($separator,$nomes);
        return $nomes_str;
        
    }

    public function buscaPrimeiroNomeDupla($inscricao_id = null, $separator = ' | '){
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
            $nomes[] = explode(' ', $jogador['ClienteCliente']['nome'])[0];
        }

        $nomes_str = implode($separator,$nomes);
        return $nomes_str;
        
    }

    public function buscaJogadoresComFoto($inscricao_id = null, $images_path){

        if ($inscricao_id == null ) {
            return '';
        }

        $jogadores = $this->find('all',[
            'fields' => [
                'ClienteCliente.nome',
                'ClienteCliente.img',
                'Usuario.img'
            ],
            'conditions' => [
                'TorneioInscricaoJogador.torneio_inscricao_id' => $inscricao_id
            ],
            'link' => ['ClienteCliente' => ['Usuario']]
        ]);

        $dados_retornar = [];
        foreach( $jogadores as $key => $jogador ){

            $dados_retornar[$key] = [
                'nome' => $jogador['ClienteCliente']['nome']
            ];

            $dados_retornar[$key]['img'] = $images_path.'clientes_clientes/'.$jogador['ClienteCliente']['img'];
    
            if ( !empty($jogador['Usuario']['img']) ) {
                $dados_retornar[$key]['img'] = $images_path.'usuarios/'.$jogador['Usuario']['img'];
            }
        }

        return $dados_retornar;
    }

    public function buscaImagemJogador($inscricao_id = null, $n_jogador, $images_path){
        if ($inscricao_id == null ) {
            return '';
        }

        $jogadores = $this->find('all',[
            'fields' => [
                'Usuario.img',
                'ClienteCliente.img'
            ],
            'conditions' => [
                'TorneioInscricaoJogador.torneio_inscricao_id' => $inscricao_id
            ],
            'link' => [
                'ClienteCliente' => [
                    'Usuario'
                ]
            ]
        ]);

        $jogador_imagem = $jogadores[($n_jogador-1)]["ClienteCliente"]["img"];

        if ( $jogadores[($n_jogador-1)]["Usuario"]["img"] ) {
            $jogador_imagem = $jogadores[($n_jogador-1)]["Usuario"]["img"];
        }

        if ( $jogador_imagem == null )  {
            $jogador_imagem = $images_path."usuarios/default.png";;
        }

        return $images_path . "usuarios/" . $jogador_imagem;

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