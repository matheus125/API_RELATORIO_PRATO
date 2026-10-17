<?php

namespace Hcode\Middleware;

use Slim\Middleware;
use Hcode\Audit\AuditLogger;
use Hcode\Model\Funcionarios;
use Hcode\Model\Permissions;
use Hcode\DB\Sql;

class PerfilMiddleware extends Middleware
{
    public function call()
    {
        $app = \Slim\Slim::getInstance();
        $path = strtok($app->request()->getPathInfo(), '?');

        $rotasPublicas = [
            '/',
            '/login',
            '/admin/login',
            '/api/updates/status'
        ];

        if (in_array($path, $rotasPublicas, true)) {
            $this->callNextWithAudit($path, false);
            return;
        }

        if (!Funcionarios::checkLogin()) {
            AuditLogger::log([
                'categoria' => AuditLogger::PERMISSION_DENIED,
                'acao' => 'AUTH_REQUIRED',
                'modulo' => 'AUTH',
                'rota' => $path,
                'descricao' => 'Tentativa de acesso sem autenticação ou sessão expirada.',
                'status' => 'DENIED',
                'severidade' => AuditLogger::WARNING,
            ]);

            if (strpos($path, '/api/') === 0) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Sessao expirada.']);
                exit;
            }
            header('Location: /admin/login');
            exit;
        }

        // Initialize new catalogue entries before checking independently managed screens.
        // A transient catalogue failure must not interrupt unrelated authenticated routes.
        try {
            Permissions::syncDefinitions();
        } catch (\Throwable $error) {
            // The permission check below remains fail-closed for unavailable grants.
        }
        $requiredPermission = $this->findRequiredPermission($path);

        if ($requiredPermission && !Funcionarios::hasPermission($requiredPermission)) {
            self::logAcessoNegado(
                $_SESSION[Funcionarios::SESSION]['perfil'] ?? 'SEM_PERFIL',
                $path,
                $requiredPermission
            );

            $label = AuditLogger::routeLabel($path);
            AuditLogger::log(array_merge($label, [
                'categoria' => AuditLogger::PERMISSION_DENIED,
                'acao' => AuditLogger::PERMISSION_DENIED,
                'rota' => $path,
                'descricao' => 'Usuário tentou acessar recurso sem permissão.',
                'status' => 'DENIED',
                'severidade' => AuditLogger::WARNING,
                'erro' => ['permissao_exigida' => $requiredPermission],
            ]));

            if (strpos($path, '/api/') === 0) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
                exit;
            }

            header('Location: /acesso-negado');
            exit;
        }

        // All authenticated state-changing browser requests must carry the
        // session CSRF token. GADSAN has a dedicated token and the status
        // heartbeat is authenticated by its own bearer token.
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $csrfExempt = $path === '/admin/login'
            || strpos($path, '/admin/gadsan') === 0
            || $path === '/api/updates/status';
        $sourceTokenRequest = $path === '/admin/api/source-sync/upload'
            && function_exists('portalSourceUploadAuthorized')
            && portalSourceUploadAuthorized();
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && !$csrfExempt && !$sourceTokenRequest && function_exists('portal_require_csrf')) {
            portal_require_csrf($_POST);
        }

        $this->callNextWithAudit($path, true);
    }

    private function callNextWithAudit(string $path, bool $auditAccess): void
    {
        try {
            $this->next->call();
            if ($auditAccess && $this->shouldAuditAccess($path)) {
                $label = AuditLogger::routeLabel($path);
                AuditLogger::log(array_merge($label, [
                    'categoria' => AuditLogger::ACCESS,
                    'acao' => 'ACCESS_ROUTE',
                    'rota' => $path,
                    'descricao' => 'Usuário acessou a tela "' . ($label['pagina'] ?? $path) . '".',
                    'entidade' => $label['entidade'] ?? null,
                    'registro_id' => $label['registro_id'] ?? null,
                ]));
            }
        } catch (\Throwable $e) {
            AuditLogger::logError($e, [
                'modulo' => 'SISTEMA',
                'rota' => $path,
                'descricao' => 'Erro interno durante processamento da rota.',
            ]);
            throw $e;
        }
    }

    private function shouldAuditAccess(string $path): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            return false;
        }
        if (strpos($path, '/admin/api/') === 0 || strpos($path, '/api/') === 0) {
            return false;
        }
        if (preg_match('/\.(css|js|map|png|jpe?g|gif|svg|ico|woff2?|ttf|eot)$/i', $path)) {
            return false;
        }
        return strpos($path, '/admin') === 0 || $path === '/acesso-negado';
    }

    private function findRequiredPermission(string $uri): ?string
    {
        foreach (Permissions::routeMap() as $rota => $permission) {
            $rotaRegex = preg_replace('/:\w+/', '[^/]+', $rota);
            $rotaRegex = str_replace('{id}', '[^/]+', $rotaRegex);

            if (preg_match("#^{$rotaRegex}$#", $uri)) {
                return $permission;
            }
        }

        return null;
    }

    private static function logAcessoNegado($perfil, $rota, $permission = null)
    {
        try {
            $sql = new Sql();
            $sql->query(
                "INSERT INTO tb_access_denied (perfil, rota, ip, user_agent, created_at)
                 VALUES (:perfil, :rota, :ip, :user_agent, :created_at)",
                [
                    ':perfil' => $perfil . ($permission ? ' [' . $permission . ']' : ''),
                    ':rota' => $rota,
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
                    ':created_at' => function_exists('portal_utc_now') ? \portal_utc_now() : gmdate('Y-m-d H:i:s')
                ]
            );
        } catch (\Exception $e) {
            // evita quebrar o sistema
        }
    }
}
