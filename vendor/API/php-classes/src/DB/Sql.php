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
		$this->conn = new \PDO(
			"mysql:dbname=" . Sql::DBNAME . ";host=" . Sql::HOSTNAME . ";charset=utf8mb4",
			Sql::USERNAME,
			Sql::PASSWORD,
			array(
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
				\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
			)
		);

		$this->conn->exec("SET NAMES utf8mb4");
		$this->conn->exec("SET CHARACTER SET utf8mb4");
		$this->conn->exec("SET SESSION collation_connection = utf8mb4_unicode_ci");
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
}
