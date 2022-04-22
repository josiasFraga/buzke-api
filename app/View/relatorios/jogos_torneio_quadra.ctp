
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
        <th>Horario</th>
        <th>Categoria</th>
        <th>Dupla 1</th>
        <th>Dupla 2</th>
    </tr>
  </thead>
  <tbody>
  <?php 
  foreach($jogos as $categoria => $jogos):
  ?>
  
    <tr class="subheader">
      <td colspan="4">
      <?= $categoria ?>
      </td>
    </tr>
    <?php 
    foreach($jogos as $key => $jogo):
    ?>
    <tr class="<?= $key % 2 == 0 ? 'even' : ''; ?>">
        <td class="text-center">
        <?= date('d/m',strtotime($jogo['Agendamento']['horario'])).' às '.$jogo['TorneioJogo']['_hora'] ?>
        </td>
        <td>
        <?= $jogo['TorneioJogo']['_categoria_nome'].' '.($jogo['TorneioCategoria']['sexo'] == 'M' ? 'Masculina' : 'Feminina')  ?>
        </td>
        <td>
        <?= $jogo['TorneioJogo']['_nome_dupla1'] ?>
        </td>
        <td>
        <?= $jogo['TorneioJogo']['_nome_dupla2'] ?>
        </td>
    </tr>
  <?php 
    endforeach;
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