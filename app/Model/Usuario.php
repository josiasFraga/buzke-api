<?php 
App::uses('ImageUploader', 'Lib');

class Usuario extends AppModel {
    public $useTable = 'usuarios';

    public $name = 'Usuario';

    public $hasMany = array(
		'Token' => array(
			'foreignKey' => 'usuario_id'
		),
		'ClienteCliente' => array(
			'foreignKey' => 'usuario_id'
		),
        'UsuarioResetSenha' => [
			'foreignKey' => 'usuario_id'
        ],
        'UsuarioLocalizacao' => [
			'foreignKey' => 'usuario_id'
        ],
        'UsuarioDadosPadel' => [
			'foreignKey' => 'usuario_id'
        ],
        'UsuarioPadelCategoria' => [
			'foreignKey' => 'usuario_id'
        ],
        'Sugestao' => [
			'foreignKey' => 'usuario_id'
        ],
        'ClienteServicoProfissional' => [
			'foreignKey' => 'usuario_id'
        ],
        'Agendamento' => [
			'foreignKey' => 'profissional_id'
        ],
        'ClienteServicoAvaliacao' => [
			'foreignKey' => 'usuario_id'
        ],
        'SincronizacaoAgenda' => [
			'foreignKey' => 'usuario_id'
        ],
        'EstatisticaPadel' => [
			'foreignKey' => 'usuario_id'
        ]
    );

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
		),
    );

    public $hasOne = [
        'NotificacaoConfiguracaoUsuario' => [
			'foreignKey' => 'usuario_id'
        ]
    ];
    
    public $validate = array();

    // Método que será chamado para fazer o upload da imagem
    public function uploadImage($file) {
        $imageUploader = new ImageUploader();

        // Faz o upload da imagem para o S3
        $imageUrl = $imageUploader->uploadToS3($file, 'users', true);

        if ($imageUrl) {
            // Armazene a URL da imagem no banco de dados (ou qualquer outra ação)
            $this->data['Usuario']['img'] = $imageUrl;
            return true;
        } else {
            // Retorne um erro caso o upload falhe
            return false;
        }
    }

    public function beforeSave($options = array()) {
        if ( isset($this->data[$this->alias]['senha']) && $this->data[$this->alias]['senha'] != '') {
            $this->data[$this->alias]['senha'] = AuthComponent::password($this->data[$this->alias]['senha']);
        }

        // Verifique se há uma imagem enviada
        if (!empty($this->data['Usuario']['img'])) {
            $file = $this->data['Usuario']['img'];
            // Faça o upload da imagem
            $this->uploadImage($file);
        }
    
        if ( empty($this->data['Usuario']['id']) && empty($this->data['Usuario']['usuario']) ) {
            // Gera o nome de usuário apenas se não for uma atualização e se o nome de usuário não estiver definido
            $usuario = $this->generateUsername($this->data['Usuario']['nome']);
            $this->data['Usuario']['usuario'] = $usuario;
        }
        return true;
    }

    private function generateUsername($nome) {
        // Inicializa o nome base (sem número sequencial)
        $nomeBase = '@' . preg_replace('/[^a-zA-Z0-9]/', '', $nome); // Remove caracteres especiais
        $usuario = $nomeBase;
        
        // Verifica se já existe um usuário com esse nome
        $count = $this->find('count', [
            'conditions' => ['Usuario.usuario' => $usuario]
        ]);

        // Se já existir, adiciona um número sequencial
        if ($count > 0) {
            $i = 1;
            do {
                $usuario = $nomeBase . $i;
                $i++;
                $count = $this->find('count', [
                    'conditions' => ['Usuario.usuario' => $usuario]
                ]);
            } while ($count > 0);
        }

        return $usuario;
    }

    public function listUsuarios() {
        return $this->find('list', array(
            'fields' => array(
                'Usuario.id',
                'Usuario.nome'
            ),
            'conditions' => array(
                'Usuario.ativo' => 'Y',
                'Usuario.nivel !=' => 'admin'
            )
        ));
    }

	public function findIdsOneSingalAdmins() {
		return $this->find('list',array(
			'fields' => array(
				'Usuario.id', 
				'Usuario.notifications_id'
			),
			'conditions' => array(
				'Usuario.usuario_tipo_id' => array(2,3)
			)
		));
	}

	public function getClientDataByPadelistProfile($dados_peril) {

        $conditions = [
            'ClienteCliente.cliente_id' => null,
            'not' => [
                'ClienteCliente.id' => null
            ]
        ];
        if ( $dados_peril->lado != 'I' ) {
            $conditions = array_merge($conditions, [
                'UsuarioDadosPadel.lado' => ['A', $dados_peril->lado]
            ]);
        }
        if ( $dados_peril->sexo != 'I' ) {
            $conditions = array_merge($conditions, [
                'ClienteCliente.sexo' => $dados_peril->sexo
            ]);
        }

        $categorias = [];
        foreach($dados_peril as $key => $perfil) {
            if (strpos($key, 'categoria_') > -1 && $perfil) {
                list($discard, $categoria) = explode('_',$key);
                $categorias[] = $categoria;
            }
        }
        if ( count($categorias) > 0 ) {
            $conditions = array_merge($conditions, [
                'UsuarioPadelCategoria.categoria_id' => $categorias
            ]);
        }

        $jogadores = $this->find('all', [
            'fields' => [
                'ClienteCliente.id',
                'ClienteCliente.usuario_id'
            ],
            'conditions' => $conditions,
            'link' => ['UsuarioDadosPadel', 'ClienteCliente', 'UsuarioPadelCategoria'],
            'group' => ['ClienteCliente.id']
        ]);
        
        return $jogadores;
	}

    public function getShedulingConfirmedUsers($agendamento_id = null,$agendamento_horario='') {
        if ( $agendamento_id == null || $agendamento_horario == '' ) {
            return [];
        }
        return array_values($this->find('list',[
            'fields' => [
                'Usuario.id',
                'Usuario.id',
            ],
            'conditions' => [
                'AgendamentoConvite.agendamento_id' => $agendamento_id,
                'AgendamentoConvite.confirmado_usuario' => 'Y',
                'AgendamentoConvite.confirmado_convidado' => 'Y',
                'AgendamentoConvite.horario' => $agendamento_horario,
                'AgendamentoConvite.horario_cancelado' => 'N'
            ],
            'link' => ['ClienteCliente' => ['AgendamentoConvite']],
            'group' => ['Usuario.id']
        ]));

    }

    public function getByEmail($email) {
        return $this->find('first',[
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                'Usuario.telefone'
            ],
            'conditions' => [
                'Usuario.email' => $email
            ]
        ]);
    }

    public function getById($id) {
        return $this->find('first',[
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                'Usuario.telefone'
            ],
            'conditions' => [
                'Usuario.id' => $id
            ]
        ]);
    }

    public function atualizaTelefone($usuario_id, $ddi, $telefone){
        return $this->save([
            'id' => $usuario_id,
            'telefone_ddi' => $ddi,
            'telefone' => $telefone
        ]);
    }

    public function getUsersByClientId($client_id) {
        return $this->find('all',[
            'fields' => [
                'id',
                'nome',
                'img'
            ],
            'conditions' => [
                'Usuario.cliente_id' => $client_id
            ],
            'link' => []
        ]);
    }

	public function getByLastLocation($pais = null, $estado = null, $cidade = null) {

        if ( $pais == null || $estado == null || $cidade == null ) {
            return [];
        }
    
        return $this->find('all',[
            'fields' => [
                'Usuario.id',
                'Usuario.nome',
                //'UsuarioLocalizacao.id',
                //'UsuarioLocalizacao.description',
            ],
            'conditions' => [
                'Usuario.cliente_id' => null,
                'UsuarioLocalizacao.description LIKE' => '%' . $pais . '%',
                'UsuarioLocalizacao.description LIKE' => '%' . $estado . '%',
                'OR' => [
                    'REPLACE(UsuarioLocalizacao.description, "\'", "") LIKE' => '%' . $cidade . '%',// Trata nomes com apóstofos com sant'ana do livramento
                    'UsuarioLocalizacao.description LIKE' => '%' . $cidade . '%'
                ]
            ],
            'link' => [
                'UsuarioLocalizacao' => [
                    'conditions' => [
                        'UsuarioLocalizacao.id = (
                            SELECT MAX(ul.id)
                            FROM usuarios_localizacoes ul
                            WHERE ul.usuario_id = Usuario.id
                        )'
                    ]
                ]
            ],
            'group' => [
                'Usuario.id'
            ]
        ]);
	}




}