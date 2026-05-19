<?php

use \Hcode\PageAdmin;
use \Hcode\Model\Funcionarios;
use \Hcode\DB\Sql;
use Hcode\Model\Message;

$app->get("/admin/funcionarios/:id_usuario/password", function ($id_usuario) {

	Funcionarios::checkPermission('FUNCIONARIOS_PASSWORD');

	$funcionarios = new Funcionarios();

	$funcionarios->get((int)$id_usuario);

	$page = new PageAdmin();

	$page->setTpl("funcionarios-password", [
		"funcionarios" => $funcionarios->getValues(),
		"msgError" => Funcionarios::getError(),
		"msgSuccess" => Funcionarios::getSuccess()
	]);
});

$app->post("/admin/funcionarios/:id_usuario/password", function ($id_usuario) {

	Funcionarios::checkPermission('FUNCIONARIOS_PASSWORD');

	if (!isset($_POST['senha']) || $_POST['senha'] === '') {

		Funcionarios::setError("Preencha a nova senha.");
		header("Location: /admin/funcionarios/$id_usuario/password");
		exit;
	}

	if (!isset($_POST['senha-confirm']) || $_POST['senha-confirm'] === '') {

		Funcionarios::setError("Preencha a confirmação da nova senha.");
		header("Location: /admin/funcionarios/$id_usuario/password");
		exit;
	}

	if ($_POST['senha'] !== $_POST['senha-confirm']) {

		Funcionarios::setError("Confirme corretamente as senhas.");
		header("Location: /admin/funcionarios/$id_usuario/password");
		exit;
	}

	$funcionarios = new Funcionarios();

	$funcionarios->get((int)$id_usuario);

	$funcionarios->setPassword($_POST['senha']);

	Funcionarios::setSuccess("Senha alterada com sucesso.");

	header("Location: /admin/funcionarios/$id_usuario/password");
	exit;
});


$app->get("/admin/funcionarios", function () {

	Funcionarios::checkPermission('FUNCIONARIOS_VIEW');

	$funcionarios = Funcionarios::listAll();

	$page = new PageAdmin();

	$page->setTpl("funcionarios", array(
		"funcionarios" => $funcionarios
	));
});




$app->get("/admin/funcionarios/create", function () {

	Funcionarios::checkPermission('FUNCIONARIOS_CREATE');

	$page = new PageAdmin();

	$page->setTpl("funcionarios-create");
});

$app->get('/admin/funcionarios/:id_usuario/delete', function ($id_usuario) {

	Funcionarios::checkPermission('FUNCIONARIOS_DELETE');

	$funcionario = new Funcionarios();
	$funcionario->get((int)$id_usuario);
	$funcionario->delete();

	Funcionarios::setSuccess("Funcionário excluído com sucesso.");

	header("Location: /admin/funcionarios");
	exit;
});




$app->get("/admin/funcionarios/:id_usuario", function ($id_usuario) {

	Funcionarios::checkPermission('FUNCIONARIOS_UPDATE');

	$funcionarios = new Funcionarios();

	$funcionarios->get((int)$id_usuario);

	$page = new PageAdmin();

	$page->setTpl("funcionarios-update", array(
		"funcionarios" => $funcionarios->getValues()
	));
});

$app->post("/admin/funcionarios/create", function () {

	Funcionarios::checkPermission('FUNCIONARIOS_CREATE');

	$funcionarios = new Funcionarios();

	$_POST["inadmin"] = (isset($_POST["inadmin"])) ? 1 : 0;
	$_POST["cpf"] = preg_replace('/\D+/', '', $_POST["cpf"]);
	$_POST["nrphone"] = preg_replace('/\D+/', '', $_POST["nrphone"]);

	// Valida CPF
	if (!Funcionarios::validaCPF($_POST["cpf"])) {
		echo "<script>alert('CPF inválido!'); window.history.back();</script>";
		exit;
	}

	// Valida Telefone
	if (!Funcionarios::validaTelefone($_POST["nrphone"])) {
		echo "<script>alert('Telefone inválido!'); window.history.back();</script>";
		exit;
	}

	// Verifica duplicidade
	if (Funcionarios::checkCpfExists($_POST["cpf"])) {
		echo "<script>alert('CPF já cadastrado!'); window.history.back();</script>";
		exit;
	}

	if (Funcionarios::checkEmailExists($_POST["email"])) {
		echo "<script>alert('E-mail já cadastrado!'); window.history.back();</script>";
		exit;
	}

	if (Funcionarios::checkPhoneExists($_POST["nrphone"])) {
		echo "<script>alert('Telefone já cadastrado!'); window.history.back();</script>";
		exit;
	}

	$funcionarios = new Funcionarios();
	$funcionarios->setData($_POST);

	$funcionarios->save();

	header("Location: /admin/funcionarios");
	exit;
});

$app->post("/admin/funcionarios/:id_usuario", function ($id_usuario) {

	Funcionarios::checkPermission('FUNCIONARIOS_UPDATE');

	$_POST["cpf"] = preg_replace('/\D+/', '', $_POST["cpf"]);
	$_POST["nrphone"] = preg_replace('/\D+/', '', $_POST["nrphone"]);

	$funcionarios = new Funcionarios();

	$funcionarios->get((int)$id_usuario);

	$funcionarios->setData($_POST);

	$funcionarios->update((int)$id_usuario, $_POST);

	header("Location: /admin/funcionarios");
	exit;
});

$app->post('/admin/funcionarios/verificar-cpf', function () {

	header('Content-Type: application/json; charset=utf-8');

	$cpf = isset($_POST['cpf']) ? $_POST['cpf'] : '';
	$id_usuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : 0;

	$cpfLimpo = preg_replace('/\D+/', '', $cpf);

	if ($cpfLimpo === '' || strlen($cpfLimpo) !== 11) {
		echo json_encode([
			'existe' => false
		]);
		exit;
	}

	$sql = new Sql();

	$results = $sql->select("
        SELECT id_usuario
        FROM funcionarios
        WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', '') = :cpf
        AND id_usuario <> :id_usuario
        LIMIT 1
    ", [
		':cpf' => $cpfLimpo,
		':id_usuario' => $id_usuario
	]);

	echo json_encode([
		'existe' => count($results) > 0
	]);

	exit;
});
