<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('Controller', 'Controller');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
 
class AppController extends Controller {
    //public $images_path = "http://192.168.1.3/buzke/api/app/webroot/img/";
    //public $images_painel_path = "http://192.168.1.3/buzke/app/webroot/img/";
    //public $files_path = "http://192.168.1.3/buzke/app/webroot/api/app/webroot/img/anexos";
    public $images_path = "https://buzke.com.br/app/webroot/api/app/webroot/img/";
    public $images_painel_path = "https://buzke.com.br/webroot/api/app/webroot/img/";
    public $files_path = "https://buzke.com.br/app/webroot/api/app/webroot/img/anexos";
    public $dias_semana_str = array('Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado');
    public $dias_semana_abrev = array('dom','seg','ter','qua','qui','sex','sáb');
    public $quadra_de_padel_subcategoria = 7;
    
    public function beforeFilter() {
        parent::beforeFilter();
            App::import("Vendor", "FacebookAuto", array("file" => "facebook/src/Facebook/autoload.php"));
            // $this->response->header('Access-Control-Allow-Origin','*');
            // $this->response->header('Access-Control-Allow-Methods','*');
            // $this->response->header('Access-Control-Allow-Headers','X-Requested-With');
            // $this->response->header('Access-Control-Allow-Headers','Content-Type, x-xsrf-token');
            // $this->response->header('Access-Control-Max-Age','172800');
    }

    public function floatEnBr($val){
        return number_format($val, 2, ',', '.');
    }

	public function dateBrEn( $data ){
		$data = explode("/",$data);
		$data = $data[2]."-".$data[1]."-".$data[0];
		$data = date("Y-m-d", strtotime($data));
		return $data;
	}

	public function dateEnBr( $data ){
		return date("d/m/Y", strtotime($data));
	}

    public function verificaValidadeToken($usuario_token, $usuario_email = null){
        $this->loadModel('Token');

        if ( $usuario_email == null ) {
            $dados_token = $this->Token->find('first',array(
                'fields' => array(
                    'Token.id',
                    'Token.token',
                    'Token.data_validade',
                    'Token.usuario_id',
                ),
                'conditions' => array(
                    'Token.token' => $usuario_token,
                    'Token.data_validade >=' => date("Y-m-d"),
                ),
            ));

        } else {
            $dados_token = $this->Token->find('first',array(
                'fields' => array(
                    'Usuario.id',
                    'Usuario.nome',
                    'Usuario.telefone',
                    'Usuario.email',
                    'Usuario.img',
                    'Usuario.nivel_id',
                    'Usuario.cliente_id',
                    'Token.token',
                    'Token.data_validade',
                ),
                'conditions' => array(
                    'Usuario.email' => $usuario_email,
                    'Token.token' => $usuario_token,
                    'Token.data_validade >=' => date("Y-m-d"),
                    'Usuario.ativo' => 'Y'
                ),
                'link' => array(
                    'Usuario'
                )
            ));

        }

        if (count($dados_token) > 0){
            return $dados_token;
        }
        return false;
    }

    public function filtra_disponiveis($horarios = array(), $usuario = null) {

        if ( count($horarios) == 0 || $usuario == null ) {
            return array();
        }

        $this->loadModel('Agendamento');        
        $this->loadModel('HorarioExcessao');

        foreach( $horarios as $key => $horario) {
            
            $n_agendamentos = $this->Agendamento->contaAgendamentos($horario['Horario']['data'],$horario['Horario']['horario'],$usuario['Usuario']['id']);

            $n_vagas = $this->HorarioExcessao->find('first',array(
                'conditions' => array(
                    'HorarioExcessao.horario' => $horario['Horario']['horario'],
                    'HorarioExcessao.data' => $horario['Horario']['data']
                )
            ));

            if ( count($n_vagas) > 0 ) {
                $horario['Horario']['vagas'] = $n_vagas['HorarioExcessao']['vagas'];
            }
            
            $n_agendamentos = $this->Agendamento->contaAgendamentos($horario['Horario']['data'],$horario['Horario']['horario']);
            if ( $n_agendamentos >= $horario['Horario']['vagas'] ) {
                $horarios[$key]['Horario']['disponivel'] = 0;
            } else {
                $horarios[$key]['Horario']['disponivel'] = 1;
            }
        }

        return $horarios;

    }

	public function sendNotification( $arr_ids = array(), $agendamento_id = null, $titulo = "", $mensagem = "", $motivo = "agendamento" ){
	
		if ( count($arr_ids) == 0 )
			return false;
	
		if ( $agendamento_id == null )
			return false;

		if ( $mensagem == "" ) {
			$mensagem = $titulo;
        }

		$heading = array(
			"en" => $titulo
		);

		$content = array(
			"en" => $mensagem
        );

        $arr_ids_app = array();
		foreach( $arr_ids as $id ) {
			$arr_ids_app[] = $id;
		}

        $fields = array(
            'app_id' => "b3d28f66-5361-4036-96e7-209aea142529",
            'include_player_ids' => $arr_ids_app,
            'data' => array("agendamento_id" => $agendamento_id, 'motivo' => $motivo),
            'small_icon' => 'https://www.zapshop.com.br/ctff/restfull/pushservice/icons/logo_icon.png',
            'large_icon' => 'https://www.zapshop.com.br/ctff/restfull/pushservice/icons/logo_icon_large.png', 
            'headings' => $heading,
            'contents' => $content,
        );
        
        $fields = json_encode($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                                                'Authorization: Basic ZWM2M2YyMjQtOTQ4My00MjI2LTg0N2EtYThiZmRiNzM5N2Nk'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
        $response = curl_exec($ch);
        curl_close($ch);
		
		return true;
    }
    
    public function dateHourEnBr( $data , $r_data, $r_hora ){
		if ($r_data && $r_hora) {
			$data = date("d/m/Y H:s:i", strtotime($data));
		} else if ($r_data) {
			$data = date("d/m/Y", strtotime($data));
		} else if ($r_hora) {
			$data = date("H:s:i", strtotime($data));
		}
		return $data;
    }
    
    public function currencyToFloat($currency) {
		if (!is_float($currency) && preg_match('/\D/', $currency)) {
			return (float) preg_replace('/\D/', '', $currency) / 100;
		}
		return $currency;
    }
    
    public function formatCelular($celular){
        $part1 = substr($celular, 0 ,5);
        $part2 = substr($celular, 5 ,5);

        return ' '.$part1 ."-".$part2;
    }

    public function calculaDatas($tipo, $data_hora_inicio, $data_hora_fim){
        if ( $tipo == 'd' ) {
            // Create two new DateTime-objects...
            $date1 = new DateTime($data_hora_inicio);
            $date2 = new DateTime($data_hora_fim);

            // The diff-methods returns a new DateInterval-object...
            $diff = $date2->diff($date1);

            // Call the format method on the DateInterval-object
            return $diff->format('%d');
        } else if($tipo == 'h') {
            // Create two new DateTime-objects...
            $date1 = new DateTime($data_hora_inicio);
            $date2 = new DateTime($data_hora_fim);

            // The diff-methods returns a new DateInterval-object...
            $diff = $date2->diff($date1);

            // Call the format method on the DateInterval-object
            return $diff->format('%h:%i:%s');

        }

    }

	function validar_cnpj($cnpj)
	{
		$cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);
		
		// Valida tamanho
		if (strlen($cnpj) != 14)
			return false;

		// Verifica se todos os digitos são iguais
		if (preg_match('/(\d)\1{13}/', $cnpj))
			return false;	

		// Valida primeiro dígito verificador
		for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++)
		{
			$soma += $cnpj[$i] * $j;
			$j = ($j == 2) ? 9 : $j - 1;
		}

		$resto = $soma % 11;

		if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto))
			return false;

		// Valida segundo dígito verificador
		for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++)
		{
			$soma += $cnpj[$i] * $j;
			$j = ($j == 2) ? 9 : $j - 1;
		}

		$resto = $soma % 11;

		return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
	}

	function validar_cpf($cpf) {
	
		// Extrai somente os números
		$cpf = preg_replace( '/[^0-9]/is', '', $cpf );
		
		// Verifica se foi informado todos os digitos corretamente
		if (strlen($cpf) != 11) {
			return false;
		}

		// Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
		if (preg_match('/(\d)\1{10}/', $cpf)) {
			return false;
		}

		// Faz o calculo para validar o CPF
		for ($t = 9; $t < 11; $t++) {
			for ($d = 0, $c = 0; $c < $t; $c++) {
				$d += $cpf[$c] * (($t + 1) - $c);
			}
			$d = ((10 * $d) % 11) % 10;
			if ($cpf[$c] != $d) {
				return false;
			}
		}
		return true;

	}

}
