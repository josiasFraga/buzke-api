
<?php
require_once ROOT . '/vendors/autoload.php';
$stylesheet = file_get_contents(ROOT . '/app/webroot/css/reports.css');
$header  = '<div class="row">';
$header .= '<div class="col w-15">';
$header .=      '<img src="'.ROOT . '/app/webroot/img/clientes/'.$dados_cliente['Cliente']['logo'].'" width="80px" />';
$header .= '</div>';
$header .= '<div class="col w-68">';
$header .=      '<h3 class="text-center pt-3">'.$titulo.'</h3>';
$header .= '</div>';
$header .= '<div class="col w-15">';
$header .=      '<img src="'.ROOT . '/app/webroot/img/logo.png" width="80px" />';
$header .= '</div>';
$header .= '</div>';
$mpdf = new \Mpdf\Mpdf();
ob_start();
?>

<div class="container">
<table class="table">
  <thead>
    <tr>
        <td>#</td>
        <th>Nome da Dupla</th>
        <th>Categoria</th>
        <th>Data/Hora Cadastro</th>
    </tr>
  </thead>
  <tbody>
  <?php 
  foreach($dados_inscritos as $key => $inscrito):
  ?>
    <tr class="<?= $key % 2 == 0 ? 'even' : ''; ?>">
        <td class="text-center">
        <?= $key+1 ?>
        </td>
        <td>
        <?= $inscrito['TorneioInscricao']['_nome_dupla'] ?>
        </td>
        <td>
        <?= $inscrito['TorneioInscricao']['_categoria_nome'].' '.($inscrito['TorneioCategoria']['sexo'] == 'M' ? 'Masculina' : 'Feminina') ?>
        </td>
        <td class="text-center">
        <?= date('d/m',strtotime($inscrito['TorneioInscricao']['created']))." às ".date('H:i',strtotime($inscrito['TorneioInscricao']['created'])) ?>
        </td>
    </tr>
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