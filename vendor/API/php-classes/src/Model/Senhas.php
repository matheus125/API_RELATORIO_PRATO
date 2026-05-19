<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;

class Senhas extends Model
{
    /**
     * Insere 1 registro em tb_senhas e retorna o ID.
     * Espera chaves:
     *  cliente, cpf, idade, genero, deficiente, tipoSenha, status_cliente, data_refeicao
     */
    public static function create($data)
    {
        $sql = new Sql();

        $sql->query("
            INSERT INTO tb_senhas
            (cliente, cpf, Idade, Genero, Deficiente, tipoSenha, status_cliente, data_refeicao, registration_date, registration_date_update)
            VALUES
            (:cliente, :cpf, :idade, :genero, :deficiente, :tipoSenha, :status_cliente, :data_refeicao, NOW(), NOW())
        ", [
            ":cliente"        => $data["cliente"],
            ":cpf"            => $data["cpf"],
            ":idade"          => $data["idade"],
            ":genero"         => $data["genero"],
            ":deficiente"     => $data["deficiente"],
            ":tipoSenha"      => $data["tipoSenha"],
            ":status_cliente" => $data["status_cliente"],
            ":data_refeicao"  => $data["data_refeicao"],
        ]);

        $row = $sql->select("SELECT LAST_INSERT_ID() AS id");
        return (int)$row[0]["id"];
    }

    /**
     * Insere em lote e retorna array de IDs.
     * $itens: array de itens com (cliente, cpf, idade, genero, deficiente)
     */
    public static function createBatch($tipoSenha, $status_cliente, $data_refeicao, $itens)
    {
        $sql = new Sql();

        $sql->query("START TRANSACTION");

        try {

            $ids = [];

            foreach ($itens as $item) {

                $id = self::create([
                    "cliente"        => isset($item["cliente"]) ? $item["cliente"] : "",
                    "cpf"            => isset($item["cpf"]) ? $item["cpf"] : "",
                    "idade"          => isset($item["idade"]) ? $item["idade"] : "",
                    "genero"         => isset($item["genero"]) ? $item["genero"] : "",
                    "deficiente"     => isset($item["deficiente"]) ? $item["deficiente"] : "",
                    "tipoSenha"      => $tipoSenha,
                    "status_cliente" => $status_cliente,
                    "data_refeicao"  => $data_refeicao,
                ]);

                $ids[] = $id;
            }

            $sql->query("COMMIT");

            return $ids;

        } catch (\Exception $e) {

            $sql->query("ROLLBACK");
            throw $e;

        }
    }

    /**
     * Conta quantas "refeições" (linhas em tb_senhas) existem na data informada.
     */
    public static function countByDate($data_refeicao)
    {
        $sql = new Sql();

        $result = $sql->select("
            SELECT COUNT(*) AS total
            FROM tb_senhas
            WHERE data_refeicao = :data_refeicao
        ", [
            ":data_refeicao" => $data_refeicao
        ]);

        if (isset($result[0]) && isset($result[0]["total"])) {
            return (int)$result[0]["total"];
        }

        return 0;
    }
}
