<?php

namespace Hcode\Audit;

use Hcode\DB\Sql;

class AuditLogger
{
    public const AUTH_LOGIN = 'AUTH_LOGIN';
    public const AUTH_LOGIN_FAILED = 'AUTH_LOGIN_FAILED';
    public const AUTH_LOGOUT = 'AUTH_LOGOUT';
    public const AUTH_PASSWORD_CHANGE = 'AUTH_PASSWORD_CHANGE';
    public const AUTH_PASSWORD_RECOVERY = 'AUTH_PASSWORD_RECOVERY';
    public const ACCESS = 'ACCESS';
    public const VIEW = 'VIEW';
    public const SEARCH = 'SEARCH';
    public const CREATE = 'CREATE';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';
    public const DOWNLOAD = 'DOWNLOAD';
    public const UPLOAD = 'UPLOAD';
    public const PRINT_EVENT = 'PRINT';
    public const EXPORT = 'EXPORT';
    public const PERMISSION_DENIED = 'PERMISSION_DENIED';
    public const SYSTEM_ERROR = 'SYSTEM_ERROR';

    public const INFO = 'INFO';
    public const NOTICE = 'NOTICE';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    private const SENSITIVE_KEYS = [
        'password',
        'senha',
        'senha-confirm',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'api_key',
        'authorization',
        'cookie',
        'cookies',
        'hash',
        'desrecovery',
        'recovery',
    ];

    private static $columnsCache = [];

    public static function log(array $event): void
    {
        try {
            $sql = new Sql();
            $columns = self::columns($sql);
            if (!$columns) {
                return;
            }

            $sessionUser = $_SESSION['User'] ?? [];
            $actor = array_merge([
                'id_usuario' => $sessionUser['id_usuario'] ?? null,
                'cpf' => $sessionUser['cpf'] ?? '',
                'nome_funcionario' => $sessionUser['nome_funcionario'] ?? '',
            ], is_array($event['actor'] ?? null) ? $event['actor'] : []);

            $categoria = (string)($event['categoria'] ?? $event['acao'] ?? self::ACCESS);
            $acao = (string)($event['acao'] ?? $categoria);
            $status = (string)($event['status'] ?? 'SUCCESS');
            $severity = (string)($event['severidade'] ?? self::severityFor($categoria, $status));
            $details = $event['detalhes'] ?? $event['descricao'] ?? null;

            $record = [
                'id_usuario' => (int)($actor['id_usuario'] ?? 0),
                'cpf_usuario' => (string)($actor['cpf'] ?? $actor['cpf_usuario'] ?? ''),
                'nome_funcionario' => (string)($actor['nome_funcionario'] ?? $actor['usuario_nome'] ?? ''),
                'acao' => self::limit($acao, 50),
                'categoria' => self::limit($categoria, 60),
                'severidade' => self::limit($severity, 20),
                'modulo' => self::limit((string)($event['modulo'] ?? ''), 80),
                'menu' => self::limit((string)($event['menu'] ?? ''), 120),
                'sub_menu' => self::limit((string)($event['sub_menu'] ?? ''), 120),
                'entidade' => self::limit((string)($event['entidade'] ?? ''), 100),
                'referencia_id' => isset($event['referencia_id']) ? (int)$event['referencia_id'] : (isset($event['registro_id']) && is_numeric($event['registro_id']) ? (int)$event['registro_id'] : null),
                'registro_id' => isset($event['registro_id']) ? self::limit((string)$event['registro_id'], 100) : null,
                'detalhes' => $details,
                'descricao' => (string)($event['descricao'] ?? $details ?? ''),
                'dados_anteriores' => self::json($event['dados_anteriores'] ?? null),
                'dados_novos' => self::json($event['dados_novos'] ?? null),
                'alteracoes' => self::json($event['alteracoes'] ?? null),
                'rota' => self::limit((string)($event['rota'] ?? self::route()), 255),
                'metodo_http' => self::limit((string)($event['metodo_http'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'CLI')), 12),
                'ip' => self::limit((string)($event['ip'] ?? self::ip()), 45),
                'user_agent' => (string)($event['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN')),
                'sessao_id' => self::limit((string)($event['sessao_id'] ?? (session_id() ?: '')), 128),
                'status' => self::limit($status, 30),
                'erro' => isset($event['erro']) ? self::stringify(self::sanitize($event['erro'])) : null,
                'created_at' => function_exists('portal_utc_now') ? \portal_utc_now() : gmdate('Y-m-d H:i:s'),
            ];

            if ($record['id_usuario'] <= 0 && !in_array($categoria, [self::AUTH_LOGIN, self::AUTH_LOGIN_FAILED, self::PERMISSION_DENIED, self::SYSTEM_ERROR], true)) {
                return;
            }

            $insert = [];
            $params = [];
            foreach ($record as $column => $value) {
                if (!isset($columns[$column])) {
                    continue;
                }
                if ($column === 'id_usuario' && (int)$value <= 0) {
                    continue;
                }
                $insert[] = $column;
                $params[':' . $column] = $value;
            }

            if (!isset($params[':acao']) || empty($insert)) {
                return;
            }

            $placeholders = array_map(function ($column) {
                return ':' . $column;
            }, $insert);
            $sql->query(
                'INSERT INTO tb_userlogs (' . implode(', ', $insert) . ') VALUES (' . implode(', ', $placeholders) . ')',
                $params
            );
        } catch (\Throwable $e) {
            self::fallback('Falha ao gravar auditoria: ' . $e->getMessage());
        }
    }

    public static function diff(array $before, array $after): array
    {
        $before = self::sanitize($before);
        $after = self::sanitize($after);
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if (self::normalizeComparable($old) !== self::normalizeComparable($new)) {
                $changes[$key] = ['antes' => $old, 'depois' => $new];
            }
        }
        return $changes;
    }

    public static function sanitize($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $keyText = strtolower((string)$key);
                if (self::isSensitiveKey($keyText)) {
                    $clean[$key] = '[REDACTED]';
                    continue;
                }
                $clean[$key] = self::sanitize($item);
            }
            return $clean;
        }

        if (is_object($value)) {
            return self::sanitize((array)$value);
        }

        return $value;
    }

    public static function logError(\Throwable $e, array $context = []): void
    {
        self::log(array_merge($context, [
            'categoria' => self::SYSTEM_ERROR,
            'acao' => $context['acao'] ?? self::SYSTEM_ERROR,
            'status' => 'FAILED',
            'severidade' => self::ERROR,
            'erro' => [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ],
        ]));
    }

    public static function route(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strtok($uri, '?') ?: $uri;
    }

    public static function routeLabel(string $path): array
    {
        $map = [
            '/admin' => ['DASHBOARD', 'Dashboard', '', 'Painel administrativo'],
            '/admin/funcionarios' => ['FUNCIONARIOS', 'Funcionários', '', 'Funcionários'],
            '/admin/funcionarios/create' => ['FUNCIONARIOS', 'Funcionários', 'Cadastro', 'Novo funcionário'],
            '/admin/usuarios/seguranca' => ['SEGURANCA', 'Segurança', 'Usuários', 'Usuários e segurança'],
            '/admin/seguranca/permissoes' => ['SEGURANCA', 'Segurança', 'Permissões', 'Permissões por perfil'],
            '/admin/seguranca/acessos-negados' => ['SEGURANCA', 'Segurança', 'Acessos negados', 'Acessos negados'],
            '/admin/seguranca/auditoria' => ['SEGURANCA', 'Segurança', 'Auditoria', 'Auditoria'],
            '/admin/relatorio/planilha' => ['RELATORIOS', 'Relatórios', 'Planilhas', 'Relatório de planilha'],
            '/admin/relatorio/pdf/historico' => ['RELATORIOS', 'Relatórios', 'PDFs', 'Histórico de PDFs'],
            '/admin/consulta-unificada' => ['RELATORIOS', 'Relatórios', 'Consulta unificada', 'Consulta unificada'],
        ];

        if (isset($map[$path])) {
            return ['modulo' => $map[$path][0], 'menu' => $map[$path][1], 'sub_menu' => $map[$path][2], 'pagina' => $map[$path][3]];
        }

        if (preg_match('#^/admin/funcionarios/(\d+)(?:/password)?$#', $path, $m)) {
            return [
                'modulo' => 'FUNCIONARIOS',
                'menu' => 'Funcionários',
                'sub_menu' => self::endsWith($path, '/password') ? 'Senha' : 'Cadastro',
                'pagina' => self::endsWith($path, '/password') ? 'Alteração de senha' : 'Detalhes do funcionário',
                'entidade' => 'funcionario',
                'registro_id' => $m[1],
            ];
        }

        return ['modulo' => 'SISTEMA', 'menu' => '', 'sub_menu' => '', 'pagina' => $path];
    }

    private static function columns(Sql $sql): array
    {
        if (self::$columnsCache) {
            return self::$columnsCache;
        }

        try {
            $rows = $sql->select('SHOW COLUMNS FROM tb_userlogs');
            foreach ($rows as $row) {
                self::$columnsCache[(string)$row['Field']] = true;
            }
        } catch (\Throwable $e) {
            self::$columnsCache = [];
        }

        return self::$columnsCache;
    }

    private static function severityFor(string $category, string $status): string
    {
        if ($status !== 'SUCCESS') {
            return in_array($category, [self::AUTH_LOGIN_FAILED, self::PERMISSION_DENIED], true) ? self::WARNING : self::ERROR;
        }
        if (in_array($category, [self::DELETE, self::AUTH_PASSWORD_CHANGE], true)) {
            return self::NOTICE;
        }
        return self::INFO;
    }

    private static function json($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return json_encode(self::sanitize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function stringify($value): string
    {
        if (is_array($value) || is_object($value)) {
            return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string)$value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || strpos($key, $sensitive) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function normalizeComparable($value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return trim((string)$value);
    }

    private static function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $value = (string)$_SERVER[$key];
                return trim(explode(',', $value)[0]);
            }
        }
        return '0.0.0.0';
    }

    private static function limit(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }
        return substr($value, 0, $length);
    }

    private static function endsWith(string $value, string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }
        return substr($value, -strlen($suffix)) === $suffix;
    }

    private static function fallback(string $message): void
    {
        $dir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $stamp = function_exists('portal_log_timestamp') ? \portal_log_timestamp() : gmdate('Y-m-d H:i:s') . ' UTC';
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'audit-errors.log', '[' . $stamp . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
