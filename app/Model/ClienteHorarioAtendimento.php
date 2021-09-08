<?php 

class ClienteHorarioAtendimento extends AppModel {
    public $useTable = 'clientes_horarios';

    public $name = 'ClienteHorarioAtendimento';

    public $belongsTo = array(
		'Cliente' => array(
			'foreignKey' => 'cliente_id'
        )
    );

    public function generateListHorarios($horario_atendimento = null, $model_horario) {

        if ( $horario_atendimento == null || count($horario_atendimento) == 0 ) {
            return false;
        }
        

        foreach( $horario_atendimento as $key => $hr ){
            $horario_abertura = substr($hr[$model_horario]['abertura'], 0, 5);
            $horario_fechamento = substr($hr[$model_horario]['fechamento'], 0, 5);
            $intervalo_horarios = substr($hr[$model_horario]['intervalo_horarios'], 0, 5);
            $intervalo_horarios_min = $this->time2minutes($intervalo_horarios.":00");
            $ultimo_hoario_gerado = $horario_abertura.":00";
            $max_vagas_horario = $hr[$model_horario]['vagas_por_horario'];

            if ( strtotime($horario_abertura) >= strtotime($horario_fechamento) ) {
                return false;
            }

            $horarios[0] = ['horario' => $hr[$model_horario]['abertura'], 'vagas' => $max_vagas_horario, 'domicilio' => $hr[$model_horario]['a_domicilio']];

            while ( strtotime($this->addMinutes2Time($ultimo_hoario_gerado, $intervalo_horarios_min)) < strtotime($horario_fechamento) ) {
                $nextTime = $this->addMinutes2Time($ultimo_hoario_gerado, $intervalo_horarios_min);
                $horarios[] = ['horario' => $nextTime, 'vagas' => $max_vagas_horario, 'domicilio' => $hr[$model_horario]['a_domicilio']];
                $ultimo_hoario_gerado = $nextTime;
            }

        }

    
        return $horarios;

    }

    private function time2minutes($time){
        $time = explode(':', $time);
        return ($time[0]*60) + ($time[1]) + ($time[2]/60);
    }
    
    private function addMinutes2Time($time, $minutes) {
        $secondsToAdd = $minutes*60;
        $newTime = strtotime($time) + $secondsToAdd;

        return date('H:i:s', $newTime);
    }

    public function diasSemanaDesativar($cliente_id) {
        $horarios_atendimento = $this->find('all',[
            'conditions' => [
                'ClienteHorarioAtendimento.cliente_id' => $cliente_id
            ],
            'link' => []
        ]);

        $arr_dias_semana = [0,1,2,3,4,5,6];
        $dias_encontrados = [];
        foreach( $horarios_atendimento as $key => $ha){
            $dias_encontrados[] = $ha['ClienteHorarioAtendimento']['horario_dia_semana'];
        }

        $dias_semana_desativar = array_diff($arr_dias_semana, $dias_encontrados);
        $dias_semana_desativar = array_values($dias_semana_desativar);
        return $dias_semana_desativar;
    }

    public function contaVagaRestantesHorario($cliente_id, $data, $hora, $n_agendamentos) {

        $dados_vagas = $this->find('first',[
            'conditions' => [
                'ClienteHorarioAtendimento.cliente_id' => $cliente_id,
                'ClienteHorarioAtendimento.horario_dia_semana' => date('w',strtotime($data)),
                'ClienteHorarioAtendimento.abertura <=' => $hora,
                'ClienteHorarioAtendimento.fechamento >=' => $hora,
            ],
            'link' => []
        ]);

        if ( count($dados_vagas) == 0 )
            return false;

        return ($dados_vagas['ClienteHorarioAtendimento']['vagas_por_horario'] - $n_agendamentos);


    }
}
