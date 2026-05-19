<?php

namespace Hcode\Backup;

use CURLFile;
use Exception;

class UploadService
{
    private string $uploadUrl = 'https://ms-tecnologia.app.br/backups/backup-upload.php';
    private string $token = 'PRATO_CHEIO_2026_SEGURANCA';

    private int $timeoutSeconds = 60;
    private int $connectTimeoutSeconds = 15;

    public function send(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo de backup não encontrado: {$filePath}");
        }

        $sentFlag = $filePath . '.sent';
        $lockFlag = $filePath . '.uploading';

        // ✅ já enviado
        if (file_exists($sentFlag)) {
            return;
        }

        // ✅ evita upload duplicado em paralelo (múltiplas requisições)
        if (file_exists($lockFlag)) {
            $lockTime = (int) @file_get_contents($lockFlag);
            // lock válido por 10 minutos
            if ($lockTime > 0 && (time() - $lockTime) < 600) {
                return;
            }
        }

        // cria/atualiza lock
        @file_put_contents($lockFlag, (string) time());

        $ch = curl_init($this->uploadUrl);
        if ($ch === false) {
            @unlink($lockFlag);
            throw new Exception("Falha ao inicializar cURL.");
        }

        $post = [
            // seu servidor espera 'backup'
            "backup" => new CURLFile($filePath, "application/sql", basename($filePath)),
            "token"  => $this->token
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,

            // ✅ timeouts
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,

            // ✅ seguir redirects (http -> https, etc.)
            CURLOPT_FOLLOWLOCATION => true,

            // ✅ SSL (mantenha ligado em produção)
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,

            // ✅ evita latência do Expect: 100-continue em alguns hosts
            CURLOPT_HTTPHEADER => ['Expect:'],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // ✅ captura erro do cURL
        if ($response === false) {
            $errNo = curl_errno($ch);
            $errMsg = curl_error($ch);
            curl_close($ch);
            @unlink($lockFlag);
            throw new Exception("Erro cURL ({$errNo}): {$errMsg}");
        }

        curl_close($ch);
        @unlink($lockFlag);

        $responseTrim = trim((string)$response);

        // ✅ só sucesso se HTTP 2xx
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Upload falhou. HTTP={$httpCode}. Resposta='{$responseTrim}'");
        }

        // ✅ valida resposta do seu servidor
        if ($responseTrim !== 'UPLOAD_OK') {
            throw new Exception("Upload falhou. Resposta inesperada: '{$responseTrim}'");
        }

        // ✅ só marca como enviado depois de sucesso real
        file_put_contents($sentFlag, 'OK');
    }
}
