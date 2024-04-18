<?php
class AgendamentosController extends AppController {
    
    public $helpers = array('Html', 'Form');
    public $components = array('RequestHandler');

    public function beforeFilter() {
        parent::beforeFilter();
        header("Access-Control-Allow-Origin: *");
    }

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['agendamento_id']) || $dados['agendamento_id'] == "" ) {
            throw new BadRequestException('Agendamento não informado!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $agendamento_id = $dados['agendamento_id'];

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteSubcategoria');

        $conditions = [
            'Agendamento.id' => $agendamento_id,
            'not' => [
                'Agendamento.cancelado' => 'Y'
            ]
        ];

        $agendamento = $this->Agendamento->find('first',[
            'conditions' => $conditions,
            'fields' => [
                'Agendamento.*',
                'ClienteCliente.id',
                'ClienteCliente.nome',
                'ClienteCliente.telefone',
                'ClienteCliente.telefone_ddi',
                'ClienteCliente.pais',
                'Cliente.id',
                'Cliente.nome',
                'ClienteCliente.img',
                'ClienteCliente.endereco',
                'ClienteCliente.endreceo_n',
                'Localidade.loc_no',
                'ClienteServico.id',
                'ClienteServico.valor',
                'ClienteServico.nome'
            ],
            'link' => ['ClienteCliente' => ['Localidade'], 'Cliente', 'ClienteServico']
        ]);

        if ( count($agendamento) > 0 ) {
            $agendamento['Agendamento']['tipo'] = 'padrao';

            if ( $agendamento['Agendamento']['dia_semana'] != '' ) {
                if (!isset($dados['horario'])) {
                    $agendamento = [];
                } else {
                    $dia_semana_horario_informado = date("w", strtotime($dados['horario']));
                    if ( $dia_semana_horario_informado == $agendamento['Agendamento']['dia_semana'] ) {
                        $agendamento['Agendamento']['horario'] = $dados['horario'];
                        $agendamento['Agendamento']['tipo'] = 'fixo';
                    } else {
                        $agendamento = [];
                    }
                }
            }
            else if ( $agendamento['Agendamento']['dia_mes'] != '' ) {
                if (!isset($dados['horario'])) {
                    $agendamento = [];
                } else {
                    $dia_mes_horario_informado = date("d", strtotime($dados['horario']));
                    if ( $dia_mes_horario_informado == $agendamento['Agendamento']['dia_mes'] ) {
                        $agendamento['Agendamento']['horario'] = $dados['horario'];
                        $agendamento['Agendamento']['tipo'] = 'fixo';
                    } else {
                        $agendamento = [];
                    }
                }
            }
        }

        if ( count($agendamento) > 0 ) {
            $agendamento['ClienteCliente']['img'] = $this->images_path . 'clientes_clientes/' . $agendamento['ClienteCliente']['img'];
            $agendamento['Agendamento']['horario_str'] = date('d/m',strtotime($agendamento['Agendamento']['horario']))." às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
            $data_agendamento = date('Y-m-d',strtotime($agendamento['Agendamento']['horario']));
            
            $agendamento['ClienteServico']['valor_br'] = number_format($agendamento['ClienteServico']['valor'], 2, ',', '.');
            $agendamento['Cliente']['isCourt'] = $this->ClienteSubcategoria->checkIsCourt($agendamento['Cliente']['id']);

            if ( $data_agendamento == date('Y-m-d') ) {
                $agendamento['Agendamento']['horario_str'] = "Hoje às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
            }
        }
        
        
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $agendamento))));
    }

    public function agendamentos() {

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

        $this->loadModel('ClienteCliente');

        $cancelable = null;
     
        if ( $dados_token['Usuario']['cliente_id'] != '' && $dados_token['Usuario']['cliente_id'] != null ) {

            if ( !isset($dados['cliente_cliente_id']) || $dados['cliente_cliente_id'] == "" ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => []))));
            }

            $meus_ids_de_cliente = [$dados['cliente_cliente_id']];
            $cancelable = true;

        } else {            
            $meus_ids_de_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_token['Usuario']['id'], true);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteHorarioAtendimento');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');
        $this->loadModel('AgendamentoFixoCancelado');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('AgendamentoConvite');

        $agendamentos = $this->Agendamento->buscaAgendamentoUsuario($meus_ids_de_cliente);
        $agendamentos = $this->ClienteHorarioAtendimentoExcessao->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->ClienteHorarioAtendimento->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->AgendamentoFixoCancelado->checkStatus($agendamentos);

        if ( count($agendamentos) > 0 ) {
            usort($agendamentos, function($a, $b) {
                return $a['Agendamento']['horario'] <=> $b['Agendamento']['horario'];
            });

            foreach($agendamentos as $key => $agendamento){
                
                $agendamentos[$key]['Agendamento']['horario_str'] = date('d/m',strtotime($agendamento['Agendamento']['horario']))." às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
                $agendamentos[$key]['Cliente']['logo'] = $this->images_path.'clientes/'.$agendamento['Cliente']['logo'];
                $agendamentos[$key]['Agendamento']['data'] = date('d/m/Y',strtotime($agendamento['Agendamento']['horario']));
                $agendamentos[$key]['Agendamento']['hora'] = date('H:i',strtotime($agendamento['Agendamento']['horario']));
                $agendamentos[$key]['Agendamento']['tipo'] = 'padrao';

                $horario = $agendamento['Agendamento']['horario'];
                $duracao = $agendamento['Agendamento']['duracao'];

                $dateTime = new DateTime($horario);
                list($hours, $minutes, $seconds) = explode(':', $duracao);
                $interval = new DateInterval("PT{$hours}H{$minutes}M{$seconds}S");
                $dateTime->add($interval);;

                $fim_agendamento = $dateTime->format('H:i');

                $agendamentos[$key]['Agendamento']['fim_agendamento'] = $fim_agendamento;
                
                if ( isset($agendamento['Agendamento']['torneio_id']) && $agendamento['Agendamento']['torneio_id'] != null ) 
                    $agendamentos[$key]['Agendamento']['tipo'] = 'tournament';

                if ( isset($agendamentos[$key]['ClienteServico']['valor']) )
                    $agendamentos[$key]['ClienteServico']['valor_br'] = number_format($agendamentos[$key]['ClienteServico']['valor'], 2, ',', '.');
                else
                    $agendamentos[$key]['ClienteServico']['valor_br'] = number_format(0, 2, ',', '.');
                $agendamentos[$key]['Cliente']['isCourt'] = $this->ClienteSubcategoria->checkIsCourt($agendamento['Cliente']['id']);

                if ( isset($agendamento['AgendamentoConvite']) ) {
                    $agendamentos[$key]['Agendamento']['tipo'] = 'convidado';
                    $agendamentos[$key]['Agendamento']['convidado_por'] = [
                        'nome' => $agendamento['ClienteCliente']['nome'],
                        'foto' => $this->images_path.'usuarios/'.$agendamento['Usuario']['img'],
                    ];
                }
                else if ( $agendamento['Agendamento']['dia_semana'] != '' || $agendamento['Agendamento']['dia_mes'] != '' ) {
                    $agendamentos[$key]['Agendamento']['tipo'] = 'fixo';
                }

                $agendamentos[$key]['Agendamento']['_usuarios_confirmados'] = $this->AgendamentoConvite->getConfirmedUsers($agendamento['Agendamento']['id'], $this->images_path.'/usuarios/', $agendamento['Agendamento']['horario']);

                if ($cancelable === null) {
                    if ( $agendamentos[$key]['Agendamento']['tipo'] == 'tournament' ) 
                        $cancelable_return = false;
                    else 
                        $cancelable_return = $this->checkIsCancelable($agendamento['Agendamento']['horario'], $agendamento['Cliente']['prazo_maximo_para_canelamento'],$agendamentos[$key]['Agendamento']['tipo']);
                } else {
                    if ( $agendamentos[$key]['Agendamento']['tipo'] == 'tournament' ) 
                        $cancelable_return = false;
                    else
                        $cancelable_return = true;
                }

                $agendamentos[$key]['Agendamento']['cancelable'] = $cancelable_return;
                unset($agendamentos[$key]['AgendamentoConvite']);
            }
        }
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $agendamentos))));
    }

    public function empresa() {

        $this->layout = 'ajax';
        $dados = $this->request->query;
        if ( !isset($dados['token']) || $dados['token'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['email']) || $dados['email'] == "" ) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }
        if ( !isset($dados['data']) || $dados['data'] == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }
        if ( !isset($dados['type']) || $dados['type'] == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }

        $token = $dados['token'];
        $email = $dados['email'];
        $data = $dados['data'];
        $type = $dados['type'];
        $year_week = date('oW',strtotime($data. ' +1 day'));

        $dados_token = $this->verificaValidadeToken($token, $email);

        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteHorarioAtendimento');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');
        $this->loadModel('AgendamentoFixoCancelado');

        $aditional_conditions = [];

        if ( isset($dados["cliente_cliente_id"]) && $dados["cliente_cliente_id"] ) {
            $aditional_conditions["Agendamento.cliente_cliente_id"] = $dados["cliente_cliente_id"];
        }

        if ( isset($dados["services_ids"]) ) {
            $aditional_conditions["Agendamento.servico_id"] = $dados["services_ids"];
        }

        $agendamentos = $this->Agendamento->buscaAgendamentoEmpresa($dados_token['Usuario']['cliente_id'],$type,$data,$year_week,$aditional_conditions);
        $agendamentos = $this->ClienteHorarioAtendimentoExcessao->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->ClienteHorarioAtendimento->checkStatus($agendamentos);//obs, não inverter a ordem senão as excessoes serão ignoradas
        $agendamentos = $this->AgendamentoFixoCancelado->checkStatus($agendamentos);
        //debug($agendamentos); die();

        if ( count($agendamentos) > 0 ) {
            usort($agendamentos, function($a, $b) {
                return $a['Agendamento']['horario'] <=> $b['Agendamento']['horario'];
            });
        
            /*foreach($agendamentos as $key => $agendamento){
                
                $agendamentos[$key]['Agendamento']['horario_str'] = date('d/m',strtotime($agendamento['Agendamento']['horario']))." às " . date('H:i',strtotime($agendamento['Agendamento']['horario']));
                $agendamentos[$key]['Cliente']['logo'] = $this->images_path.'clientes/'.$agendamento['Cliente']['logo'];
                $agendamentos[$key]['Agendamento']['data'] = date('d/m/Y',strtotime($agendamento['Agendamento']['horario']));
                $agendamentos[$key]['Agendamento']['hora'] = date('H:i',strtotime($agendamento['Agendamento']['horario']));
            }*/
        }
    

        $dados_retornar = $this->formataAgendamentos($agendamentos, $data, $type);
        //$dados_retornar = json_encode($dados_retornar, true);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_retornar))));
    }

    private function formataAgendamentos($agendamentos = [], $data = '', $type = '') {

        if ( $data == '' )
            return [];
        if ( $type == '' )
            return [];

        $php_date = new DateTime($data);
        $day_of_week = $php_date->format("w");
        $php_date->modify("-".($day_of_week)." days");
        $primeiro_dia_semana = $php_date->format('Y-m-d');
        $primeiro_dia_mes = date('Y-m-01',strtotime($data));
        $ultimo_dia_mes = date("t-m-Y", strtotime($data));

        $arr_retornar = [];

        if ($type == 1) {
            $proxima_data = $primeiro_dia_mes;
            while (strtotime($proxima_data) <= strtotime($ultimo_dia_mes)) {
                $arr_retornar[$proxima_data] = [];
                $proxima_data = date('Y-m-d',strtotime($proxima_data." + 1 days"));
            }

        } else if ( $type == 2 ) {
            
            $proxima_data = $primeiro_dia_semana;
            for ($i = 0; $i <= 6; $i++) {
                $arr_retornar[$proxima_data] = [];
                $proxima_data = date('Y-m-d',strtotime($proxima_data." + 1 days"));
            }
        }

        $last_data = '';
        $count = -1;
        if ( count($agendamentos) > 0 ) {
            $ultimo_horario = "";
            $cor = $this->list_even_color;
            foreach( $agendamentos as $key => $agend) {
                $hora = date('H:i',strtotime($agend['Agendamento']['horario']));
                $data = date('Y-m-d',strtotime($agend['Agendamento']['horario']));
                $duracao = $agend['Agendamento']['duracao'];

                if ( $duracao != '') {
                    $timeBase = new DateTime($agend['Agendamento']['horario']);
                    list($hours,$minutes,$seconds) = explode(':',$duracao);
                    $timeToAdd = new DateInterval('PT'.$hours.'H'.$minutes.'M'.$seconds.'S'); 
                    $timeBase->add($timeToAdd);
                    $duracao = $timeBase->format('H:i');
                }

                $tipo = "Padrão";
                $imagem = $this->images_path;
    
                if ( $agend['Agendamento']['dia_semana'] != null ||  $agend['Agendamento']['dia_mes'] != null ) {
                    $tipo = "Fixo";
                }

                if ( isset($agend['Agendamento']['torneio_id']) && $agend['Agendamento']['torneio_id'] != null ) {
                    $tipo = "Torneio";
                    $agend['ClienteCliente']['nome'] = "Jogo de Torneio";
                    $imagem .= "torneios/".$agend['Torneio']['img'];
                } else {
                    $imagem .= 'clientes_clientes/'.$agend['ClienteCliente']['img'];
                }

                if ( $ultimo_horario != $agend['Agendamento']['horario'] ) {
                    $ultimo_horario = $agend['Agendamento']['horario'];
                    $cor = ($cor == $this->list_odd_color) ? $this->list_even_color : $this->list_odd_color;
                }

                $arr_dados = [
                    'name' => $hora, 
                    'admin_id' => isset($agend['ClienteServico']['id']) ?  $agend['ClienteServico']['id'] : $agend['Agendamento']['cliente_id'], 
                    'height' => $agend['Agendamento']['endereco'] == '' || $agend['Agendamento']['endereco'] == '' ? 100 : 130, 
                    "bg_color" => $cor,
                    'usuario' => $agend['ClienteCliente']['nome'], 
                    'id' => $agend['Agendamento']['id'], 
                    'termino' => $duracao,
                    'img' => $imagem,
                    'servico' => $agend['ClienteServico']['nome'], 
                    'status' => $agend['Agendamento']['status'], 
                    'motive' => $agend['Agendamento']['motive'], 
                    'horario' => $agend['Agendamento']['horario'], 
                    'endereco' => $agend['Agendamento']['endereco'], 
                    'tipo_str' => $tipo,
                ];

                if ( $data != $last_data ) {
                    $count++;
                    $arr_retornar[$data][] = $arr_dados;
                    $last_data = $data;
                } else {
                    $arr_retornar[$data][] = $arr_dados;
                }
            }
        }

        return $arr_retornar;

    }

    private function checkIsCancelable($horario, $prazo_maximo,$tipo) {
        
        if ( $tipo == 'convidado' ) {//se o agendamento é originado de um convite, não é possível cancelar
            return false;
        }

        if ($prazo_maximo == null || $prazo_maximo == '') {//se a empresa nào setou prazo para cancelamento, é possível cancelar
            return true;
        }

        list($horas,$minutos,$segundos) = explode(':',$prazo_maximo);

        $hs_in_unix = 0;
        if ($horas > 0) {
            $hs_in_unix = $horas * 60 * 60;
        }

        $min_in_unix = 0;
        if ($minutos > 0) {
            $min_in_unix = $minutos * 60;
        }

        $horario_unix = strtotime($horario);
        $horario_maximo_unix = $horario_unix-$hs_in_unix-$min_in_unix;
        $now_unix = strtotime(date('Y-m-d H:i:s'));
        /*debug($horario);
        debug(date('d/m/Y H:i',$horario_maximo_unix));
        debug(date('d/m/Y H:i',$now_unix));
        echo 'unix_max = '.$horario_maximo_unix.'<br>';
        echo 'agora ='.$now_unix.'<br>';*/

        return $horario_maximo_unix >= $now_unix;

    }

    public function add(){

        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        //$this->log($dados, 'debug');
        //die();

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        } elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->day) || $dados->day == "" ) {
            throw new BadRequestException('Data não informada!', 401);
        }

        if ( !isset($dados->time) || $dados->time == "" ) {
            throw new BadRequestException('Hora não informada!', 401);
        }

        if ( !isset($dados->servico_id) || $dados->servico_id == "" || !is_numeric($dados->servico_id) ) {
            throw new BadRequestException('Serviço não informado!', 401);
        }        

        $data_selecionada = $dados->day;
        $horario_selecionado = $dados->time;

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');
        $this->loadModel('ClienteCliente');
        $this->loadModel('ClienteHorarioAtendimentoExcessao');
        $this->loadModel('Cliente');        
        $this->loadModel('Token');
        $this->loadModel('ClienteServico');

        $dados_servico = $this->ClienteServico->find("first",[
            "conditions" => [
                "ClienteServico.id" => $dados->servico_id,
                "ClienteServico.ativo" => 'Y'
            ],
            'link' => []
        ]);

        if ( count($dados_servico) === 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Dados do serviço não encontrados.'))));
        }

        //verifico quem está tentando salvar o agendamento, se é uma empresa ou um usuário
        if ( $dados_usuario['Usuario']['cliente_id'] != '' && $dados_usuario['Usuario']['cliente_id'] != null ) {

            if ( !isset($dados->client_client_id) || $dados->client_client_id == "" ) {
                throw new BadRequestException('Cliente não informado!', 401);
            }
    
            $cliente_id = $dados_usuario['Usuario']['cliente_id'];
            $cliente_cliente_id = $dados->client_client_id;
            $cadastrado_por = 'cliente';
            $dados_cliente_cliente = $this->ClienteCliente->buscaDadosClienteCliente($cliente_cliente_id, $dados->cliente_id);
    
            if ( !$dados_cliente_cliente || count($dados_cliente_cliente) == 0) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Lamentamos. Não conseguimos encontrar os dados do cliente! ;('))));
            }

        } else {
            $cadastrado_por = 'cliente_cliente';

            if ( !isset($dados->cliente_id) || $dados->cliente_id == "" || !is_numeric($dados->cliente_id) ) {
                throw new BadRequestException('Dados da empresa não informada!', 401);
            }

            //busca os dados do usuário do agendamento como cliente
            $dados_usuario_como_cliente = $this->ClienteCliente->buscaDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], $dados->cliente_id);
    
            if ( !$dados_usuario_como_cliente || count($dados_usuario_como_cliente) == 0) {
                $dados_usuario_como_cliente = $this->ClienteCliente->criaDadosComoCliente($dados_usuario['Usuario']['id'], $dados->cliente_id);
                if ( !$dados_usuario_como_cliente || count($dados_usuario_como_cliente) == 0) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Lamentamos. Não conseguimos encontrar seus dados! ;('))));
                }

            }

            $cliente_id = $dados->cliente_id;
            $cliente_cliente_id = $dados_usuario_como_cliente['ClienteCliente']['id'];
    
        }

        if ( isset($dados->domicilio) && $dados->domicilio == true ) {
            if (!isset($dados->endereco) || $dados->endereco == '') {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Endereço de atendimento não informado.'))));
            }
        }

        //busca os dados da empresa
        $dados_cliente = $this->Cliente->find('first',[
            'fields' => ['Cliente.id', 'Localidade.loc_no', 'Localidade.ufe_sg', 'ClienteConfiguracao.*'],
            'conditions' => [
                'Cliente.id' => $cliente_id,
                'Cliente.ativo' => 'Y'
            ],
            'link' => ['Localidade', 'ClienteConfiguracao']
        ]);

        if (count($dados_cliente) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Empresa não encontrada!'))));
        }

        //verfica se o cliente fechará excepcionalmente nesse dia no dia
        $verificaFechamento = $this->ClienteHorarioAtendimentoExcessao->verificaExcessao($cliente_id, $data_selecionada, 'F');
        if ( count($verificaFechamento) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'A empresa não atenderá no dia e horário escolhido!'))));
        }

        //verifica se o usuário já não possui um agendamento pro mesmo dia e horário que está tentando
        $verificaAgendamento = $this->Agendamento->verificaAgendamento($cliente_cliente_id, null, $data_selecionada, $horario_selecionado);
        if ( $verificaAgendamento !== false && count($verificaAgendamento) > 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Você já tem um agendamento neste dia e hora!'))));
        }

        // Busca os horários do serviço disponíveis para o dia selecionado
        $horarios = $this->quadra_horarios($dados->servico_id, $data_selecionada, $dados_cliente['ClienteConfiguracao']['horario_fixo']);

        if ( count($horarios) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. A empresa não informou os horários de atendimento deste serviço nesse dia! ;('))));
        }

        $horario_x_horario_selecionado = [];
        foreach( $horarios as $key => $horario ){
            if ( $horario['time'] === $horario_selecionado ) {
                $horario_x_horario_selecionado = $horario;
            }
        }

        if ( count($horario_x_horario_selecionado) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. Não encontramos os dados do horário selecionado! ;('))));
        }

        // Se o horário selecionado não está dispnível
        if ( !$horario_x_horario_selecionado['active'] ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. ' . $horario_x_horario_selecionado['motivo'] . ' ;('))));
        }
        
        $agendamento_dia_semana = null;
        $agendamento_dia_mes = null;

        //verifica se o usuário/empresa está tentando salvar um agendamento fixo
        if ( isset($dados->fixo) && $dados->fixo == true ) {

            // Se o agendamento fixo não está disponível para o horário
            if (!$horario_x_horario_selecionado['enable_fixed_scheduling'] ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. Esse horário fixo já pertence a outro usuário! ;('))));
            }
  
            if ( $dados_cliente['ClienteConfiguracao']['horario_fixo'] == null ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Lamentamos. Ocorreu um erro ao buscar as configurações da empresa selecionada! ;('))));
            }
            
            if ( $dados_cliente['ClienteConfiguracao']['horario_fixo'] != 'Y' || $dados_cliente['ClienteConfiguracao']['fixo_tipo'] == 'Nenhum' ) {
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Infelizmente a empresa selecioanda não aceita agendamentos fixos'))));
            }

            if ( $dados_cliente['ClienteConfiguracao']['fixo_tipo'] == 'Semanal' ) {
                $agendamento_dia_semana = date('w',strtotime($data_selecionada.' '.$horario_selecionado));
            }
            else if ( $dados_cliente['ClienteConfiguracao']['fixo_tipo'] == 'Mensal' ) {
                $agendamento_dia_mes = (int)date('d',strtotime($data_selecionada.' '.$horario_selecionado));
            }
        }

        $dados_salvar = [
            'cliente_id' => $cliente_id,
            'cliente_cliente_id' => $cliente_cliente_id,
            'servico_id' => $dados->servico_id,
            'horario' => $data_selecionada.' '.$horario_selecionado,
            'domicilio' => !$dados->domicilio ? 'N' : 'Y',
            'endereco' => $dados->endereco,
            'dia_semana' => $agendamento_dia_semana,
            'dia_mes' => $agendamento_dia_mes,
            'duracao' => $horario_x_horario_selecionado['duration'],
        ];

        if ( isset($dados->convites_tpj) && is_array($dados->convites_tpj)) {
            $dados->convites_tpj = (object)$dados->convites_tpj;
        }

        if ( isset($dados->convites_grl) && is_array($dados->convites_grl)) {
            $dados->convites_grl = (object)$dados->convites_grl;
        }

        $this->Agendamento->create();
        $this->Agendamento->set($dados_salvar);
        $dados_agendamento_salvo = $this->Agendamento->save($dados_salvar);
        //$dados_agendamento_salvo = true;
        if ( !$dados_agendamento_salvo ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cadastrar seu agendamento!'))));
        }

        //busca os ids do onesignal do usuário a ser notificado do cadastro do horário
        if ( $cadastrado_por == 'cliente' ) {
            $notifications_ids = $this->Token->getIdsNotificationsUsuario($dados_cliente_cliente['ClienteCliente']['usuario_id']);
            $cadastrado_por = $this->Cliente->findEmpresaNomeById($cliente_id);
        } else {
            $notifications_ids = $this->Token->getIdsNotificationsEmpresa($cliente_id);
            $cadastrado_por = $dados_usuario['Usuario']['nome'];
        }

        if ( count($notifications_ids) > 0 ) {
  
            $data_str_agendamento = date('d/m',strtotime($dados_agendamento_salvo['Agendamento']['horario']));
            $hora_str_agendamento = date('H:i',strtotime($dados_agendamento_salvo['Agendamento']['horario']));
            $notification_msg = "Você tem um novo agendamento de ".$cadastrado_por." às ".$hora_str_agendamento." do dia ".$data_str_agendamento;

            if ( $dados_servico["ClienteServico"]["tipo"] === "Quadra" ) {
                $notification_msg .= " na quadra " . $dados_servico["ClienteServico"]["nome"];
            } else {
                $notification_msg .= " serviço selecionado: " . $dados_servico["ClienteServico"]["nome"];
            }

            $this->sendNotification( 
                $notifications_ids, 
                $dados_agendamento_salvo['Agendamento']['id'],
                "Novo Agendamento :)", 
                $notification_msg, 
                "agendamento", 
                'novo_agendamento', 
                ["en"=> '$[notif_count] Novos Agendamentos']
            );
        }

        $this->enviaConvites($dados, $dados_agendamento_salvo, $dados_cliente['Localidade']);
        
        return new CakeResponse([
            'type' => 'json', 
            'body' => json_encode(
                [
                    'status' => 'ok', 
                    'msg' => 'Tudo certo! Agendamento cadastrado com sucesso!',
                    'cliente_cliente_id' => $cliente_cliente_id
                ]
            )
        ]);
    }

    public function convitesAdicionais(){
        $this->layout = 'ajax';
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), false);
        }elseif ( gettype($dados) == 'array' ) {
            $dados = json_decode(json_encode($dados), false);
        }


        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->cliente_id) || $dados->cliente_id == "" || !is_numeric($dados->cliente_id) ) {
            throw new BadRequestException('Dados da empresa não informada!', 401);
        }

        if ( !isset($dados->horaSelecionada) || $dados->horaSelecionada == "" ) {
            throw new BadRequestException('Hora não informada!', 401);
        }

        list($data_selecionada, $horario_selecionado) = explode(' ',$dados->horaSelecionada->horario);

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        $this->loadModel('ClienteCliente');
        $this->loadModel('Agendamento');
        $this->loadModel('ClienteSubcategoria');
        $this->loadModel('Cliente');

        //busca os dados da empresa
        $dados_cliente = $this->Cliente->find('first',[
            'fields' => ['Cliente.id', 'Localidade.loc_no', 'Localidade.ufe_sg'],
            'conditions' => [
                'Cliente.id' => $dados->cliente_id,
                'Cliente.ativo' => 'Y'
            ],
            'link' => ['Localidade']
        ]);

        if (count($dados_cliente) == 0) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Empresa não encontrada!'))));
        }

        $agendamento_dia_semana = date('w',strtotime($data_selecionada.' '.$horario_selecionado));
        $agendamento_dia_mes = (int)date('d',strtotime($data_selecionada.' '.$horario_selecionado));

        //verifica se a empresa é uma quadra, se não for, nào sào permitidos convites
        $isCourt = $this->ClienteSubcategoria->checkIsCourt($dados->cliente_id);

        if (!$isCourt) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O agendamento não pertence a uma quadra!'))));
        }
        
        //busca os dados do usuário do agendamento como cliente
        $dados_usuario_como_cliente = $this->ClienteCliente->buscaTodosDadosUsuarioComoCliente($dados_usuario['Usuario']['id'], true);
        $dados_agendamento = $this->Agendamento->find('first',[
            'conditions' => [
                'Agendamento.cliente_id' => $dados->cliente_id,
                'Agendamento.cliente_cliente_id' => $dados_usuario_como_cliente,
                'TIME(Agendamento.horario)' => $horario_selecionado,
                'Agendamento.cancelado' => 'N',
                'or' => [
                    [
                        'DATE(Agendamento.horario)' => $data_selecionada,
                        'Agendamento.dia_semana' => null,
                        'Agendamento.dia_mes' => null,
                    ],[
                        'Agendamento.dia_semana' => $agendamento_dia_semana,
                    ],[
                        'Agendamento.dia_mes' => $agendamento_dia_mes,
                    ]
                ]
            ],
            'link' => []
        ]);

        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Agendamento não encontrado!'))));
        }

        if ( isset($dados->convites_tpj) && is_array($dados->convites_tpj)) {
            $dados->convites_tpj = (object)$dados->convites_tpj;
        }

        if ( isset($dados->convites_grl) && is_array($dados->convites_grl)) {
            $dados->convites_grl = (object)$dados->convites_grl;
        }

        $dados_agendamento['Agendamento']['horario'] = $dados->horaSelecionada->horario;

        $this->enviaConvites($dados, $dados_agendamento, $dados_cliente['Localidade']);
        
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Tudo certo! Convites enviados com sucesso!'))));
    }

    private function enviaConvites ($dados, $dados_agendamento_salvo, $cliente_localizacao) {

        $this->loadModel('Usuario');
        $this->loadModel('UsuarioLocalizacao');
    
        $clientes_clientes_ids_convidados = [];

        //convites do to pro jogo
        if (isset($dados->convites_tpj) && count(get_object_vars($dados->convites_tpj)) > 0) {
            foreach($dados->convites_tpj as $key => $convite){
                if($convite) {
                    list($discard, $id_convidado) = explode('_',$key);
                    $clientes_clientes_ids_convidados[] = $id_convidado;
                }
            }
        }

        //convites geral
        if (isset($dados->convites_grl) && count(get_object_vars($dados->convites_grl)) > 0) {
            $usuarios_perfil_convite = $this->Usuario->getClientDataByPadelistProfile($dados->convites_grl);
            $usuarios_perfil_convite = $this->UsuarioLocalizacao->filterByLastLocation($usuarios_perfil_convite, $cliente_localizacao);
            $clientes_clientes_ids_convidados = array_merge($clientes_clientes_ids_convidados, $usuarios_perfil_convite);
            $clientes_clientes_ids_convidados = array_values($clientes_clientes_ids_convidados);
        }

        if ( count($clientes_clientes_ids_convidados) > 0 ) {
            $this->saveInviteAndSendNotification($clientes_clientes_ids_convidados, $dados_agendamento_salvo['Agendamento']);
        }

    }

    public function excluir(){
        $this->layout = 'ajax';
        //$dados = json_decode($this->request->data['dados']);
        $dados = $this->request->data['dados'];

        if ( gettype($dados) == 'string' ) {
            $dados = json_decode($dados);
            $dados = json_decode(json_encode($dados), true);
        }

        $dados = (object)$dados;

        if ( !isset($dados->token) || $dados->token == "" ||  !isset($dados->email) || $dados->email == "" || !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException('Dados de usuário não informado!', 401);
        }

        if ( !isset($dados->agendamento_id) || $dados->agendamento_id == "" ) {
            throw new BadRequestException('ID não informado!', 401);
        }

        if ( !isset($dados->tipo) || $dados->tipo == "" ) {
            throw new BadRequestException('Tipo não informado!', 401);
        }

        if ( !isset($dados->horario) || $dados->horario == "" ) {
            throw new BadRequestException('Horário não informado!', 401);
        }

        $dados_usuario = $this->verificaValidadeToken($dados->token, $dados->email);

        if ( !$dados_usuario ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }

        $this->loadModel('Agendamento');

        $conditions = [
            'Agendamento.id' => $dados->agendamento_id,
        ];

        $cancelado_por = 'cliente';
        if ( $dados_usuario['Usuario']['cliente_id'] != '' ) {
            $conditions = array_merge($conditions, [
                'Agendamento.cliente_id' => $dados_usuario['Usuario']['cliente_id']
            ]);
        } else {
            $conditions = array_merge($conditions, [
                'ClienteCliente.usuario_id' => $dados_usuario['Usuario']['id']
            ]);
            $cancelado_por = 'cliente_cliente';
        }

        $dados_agendamento = $this->Agendamento->find('first',[
            'fields' => [
                'Agendamento.id', 
                'Agendamento.horario', 
                'Agendamento.dia_semana', 
                'Agendamento.dia_mes',  
                'ClienteCliente.*',
                'Cliente.id',
                'Cliente.nome',
                'Usuario.id', 
                'Usuario.nome'
            ],
            'conditions' => $conditions,
            'link' => [
                'ClienteCliente' => ['Usuario'], 'Cliente'
            ]
        ]);
       
        if ( count($dados_agendamento) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'O agendamento que você está tentando exlcuir, não existe!'))));
        }

        if ( $dados_agendamento['Agendamento']['dia_semana'] != '' || $dados_agendamento['Agendamento']['dia_mes'] != '' ) {
            if ( $dados->tipo == 1 ) {
                $this->loadModel('AgendamentoFixoCancelado');
                $dados_salvar = [
                    'agendamento_id' => $dados_agendamento['Agendamento']['id'],
                    'cliente_cliente_id' => $dados_agendamento['ClienteCliente']['id'],
                    'horario' => $dados->horario,
                    'cancelado_por' => $cancelado_por,
                    'cancelado_por_id' => $dados_usuario['Usuario']['id'],
                ];

                if ( !$this->AgendamentoFixoCancelado->save($dados_salvar) ) {
                    return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cancelar o agendamento. Por favor, tente novamente mais tarde!'))));
                }

                $this->avisaConvidadosCancelamento($dados_agendamento, $dados);
                $this->enviaNotificacaoDeCancelamento($cancelado_por, $dados_agendamento);
                return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Agendamento cancelado com sucesso!'))));

            } 
        }

        $dados_salvar['Agendamento']['id'] = $dados_agendamento['Agendamento']['id'];
        $dados_salvar['Agendamento']['cancelado'] = 'Y';        
        $dados_salvar['Agendamento']['cancelado_por_id'] = $dados_usuario['Usuario']['id'];

        if ( !$this->Agendamento->save($dados_salvar) ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar cancelar o agendamento. Por favor, tente mais tarde!'))));
        }
        
        $this->avisaConvidadosCancelamento($dados_agendamento, $dados);
        $this->enviaNotificacaoDeCancelamento($cancelado_por, $dados_agendamento);
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Agendamento cancelado com sucesso!'))));

    }
}