<?php
// app/Lib/ImageUploader.php

App::uses('CakeEmail', 'Network/Email');

class ImageUploader
{
    private $s3Bucket = 'buzke-images'; // Nome do seu bucket S3
    private $s3Client;

    // Construtor para inicializar o cliente S3
    public function __construct()
    {
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

    /**
     * Ajusta a rotação da imagem (caso necessário) usando a info de Orientation no EXIF.
     * 
     * @param resource $im       Recurso GD da imagem já carregada
     * @param string   $filePath Caminho do arquivo original (JPEG que contém EXIF)
     * @return resource Recurso GD da imagem corrigida
     */
    private function fixOrientationIfNeeded($im, $filePath) {
        // Para JPG, tentamos ler o EXIF
        $exif = @exif_read_data($filePath);
        if (!$exif || !isset($exif['Orientation'])) {
            return $im; // Se não tiver orientation, não faz nada
        }

        switch ($exif['Orientation']) {
            case 3:
                // Rotaciona 180 graus
                $im = imagerotate($im, 180, 0);
                break;
            case 6:
                // Rotaciona 90 graus à direita
                $im = imagerotate($im, -90, 0);
                break;
            case 8:
                // Rotaciona 90 graus à esquerda
                $im = imagerotate($im, 90, 0);
                break;
        }
        return $im;
    }


    /**
     * Cria uma thumbnail quadrada (512x512) **no formato JPEG**.
     * Mantém a proporção e redimensiona para caber em 512x512 (sem recortar).
     *
     * @param string $filePath  Caminho do arquivo original
     * @param string $thumbPath Caminho onde será salva a thumbnail (JPEG)
     * @throws Exception se o formato não for suportado
     */
    private function createThumbnail($filePath, $thumbPath)
    {
        list($width, $height, $type) = getimagesize($filePath);

        $thumbWidth = 512;
        $thumbHeight = 512;
        $ratio = min($thumbWidth / $width, $thumbHeight / $height);
        $newWidth  = (int)($width  * $ratio);
        $newHeight = (int)($height * $ratio);

        // Cria uma imagem GD em memória para a thumbnail
        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filePath);
                // Ajusta a orientação se tiver EXIF
                $source = $this->fixOrientationIfNeeded($source, $filePath);
                break;
        
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filePath);
                break;
        
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filePath);
                break;
        
            default:
                throw new Exception('Formato de imagem não suportado');
        }

        // Redimensiona para caber em (newWidth x newHeight)
        imagecopyresampled(
            $thumb,
            $source,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // Salva a thumbnail em JPEG
        imagejpeg($thumb, $thumbPath);

        // Libera recursos
        imagedestroy($thumb);
        imagedestroy($source);
    }

    /**
     * Cria uma **thumbnail redonda** (512x512) em **PNG** com fundo transparente,
     * usando a mesma lógica do RodarEExcluirController.
     *
     * @param string $filePath  Caminho do arquivo original
     * @param string $thumbPath Caminho onde será salvo o arquivo PNG final (redondo)
     * @throws Exception se não for possível criar a imagem
     */
    private function createRoundThumbnail($filePath, $thumbPath)
    {
        // 1. Cria um resource GD a partir do binário do arquivo original
        $im = imagecreatefromstring(file_get_contents($filePath));
        if (!$im) {
            throw new Exception("Falha ao criar imagem a partir dos dados fornecidos.");
        }

        // Dimensões originais
        $origWidth  = imagesx($im);
        $origHeight = imagesy($im);

        // Tamanho final desejado
        $targetSize = 512;

        // 2. Redimensionar mantendo proporção (similar ao RodarEExcluir)
        $scale = max($targetSize / $origWidth, $targetSize / $origHeight);
        $scaledWidth  = (int)($origWidth  * $scale);
        $scaledHeight = (int)($origHeight * $scale);

        // Cria imagem escalada provisória
        $scaledImage = imagecreatetruecolor($scaledWidth, $scaledHeight);
        // Ativar canal alfa
        imagealphablending($scaledImage, false);
        imagesavealpha($scaledImage, true);
        // Preencher com transparente
        $transparent = imagecolorallocatealpha($scaledImage, 0, 0, 0, 127);
        imagefill($scaledImage, 0, 0, $transparent);

        // Redimensiona a imagem original para scaledImage
        imagecopyresampled(
            $scaledImage,
            $im,
            0, 0, 
            0, 0,
            $scaledWidth, $scaledHeight,
            $origWidth, $origHeight
        );

        // 3. Recorta 512x512 do centro do scaledImage
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

        // 4. Aplicar a máscara circular (pixel a pixel)
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

        // 5. Salva em PNG
        ob_start();
        imagepng($finalImage);
        $pngData = ob_get_clean();
        file_put_contents($thumbPath, $pngData);

        // Libera recursos
        imagedestroy($im);
        imagedestroy($scaledImage);
        imagedestroy($finalImage);
    }

    // Método para upload de imagem
    public function uploadToS3($file, $directory, $round_thumb = false)
    {
        try {
            // Verifique se o arquivo foi carregado corretamente
            if (empty($file['tmp_name'])) {
                throw new Exception('Arquivo não carregado corretamente');
            }

            // Nome do arquivo para o S3 (arquivo original)
            $image_name = uniqid() . '_' . basename($file['name']);
            $fileName = $directory . '/' . $image_name;

            // Detecta MIME do arquivo original
            $mimeType = mime_content_type($file['tmp_name']);

            // 1. Enviar o arquivo original para o bucket S3
            $result = $this->s3Client->putObject([
                'Bucket'      => $this->s3Bucket,
                'Key'         => $fileName,
                'SourceFile'  => $file['tmp_name'],
                'ContentType' => $mimeType,
            ]);

            // 2. Criar miniatura quadrada (512x512) no formato JPEG
            $thumbName = $image_name; // ex.: xxx.jpg
            $thumbPath = sys_get_temp_dir() . '/' . $thumbName;

            $this->createThumbnail($file['tmp_name'], $thumbPath);

            // Enviar a thumb quadrada para o S3
            $this->s3Client->putObject([
                'Bucket'      => $this->s3Bucket,
                'Key'         => $directory . '/thumbs/' . $thumbName,
                'SourceFile'  => $thumbPath,
                'ContentType' => 'image/jpeg', // Força JPEG
            ]);
            unlink($thumbPath);

            // 3. Se for solicitado round_thumb, criar a round_ em PNG
            if ($round_thumb) {
                // Nome do arquivo round
                // Ex.: round_xxx.png
                // Obs.: mudamos a extensão p/ .png
                $roundBaseName = pathinfo($image_name, PATHINFO_FILENAME) . '.png';
                $roundName = $roundBaseName;
                $roundPath = sys_get_temp_dir() . '/' . $roundName;

                // Cria a thumbnail redonda (PNG)
                $this->createRoundThumbnail($file['tmp_name'], $roundPath);

                // Envia para o S3 (round_)
                $this->s3Client->putObject([
                    'Bucket'      => $this->s3Bucket,
                    'Key'         => $directory . '/round_thumbs/' . $roundName,
                    'SourceFile'  => $roundPath,
                    'ContentType' => 'image/png', // Força PNG
                ]);
                unlink($roundPath);
            }

            // Retornar a URL do arquivo original
            return $result['ObjectURL'];

        } catch (Exception $e) {
            // Trate o erro aqui (log, re-lançar, etc.)
            return false;
        }
    }

    // Método para deletar imagem do S3
    public function deleteFromS3($fileKey)
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $fileKey,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
