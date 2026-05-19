<?php


use Hcode\Model\Funcionarios;


class AuthMiddleware
{
    private $perfisPermitidos;


    public function __construct($perfisPermitidos = null)
    {
        $this->perfisPermitidos = $perfisPermitidos;
    }


    public function __invoke($request, $response, $next)
    {
        if (!Funcionarios::checkLogin($this->perfisPermitidos)) {


            if (!isset($_SESSION[Funcionarios::SESSION]['id_usuario'])) {
                return $response->withHeader('Location', '/login')->withStatus(302);
            }


            return $response->withHeader('Location', '/acesso-negado')->withStatus(302);
        }


        return $next($request, $response);
    }
}
