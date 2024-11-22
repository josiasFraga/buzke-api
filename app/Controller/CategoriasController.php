<?php

class CategoriasController extends AppController {

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        if ( $dados_token['Usuario']['nivel_id'] != 2 ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Categoria');
        $categorias = $this->Categoria->find('all',[
            'order' => [
                'Categoria.titulo'
            ],
            'link' => ['Subcategoria'],
            'conditions' => [
                'not' => [
                    'Subcategoria.id' => null
                ]
            ],
            'group' => ['Categoria.id']
        ]);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $categorias))));

    }

    public function em_promocao() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        
        $this->loadModel('Promocao');

        $dia_semana_atual = (int)date('w');

        $conditions = [
            'Cliente.mostrar' => 'Y',
            'Cliente.ativo' => 'Y',
            'Promocao.finalizada' => 'N',
            'OR' => [
                [
                    'Promocao.validade_ate_cancelar' => 'Y'
                ],
                [
                    'Promocao.validade_inicio <=' => date('Y-m-d H:i:s'),
                    'Promocao.validade_fim >=' => date('Y-m-d H:i:s'),
                ]
            ],
            'PromocaoDiaSemana.dia_semana' => $dia_semana_atual,
            'ClienteServicoHorario.dia_semana' => $dia_semana_atual,
            'ClienteServico.ativo' => 'Y'
        ];

        if ( isset($dados['address']) && $dados['address'] != '' ) {

            if ( isset($dados['address'][1]) && (trim($dados['address'][1]) == "Uruguai" || trim($dados['address'][1]) == "Uruguay") ) {
                $this->loadModel('UruguaiCidade');
                $dados_localidade = $this->UruguaiCidade->findByGoogleAddress($dados['address'][0]);

                $conditions = array_merge($conditions, [
                    'Cliente.ui_cidade' => $dados_localidade['UruguaiCidade']['id'],
                ]);

            } else {
                $this->loadModel('Localidade');
                $dados_localidade = $this->Localidade->findByGoogleAddress($dados['address']);

                $conditions = array_merge($conditions, [
                    'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                    'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
                ]);
            }
        } else {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        $this->loadModel('ClienteServico');


        $categorias = $this->ClienteServico->find('all',[
            'fields' => [
                'Categoria.*',
            ],
            'link' => [
                'Cliente' => [
                    'ClienteSubcategoria' => [
                        'Subcategoria' => [
                            'Categoria'
                        ]
                    ]
                ],
                'ClienteServicoHorario',
                'PromocaoServico' => [
                    'Promocao' => [
                        'PromocaoDiaSemana'
                    ]
                ]
            ],
            'conditions' => $conditions,
            'group' => ['Categoria.id']
        ]);

        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $categorias))));

    }
}