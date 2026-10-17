<?php

namespace Hcode\Model;

use Hcode\DB\Sql;
use Throwable;

class Notification
{
    const SESSION_KEY = "notifications";

    public static function add($msg, $time = null)
    {
        self::addPersistent([
            'titulo' => 'Notificação',
            'mensagem' => (string)$msg,
            'tipo' => 'sistema',
            'status' => 'novo',
            'created_at' => $time ? self::normalizeDateTime(date('Y-m-d') . ' ' . $time . ':00', 'America/Manaus') : self::nowUtc(),
        ]);
    }

    public static function addPersistent(array $data): bool
    {
        if (!self::tableAvailable()) {
            self::addSessionFallback($data);
            return false;
        }

        try {
            $sql = new Sql();
            $sql->query("
                INSERT INTO tb_notificacoes (
                    titulo, mensagem, tipo, unidade_id, relatorio_id, link_destino,
                    status, lida, created_at, updated_at
                ) VALUES (
                    :titulo, :mensagem, :tipo, :unidade_id, :relatorio_id, :link_destino,
                    :status, 0, :created_at, UTC_TIMESTAMP()
                )
            ", [
                ':titulo' => self::limit($data['titulo'] ?? 'Notificação', 180),
                ':mensagem' => (string)($data['mensagem'] ?? ''),
                ':tipo' => self::limit($data['tipo'] ?? 'sistema', 60),
                ':unidade_id' => self::nullable($data['unidade_id'] ?? null, 120),
                ':relatorio_id' => isset($data['relatorio_id']) && $data['relatorio_id'] !== '' ? (int)$data['relatorio_id'] : null,
                ':link_destino' => self::nullable($data['link_destino'] ?? null, 500),
                ':status' => self::limit($data['status'] ?? 'novo', 60),
                ':created_at' => self::normalizeDateTime($data['created_at'] ?? null, 'UTC'),
            ]);
            return true;
        } catch (Throwable $e) {
            self::log('Falha ao gravar notificação: ' . $e->getMessage());
            self::addSessionFallback($data);
            return false;
        }
    }

    public static function addReportNotification(array $relatorio): bool
    {
        $relatorioId = isset($relatorio['id']) ? (int)$relatorio['id'] : null;
        $unidade = self::firstNonEmpty($relatorio, ['nome_unidade', 'unidade', 'unidade_nome', 'nome_banco', 'responsavel'], 'Unidade não identificada');
        $status = self::firstNonEmpty($relatorio, ['status_upload'], 'SUCESSO');
        $tipoRelatorio = self::detectReportType($relatorio);
        $dataEnvio = self::normalizeDateTime($relatorio['data_upload'] ?? $relatorio['data_geracao'] ?? null, 'UTC');

        return self::addPersistent([
            'titulo' => 'Relatório recebido',
            'mensagem' => $unidade . ' enviou ' . $tipoRelatorio . ' em ' . self::displayDateTime($dataEnvio) . '. Status: ' . $status . '.',
            'tipo' => 'relatorio_enviado',
            'unidade_id' => $relatorio['id_base_consulta'] ?? $relatorio['nome_banco'] ?? null,
            'relatorio_id' => $relatorioId,
            'link_destino' => $relatorioId ? '/admin/relatorio/pdf/historico?id=' . $relatorioId : '/admin/relatorio/pdf/historico',
            'status' => $status,
            'created_at' => $dataEnvio,
        ]);
    }

    public static function addUpdatePublished(array $data): bool
    {
        return self::addPersistent([
            'titulo' => 'Atualização publicada',
            'mensagem' => 'Versão ' . ($data['version'] ?? '-') . ' publicada em ' . self::displayDateTime($data['published_at'] ?? null) . '.',
            'tipo' => 'atualizacao_publicada',
            'link_destino' => '/admin/monitoramento-atualizacoes',
            'status' => 'SUCESSO',
        ]);
    }

    public static function getAll($limit = 10)
    {
        if (!self::tableAvailable()) {
            return self::getSessionAll();
        }

        try {
            $sql = new Sql();
            $limit = max(1, min(50, (int)$limit));
            $rows = $sql->select("
                SELECT id, titulo, mensagem, mensagem AS msg, tipo, unidade_id, relatorio_id,
                       link_destino, status, lida, created_at, updated_at, read_at,
                       created_at AS time
                FROM tb_notificacoes
                ORDER BY created_at DESC, id DESC
                LIMIT {$limit}
            ");
            foreach ($rows as &$row) {
                $row['created_at'] = self::displayDateTime($row['created_at'] ?? null);
                $row['updated_at'] = self::displayDateTime($row['updated_at'] ?? null);
                $row['read_at'] = self::displayDateTime($row['read_at'] ?? null);
                $row['time'] = $row['created_at'];
            }
            unset($row);
            return $rows ?: [];
        } catch (Throwable $e) {
            self::log('Falha ao listar notificações: ' . $e->getMessage());
            return self::getSessionAll();
        }
    }

    public static function getTotal()
    {
        if (!self::tableAvailable()) {
            return count(self::getSessionAll());
        }

        try {
            $sql = new Sql();
            $rows = $sql->select("SELECT COUNT(*) AS total FROM tb_notificacoes WHERE lida = 0");
            return (int)($rows[0]['total'] ?? 0);
        } catch (Throwable $e) {
            self::log('Falha ao contar notificações: ' . $e->getMessage());
            return count(self::getSessionAll());
        }
    }

    public static function markRead($id): bool
    {
        if (!self::tableAvailable()) return false;
        try {
            $sql = new Sql();
            $sql->query("UPDATE tb_notificacoes SET lida = 1, read_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = :id", [
                ':id' => (int)$id,
            ]);
            return true;
        } catch (Throwable $e) {
            self::log('Falha ao marcar notificação como lida: ' . $e->getMessage());
            return false;
        }
    }

    public static function markAllRead(): bool
    {
        if (!self::tableAvailable()) {
            self::clear();
            return false;
        }
        try {
            $sql = new Sql();
            $sql->query("UPDATE tb_notificacoes SET lida = 1, read_at = COALESCE(read_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP() WHERE lida = 0");
            return true;
        } catch (Throwable $e) {
            self::log('Falha ao marcar todas notificações como lidas: ' . $e->getMessage());
            return false;
        }
    }

    public static function clear()
    {
        $_SESSION[self::SESSION_KEY] = [];
    }

    private static function tableAvailable(): bool
    {
        static $available = null;
        if ($available !== null) return $available;
        try {
            $sql = new Sql();
            $rows = $sql->select("SHOW TABLES LIKE 'tb_notificacoes'");
            $available = !empty($rows);
        } catch (Throwable $e) {
            $available = false;
        }
        return $available;
    }

    private static function addSessionFallback(array $data): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        array_unshift($_SESSION[self::SESSION_KEY], [
            'id' => null,
            'titulo' => $data['titulo'] ?? 'Notificação',
            'msg' => $data['mensagem'] ?? ($data['msg'] ?? ''),
            'mensagem' => $data['mensagem'] ?? ($data['msg'] ?? ''),
            'tipo' => $data['tipo'] ?? 'sistema',
            'link_destino' => $data['link_destino'] ?? null,
            'lida' => 0,
            'time' => self::displayDateTime(null),
        ]);
    }

    private static function getSessionAll(): array
    {
        return isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY])
            ? $_SESSION[self::SESSION_KEY]
            : [];
    }

    private static function normalizeDateTime($value, string $sourceTimezone = 'America/Manaus'): string
    {
        if (function_exists('portal_to_utc_sql')) {
            return portal_to_utc_sql($value ?: self::nowUtc(), $sourceTimezone) ?: self::nowUtc();
        }
        $ts = strtotime((string)$value);
        return $ts ? gmdate('Y-m-d H:i:s', $ts) : gmdate('Y-m-d H:i:s');
    }

    private static function nowUtc(): string
    {
        return function_exists('portal_utc_now') ? portal_utc_now() : gmdate('Y-m-d H:i:s');
    }

    private static function displayDateTime($value): string
    {
        if (function_exists('portal_format_manaus')) {
            return portal_format_manaus($value ?: self::nowUtc());
        }
        $ts = $value ? strtotime((string)$value) : time();
        return gmdate('d/m/Y H:i:s', $ts ?: time());
    }

    private static function detectReportType(array $relatorio): string
    {
        $file = strtolower((string)($relatorio['nome_arquivo'] ?? $relatorio['caminho_remoto'] ?? ''));
        if (strpos($file, '.xlsx') !== false) return 'planilha mensal';
        if (strpos($file, '.pdf') !== false) return 'relatório PDF';
        return 'relatório';
    }

    private static function firstNonEmpty(array $data, array $keys, string $default): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string)$data[$key]) !== '') return trim((string)$data[$key]);
        }
        return $default;
    }

    private static function nullable($value, int $limit)
    {
        $value = self::limit($value, $limit);
        return $value === '' ? null : $value;
    }

    private static function limit($value, int $limit): string
    {
        $value = trim((string)$value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private static function log(string $message): void
    {
        $dir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 5) . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'notificacoes.log', '[' . (function_exists('portal_log_timestamp') ? portal_log_timestamp() : gmdate('Y-m-d H:i:s') . ' UTC') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
