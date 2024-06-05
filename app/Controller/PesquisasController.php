<?php

class PesquisasController extends AppController {

    public $components = array('RequestHandler');

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        $token = $dados['token'];
        $email = null;

        if ( isset($dados['email']) ) {
            $email = $dados['email'];
        }

        $dados_usuario = $this->verificaValidadeToken($token, $email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Token expirado!', 401);
        }

        if ( !isset($dados['search']) || trim($dados['search']) === '' ){ 
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
        }

        $this->loadModel('Pesquisa');
        $this->loadModel('UsuarioLocalizacao');

        $localizacao_usuario = $this->UsuarioLocalizacao->filterByLastByTokenAndUserId($dados_usuario['Token']['id'], isset($dados_usuario['Usuario']['id']) ? $dados_usuario['Usuario']['id'] : null );

        $dados_salvar = [
            'token_id' => $dados_usuario['Token']['id'],
            'texto' => $dados['search'],
        ];

        if ( count($localizacao_usuario) > 0 ) {
            $dados_salvar['usuario_localizacao_id'] = $localizacao_usuario['UsuarioLocalizacao']['id'];
        }

        $this->Pesquisa->create();
        $this->Pesquisa->save($dados_salvar);

        $conditions_empresas = $conditions_servicos = $conditions_quadras = [
            'Cliente.ativo' => 'Y',
            'Cliente.mostrar' => 'Y',
            'OR' => [
                'Cliente.nome LIKE' => "%".$dados['search']."%",
                'Categoria.titulo LIKE' => "%".$dados['search']."%",                
                'Subcategoria.nome LIKE' => "%".$dados['search']."%"

            ]
        ];

        $conditions_servicos['OR']["ClienteServico.nome LIKE"] = "%".$dados['search']."%";
        $conditions_servicos['OR']["ClienteServico.descricao LIKE"] = "%".$dados['search']."%";
        $conditions_servicos["ClienteServico.tipo"] = 'Serviço';
    
        $conditions_quadras['OR']["ClienteServico.nome LIKE"] = "%".$dados['search']."%";
        $conditions_quadras['OR']["ClienteServico.descricao LIKE"] = "%".$dados['search']."%";
        $conditions_quadras["ClienteServico.tipo"] = 'Quadra';


        if ( count($localizacao_usuario) > 0 ) {

            $dados_endereco = explode(',', $localizacao_usuario['UsuarioLocalizacao']['description']);

            if ( isset($dados_endereco[1]) && (trim($dados_endereco[1]) == "Uruguai" || trim($dados_endereco[1]) == "Uruguay") ) {
                $this->loadModel('UruguaiCidade');
                $dados_localidade = $this->UruguaiCidade->findByGoogleAddress($dados_endereco[0]);

                $conditions_empresas = array_merge($conditions_empresas, [
                    'Cliente.ui_cidade' => $dados_localidade['UruguaiCidade']['id'],
                ]);

                $conditions_servicos = array_merge($conditions_servicos, [
                    'Cliente.ui_cidade' => $dados_localidade['UruguaiCidade']['id'],
                ]);

                $conditions_quadras = array_merge($conditions_quadras, [
                    'Cliente.ui_cidade' => $dados_localidade['UruguaiCidade']['id'],
                ]);

            } else {
                $this->loadModel('Localidade');
                $dados_localidade = $this->Localidade->findByGoogleAddress($dados_endereco);

                $conditions_empresas = array_merge($conditions_empresas, [
                    'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                    'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
                ]);

                $conditions_servicos = array_merge($conditions_servicos, [
                    'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                    'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
                ]);

                $conditions_quadras = array_merge($conditions_quadras, [
                    'Cliente.cidade_id' => $dados_localidade['Localidade']['loc_nu_sequencial'],
                    'Cliente.estado' => $dados_localidade['Localidade']['ufe_sg'],
                ]);
            }
        }

        $this->loadModel('Cliente');
        $this->loadModel('ClienteServico');
        $this->loadModel('ClienteHorarioAtendimento');
        $this->loadModel('ClienteSubcategoria');

        $clientes = $this->Cliente->find('all',[
            'fields' => [
                'Cliente.*',
                'Localidade.loc_no',
                'UruguaiCidade.nome',
                'UruguaiDepartamento.nome'
            ],
            'link' => [
                'ClienteSubcategoria' => ['Subcategoria' => ['Categoria']], 'Localidade', "UruguaiCidade", "UruguaiDepartamento"
            ],
            'conditions' => $conditions_empresas,
            'group' => [
                'Cliente.id'
            ],
            'order' => ['Cliente.nome']
        ]);

        $arr_clientes_ids = [];
        foreach($clientes as $key => $cliente) {

            $arr_clientes_ids[] = $cliente['Cliente']['id'];
            $clientes[$key]['Cliente']['logo'] = $this->images_path.'clientes/'.$clientes[$key]['Cliente']['logo'];
            $clientes[$key]['Horarios'] = $this->ClienteHorarioAtendimento->find('all',[
                'conditions' => [
                    'ClienteHorarioAtendimento.cliente_id' => $cliente['Cliente']['id']
                ],
                'link' => []
            ]);

            $clientes[$key]['Cliente']['atendimento_hoje'] = $this->procuraHorariosHoje($clientes[$key]['Horarios']);
            $subcategorias = $this->ClienteSubcategoria->find('list',[
                'fields' => [
                    'Subcategoria.nome',
                    'Subcategoria.nome'
                ],
                'conditions' => [
                    'ClienteSubcategoria.cliente_id' => $cliente['Cliente']['id'],
                ],
                'link' => [
                    'Subcategoria'
                ],
                'group' => [
                    'Subcategoria.nome'
                ]
            ]);

            $clientes[$key]['Cliente']['subcategorias_str'] = implode(", ", $subcategorias);
        }

        // Bsuca os serviços de acordo com a busca
        $servicos = $this->ClienteServico->find('all',[
            'fields' => [
                'ClienteServico.*'
            ],
            'conditions' => $conditions_servicos,
            'order' => [
                'ClienteServico.nome'
            ],
            'link' => [
                'Cliente' => ['ClienteSubcategoria' => ['Subcategoria' => ['Categoria']]],
                'ClienteServicoFoto' => [
                    'fields' => [
                        'id',
                        'imagem'
                    ]
                ]
            ],
            'group' => [
                'ClienteServico.id'
            ]
        ]);

        foreach($servicos as $key => $ser){

            $servicos[$key]['ClienteServico']['_valor'] = number_format($ser['ClienteServico']['valor'],2,',','.');
            $servicos[$key]["ClienteServico"]["_horarios"] = $this->quadra_horarios($ser['ClienteServico']['id'], date('Y-m-d'), false);           

            if ( !empty($ser['ClienteServicoFoto']['imagem']) ) {
                $servicos[$key]['ClienteServicoFoto'][0]['imagem'] = $this->images_path . "/servicos/" . $ser['ClienteServicoFoto']['imagem'];
            } else {
                $servicos[$key]['ClienteServicoFoto'][0]['imagem'] = $this->images_path . "/servicos/sem_imagem.jpeg";
            }

        }

        // Bsuca as quadras de acordo com a busca
        $quadras = $this->ClienteServico->find('all',[
            'fields' => [
                'ClienteServico.*'
            ],
            'conditions' => $conditions_quadras,
            'order' => [
                'ClienteServico.nome'
            ],
            'link' => [
                'Cliente' => ['ClienteSubcategoria' => ['Subcategoria' => ['Categoria']]],
                'ClienteServicoFoto' => [
                    'fields' => [
                        'id',
                        'imagem'
                    ]
                ]
            ],
            'group' => [
                'ClienteServico.id'
            ]
        ]);

        foreach($quadras as $key => $qua){

            $quadras[$key]['ClienteServico']['_valor'] = number_format($qua['ClienteServico']['valor'],2,',','.');
            $quadras[$key]["ClienteServico"]["_horarios"] = $this->quadra_horarios($qua['ClienteServico']['id'], date('Y-m-d'), false);           

            if ( !empty($qua['ClienteServicoFoto']['imagem']) ) {
                $quadras[$key]['ClienteServicoFoto'][0]['imagem'] = $this->images_path . "/servicos/" . $qua['ClienteServicoFoto']['imagem'];
            } else {
                $quadras[$key]['ClienteServicoFoto'][0]['imagem'] = $this->images_path . "/servicos/sem_imagem.jpeg";
            }

        }

        $dados_retornar = [];

        if ( count($clientes) > 0 ) {
            $dados_retornar[] = ['type' => 'Empresas', 'data' => $clientes];
        }

        if ( count($servicos) > 0 ) {
            $dados_retornar[] = ['type' => 'Serviços', 'data' => $servicos];
        }

        if ( count($quadras) > 0 ) {
            $dados_retornar[] = ['type' => 'Quadras', 'data' => $quadras];
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));

    }

    private function procuraHorariosHoje($horarios = null) {

        if ( $horarios == null || count($horarios) == 0) {
            return "Não atende hoje";
        }

        $retorno = "Não atende hoje";
        foreach($horarios  as $key => $horario){
            if( $horario['ClienteHorarioAtendimento']['horario_dia_semana'] == date('w',strtotime('Y-m-d')) ) {
                if ( $retorno == 'Não atende hoje' )
                    $retorno = "das ".substr($horario['ClienteHorarioAtendimento']['abertura'], 0, 5).' até '.substr($horario['ClienteHorarioAtendimento']['fechamento'],0, 5);
                else
                    $retorno .= " | das ".substr($horario['ClienteHorarioAtendimento']['abertura'], 0, 5).' até '.substr($horario['ClienteHorarioAtendimento']['fechamento'],0, 5);
  
            }
        }
        
        return $retorno;

    }
}