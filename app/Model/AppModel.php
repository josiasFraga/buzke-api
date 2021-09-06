<?php
/**
 * Application model for CakePHP.
 *
 * This file is application-wide model file. You can put all
 * application-wide model-related methods here.
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
 * @package       app.Model
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
header("Access-Control-Allow-Origin: *");
App::uses('Model', 'Model');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Application model for Cake.
 *
 * Add your application-wide methods in the class below, your models
 * will inherit them.
 *
 * @package       app.Model
 */
class AppModel extends Model {
	public $actsAs = array('Containable', 'Linkable');
	
	public function formatDate($date, $formato = 'Y-m-d') {
		return date($formato, strtotime(str_replace('/', '-', $date)));
	}

	public function dateBrEn( $data ){
		$data = explode("/",$data);
		$data = $data[2]."-".$data[1]."-".$data[0];
		$data = date("Y-m-d", strtotime($data));
		return $data;
	}

	public function dateEnBr( $data ){
		$data = date("d/m/Y", strtotime($data));
		return $data;
	}

	public function currencyToFloat($currency) {
		if (!is_float($currency) && preg_match('/\D/', $currency)) {
			return (float) preg_replace('/\D/', '', $currency) / 100;
		}
		return $currency;
	}

}
