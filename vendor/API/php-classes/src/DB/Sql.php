<?php

namespace Hcode\DB;

class Sql
{
	const HOSTNAME = "69.6.249.161";
	const USERNAME = "mat06153_mat06153";
	const PASSWORD = "MM@t@13192921";
	const DBNAME   = "mat06153_portal_relatorios";

	private $conn;

	public function __construct()
	{
		$host = self::env('DB_HOST', self::HOSTNAME);
		$user = self::env('DB_USER', self::USERNAME);
		$password = self::env('DB_PASSWORD', self::PASSWORD);
		$dbname = self::env('DB_NAME', self::DBNAME);
		$port = (int)self::env('DB_PORT', 3306);
		$timeout = max(1, (int)self::env('DB_CONNECT_TIMEOUT', 5));
		$queryTimeoutMs = max(0, (int)self::env('DB_QUERY_TIMEOUT_MS', 15000));

		$dsn = "mysql:dbname=" . $dbname . ";host=" . $host . ";port=" . $port . ";charset=utf8mb4";
		try {
		$this->conn = new \PDO(
			$dsn,
			$user,
			$password,
			array(
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
				\PDO::ATTR_TIMEOUT => $timeout,
				\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
			)
		);

		$this->conn->exec("SET NAMES utf8mb4");
		$this->conn->exec("SET CHARACTER SET utf8mb4");
		$this->conn->exec("SET SESSION collation_connection = utf8mb4_unicode_ci");
		if ($queryTimeoutMs > 0) {
			$this->applyQueryTimeout($queryTimeoutMs);
		}
		} catch (\Throwable $e) {
			$this->logConnectionFailure($host, $port, $dbname, $e->getMessage());
			throw $e;
		}
	}

	private function setParams($statement, $parameters = array())
	{
		foreach ($parameters as $key => $value) {
			$this->bindParam($statement, $key, $value);
		}
	}

	private function bindParam($statement, $key, $value)
	{
		$statement->bindValue($key, $value);
	}

	public function query($rawQuery, $params = array())
	{
		$stmt = $this->conn->prepare($rawQuery);
		$this->setParams($stmt, $params);
		$stmt->execute();

		return $stmt;
	}

	public function select($rawQuery, $params = array()): array
	{
		$stmt = $this->conn->prepare($rawQuery);
		$this->setParams($stmt, $params);
		$stmt->execute();

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	private static function env($key, $default = null)
	{
		if (function_exists('portal_env')) {
			$value = \portal_env($key, null);
			if ($value !== null && $value !== '') return $value;
		}

		$value = getenv($key);
		if ($value === false && isset($_ENV[$key])) $value = $_ENV[$key];
		if ($value === false && isset($_SERVER[$key])) $value = $_SERVER[$key];
		return ($value === false || $value === '') ? $default : $value;
	}

	private function applyQueryTimeout($milliseconds)
	{
		try {
			$this->conn->exec("SET SESSION max_execution_time=" . (int)$milliseconds);
		} catch (\Throwable $e) {
			try {
				$this->conn->exec("SET SESSION max_statement_time=" . max(1, (int)ceil($milliseconds / 1000)));
			} catch (\Throwable $ignored) {
				$this->log('Aviso: banco nao aceitou timeout de consulta configuravel.');
			}
		}
	}

	private function logConnectionFailure($host, $port, $dbname, $message)
	{
		$this->log('Falha ao conectar no banco central | host=' . $host . ' | port=' . $port . ' | db=' . $dbname . ' | erro=' . $message);
	}

	private function log($message)
	{
		$dir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
		if (!is_dir($dir)) @mkdir($dir, 0775, true);
		@file_put_contents($dir . DIRECTORY_SEPARATOR . 'db-errors.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
	}
}
