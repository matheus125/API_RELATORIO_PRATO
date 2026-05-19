<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use Hcode\Security\Permissions;

class Funcionarios extends Model
{
	const SESSION = "User";
	const SECRET = "HcodePhp7_Secret";
	const SECRET_IV = "HcodePhp7_Secret_IV";
	const ERROR = "UserError";
	const ERROR_REGISTER = "UserErrorRegister";
	const SUCCESS = "UserSucesss";


	public static function getFromSession()
	{
		$funcionarios = new Funcionarios();

		if (isset($_SESSION[self::SESSION]) && (int)($_SESSION[self::SESSION]['id_usuario'] ?? 0) > 0) {
			$funcionarios->setData($_SESSION[self::SESSION]);
		}

		return $funcionarios;
	}

	public static function userData(): array
	{
		return $_SESSION[self::SESSION] ?? [];
	}

	public static function checkLogin($perfilPermitido = null)
	{
		if (
			!isset($_SESSION[self::SESSION]) ||
			!isset($_SESSION[self::SESSION]['id_usuario']) ||
			(int)$_SESSION[self::SESSION]['id_usuario'] <= 0
		) {
			return false;
		}

		if ($perfilPermitido === null) {
			return true;
		}

		$perfilUsuario = $_SESSION[self::SESSION]['perfil'] ?? null;

		if ($perfilUsuario === null) {
			return false;
		}

		if (is_array($perfilPermitido)) {
			return in_array($perfilUsuario, $perfilPermitido, true);
		}

		return $perfilUsuario === $perfilPermitido;
	}

	public static function verifyLogin($perfilPermitido = null)
	{
		if (!self::checkLogin($perfilPermitido)) {

			if (!isset($_SESSION[self::SESSION]['id_usuario'])) {
				header("Location: /admin/login");
				exit;
			}

			header("Location: /acesso-negado");
			exit;
		}
	}

	public static function getPermissions(bool $forceRefresh = false): array
	{
		if (!self::checkLogin()) {
			return [];
		}

		if (
			!$forceRefresh &&
			isset($_SESSION[self::SESSION]['permissions']) &&
			is_array($_SESSION[self::SESSION]['permissions'])
		) {
			return $_SESSION[self::SESSION]['permissions'];
		}

		$perfil = $_SESSION[self::SESSION]['perfil'] ?? 'ASSESSOR';
		$permissions = [];

		try {
			$sql = new Sql();

			$existsPermissions = $sql->select("SHOW TABLES LIKE 'tb_permissions'");
			$existsProfilePermissions = $sql->select("SHOW TABLES LIKE 'tb_profile_permissions'");

			if (count($existsPermissions) > 0 && count($existsProfilePermissions) > 0) {
				$rows = $sql->select("
					SELECT p.permission_key
					FROM tb_profile_permissions pp
					INNER JOIN tb_permissions p ON p.id_permission = pp.id_permission
					WHERE pp.perfil = :perfil
					ORDER BY p.permission_key
				", [
					':perfil' => $perfil
				]);

				$permissions = array_map(function ($row) {
					return $row['permission_key'];
				}, $rows);
			}
		} catch (\Exception $e) {
			$permissions = [];
		}

		if (empty($permissions)) {
			$defaults = Permissions::defaultProfilePermissions();
			$permissions = $defaults[$perfil] ?? [];
		}

		$_SESSION[self::SESSION]['permissions'] = array_values(array_unique($permissions));

		return $_SESSION[self::SESSION]['permissions'];
	}

	public static function refreshPermissions(): array
	{
		return self::getPermissions(true);
	}

	public static function hasPermission(string $permission): bool
	{
		return in_array($permission, self::getPermissions(), true);
	}

	public static function hasAnyPermission(array $permissions): bool
	{
		foreach ($permissions as $permission) {
			if (self::hasPermission($permission)) {
				return true;
			}
		}
		return false;
	}

	public static function can(string $permissionKey): bool
	{
		if (!self::checkLogin()) {
			return false;
		}

		return self::hasPermission($permissionKey);
	}

	public static function checkPermission(string $permission, bool $redirect = true): bool
	{
		self::verifyLogin();

		$allowed = self::hasPermission($permission);

		if (!$allowed) {
			self::logAccessDenied($permission);

			if ($redirect) {
				header("Location: /acesso-negado");
				exit;
			}
		}

		return $allowed;
	}

	public static function logAccessDenied(string $rota): void
	{
		try {
			$sql = new Sql();

			$sql->query("
				INSERT INTO tb_access_denied (perfil, rota, ip, user_agent)
				VALUES (:perfil, :rota, :ip, :user_agent)
			", [
				':perfil' => $_SESSION[self::SESSION]['perfil'] ?? 'DESCONHECIDO',
				':rota' => $rota,
				':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
				':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', 0, 255)
			]);
		} catch (\Throwable $e) {
			// evita quebrar o fluxo por erro de log
		}
	}

	public static function login($cpf, $senha)
	{
		$sql = new Sql();

		$cpf = preg_replace('/\D+/', '', $cpf);

		$results = $sql->select("
		SELECT
			a.*,
			a.ativo AS usuario_ativo,
			b.id_pessoa,
			b.nome_funcionario,
			b.email,
			b.nrphone,
			b.dtregister AS funcionario_dtregister,
			b.ativo AS funcionario_ativo
		FROM tb_usuario a
		INNER JOIN tb_funcionario b ON a.id_pessoa = b.id_pessoa
		WHERE REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), '/', '') = :CPF
	", [
			":CPF" => $cpf
		]);

		if (count($results) === 0) {
			throw new \Exception("Usuário inexistente ou senha inválida.");
		}

		$data = $results[0];

		if ((int)($data['usuario_ativo'] ?? 0) !== 1) {
			throw new \Exception("Usuário bloqueado ou inativo.");
		}

		if ((int)($data['funcionario_ativo'] ?? 0) !== 1) {
			throw new \Exception("Funcionário inativo.");
		}

		if (!password_verify($senha, $data["senha"])) {
			throw new \Exception("Usuário inexistente ou senha inválida.");
		}

		$user = new Funcionarios();
		$user->setData($data);

		$_SESSION[self::SESSION] = $user->getValues();

		self::refreshPermissions();

		self::audit(
			'LOGIN',
			'AUTH',
			null,
			'Usuário realizou login no sistema'
		);

		return $user;
	}

	public static function registerAccess(Funcionarios $funcionarios, string $acao)
	{
		try {
			$sql = new Sql();

			$sql->query(
				"INSERT INTO tb_userlogs
				(id_usuario, cpf_usuario, nome_funcionario, acao, ip, user_agent, created_at)
				VALUES
				(:id_usuario, :cpf_usuario, :nome_funcionario, :acao, :ip, :user_agent, NOW())",
				[
					":id_usuario"       => $funcionarios->getid_usuario(),
					":cpf_usuario"      => $funcionarios->getcpf(),
					":nome_funcionario" => $funcionarios->getnome_funcionario(),
					":acao"             => $acao,
					":ip"               => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
					":user_agent"       => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN'
				]
			);
		} catch (\Exception $e) {
			var_dump($e->getMessage());
			exit;
		}
	}

	public static function logout()
	{
		self::audit(
			'LOGOUT',
			'AUTH',
			null,
			'Usuário encerrou sessão'
		);

		$_SESSION[self::SESSION] = NULL;
		session_destroy();
	}

	public static function listAll()
	{
		$sql = new Sql();

		return $sql->select("
			SELECT 
				a.*,
				b.*
			FROM tb_usuario a
			INNER JOIN tb_funcionario b USING (id_pessoa)
			WHERE a.ativo = 1
			AND b.ativo = 1
			ORDER BY b.nome_funcionario
		");
	}

	public static function listAllSecurity(): array
	{
		$sql = new Sql();

		return $sql->select("
			SELECT
				a.id_usuario,
				a.cpf,
				a.perfil,
				a.ativo AS usuario_ativo,
				b.id_pessoa,
				b.nome_funcionario,
				b.email,
				b.nrphone,
				b.ativo AS funcionario_ativo
			FROM tb_usuario a
			INNER JOIN tb_funcionario b ON a.id_pessoa = b.id_pessoa
			ORDER BY b.nome_funcionario
		");
	}

	public static function setUserActive(int $idUsuario, int $ativo): void
	{
		$sql = new Sql();

		$sql->query("
			UPDATE tb_usuario
			SET ativo = :ativo
			WHERE id_usuario = :id_usuario
		", [
			':ativo' => $ativo,
			':id_usuario' => $idUsuario
		]);

		self::audit(
			$ativo ? 'USUARIO_UNBLOCK' : 'USUARIO_BLOCK',
			'SEGURANCA',
			$idUsuario,
			$ativo ? 'Usuário ativado/desbloqueado' : 'Usuário bloqueado/inativado'
		);
	}

	public static function setFuncionarioActive(int $idPessoa, int $ativo): void
	{
		$sql = new Sql();

		$sql->query("
			UPDATE tb_funcionario
			SET ativo = :ativo
			WHERE id_pessoa = :id_pessoa
		", [
			':ativo' => $ativo,
			':id_pessoa' => $idPessoa
		]);

		self::audit(
			$ativo ? 'FUNCIONARIO_ACTIVATE' : 'FUNCIONARIO_INACTIVATE',
			'FUNCIONARIOS',
			$idPessoa,
			$ativo ? 'Funcionário ativado' : 'Funcionário inativado'
		);
	}

	public function save()
	{
		$sql = new Sql();

		$results = $sql->select(
			"CALL sp_funcionario_usuario_save(
				:nome_funcionario,
				:email,
				:nrphone,
				:cpf,
				:senha,
				:perfil
			)",
			[
				":nome_funcionario" => utf8_decode($this->getnome_funcionario()),
				":email"            => $this->getemail(),
				":nrphone"          => $this->getnrphone(),
				":cpf"              => $this->getcpf(),
				":senha"            => self::getPasswordHash(trim($this->getsenha())),
				":perfil"           => $this->getperfil()
			]
		);

		if (isset($results[0])) {
			$this->setData($results[0]);
		}

		self::audit(
			'FUNCIONARIO_CREATE',
			'FUNCIONARIOS',
			(int)($this->getid_usuario() ?? 0),
			'Novo funcionário cadastrado'
		);
	}

	public static function validaCPF($cpf)
	{
		$cpf = preg_replace('/[^0-9]/', '', $cpf);

		if (strlen($cpf) != 11) {
			return false;
		}

		if (preg_match('/(\d)\1{10}/', $cpf)) {
			return false;
		}

		for ($t = 9; $t < 11; $t++) {
			$d = 0;
			for ($c = 0; $c < $t; $c++) {
				$d += $cpf[$c] * (($t + 1) - $c);
			}
			$d = ((10 * $d) % 11) % 10;
			if ($cpf[$c] != $d) {
				return false;
			}
		}

		return true;
	}

	public static function validaTelefone($telefone)
	{
		$telefone = preg_replace('/\D/', '', $telefone);

		if (strlen($telefone) < 10 || strlen($telefone) > 11) {
			return false;
		}

		$ddd = substr($telefone, 0, 2);
		if ($ddd < 11 || $ddd > 99) {
			return false;
		}

		if (preg_match('/(\d)\1{7,10}/', $telefone)) {
			return false;
		}

		return true;
	}

	public function get($id_usuario)
	{
		$sql = new Sql();

		$results = $sql->select(
			"SELECT
				a.*,
				a.ativo AS usuario_ativo,
				b.id_pessoa,
				b.nome_funcionario,
				b.email,
				b.nrphone,
				b.dtregister AS funcionario_dtregister,
				b.ativo AS funcionario_ativo
			FROM tb_usuario a
			INNER JOIN tb_funcionario b USING(id_pessoa)
			WHERE a.id_usuario = :id_usuario",
			[
				":id_usuario" => $id_usuario
			]
		);

		if (count($results) === 0) {
			throw new \Exception("Funcionário não encontrado para este usuário.");
		}

		$data = $results[0];

		$this->setData($data);
	}

	public function update(int $id_usuario, array $data)
	{
		$sql = new Sql();

		$sql->query(
			"CALL sp_funcionario_usuario_update(
				:id_usuario,
				:nome_funcionario,
				:email,
				:nrphone,
				:cpf,
				:senha,
				:perfil
			)",
			[
				':id_usuario'       => $id_usuario,
				':nome_funcionario' => $data['nome_funcionario'],
				':email'            => $data['email'],
				':nrphone'          => $data['nrphone'],
				':cpf'              => $data['cpf'],
				':senha'            => $data['senha'] ?? null,
				':perfil'           => $data['perfil']
			]
		);

		self::audit(
			'FUNCIONARIO_UPDATE',
			'FUNCIONARIOS',
			$id_usuario,
			'Dados do funcionário foram atualizados'
		);
	}

	public function delete()
	{
		$sql = new Sql();

		if ((int)$this->getid_usuario() <= 0) {
			throw new \Exception("ID do usuário inválido para exclusão.");
		}

		$idUsuario = (int)$this->getid_usuario();

		$sql->query(
			"CALL sp_funcionario_usuario_soft_delete(:id_usuario)",
			[
				":id_usuario" => $idUsuario
			]
		);

		self::audit(
			'FUNCIONARIO_DELETE',
			'FUNCIONARIOS',
			$idUsuario,
			'Funcionário removido do sistema'
		);
	}

	public function setPassword($senha)
	{
		$sql = new Sql();

		$sql->query("
		UPDATE tb_usuario
		SET senha = :senha
		WHERE id_usuario = :id_usuario
	", [
			":senha" => self::getPasswordHash(trim($senha)),
			":id_usuario" => (int)$this->getid_usuario()
		]);

		self::audit(
			'FUNCIONARIO_PASSWORD',
			'FUNCIONARIOS',
			(int)$this->getid_usuario(),
			'Senha do usuário foi alterada'
		);
	}

	public static function getPasswordHash($senha)
	{
		return password_hash($senha, PASSWORD_DEFAULT, [
			'cost' => 12
		]);
	}

	public static function setError($msg)
	{
		$_SESSION[self::ERROR] = $msg;
	}

	public static function getError()
	{
		$msg = (isset($_SESSION[self::ERROR]) && $_SESSION[self::ERROR]) ? $_SESSION[self::ERROR] : '';

		self::clearError();

		return $msg;
	}

	public static function clearError()
	{
		$_SESSION[self::ERROR] = NULL;
	}

	public static function setSuccess($msg)
	{
		$_SESSION[self::SUCCESS] = $msg;
	}

	public static function getSuccess()
	{
		$msg = (isset($_SESSION[self::SUCCESS]) && $_SESSION[self::SUCCESS]) ? $_SESSION[self::SUCCESS] : '';

		self::clearSuccess();

		return $msg;
	}

	public static function clearSuccess()
	{
		$_SESSION[self::SUCCESS] = NULL;
	}

	public static function setErrorRegister($msg)
	{
		$_SESSION[self::ERROR_REGISTER] = $msg;
	}

	public static function getErrorRegister()
	{
		$msg = (isset($_SESSION[self::ERROR_REGISTER]) && $_SESSION[self::ERROR_REGISTER]) ? $_SESSION[self::ERROR_REGISTER] : '';

		self::clearErrorRegister();

		return $msg;
	}

	public static function clearErrorRegister()
	{
		$_SESSION[self::ERROR_REGISTER] = NULL;
	}

	public static function checkLoginExist($cpf)
	{
		$sql = new Sql();

		$results = $sql->select("
			SELECT *
			FROM tb_usuario
			WHERE cpf = :cpf
		", [
			':cpf' => $cpf
		]);

		return (count($results) > 0);
	}

	public static function checkCpfExists($cpf)
	{
		$sql = new Sql();

		$results = $sql->select("
			SELECT *
			FROM tb_usuario
			WHERE cpf = :cpf
		", [
			":cpf" => $cpf
		]);

		return (count($results) > 0);
	}

	public static function checkEmailExists($email)
	{
		$sql = new Sql();

		$results = $sql->select("
			SELECT *
			FROM tb_funcionario
			WHERE email = :email
		", [
			":email" => $email
		]);

		return (count($results) > 0);
	}

	public static function checkPhoneExists($nrphone)
	{
		$telefone = preg_replace('/\D/', '', $nrphone);

		$sql = new Sql();

		$results = $sql->select("
			SELECT *
			FROM tb_funcionario
			WHERE nrphone = :nrphone
		", [
			":nrphone" => $telefone
		]);

		return (count($results) > 0);
	}

	public static function audit(
		string $acao,
		string $modulo,
		?int $referenciaId = null,
		?string $detalhes = null
	): void {
		try {
			if (!isset($_SESSION[self::SESSION]['id_usuario'])) {
				return;
			}

			$sql = new Sql();

			$sql->query(
				"INSERT INTO tb_userlogs
				(id_usuario, cpf_usuario, nome_funcionario, acao, modulo, referencia_id, detalhes, ip, user_agent, created_at)
				VALUES
				(:id_usuario, :cpf_usuario, :nome_funcionario, :acao, :modulo, :referencia_id, :detalhes, :ip, :user_agent, NOW())",
				[
					':id_usuario' => $_SESSION[self::SESSION]['id_usuario'],
					':cpf_usuario' => $_SESSION[self::SESSION]['cpf'] ?? '',
					':nome_funcionario' => $_SESSION[self::SESSION]['nome_funcionario'] ?? '',
					':acao' => $acao,
					':modulo' => $modulo,
					':referencia_id' => $referenciaId,
					':detalhes' => $detalhes,
					':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
					':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN'
				]
			);
		} catch (\Throwable $e) {
			// não quebra o fluxo da aplicação por erro de log
		}
	}

	public static function getLoginAttempts()
	{
		if (!isset($_SESSION["login_attempts"])) {
			$_SESSION["login_attempts"] = 0;
		}
		return (int)$_SESSION["login_attempts"];
	}

	public static function addLoginAttempt()
	{
		if (!isset($_SESSION["login_attempts"])) {
			$_SESSION["login_attempts"] = 0;
		}
		$_SESSION["login_attempts"]++;
	}

	public static function clearLoginAttempts()
	{
		$_SESSION["login_attempts"] = 0;
	}

	public static function getForgot($cpf)
	{
		$sql = new Sql();

		$cpf = preg_replace('/\D/', '', $cpf);

		$results = $sql->select("
		SELECT
			a.id_usuario,
			a.id_pessoa,
			a.cpf,
			a.perfil,
			a.inadmin,
			a.ativo AS usuario_ativo,
			b.nome_funcionario,
			b.email,
			b.ativo AS funcionario_ativo
		FROM tb_usuario a
		INNER JOIN tb_funcionario b ON a.id_pessoa = b.id_pessoa
		WHERE REPLACE(REPLACE(a.cpf, '.', ''), '-', '') = :cpf
		  AND a.ativo = 1
		  AND b.ativo = 1
		LIMIT 1
	", [
			":cpf" => $cpf
		]);

		if (count($results) === 0) {
			throw new \Exception("Não foi possível localizar um usuário ativo com esse CPF.");
		}

		$data = $results[0];

		if (empty($data["email"])) {
			throw new \Exception("Este usuário não possui e-mail cadastrado para recuperação.");
		}

		$token = bin2hex(random_bytes(32));

		$sql->query("
		INSERT INTO tb_usuarios_passwords_recoveries
		(id_usuario, desip, desrecovery)
		VALUES
		(:id_usuario, :desip, :desrecovery)
	", [
			":id_usuario"  => (int)$data["id_usuario"],
			":desip"       => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
			":desrecovery" => $token
		]);

		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

		$link = $scheme . '://' . $host . '/admin/forgot/reset?code=' . $token;

		$htmlBody = self::getForgotMailTemplate($data["nome_funcionario"], $link);

		$altBody = "Olá, {$data["nome_funcionario"]}\n\n"
			. "Recebemos uma solicitação para redefinir sua senha.\n"
			. "Use o link abaixo para continuar:\n\n"
			. $link . "\n\n"
			. "Este link expira em 1 hora.\n"
			. "Se você não solicitou esta alteração, ignore este e-mail.";

		\Hcode\Mailer::quickSend(
			$data["email"],
			$data["nome_funcionario"],
			"Recuperação de senha - Prato Cheio",
			$htmlBody,
			$altBody
		);

		self::audit(
			'PASSWORD_RECOVERY_REQUEST',
			'AUTH',
			(int)$data["id_usuario"],
			'Solicitação de recuperação de senha por CPF com envio de e-mail'
		);

		return [
			"name" => $data["nome_funcionario"],
			"email" => $data["email"],
			"cpf" => $data["cpf"],
			"token" => $token,
			"link" => $link
		];
	}


	public static function validForgotDecrypt($code)
	{
		$sql = new Sql();

		$results = $sql->select("
			SELECT
				r.id_recovery,
				r.id_usuario,
				r.desrecovery,
				r.dtregister,
				r.dtrecovery,
				a.id_pessoa,
				a.cpf,
				a.perfil,
				a.inadmin,
				a.ativo AS usuario_ativo,
				b.nome_funcionario,
				b.email,
				b.nrphone,
				b.ativo AS funcionario_ativo
			FROM tb_usuarios_passwords_recoveries r
			INNER JOIN tb_usuario a ON a.id_usuario = r.id_usuario
			INNER JOIN tb_funcionario b ON b.id_pessoa = a.id_pessoa
			WHERE r.desrecovery = :code
			  AND r.dtrecovery IS NULL
			  AND DATE_ADD(r.dtregister, INTERVAL 1 HOUR) >= NOW()
			  AND a.ativo = 1
			  AND b.ativo = 1
			LIMIT 1
		", [
			":code" => $code
		]);

		if (count($results) === 0) {
			throw new \Exception("Link de recuperação inválido ou expirado.");
		}

		return $results[0];
	}

	public static function setForgotUsed($idRecovery)
	{
		$sql = new Sql();

		$sql->query("
			UPDATE tb_usuarios_passwords_recoveries
			SET dtrecovery = NOW()
			WHERE id_recovery = :id_recovery
		", [
			":id_recovery" => (int)$idRecovery
		]);
	}

	public static function checkForgotCodeExists($code): bool
	{
		$sql = new Sql();

		$results = $sql->select("
			SELECT id_recovery
			FROM tb_usuarios_passwords_recoveries
			WHERE desrecovery = :code
			LIMIT 1
		", [
			":code" => $code
		]);

		return count($results) > 0;
	}

	public static function getForgotMailTemplate(string $name, string $link): string
	{
		$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
		$link = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

		return '
	<!DOCTYPE html>
	<html lang="pt-br">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Recuperação de Senha</title>
	</head>
	<body style="margin:0; padding:0; background-color:#0f172a; font-family:Arial, Helvetica, sans-serif;">
		<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0f172a; padding:32px 16px;">
			<tr>
				<td align="center">
					<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px; background:#111827; border-radius:24px; overflow:hidden; border:1px solid rgba(255,255,255,0.06);">
						
						<tr>
							<td style="background:linear-gradient(135deg,#ff6b35,#e85a2a); padding:32px; text-align:center;">
								<img src="https://i.imgur.com/0Q2QF5O.png" alt="Prato Cheio" style="max-width:140px; height:auto; display:block; margin:0 auto 18px auto; border-radius:18px;">
								<div style="display:inline-block; padding:10px 18px; border-radius:999px; background:rgba(255,255,255,0.16); color:#ffffff; font-size:14px; font-weight:bold;">
									🔐 Recuperação segura de acesso
								</div>
								<h1 style="margin:22px 0 10px 0; color:#ffffff; font-size:36px; line-height:1.2;">Redefinição de Senha</h1>
								<p style="margin:0; color:#fff7f2; font-size:16px; line-height:1.7;">
									Recebemos uma solicitação para redefinir a sua senha no sistema Prato Cheio.
								</p>
							</td>
						</tr>

						<tr>
							<td style="padding:36px 32px;">
								<p style="margin:0 0 16px 0; color:#f8fafc; font-size:16px;">Olá, <strong>' . $name . '</strong>.</p>

								<p style="margin:0 0 18px 0; color:#cbd5e1; font-size:15px; line-height:1.8;">
									Para criar uma nova senha, clique no botão abaixo. Este link é temporário e foi gerado especialmente para a sua conta.
								</p>

								<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0;">
									<tr>
										<td align="center">
											<a href="' . $link . '" style="display:inline-block; background:linear-gradient(135deg,#ff6b35,#e85a2a); color:#ffffff; text-decoration:none; font-size:17px; font-weight:bold; padding:16px 28px; border-radius:16px;">
												Redefinir minha senha
											</a>
										</td>
									</tr>
								</table>

								<p style="margin:0 0 12px 0; color:#cbd5e1; font-size:14px; line-height:1.7;">
									Se o botão acima não funcionar, copie e cole este link no navegador:
								</p>

								<p style="margin:0 0 22px 0; word-break:break-all;">
									<a href="' . $link . '" style="color:#ff8a5c; font-size:14px; text-decoration:none;">' . $link . '</a>
								</p>

								<div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:16px 18px; margin-top:24px;">
									<p style="margin:0 0 8px 0; color:#f8fafc; font-size:14px; font-weight:bold;">Informações importantes:</p>
									<ul style="margin:0; padding-left:18px; color:#cbd5e1; font-size:14px; line-height:1.8;">
										<li>O link expira em 1 hora.</li>
										<li>Depois de usar o link, ele será invalidado.</li>
										<li>Se você não solicitou esta alteração, ignore este e-mail.</li>
									</ul>
								</div>
							</td>
						</tr>

						<tr>
							<td style="padding:22px 32px; border-top:1px solid rgba(255,255,255,0.06); text-align:center;">
								<p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.7;">
									Prato Cheio • Sistema administrativo<br>
									Este é um e-mail automático, não responda esta mensagem.
								</p>
							</td>
						</tr>

					</table>
				</td>
			</tr>
		</table>
	</body>
	</html>';
	}
}
