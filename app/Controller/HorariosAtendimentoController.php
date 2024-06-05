<?php
class HorariosAtendimentoController extends AppController {

    public function index() {

        $this->layout = 'ajax';
        $dados = $this->request->query;

        $conditions = [];

        if ( isset($dados['cliente_id']) && !empty($dados['cliente_id']) ) {            
            $cliente_id = $dados['cliente_id'];
        } else if ( isset($dados['token']) && !empty($dados['token']) && isset($dados['email']) && !empty($dados['email']) ) {
    
            $token = $dados['token'];
            $email = $dados['email'];

            $dados_token = $this->verificaValidadeToken($token, $email);
    
            if ( !$dados_token ) {
                throw new BadRequestException('Usuário não logado!', 401);
            }

            if ( !isset($dados_token['Usuario']['cliente_id']) ) {
                throw new BadRequestException('Cliente não informado!', 400);
            }
    
            $cliente_id = $dados_token['Usuario']['cliente_id'];
        }

        $this->loadModel('ClienteHorarioAtendimento');

        $conditions = array_merge($conditions, ['ClienteHorarioAtendimento.cliente_id' => $cliente_id]);
        $horarios_atendimento = $this->ClienteHorarioAtendimento->find('all',[
            'fields' => [
                'ClienteHorarioAtendimento.*'
            ],
            'conditions' => $conditions,
            'order' => [
                'ClienteHorarioAtendimento.horario_dia_semana'
            ],
            'link' => [
            ]
        ]);

        $horarios_atendimento_retornar = [];
        if ( count($horarios_atendimento) > 0 ) {
            foreach( $horarios_atendimento as $key => $horario ){
                $horario_dia_semana = $horario['ClienteHorarioAtendimento']['horario_dia_semana'];
                $horarios_atendimento[$key]['ClienteHorarioAtendimento']['_horario_dia_semana'] = $this->dias_semana_str[$horario_dia_semana];
                $horarios_atendimento_retornar[] = $horarios_atendimento[$key]['ClienteHorarioAtendimento'];
            }
        }

        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $horarios_atendimento_retornar))));

    }

    public function add() {
        
        $this->layout = 'ajax';

        $dados = $this->request->data['dados'];

        if ( is_array($dados) ) {
            $dados = json_decode(json_encode($dados, true));
        } else {
            $dados = json_decode($dados);
        }

        if (!isset($dados->email) || $dados->email == '') {
            throw new BadRequestException('E-mail não informado', 400);
        }

        if ( !filter_var($dados->email, FILTER_VALIDATE_EMAIL)) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'E-mail inválido!'))));
        }

        if (!isset($dados->token) || $dados->token == '') {
            throw new BadRequestException('Token não informado', 400);
        }

        $dados_token = $this->verificaValidadeToken($dados->token, $dados->email);
        if ( !$dados_token ) {
            throw new BadRequestException('Usuário não logado!', 401);
        }
        
        if ( !isset($dados->horarios) || !is_array($dados->horarios) || count($dados->horarios) == 0 ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Horários de atendimento não informados!'))));
        }

        $this->loadModel('ClienteHorarioAtendimento');

        $horarios = (array)$dados->horarios;
        $horarios_salvar = [];
        $horariosPermanecer = [];

        foreach ( $horarios as $key => $horario ) {

            $horarios_salvar[$key] = $horario;
            $horarios_salvar[$key]->cliente_id = $dados_token['Usuario']['cliente_id'];
 
            // Novo Registro
            if ( !is_numeric($horario->id) ) {
                unset($horarios_salvar[$key]->id);
            } 
            // Alterando Registro
            else {

                $checkHorario = $this->ClienteHorarioAtendimento->find('first',[
                    'conditions' => [
                        'ClienteHorarioAtendimento.cliente_id' => $dados_token['Usuario']['cliente_id'],
                        'ClienteHorarioAtendimento.id' => $horario->id
                    ],
                    'link' => []
                ]);

                if ( count($checkHorario) == 0 ) {
                    unset($horarios_salvar[$key]);
                    continue;
                }

                $horariosPermanecer[] = $horario->id;
            }

        }

        // Remove os horarios que não vieram no post
        $this->ClienteHorarioAtendimento->deleteAll([
            'ClienteHorarioAtendimento.cliente_id' => $dados_token['Usuario']['cliente_id'],
            'not' => [
                'ClienteHorarioAtendimento.id' => $horariosPermanecer
            ]
        ]);
        
        $dados_horario_salvo = $this->ClienteHorarioAtendimento->saveMany($horarios_salvar);

        if ( !$dados_horario_salvo ) {
            return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'erro', 'msg' => 'Ocorreu um erro ao tentar salvar os dados do horário!'))));
        }            
            
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'msg' => 'Horários cadastrados com sucesso!'))));
    
       

    }

}
