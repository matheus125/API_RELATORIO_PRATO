<?php

namespace Hcode\Middleware;

use Slim\Slim;

class UsuarioAtivoMiddleware
{
    private $next;

    public function __construct($next = null)
    {
        $this->next = $next;
    }

    public function call()
    {
        $app = Slim::getInstance();

        if (!isset($_SESSION['User'])) {
            $app->redirect('/login');
            return;
        }

        if ((int)$_SESSION['User']['ativo'] === 0) {
            // Destroi sessão
            session_destroy();

            // Redireciona com mensagem
            $app->flash('error', 'Usuario desativado. Contate o administrador.');
            $app->redirect('/login');
            return;
        }

        if ($this->next) {
            $this->next->call();
        }
    }
}
