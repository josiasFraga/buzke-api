<?php
require_once ROOT . '/vendors/autoload.php';
$stylesheet = file_get_contents(ROOT . '/app/webroot/css/reports.css');
$header  = '<div class="row">';
$header .= '<div class="col w-15">';
$header .=      '<img src="'.ROOT . '/app/webroot/img/clientes/'.$promocao['Cliente']['logo'].'" width="80px" />';
$header .= '</div>';
$header .= '<div class="col w-68">';
$header .=      '<h3 class="text-center pt-3">Relatório de Promoção</h3>';
$header .= '</div>';
$header .= '<div class="col w-15">';
$header .=      '<img src="'.ROOT . '/app/webroot/img/logo.png" width="80px" />';
$header .= '</div>';
$header .= '</div>';

$mpdf = new \Mpdf\Mpdf();
ob_start();

function traduzirDiaSemana($diaIngles) {
    $dias = [
        'Monday' => 'Segunda',
        'Tuesday' => 'Terça',
        'Wednesday' => 'Quarta',
        'Thursday' => 'Quinta',
        'Friday' => 'Sexta',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    return $dias[$diaIngles] ?? $diaIngles;
}
?>

<div class="container">
    <h4>Detalhes da Promoção</h4>
    <p><strong>Título:</strong> <?= h($promocao['Promocao']['titulo']) ?></p>
    <p><strong>Descrição:</strong> <?= nl2br(h($promocao['Promocao']['descricao'])) ?></p>
    <?php if (!empty($promocao['Promocao']['valor_padrao'])) { ?>
        <p><strong>Valor Promocional:</strong> R$ <?= number_format($promocao['Promocao']['valor_padrao'], 2, ',', '.') ?></p>
    <?php } ?>
    <?php if (!empty($promocao['Promocao']['valor_fixos'])) { ?>
        <p><strong>Valor Promocional Fixos:</strong> R$ <?= number_format($promocao['Promocao']['valor_fixos'], 2, ',', '.') ?></p>
    <?php } ?>
    <p><strong>Validade:</strong> 
        <?php 
        if ($promocao['Promocao']['validade_ate_cancelar'] === 'Y') {
            if ($promocao['Promocao']['finalizada'] === 'N') {
                echo date('d/m/Y', strtotime($promocao['Promocao']['created'])) . ' até Indefinido';
            } else {
                echo date('d/m/Y', strtotime($promocao['Promocao']['created'])) . ' até ' . date('d/m/Y', strtotime($promocao['Promocao']['updated']));
            }
        } else {
            echo date('d/m/Y', strtotime($promocao['Promocao']['validade_inicio'])) . ' até ' . date('d/m/Y', strtotime($promocao['Promocao']['validade_fim']));
        }
        ?>
    </p>

    <?php foreach ($dados_relatorio as $servico => $dados): ?>
        <table class="table">
            <thead>
                <tr>
                    <th colspan="6"><?= h($servico) ?></th>
                </tr>
                <tr>
                    <th>Data</th>
                    <th>Dia da Semana</th>
                    <th>Cliques</th>
                    <th>Visitantes Únicos</th>
                    <th>Agend. Padrão</th>
                    <th>Agend. Fixos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados as $dado): ?>
                    <tr>
                        <td><?= h($dado['data']) ?></td>
                        <td><?= traduzirDiaSemana($dado['dia_semana']) ?></td>
                        <td class="text-center"><?= h($dado['total_cliques']) ?></td>
                        <td class="text-center"><?= h(floatVal($dado['total_visitantes_unicos'])) ?></td>
                        <td class="text-center"><?= h($dado['total_agendamentos_padrao']) ?></td>
                        <td class="text-center"><?= h($dado['total_agendamentos_fixos']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>

<?php
$html = ob_get_contents();
ob_end_clean();
$mpdf->SetHTMLHeader($header);
$mpdf->SetFooter(date('d/m/Y H:i') . ' | Relatório de Promoção | {PAGENO}');
$mpdf->AddPage('','','1 - ∞','','','','',25,'','','');
$mpdf->WriteHTML($stylesheet,\Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html,\Mpdf\HTMLParserMode::HTML_BODY);

$mpdf->Output('files/relatorios/'.$promocao['Cliente']['id'].'_'.$nome.'.pdf', 'F');
return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'sucesso', 'msg' => 'Relatório gerado com sucesso!'))));
