<?php

namespace Hcode\Backup;

use Exception;

class BackupService
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = __DIR__ . '/../../../../backup';

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }
    }

    public function createBackup(string $database): string
    {
        $date = date('Y-m-d');
        $file = "{$this->backupDir}/{$database}_{$date}.sql";

        // 🔒 Se já existe, não gera novamente
        if (file_exists($file)) {
            return $file;
        }

        $dbHost = 'localhost';
        $dbUser = 'root';
        $dbPass = escapeshellarg('#Wiccan13#');

        $mysqldump = '"C:\\xampp\\mysql\\bin\\mysqldump.exe"';

        $command = "{$mysqldump} -h {$dbHost} -u {$dbUser} -p{$dbPass} {$database} > \"{$file}\"";

        exec($command, $output, $return);

        if ($return !== 0 || !file_exists($file)) {
            throw new \Exception('Falha ao gerar backup');
        }

        return $file;
    }
}
