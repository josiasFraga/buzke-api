
<?php
require_once ROOT . '/vendors/autoload.php';
$stylesheet = file_get_contents(ROOT . '/app/webroot/css/reports.css');
$header  = '<div class="row">';
$header .= '<div class="col w-15">';
$header .=      '<img src="'.$this->webroot.'/img/clientes/'.$dados_cliente['Cliente']['logo'].'" width="80px" />';
$header .= '</div>';
$header .= '<div class="col w-68">';
$header .=      '<h3 class="text-center pt-3">'.$titulo.'</h3>';
$header .= '</div>';
$header .= '<div class="col w-15">';
$header .=      '<img src="'.$this->webroot.'/img/logo.png" width="80px" />';
$header .= '</div>';
$header .= '</div>';
$mpdf = new \Mpdf\Mpdf();
ob_start();
?>

<div class="container">
<table class="table">
  <thead>
    <tr>
      <th>Horário</th>
      <th>Vagas</th>
      <th>Agendamentos</th>
      <th>Ocupação</th>
    </tr>
  </thead>
  <tbody>
  <?php 
  $n_lines = 0;
  foreach($arr_datas as $data => $horarios):
  ?>
    <tr class="subheader">
      <td colspan="4">
      <?= $dias_semana_str[date('w',strtotime($data))] ?>, <?= date('d',strtotime($data)) ?> de <?= $meses_str_abrev[(int)date('m',strtotime($data))] ?> de <?= date('Y',strtotime($data)) ?>
      </td>
    </tr>
    <?php 
    if ( isset($horarios['msg']) ) {
    ?>
    <tr class="sem_atendimento">
      <td colspan="4">
      <?= $horarios['msg'] ?>
      </td>
    </tr>
    <?php
    }
    foreach(@$horarios['horarios'] as $key_horario => $horario):
      $ocupacao = ($horario['vagas_ocupadas'] * 100) / $horario['vagas'];
    ?>
      <tr class="<?= $n_lines % 2 == 0 ? 'even' : ''; ?>">
        <td class="text-center">
          <?= date('H:i',strtotime($horario['horario'])) ?>
        </td>
        <td class="text-center">
          <?= $horario['vagas'] ?>
        </td>
        <td class="text-center">
          <?= $horario['vagas_ocupadas'] ?>
        </td>
        <td class="text-center <?= $ocupacao == 0 ? 'color-red' : '' ?>">
          <?= number_format($ocupacao, 2, ',', '') ?>%
        </td>
      </tr>
    <?php 
    $n_lines++;
    endforeach;
    ?>
  <?php 
  endforeach;
  ?>
  </tbody>
</table>
</div>
<?php
$html = ob_get_contents();
ob_end_clean();
$mpdf->SetHTMLHeader($header);
$mpdf->SetFooter(date('d/m/Y H:i') . ' | ' . $titulo . ' | {PAGENO}');
$mpdf->AddPage('','','1 - ∞','','','','',25,'','','');
$mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

$mpdf->Output('files/relatorios/'.$dados_cliente['Cliente']['id'].'_'.$nome.'.pdf', 'F');
return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'warning', 'msg' => 'Infelizmente a quadra estará ocupada com um torneio no dia e horário selecionados!'))));