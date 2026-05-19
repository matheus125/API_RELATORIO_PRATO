<?php

namespace Hcode\Middleware;

use Slim\Middleware;
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
            '/admin/login'
        ];

        if (in_array($path, $rotasPublicas, true)) {
            $this->next->call();
            return;
        }

        if (!Funcionarios::checkLogin()) {
            header('Location: /admin/login');
            exit;
        }

        $requiredPermission = $this->findRequiredPermission($path);

        if ($requiredPermission && !Funcionarios::hasPermission($requiredPermission)) {
            self::logAcessoNegado(
                $_SESSION[Funcionarios::SESSION]['perfil'] ?? 'SEM_PERFIL',
                $path,
                $requiredPermission
            );

            header('Location: /acesso-negado');
            exit;
        }

        $this->next->call();
    }

    private function findRequiredPermission(string $uri): ?string
    {
        foreach (Permissions::routeMap() as $rota => $permission) {
            $rotaRegex = preg_replace('/:\w+/', '[^/]+', $rota);
            $rotaRegex = str_replace('{id}', '[^/]+', $rota);

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
                 VALUES (:perfil, :rota, :ip, :user_agent, NOW())",
                [
                    ':perfil' => $perfil . ($permission ? ' [' . $permission . ']' : ''),
                    ':rota' => $rota,
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN'
                ]
            );
        } catch (\Exception $e) {
            // evita quebrar o sistema
        }
    }
}
