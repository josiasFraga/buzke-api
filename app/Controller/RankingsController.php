<?php
class RankingsController extends AppController
{
    public $components = array('RequestHandler');
    public $uses = array('Usuario', 'ClienteCliente', 'TorneioInscricaoJogador', 'TorneioJogo');

    public function index()
    {
        $this->autoRender = false;
        $this->response->type('json');

        // Filtro opcional por categoria_id
        $categoriaId = $this->request->query('categoria_id');
        $categoriaCondition = $categoriaId ? "AND tc.categoria_id = {$categoriaId}" : "";

        // Query para calcular a pontuação
        $sql = "
            SELECT
                u.id AS usuario_id,
                u.nome AS nome,
                u.img AS img,

                -- Torneios Vencidos (Final Vencida = 25 pontos)
                COUNT(DISTINCT CASE
                    WHEN tj.fase_nome = 'Final' AND tj.vencedor = ti.id THEN tj.id
                END) AS torneios_vencidos,

                -- Finais Perdidas (Final Perdida = 10 pontos)
                COUNT(DISTINCT CASE
                    WHEN tj.fase_nome = 'Final' AND tj.vencedor != ti.id AND (tj.time_1 = ti.id OR tj.time_2 = ti.id) THEN tj.id
                END) AS finais_perdidas,

                -- Avanços de Fase (Avanço de Fase = 5 pontos, exceto em finais)
                COUNT(DISTINCT CASE
                    WHEN tj.fase > 1 AND tj.fase_nome != 'Final' THEN tj.id
                END) AS avancos_de_fase,

                -- Vitórias em Jogos (Jogo Ganho = 2 pontos, exceto em finais vencidas)
                COUNT(DISTINCT CASE
                    WHEN tj.vencedor = ti.id AND tj.fase_nome = 'Fase de Grupos' THEN tj.id
                END) AS vitorias_jogos,

                -- Pontuação Total
                SUM(
                    CASE
                        WHEN tj.fase_nome = 'Final' AND tj.vencedor = ti.id THEN 25   -- Final vencida
                        WHEN tj.fase_nome = 'Final' AND tj.vencedor != ti.id THEN 10  -- Final perdida
                        WHEN tj.fase > 1 AND tj.fase_nome != 'Final' THEN 5           -- Avanço de fase (exceto final)
                        WHEN tj.vencedor = ti.id AND tj.fase_nome = 'Fase de Grupos' THEN 2   -- Jogo ganho (exceto final)
                        ELSE 0
                    END
                ) AS total_pontos

            FROM usuarios u

            -- Subquery: Une o usuário às inscrições únicas
            INNER JOIN (
                SELECT DISTINCT cc.usuario_id, ti.id AS torneio_inscricao_id
                FROM clientes_clientes cc
                INNER JOIN torneio_inscricao_jogadores tij ON tij.cliente_cliente_id = cc.id
                INNER JOIN torneio_inscricoes ti ON ti.id = tij.torneio_inscricao_id
            ) AS inscricoes ON inscricoes.usuario_id = u.id

            -- Jogos relacionados às inscrições
            LEFT JOIN torneio_jogos tj ON tj.time_1 = inscricoes.torneio_inscricao_id OR tj.time_2 = inscricoes.torneio_inscricao_id

            -- Vinculação final com torneio_inscricoes
            LEFT JOIN torneio_inscricoes ti ON ti.id = inscricoes.torneio_inscricao_id

            -- Vinculação final com torneio_inscricoes
            LEFT JOIN torneio_categorias tc ON tj.torneio_categoria_id = tc.id

            WHERE u.ativo = 'Y' AND tj.vencedor IS NOT NULL {$categoriaCondition}
            GROUP BY u.id
            ORDER BY total_pontos DESC LIMIT 20";


        // Executa a query diretamente
        $results = $this->Usuario->query($sql);

        // Caminho para imagens
        $images_path = $this->images_path;

        // Formata os resultados
        $ranking = array();
        foreach ($results as $row) {
            $ranking[] = array(
                'id' => $row['u']['usuario_id'],
                'nome' => $row['u']['nome'],
                'img' => $images_path . '/usuarios/' . $row['u']['img'],
                'torneios_vencidos' => (int)$row[0]['torneios_vencidos'],
                'finais_perdidas' => (int)$row[0]['finais_perdidas'],
                'avancos_de_fase' => (int)$row[0]['avancos_de_fase'],
                'vitorias_jogos' => (int)$row[0]['vitorias_jogos'],
                'pontos' => (int)$row[0]['total_pontos']
            );
        }

        // Retorna o JSON
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $ranking))));
    }
}
