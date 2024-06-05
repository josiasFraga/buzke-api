<?php 

class UsuarioLocalizacao extends AppModel {
    public $useTable = 'usuarios_localizacoes';

    public $name = 'UsuarioLocalizacao';
    
    public $validate = array();

    public $belongsTo = array(
		'Usuario' => array(
			'foreignKey' => 'usuario_id'
        ),
		'Token' => array(
			'foreignKey' => 'token_id'
        )
    );

    public $hasMany = array(
		'ToProJogo' => array(
			'foreignKey' => 'localizacao_id'
        ),
		'Pesquisa' => array(
			'foreignKey' => 'usuario_localizacao_id'
        )
    );
    
    public function findByUserIdAndData($usuario_id = null, $localizacao = []) {
        if ( $usuario_id == null || count($localizacao) == 0 ) {
            return [];
        }

        $string_localizacao = str_replace(' ', '', implode(',',array_map("trim",array_filter($localizacao))));
            

        return $this->find('first',[
            'conditions' => [
                'or' => [
                    'Token.usuario_id' => $usuario_id,
                    'UsuarioLocalizacao.usuario_id' => $usuario_id,
                ],
                
                "REPLACE(UsuarioLocalizacao.description, ' ','')" => $string_localizacao,
            ],
            'link' => ['Token'],
            'order' => ['UsuarioLocalizacao.id DESC']
        ]);

    }

    public function filterByLastLocation($players, $location) {
        if ( count($players) == 0 ) {
            return [];
        }
        
        $dados_retornar = [];
        foreach($players as $key => $palyer) {
            $usuario_id = $palyer['ClienteCliente']['usuario_id'];
            $dados_ultima_localizacao = $this->find('first', [
                'conditions' => [
                    'or' => [
                        'Token.usuario_id' => $usuario_id,
                        'UsuarioLocalizacao.usuario_id' => $usuario_id,
                    ],
                ],
                'link' => ['Token'],
                'order' => ['UsuarioLocalizacao.id DESC']
            ]);


            if ( count($dados_ultima_localizacao) > 0 ) {
                $verifica_cidade = strpos($dados_ultima_localizacao['UsuarioLocalizacao']['description'], $location['ufe_sg'].',') > -1;
                $verifica_uf = strpos($dados_ultima_localizacao['UsuarioLocalizacao']['description'], $location['loc_no']) > -1;
                if ( $verifica_cidade && $verifica_uf ) {
                    $dados_retornar[] = $palyer['ClienteCliente']['id'];
                } 
            }
        }

        return $dados_retornar;
    }

    public function filterByLastByTokenAndUserId($token_id = null, $usuario_id) {

        if ( empty($token_id) ) {
            return [];
        }

        $dados_retornar = $this->find('first',[
            'conditions' => [
                'UsuarioLocalizacao.token_id' => $token_id
            ],
            'link' => [],
            'order' => [
                'UsuarioLocalizacao.id DESC'
            ]
        ]);

        if ( !empty($usuario_id) && count($dados_retornar) == 0 ) {

            $dados_retornar = $this->find('first',[
                'conditions' => [
                    'UsuarioLocalizacao.usuario_id' => $usuario_id
                ],
                'link' => [],
                'order' => [
                    'UsuarioLocalizacao.id DESC'
                ]
            ]);

        }

        return $dados_retornar;
    }
}
