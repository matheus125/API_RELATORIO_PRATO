<?php

namespace Hcode;

use Hcode\Model\Funcionarios;
use Hcode\Model\Notification;

class PageAdmin extends Page
{
    public function __construct($opts = array(), $tpl_dir = "/admin/")
    {
        $data = isset($opts['data']) && is_array($opts['data']) ? $opts['data'] : [];

        $headerEnabled = ($opts['header'] ?? true) !== false;

        if ($headerEnabled) {
            $notificacoesSessao = $data['notificacoes'] ?? Notification::getAll();
            $notificacoesBackup = function_exists('getBackupNotifications') ? getBackupNotifications(10) : [];

            $notificacoes = array_merge($notificacoesSessao, $notificacoesBackup);
            $total = $data['total'] ?? count($notificacoes);

            $usuario = Funcionarios::getFromSession();

            $opts['data'] = array_merge([
                'notificacoes' => $notificacoes,
                'total' => $total,
                'currentProfile' => $usuario->getperfil() ?: '',
                'perfil_usuario' => $usuario->getperfil() ?: '',
                'currentPermissions' => Funcionarios::getPermissions(),
                'acl_permissions' => $_SESSION['acl_permissions'] ?? Funcionarios::getPermissions()
            ], $data);
        } else {
            $opts['data'] = $data;
        }

        parent::__construct($opts, $tpl_dir);
    }
}