<?php 
class UruguaiCidade extends AppModel {

    public $useTable = 'uruguai_cidades';
    public $name = 'UruguaiCidade';

    public $hasMany = [
		'Cliente' => [
			'foreignKey' => 'ui_cidade'
        ]
    ];

    public $belongsTo = [
		'UruguaiDepartamento' => [
			'foreignKey' => 'departamento_id'
        ]
    ];

    public function findByGoogleAddress( $address = null ) {
        if ($address == null ) {
			return ['UruguaiCidade' => ['id' => -500]];
        }

        $lista_departamentos = $this->UruguaiDepartamento->find("list", [
            "fields" => [
                "UruguaiDepartamento.nome",
                "UruguaiDepartamento.nome"
            ]
        ]);

        foreach( $lista_departamentos as $key => $departamento ) {

            if ( strpos($address, $departamento) !== false ) {
                $departamento_nome = trim(substr($address, strpos($address, $departamento)));
                $cidade_nome = trim(str_replace($departamento_nome,"", $address));

                if ( $cidade_nome == "" ){
                    $cidade_nome = $departamento_nome;
                }
            }
        }

        if ( !$departamento_nome ) {
			return ['UruguaiCidade' => ['id' => -500]];
        }

        $dados = $this->find("first", [
            "conditions" => [
                "UruguaiCidade.nome" => $cidade_nome,
                "UruguaiDepartamento.nome" => $departamento_nome
            ],
            "link" => ["UruguaiDepartamento"]
        ]);

        if ( count($dados) == 0 ) {
			return ['UruguaiCidade' => ['id' => -500]];
        }
        
        return $dados;
    }
}