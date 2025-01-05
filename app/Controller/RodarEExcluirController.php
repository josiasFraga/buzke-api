<?php
ini_set('memory_limit', '512M');
class RodarEExcluirController extends AppController {

    public $components = array('RequestHandler');
    public $uses = array('Usuario', 'ClienteCliente', 'TorneioInscricaoJogador', 'TorneioJogo');

    private $s3Client;
    private $s3Bucket = 'buzke-images'; // Nome do seu bucket S3

    // Método para inicializar o controlador (usando beforeFilter no CakePHP 2.x)
    public function beforeFilter() {
        parent::beforeFilter();

        // Configuração do cliente S3
        $this->loadS3Client();
    }

    // Função para carregar o cliente S3
    private function loadS3Client() {
        // Verifique se as credenciais estão corretas
        if (empty(getenv('AWS_ACCESS_KEY')) || empty(getenv('AWS_SECRET_KEY'))) {
            throw new Exception('Credenciais da AWS não encontradas.');
        }

        // Inicializar o cliente S3
        $this->s3Client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => 'sa-east-1',
            'credentials' => [
                'key'    => getenv('AWS_ACCESS_KEY'),
                'secret' => getenv('AWS_SECRET_KEY'),
            ],
            'suppress_php_deprecation_warning' => true,
            'http' => [
                'verify' => false,
            ]
        ]);
    }

    function seta_vencedor() {
        $this->loadModel('TorneioJogo');
        $this->loadModel('TorneioJogoPlacar');
        $jogos = $this->TorneioJogo->find('all',[
            'link' => []
        ]);

        foreach ($jogos as $key => $jogo) {
            $jogo_id = $jogo['TorneioJogo']['id'];
            $vencedor_field = $this->TorneioJogoPlacar->busca_vencedor_por_jogo($jogo_id);

            if ( !empty($vencedor_field) ) {
                $inscricao_vencedora  = $jogo['TorneioJogo'][$vencedor_field];

                $dados_salvar = [
                    'id' => $jogo_id,
                    'vencedor' => $inscricao_vencedora
                ];

                $this->TorneioJogo->save($dados_salvar);

            }

        }

        die('Fim');

    }




    function cria_thumbs_redondas($diretorio = 'default_directory') {
        $this->autoRender = false;
    
        // Verifica se o cliente S3 está inicializado
        if (!$this->s3Client) {
            throw new Exception('Cliente S3 não inicializado.');
        }

        $args = $this->request->params['pass'];
        // Se quiser juntar de volta:
        $diretorio = implode('/', $args);
    
        try {
            // Listar objetos no S3
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->s3Bucket,
                'Prefix' => $diretorio . '/',
            ]);
    
            if (empty($objects['Contents'])) {
                echo "Nenhuma imagem encontrada no diretório: {$diretorio}\n";
                return;
            }
    
            foreach ($objects['Contents'] as $object) {
                $fileName = basename($object['Key']);
                
                if ( $object['Key'] === $diretorio . '/' ) continue;
    
                // Ignorar arquivos que já possuem "thumb_" ou "round_" no nome
                if (strpos($fileName, 'thumbs') === 0 || strpos($fileName, 'round_thumbs') === 0) {
                    continue;
                }
    
                // Nome da nova imagem redonda
                $fileNameNoExt = pathinfo($fileName, PATHINFO_FILENAME); 
                $roundFileName = $diretorio . '/round_thumbs/' . $fileNameNoExt . '.png';
    
                // Verificar se a imagem redonda já existe
                if ($this->checkIfFileExists($fileNameNoExt . '.png', $diretorio . '/round_thumbs')) {
                    echo "A imagem redonda já existe para: {$fileName}\n";
                    continue;
                }
    
                // Baixar a imagem original
                $result = $this->s3Client->getObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $object['Key'],
                ]);

                if (empty($result['Body'])) {
                    echo "A imagem {$fileName} está vazia ou inválida.\n";
                    continue;
                }
    
                // Converta o stream em uma string
                $originalImageData = (string) $result['Body']; // Conteúdo binário



                if (empty($originalImageData)) {
                    echo "A imagem {$fileName} está vazia ou inválida.\n";
                    continue;
                }

                // Criar a imagem redonda
                $roundImageData = $this->createRoundImage($originalImageData);
    
                // Enviar a nova imagem para o S3
                $this->s3Client->putObject([
                    'Bucket'      => $this->s3Bucket,
                    'Key'         => $roundFileName,
                    'Body'        => $roundImageData,  // binário
                    'ContentType' => 'image/png',
                ]);
    
                echo "Imagem redonda criada e enviada para o S3: {$roundFileName}\n";
                //die();
            }
        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage() . "\n";
        }
    }

    private function createRoundImage($imageData)
    {
        // 1. Salvar em um arquivo temporário para ler EXIF
        $tempFile = tempnam(sys_get_temp_dir(), 'orientation_');
        file_put_contents($tempFile, $imageData);
    
        // 2. Carrega a imagem a partir do arquivo
        $im = imagecreatefromstring(file_get_contents($tempFile));
        if (!$im) {
            unlink($tempFile);
            throw new Exception("Falha ao criar imagem a partir dos dados fornecidos.");
        }
    
        // 3. Se for JPEG, corrigir orientação via EXIF
        //    Você pode tentar identificar a extensão do arquivo (por ex., pathinfo($tempFile)),
        //    ou apenas checar se exif_read_data tem Orientation e rotacionar.
        $exif = @exif_read_data($tempFile);
        if ($exif && isset($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $im = imagerotate($im, 180, 0);
                    break;
                case 6:
                    $im = imagerotate($im, -90, 0);
                    break;
                case 8:
                    $im = imagerotate($im, 90, 0);
                    break;
            }
        }
    
        // Agora, a imagem $im está fisicamente na posição correta
    
        // 4. Dimensões
        $origWidth  = imagesx($im);
        $origHeight = imagesy($im);
    
        // Tamanho final
        $targetSize = 512;
    
        // 5. Redimensionar mantendo proporção (usando max() para preencher 512 e recortar bordas)
        $scale = max($targetSize / $origWidth, $targetSize / $origHeight);
        $scaledWidth  = (int)($origWidth  * $scale);
        $scaledHeight = (int)($origHeight * $scale);
    
        $scaledImage = imagecreatetruecolor($scaledWidth, $scaledHeight);
        imagealphablending($scaledImage, false);
        imagesavealpha($scaledImage, true);
        $transparent = imagecolorallocatealpha($scaledImage, 0, 0, 0, 127);
        imagefill($scaledImage, 0, 0, $transparent);
    
        // Copiar redimensionado
        imagecopyresampled(
            $scaledImage,
            $im,
            0, 0, 0, 0,
            $scaledWidth, $scaledHeight,
            $origWidth, $origHeight
        );
    
        // 6. Recorta 512×512 do centro
        $finalImage = imagecreatetruecolor($targetSize, $targetSize);
        imagealphablending($finalImage, false);
        imagesavealpha($finalImage, true);
    
        $transparent2 = imagecolorallocatealpha($finalImage, 0, 0, 0, 127);
        imagefill($finalImage, 0, 0, $transparent2);
    
        $offsetX = (int)(($scaledWidth  - $targetSize) / 2);
        $offsetY = (int)(($scaledHeight - $targetSize) / 2);
    
        imagecopy(
            $finalImage,
            $scaledImage,
            0, 0,
            $offsetX, $offsetY,
            $targetSize, $targetSize
        );
    
        // 7. Aplicar máscara circular pixel a pixel
        $center = $targetSize / 2; // 256
        $radius = $targetSize / 2; // 256
    
        for ($x = 0; $x < $targetSize; $x++) {
            for ($y = 0; $y < $targetSize; $y++) {
                $dist = sqrt(pow($x - $center, 2) + pow($y - $center, 2));
                if ($dist > $radius) {
                    imagesetpixel($finalImage, $x, $y, $transparent2);
                }
            }
        }
    
        // 8. Converter para PNG em string
        ob_start();
        imagepng($finalImage);
        $output = ob_get_clean();
    
        // 9. Liberar memória
        imagedestroy($im);
        imagedestroy($scaledImage);
        imagedestroy($finalImage);
    
        // 10. Exclui o arquivo temporário
        unlink($tempFile);
    
        // Retorna binário PNG
        return $output;
    }
    



    public function cria_thumbs_quadradas($diretorio = 'default_directory') {
        $this->autoRender = false;
    
        if (!$this->s3Client) {
            throw new Exception('Cliente S3 não inicializado.');
        }

        $args = $this->request->params['pass'];
        // Se quiser juntar de volta:
        $diretorio = implode('/', $args);
    
        try {
            // Listar objetos no S3
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->s3Bucket,
                'Prefix' => $diretorio . '/',
            ]);
    
            if (empty($objects['Contents'])) {
                echo "Nenhuma imagem encontrada no diretório: {$diretorio}\n";
                return;
            }
    
            foreach ($objects['Contents'] as $object) {
                $fileName = basename($object['Key']);
    
                // Pular "pasta" se for só o prefixo
                if ($object['Key'] === $diretorio . '/') {
                    continue;
                }
    
                // Ignorar arquivos que já estejam em "thumbs/" ou "round_thumbs/"
                if (strpos($object['Key'], $diretorio . '/thumbs/') === 0) {
                    // Já está na pasta de thumbs
                    continue;
                }
                if (strpos($object['Key'], $diretorio . '/round_thumbs/') === 0) {
                    // Já está na pasta de round_thumbs
                    continue;
                }
    
                // Descobrir a extensão (ex.: .jpg, .png, .gif)
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    
                // Nome do novo arquivo (por exemplo, "thumbs/foto.png" se original era ".png")
                $thumbFileName = $diretorio . '/thumbs/' . $fileName; 
                // Ex: users/thumbs/foto.png
    
                // Verificar se a thumb já existe
                if ($this->checkIfFileExists($fileName, $diretorio . '/thumbs')) {
                    echo "A thumb já existe para: {$fileName}\n";
                    continue;
                }
    
                // Baixar a imagem original do S3
                $result = $this->s3Client->getObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $object['Key'],
                ]);
    
                if (empty($result['Body'])) {
                    echo "A imagem {$fileName} está vazia ou inválida.\n";
                    continue;
                }
    
                // Converte o stream em string binária
                $originalImageData = (string) $result['Body'];
                if (empty($originalImageData)) {
                    echo "A imagem {$fileName} está vazia ou inválida.\n";
                    continue;
                }
    
                // Criar a thumb 512x512 (mantendo extensão)
                $thumbData = $this->createThumb512($originalImageData, $extension);
    
                // Subir a nova imagem para o S3
                $this->s3Client->putObject([
                    'Bucket'      => $this->s3Bucket,
                    'Key'         => $thumbFileName,
                    'Body'        => $thumbData,
                    'ContentType' => $this->getMimeTypeByExtension($extension), 
                ]);
    
                echo "Thumb criada e enviada para o S3: {$thumbFileName}\n";
    
                // Libera memória (se tiver resources GD)
                // Nesse caso, a createThumb512 já faz o imagedestroy, mas você pode chamar gc_collect_cycles() se quiser
            }
        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage() . "\n";
        }
    }
    
    private function createThumb512($imageData, $extension) {

        // 1. Criar um arquivo temporário para podermos ler EXIF
        $tempFile = tempnam(sys_get_temp_dir(), 'orientation_');
        file_put_contents($tempFile, $imageData);
    
        // 2. Carregar a imagem a partir do arquivo
        $im = imagecreatefromstring(file_get_contents($tempFile));
        if (!$im) {
            unlink($tempFile);
            throw new Exception("Falha ao criar imagem a partir dos dados fornecidos.");
        }
    
        // 3. Se for JPEG, corrigir orientação lendo EXIF
        if (in_array(strtolower($extension), ['jpg','jpeg'])) {
            $im = $this->fixOrientationIfNeeded($im, $tempFile);
        }
    
        // AGORA, a imagem $im está fisicamente rotacionada corretamente,
        // e podemos prosseguir com o redimensionamento.
    
        $origWidth  = imagesx($im);
        $origHeight = imagesy($im);
        $targetSize = 512;
    
        // min() => encaixar sem recortar
        $scale = min($targetSize / $origWidth, $targetSize / $origHeight);
        $scaledWidth  = (int)($origWidth  * $scale);
        $scaledHeight = (int)($origHeight * $scale);
    
        $thumb = imagecreatetruecolor($scaledWidth, $scaledHeight);
    
        // Se for PNG/GIF, preservar transparência
        if (in_array(strtolower($extension), ['png','gif'])) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefill($thumb, 0, 0, $transparent);
        }
    
        // Redimensiona
        imagecopyresampled(
            $thumb,
            $im,
            0, 0, 0, 0,
            $scaledWidth, $scaledHeight,
            $origWidth, $origHeight
        );
    
        // Converter para string binária
        ob_start();
        switch (strtolower($extension)) {
            case 'png':
                imagepng($thumb);
                break;
            case 'gif':
                imagegif($thumb);
                break;
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumb, null, 90);
                break;
            default:
                imagejpeg($thumb, null, 90);
                break;
        }
        $thumbData = ob_get_clean();
    
        // Limpar recursos
        imagedestroy($im);
        imagedestroy($thumb);
    
        // Exclui o arquivo temporário
        unlink($tempFile);
    
        return $thumbData;
    }
    
    
    
    
    /**
     * Retorna um mime-type básico para a extensão dada.
     */
    private function getMimeTypeByExtension($ext) {
        switch (strtolower($ext)) {
            case 'png': return 'image/png';
            case 'gif': return 'image/gif';
            case 'jpg':
            case 'jpeg': return 'image/jpeg';
            default: return 'application/octet-stream';
        }
    }

    private function fixOrientationIfNeeded($im, $filePath) {
        // Lê EXIF do arquivo
        $exif = @exif_read_data($filePath);
        if (!$exif || !isset($exif['Orientation'])) {
            return $im; // Sem Orientation => nada a fazer
        }
    
        switch ($exif['Orientation']) {
            case 3:
                // Rotaciona 180°
                $im = imagerotate($im, 180, 0);
                break;
            case 6:
                // Rotaciona 90° horário
                $im = imagerotate($im, -90, 0);
                break;
            case 8:
                // Rotaciona 90° anti-horário
                $im = imagerotate($im, 90, 0);
                break;
        }
    
        return $im;
    }

    // Função para verificar se o arquivo existe no S3
    // Função para verificar se o arquivo existe no S3
    private function checkIfFileExists($fileName, $directory) {
        try {
            // Tenta obter o objeto usando a chave (nome do arquivo)
            if ($this->s3Client) {
                $this->s3Client->headObject([
                    'Bucket' => $this->s3Bucket,
                    'Key'    => $directory. '/' .$fileName,
                ]);
                // Se não lançar erro, significa que o arquivo existe
                return true;
            } else {
                throw new Exception("Cliente S3 não está inicializado.");
            }
        } catch (Aws\S3\Exception\S3Exception $e) {
            // Se o erro for NoSuchKey, significa que o arquivo não existe
            if ($e->getAwsErrorCode() === 'NoSuchKey' || $e->getAwsErrorCode() === 'NotFound') {
                return false;
            }
            // Para outros erros, lançar novamente
            throw $e;
        }
    }

    // Função para enviar a imagem para o S3
    private function uploadImageToS3($filePath, $directory, $fileName) {
        try {
            $mimeType = mime_content_type($filePath); // Obtém o tipo MIME da imagem

            // Enviar para o bucket S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $directory . '/' . $fileName, // Caminho do arquivo no S3
                'SourceFile' => $filePath, // Caminho local do arquivo
                'ContentType' => $mimeType, // Tipo de conteúdo da imagem
            ]);

            // Enviar para o bucket S3
            $result_thumb = $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $directory . '/thumbs/' . $fileName, // Caminho do arquivo no S3
                'SourceFile' => $filePath, // Caminho local do arquivo
                'ContentType' => $mimeType, // Tipo de conteúdo da imagem
            ]);

            // Retornar a URL do arquivo no S3
            return $fileName;

        } catch (Exception $e) {
            // Tratar erro
            echo "Erro ao enviar a imagem para o S3: " . $e->getMessage() . "\n";
            return false;
        }
    }



    // Função atualizar estatisticas padel
    public function popula_estatisticas_padel()
    {
        $this->autoRender = false;
        $this->response->type('json');

        $this->loadModel('EstatisticaPadel');

        // Query para calcular a pontuação
        $sql = "
            SELECT
                u.id AS usuario_id,

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

                COUNT(DISTINCT tor.id) AS torneios_participados,
        
                COUNT(DISTINCT tj.id) AS jogos_participados,

                -- Vitórias em Jogos (Jogo Ganho = 2 pontos, exceto em finais vencidas)
                COUNT(DISTINCT CASE
                    WHEN tj.vencedor = ti.id THEN tj.id
                END) AS vitorias_jogos,

                tc.categoria_id as tc_id,
                tc.sexo as tc_sexo


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

            -- Vinculação categorias com torneios
            LEFT JOIN torneios tor ON tc.torneio_id = tor.id

            WHERE u.ativo = 'Y' AND tj.vencedor IS NOT NULL
            GROUP BY u.id, tc.categoria_id, tc.sexo";


        // Executa a query diretamente
        $results = $this->Usuario->query($sql);

        // Formata os resultados
        $ranking = array();
        foreach ($results as $row) {
            $dados_salvar = [
                'categoria_id' => $row['tc']['tc_id'],
                'sexo' => $row['tc']['tc_sexo'],
                'vitorias' => $row[0]['vitorias_jogos'],
                'torneio_jogos' => $row[0]['jogos_participados'],
                'torneios_participados' => $row[0]['torneios_participados'],
                'torneios_vencidos' => $row[0]['torneios_vencidos'],
                'usuario_id' => $row['u']['usuario_id'],
                'finais_perdidas' => $row[0]['finais_perdidas'],
                'avancos_de_fase' => $row[0]['avancos_de_fase'],
                'pontuacao_total' => $this->calcula_pontuacao_total($row)
            ];

            try {
                $this->EstatisticaPadel->create();
                if (!$this->EstatisticaPadel->save($dados_salvar)) {
                    // Caso a operação de save falhe sem lançar uma exceção
                    // Você pode lidar com isso aqui, por exemplo, logando os erros de validação
                    debug($this->EstatisticaPadel->validationErrors);
                    // Opcionalmente, você pode continuar ou interromper a execução
                    // continue; // Para continuar com o próximo loop
                    // ou
                    // throw new Exception('Falha ao salvar estatísticas para o usuário ID: ' . $row['u']['usuario_id']);
                }
            } catch (Exception $e) {
                debug($dados_salvar);
                // Captura qualquer exceção lançada durante o processo de save
                debug($e->getMessage());
                // Termina a execução. Você pode optar por não usar `die()` em ambientes de produção
                die();
            }
            
        }

        // Retorna o JSON
        return new CakeResponse(array('type' => 'json', 'body' => json_encode(array('status' => 'ok', 'dados' => $dados_salvar))));
    }
    
    private function calcula_pontuacao_total($row) {
        $pontuacao_total = 0;

        $pontuacao_total += $row[0]['torneios_vencidos'] * 23;
        $pontuacao_total += $row[0]['finais_perdidas'] * 10;
        $pontuacao_total += $row[0]['avancos_de_fase'] * 5;
        $pontuacao_total += $row[0]['vitorias_jogos'] * 2;

        return $pontuacao_total;
    }

}