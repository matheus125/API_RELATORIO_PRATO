<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;

class Clientes extends Model
{
    const SESSION = "User";
    const ERROR = "UserError";
    const ERROR_REGISTER = "UserErrorRegister";
    const SUCCESS = "UserSucesss";

    public static function getFromSession()
    {

        $clientes = new Clientes();

        if (isset($_SESSION[Clientes::SESSION]) && (int)$_SESSION[Clientes::SESSION]['iduser'] > 0) {

            $clientes->setData($_SESSION[Clientes::SESSION]);
        }

        return $clientes;
    }

    public static function lista_titulares()
    {
        $sql = new Sql();

        // Seleciona todas as informações do titular e do endereço
        $results = $sql->select("
    SELECT 
        t.id,
        t.nome_completo,
        t.nome_social,
        t.cor_cliente,
        t.nome_mae,
        t.telefone,
        t.data_nascimento,
        TIMESTAMPDIFF(YEAR, t.data_nascimento, CURDATE()) AS idade_cliente,
        t.genero AS genero_cliente,
        t.estado_civil,
        t.rg,
        t.cpf,
        t.nis,
        t.status_cliente,
        e.id AS id_endereco,
        e.cep,
        e.bairro,
        e.rua,
        e.numero,
        e.referencia,
        e.nacionalidade,
        e.naturalidade,
        e.cidade,
        e.tempo_moradia_anos AS tempo_moradia
    FROM tb_titular t
    LEFT JOIN tb_endereco e ON t.id_endereco = e.id
    ORDER BY t.nome_completo
");

        // Garante que sempre retorna um array (mesmo vazio)
        return $results ?: [];
    }


    public function salvar_cliente_titular()
    {
        $sql = new Sql();

        // ====== GERAR NOME DA FAMÍLIA AUTOMATICAMENTE ======
        $nomeCompleto = $this->getnome_completo();
        $nomeFamilia = '';

        if (empty($this->getnome_familia())) {
            $partes = explode(' ', trim($nomeCompleto));

            if (count($partes) >= 2) {
                $segundoNome = $partes[1];
                $nomeFamilia = 'Família ' . $segundoNome;
            } else {
                $nomeFamilia = 'Família ' . $partes[0];
            }
        } else {
            $nomeFamilia = $this->getnome_familia();
        }

        // ====== VALIDAR E NORMALIZAR GÊNERO ======
        $genero = strtoupper(trim($this->getgenero_cliente())); // maiúsculo
        $valores_validos = ['M', 'F', 'OUTRO'];

        if (!in_array($genero, $valores_validos)) {
            $genero = 'OUTRO';
        }

        // ====== CHAMAR A PROCEDURE UNIFICADA ======
        $results = $sql->select("CALL sp_cadastrar_titular_familia_endereco(
        :cep,
        :bairro,
        :rua,
        :numero,
        :referencia,
        :nacionalidade,
        :naturalidade,
        :cidade,
        :tempo_moradia_anos,
        :nome_familia,
        :nome_completo,
        :nome_social,
        :cor_cliente,
        :nome_mae,
        :telefone,
        :data_nascimento,
        :genero,
        :estado_civil,
        :rg,
        :cpf,
        :nis,
        :status_cliente
    )", array(
            // ENDEREÇO
            ":cep"                 => $this->getcep(),
            ":bairro"              => $this->getbairro(),
            ":rua"                 => $this->getrua(),
            ":numero"              => $this->getnumero(),
            ":referencia"          => $this->getreferencia(),
            ":nacionalidade"       => $this->getnacionalidade(),
            ":naturalidade"        => $this->getnaturalidade(),
            ":cidade"              => $this->getcidade(),
            ":tempo_moradia_anos"  => $this->gettempo_moradia_cliente(),

            // FAMÍLIA
            ":nome_familia"        => $nomeFamilia,

            // TITULAR
            ":nome_completo"       => $nomeCompleto,
            ":nome_social"         => $this->getnome_social(),
            ":cor_cliente"         => $this->getcor_cliente(),
            ":nome_mae"            => $this->getnome_mae(),
            ":telefone"            => $this->gettelefone(),
            ":data_nascimento"     => $this->getdata_nascimento(),
            ":genero"              => $genero,
            ":estado_civil"        => $this->getestado_civil(),
            ":rg"                  => $this->getrg(),
            ":cpf"                 => $this->getcpf(),
            ":nis"                 => $this->getnis(),
            ":status_cliente"      => $this->getstatus_cliente()
        ));

        if (isset($results[0])) {
            $this->setData($results[0]);
        }
    }

    public function getByTitular($id)
    {
        $sql = new Sql();

        $results = $sql->select("
        SELECT 
            t.*,
            e.cep,
            e.bairro,
            e.rua,
            e.numero,
            e.referencia,
            e.nacionalidade,
            e.naturalidade,
            e.cidade,
            e.tempo_moradia_anos
        FROM tb_titular t
        LEFT JOIN tb_endereco e 
            ON e.id = t.id_endereco
        WHERE t.id = :id
        LIMIT 1
    ", [
            ":id" => $id
        ]);

        if (count($results) === 0) {
            return false;
        }

        $data = $results[0];

        $familia = $sql->select("
        SELECT *
        FROM tb_familia
        WHERE id = :id_familia
        LIMIT 1
    ", [
            ":id_familia" => $data["id_familia"]
        ]);

        $data['familia'] = $familia;

        $this->setData($data);
        return $this;
    }



    public static function setError($msg)
    {

        $_SESSION[Clientes::ERROR] = $msg;
    }

    public static function getError()
    {

        $msg = (isset($_SESSION[Clientes::ERROR]) && $_SESSION[Clientes::ERROR]) ? $_SESSION[Clientes::ERROR] : '';

        Clientes::clearError();

        return $msg;
    }

    public static function clearError()
    {

        $_SESSION[Clientes::ERROR] = NULL;
    }

    public static function setSuccess($msg)
    {

        $_SESSION[Clientes::SUCCESS] = $msg;
    }

    public static function getSuccess()
    {

        $msg = (isset($_SESSION[Clientes::SUCCESS]) && $_SESSION[Clientes::SUCCESS]) ? $_SESSION[Clientes::SUCCESS] : '';

        Clientes::clearSuccess();

        return $msg;
    }

    public static function clearSuccess()
    {

        $_SESSION[Clientes::SUCCESS] = NULL;
    }

    public static function setErrorRegister($msg)
    {

        $_SESSION[Clientes::ERROR_REGISTER] = $msg;
    }

    public static function getErrorRegister()
    {

        $msg = (isset($_SESSION[Clientes::ERROR_REGISTER]) && $_SESSION[Clientes::ERROR_REGISTER]) ? $_SESSION[Clientes::ERROR_REGISTER] : '';

        Clientes::clearErrorRegister();

        return $msg;
    }

    public static function clearErrorRegister()
    {

        $_SESSION[Clientes::ERROR_REGISTER] = NULL;
    }

    public static function total_usuarios()
    {
        $sql = new Sql();
        $result = $sql->select("SELECT COUNT(*) AS total FROM tb_titular");
        return $result[0]['total'];
    }

    public static function total_dependentes()
    {
        $sql = new Sql();
        $result = $sql->select("SELECT COUNT(*) AS total FROM tb_dependentes");
        return $result[0]['total'];
    }

    public static function total_familias()
    {
        $sql = new Sql();
        $result = $sql->select("SELECT COUNT(*) AS total FROM tb_familia");
        return $result[0]['total'];
    }
}
