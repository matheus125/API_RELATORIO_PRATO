<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;

class Dependente extends Model
{

    public function saveLote($idTitular, $dependentes)
    {
        $sql = new Sql();

        try {

            $sql->beginTransaction();

            foreach ($dependentes as $d) {

                $sql->query("
                INSERT INTO tb_dependente (
                    id_titular,
                    nome,
                    rg,
                    cpf,
                    data_nascimento,
                    idade,
                    genero,
                    dependencia_cliente
                ) VALUES (
                    :id_titular,
                    :nome,
                    :rg,
                    :cpf,
                    :data_nascimento,
                    :idade,
                    :genero,
                    :dependencia_cliente
                )
            ", [
                    ':id_titular' => $idTitular,
                    ':nome' => $d['nome'],
                    ':rg' => $d['rg'],
                    ':cpf' => $d['cpf'],
                    ':data_nascimento' => $d['data_nascimento'],
                    ':idade' => $d['idade'],
                    ':genero' => $d['genero'],
                    ':dependencia_cliente' => $d['dependencia_cliente']
                ]);
            }

            $sql->commit();
        } catch (\Exception $e) {

            $sql->rollback();
            throw $e;
        }
    }
}
