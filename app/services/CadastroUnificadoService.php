<?php

use Hcode\DB\Sql;

class CadastroUnificadoService
{
    /** @var Sql */
    private $sql;

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public function listBases(bool $onlyActive = false): array
    {
        $where = $onlyActive ? 'WHERE ativo = 1' : '';
        return $this->sql->select("
            SELECT
                id, nome_unidade, identificador, host, porta, nome_banco, usuario, ativo, ultima_sincronizacao, ultimo_status, ultima_mensagem
            FROM tb_bases_consulta
            {$where}
            ORDER BY nome_unidade ASC, nome_banco ASC
        ");
    }

    public function getResumo(): array
    {
        $totais = $this->sql->select("
            SELECT
                COUNT(*) AS total_registros,
                COUNT(DISTINCT origem_banco) AS total_bases,
                SUM(CASE WHEN tipo_cadastro = 'TITULAR' THEN 1 ELSE 0 END) AS total_titulares,
                SUM(CASE WHEN tipo_cadastro = 'DEPENDENTE' THEN 1 ELSE 0 END) AS total_dependentes
            FROM tb_cadastros_unificados
        ");

        $atualizacao = $this->sql->select("
            SELECT MAX(data_sync) AS ultima_sync
            FROM tb_cadastros_unificados
        ");

        return [
            'total_registros' => (int)($totais[0]['total_registros'] ?? 0),
            'total_bases' => (int)($totais[0]['total_bases'] ?? 0),
            'total_titulares' => (int)($totais[0]['total_titulares'] ?? 0),
            'total_dependentes' => (int)($totais[0]['total_dependentes'] ?? 0),
            'ultima_sync' => $atualizacao[0]['ultima_sync'] ?? null,
        ];
    }

    public function pesquisar(array $filtros): array
    {
        $page = max(1, (int)($filtros['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($filtros['pageSize'] ?? 25)));
        $offset = ($page - 1) * $pageSize;

        $busca = trim((string)($filtros['busca'] ?? ''));
        $tipo = strtoupper(trim((string)($filtros['tipo'] ?? '')));
        $origemBanco = trim((string)($filtros['origem_banco'] ?? ''));
        $origemUnidade = trim((string)($filtros['origem_unidade'] ?? ''));
        $somenteAtivos = isset($filtros['somente_ativos']) ? ((int)$filtros['somente_ativos'] === 1) : false;

        $where = [];
        $params = [];

        if ($busca !== '') {
            $buscaCpf = preg_replace('/\D+/', '', $busca);
            $where[] = "(nome LIKE :busca_nome OR cpf_normalizado LIKE :busca_cpf OR rg LIKE :busca_rg)";
            $params[':busca_nome'] = '%' . $busca . '%';
            $params[':busca_cpf'] = '%' . $buscaCpf . '%';
            $params[':busca_rg'] = '%' . $busca . '%';
        }

        if (in_array($tipo, ['TITULAR', 'DEPENDENTE'], true)) {
            $where[] = 'tipo_cadastro = :tipo';
            $params[':tipo'] = $tipo;
        }

        if ($origemBanco !== '') {
            $where[] = 'origem_banco = :origem_banco';
            $params[':origem_banco'] = $origemBanco;
        }

        if ($origemUnidade !== '') {
            $where[] = 'origem_unidade = :origem_unidade';
            $params[':origem_unidade'] = $origemUnidade;
        }

        if ($somenteAtivos) {
            $where[] = "(status_cliente IS NULL OR status_cliente = '' OR UPPER(status_cliente) IN ('ATIVO','ATIVA','1','SIM'))";
        }

        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->sql->select("SELECT COUNT(*) AS total FROM tb_cadastros_unificados {$whereSql}", $params);
        $items = $this->sql->select("
            SELECT
                id, origem_banco, origem_unidade, origem_tabela, origem_id, tipo_cadastro,
                nome, cpf, cpf_normalizado, rg, data_nascimento, idade, genero,
                status_cliente, parentesco, registration_date, registration_date_update, data_sync
            FROM tb_cadastros_unificados
            {$whereSql}
            ORDER BY nome ASC, id DESC
            LIMIT {$offset}, {$pageSize}
        ", $params);

        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => (int)($total[0]['total'] ?? 0),
            'pages' => $pageSize > 0 ? (int)ceil(((int)($total[0]['total'] ?? 0)) / $pageSize) : 1,
            'items' => $items,
        ];
    }

    public function sincronizar(array $baseIds = [], array $tabelas = ['tb_titular', 'tb_dependentes']): array
    {
        $bases = $this->resolveBases($baseIds);
        $resultado = [
            'bases_processadas' => 0,
            'bases_sucesso' => 0,
            'bases_erro' => 0,
            'registros_upsert' => 0,
            'detalhes' => [],
        ];

        foreach ($bases as $base) {
            $resultado['bases_processadas']++;
            $detalhe = [
                'base_id' => (int)$base['id'],
                'nome_unidade' => $base['nome_unidade'],
                'nome_banco' => $base['nome_banco'],
                'status' => 'ERRO',
                'mensagem' => '',
                'registros' => 0,
            ];

            try {
                $pdo = $this->connectRemote($base);
                $totalBase = 0;

                if (in_array('tb_titular', $tabelas, true) && $this->remoteTableExists($pdo, $base['nome_banco'], 'tb_titular')) {
                    $titulares = $this->fetchRemoteTitulares($pdo, $base);
                    $totalBase += $this->upsertCadastros($titulares);
                }

                if (in_array('tb_dependentes', $tabelas, true) && $this->remoteTableExists($pdo, $base['nome_banco'], 'tb_dependentes')) {
                    $dependentes = $this->fetchRemoteDependentes($pdo, $base);
                    $totalBase += $this->upsertCadastros($dependentes);
                }

                $detalhe['status'] = 'SUCESSO';
                $detalhe['mensagem'] = 'Sincronização concluída com sucesso.';
                $detalhe['registros'] = $totalBase;
                $resultado['bases_sucesso']++;
                $resultado['registros_upsert'] += $totalBase;

                $this->safeRegistrarSyncBase((int)$base['id'], 'SUCESSO', $detalhe['mensagem']);
            } catch (Throwable $e) {
                $detalhe['mensagem'] = $e->getMessage();
                $resultado['bases_erro']++;
                $this->safeRegistrarSyncBase((int)$base['id'], 'ERRO', $e->getMessage());
            }

            $resultado['detalhes'][] = $detalhe;
        }

        return $resultado;
    }

    private function resolveBases(array $baseIds): array
    {
        $baseIds = array_values(array_filter(array_map('intval', $baseIds), function ($id) {
            return $id > 0;
        }));

        if (count($baseIds) > 0) {
            $params = [];
            $placeholders = [];

            foreach ($baseIds as $i => $id) {
                $key = ':id_' . $i;
                $placeholders[] = $key;
                $params[$key] = $id;
            }

            return $this->sql->select("
                SELECT *
                FROM tb_bases_consulta
                WHERE ativo = 1 AND id IN (" . implode(',', $placeholders) . ")
                ORDER BY nome_unidade ASC, nome_banco ASC
            ", $params);
        }

        return $this->sql->select("
            SELECT *
            FROM tb_bases_consulta
            WHERE ativo = 1
            ORDER BY nome_unidade ASC, nome_banco ASC
        ");
    }

    private function connectRemote(array $base): PDO
    {
        $host = trim((string)($base['host'] ?? ''));
        $dbName = trim((string)($base['nome_banco'] ?? ''));
        $user = trim((string)($base['usuario'] ?? ''));
        $password = (string)($base['senha'] ?? '');
        $port = (int)($base['porta'] ?: 3306);

        if ($host === '') {
            throw new Exception('Host da base remota não informado.');
        }

        if ($dbName === '') {
            throw new Exception('Nome do banco remoto não informado.');
        }

        if ($user === '') {
            throw new Exception('Usuário da base remota não informado.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $dbName
        );

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
    }

    private function remoteTableExists(PDO $pdo, string $dbName, string $table): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM information_schema.tables
            WHERE table_schema = :db AND table_name = :table
        ");
        $stmt->execute([':db' => $dbName, ':table' => $table]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($row['total'] ?? 0)) > 0;
    }

    private function remoteColumnExists(PDO $pdo, string $dbName, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM information_schema.columns
            WHERE table_schema = :db AND table_name = :table AND column_name = :column
        ");
        $stmt->execute([':db' => $dbName, ':table' => $table, ':column' => $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($row['total'] ?? 0)) > 0;
    }

    private function fetchRemoteTitulares(PDO $pdo, array $base): array
    {
        $campos = [
            'id',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'nome_completo') ? 'nome_completo' : 'NULL AS nome_completo',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'cpf') ? 'cpf' : 'NULL AS cpf',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'rg') ? 'rg' : 'NULL AS rg',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'data_nascimento') ? 'data_nascimento' : 'NULL AS data_nascimento',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'idade') ? 'idade' : 'NULL AS idade',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'genero') ? 'genero' : 'NULL AS genero',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'status_cliente') ? 'status_cliente' : 'NULL AS status_cliente',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'registration_date') ? 'registration_date' : 'NULL AS registration_date',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'registration_date_update') ? 'registration_date_update' : 'NULL AS registration_date_update',
        ];

        $sql = 'SELECT ' . implode(', ', $campos) . ' FROM tb_titular';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) use ($base) {
            return [
                'origem_banco' => $base['nome_banco'],
                'origem_unidade' => $base['nome_unidade'],
                'origem_tabela' => 'tb_titular',
                'origem_id' => (int)$row['id'],
                'tipo_cadastro' => 'TITULAR',
                'nome' => $row['nome_completo'] ?? null,
                'cpf' => $row['cpf'] ?? null,
                'cpf_normalizado' => preg_replace('/\D+/', '', (string)($row['cpf'] ?? '')),
                'rg' => $row['rg'] ?? null,
                'data_nascimento' => $this->normalizeDate($row['data_nascimento'] ?? null),
                'idade' => $row['idade'] !== null ? (int)$row['idade'] : null,
                'genero' => $row['genero'] ?? null,
                'status_cliente' => $row['status_cliente'] ?? null,
                'parentesco' => null,
                'registration_date' => $row['registration_date'] ?? null,
                'registration_date_update' => $row['registration_date_update'] ?? null,
            ];
        }, $rows);
    }

    private function fetchRemoteDependentes(PDO $pdo, array $base): array
    {
        $nomeExpr = 'NULL AS nome';
        if ($this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'nome')) {
            $nomeExpr = 'nome';
        } elseif ($this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'nome_dependente')) {
            $nomeExpr = 'nome_dependente AS nome';
        }

        $campos = [
            'id',
            $nomeExpr,
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'cpf') ? 'cpf' : 'NULL AS cpf',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'rg') ? 'rg' : 'NULL AS rg',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'data_nascimento') ? 'data_nascimento' : 'NULL AS data_nascimento',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'Idade') ? 'Idade AS idade' : ($this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'idade') ? 'idade' : 'NULL AS idade'),
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'genero') ? 'genero' : 'NULL AS genero',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'dependencia_cliente') ? 'dependencia_cliente' : 'NULL AS dependencia_cliente',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'registration_date') ? 'registration_date' : 'NULL AS registration_date',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'registration_date_update') ? 'registration_date_update' : 'NULL AS registration_date_update',
        ];

        $sql = 'SELECT ' . implode(', ', $campos) . ' FROM tb_dependentes';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) use ($base) {
            return [
                'origem_banco' => $base['nome_banco'],
                'origem_unidade' => $base['nome_unidade'],
                'origem_tabela' => 'tb_dependentes',
                'origem_id' => (int)$row['id'],
                'tipo_cadastro' => 'DEPENDENTE',
                'nome' => $row['nome'] ?? null,
                'cpf' => $row['cpf'] ?? null,
                'cpf_normalizado' => preg_replace('/\D+/', '', (string)($row['cpf'] ?? '')),
                'rg' => $row['rg'] ?? null,
                'data_nascimento' => $this->normalizeDate($row['data_nascimento'] ?? null),
                'idade' => $row['idade'] !== null ? (int)$row['idade'] : null,
                'genero' => $row['genero'] ?? null,
                'status_cliente' => null,
                'parentesco' => $row['dependencia_cliente'] ?? null,
                'registration_date' => $row['registration_date'] ?? null,
                'registration_date_update' => $row['registration_date_update'] ?? null,
            ];
        }, $rows);
    }

    private function upsertCadastros(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $this->sql->query("
                INSERT INTO tb_cadastros_unificados (
                    origem_banco, origem_unidade, origem_tabela, origem_id, tipo_cadastro,
                    nome, cpf, cpf_normalizado, rg, data_nascimento, idade, genero,
                    status_cliente, parentesco, registration_date, registration_date_update, data_sync
                ) VALUES (
                    :origem_banco, :origem_unidade, :origem_tabela, :origem_id, :tipo_cadastro,
                    :nome, :cpf, :cpf_normalizado, :rg, :data_nascimento, :idade, :genero,
                    :status_cliente, :parentesco, :registration_date, :registration_date_update, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    origem_unidade = VALUES(origem_unidade),
                    tipo_cadastro = VALUES(tipo_cadastro),
                    nome = VALUES(nome),
                    cpf = VALUES(cpf),
                    cpf_normalizado = VALUES(cpf_normalizado),
                    rg = VALUES(rg),
                    data_nascimento = VALUES(data_nascimento),
                    idade = VALUES(idade),
                    genero = VALUES(genero),
                    status_cliente = VALUES(status_cliente),
                    parentesco = VALUES(parentesco),
                    registration_date = VALUES(registration_date),
                    registration_date_update = VALUES(registration_date_update),
                    data_sync = NOW()
            ", [
                ':origem_banco' => $item['origem_banco'],
                ':origem_unidade' => $item['origem_unidade'],
                ':origem_tabela' => $item['origem_tabela'],
                ':origem_id' => $item['origem_id'],
                ':tipo_cadastro' => $item['tipo_cadastro'],
                ':nome' => $item['nome'],
                ':cpf' => $item['cpf'],
                ':cpf_normalizado' => $item['cpf_normalizado'],
                ':rg' => $item['rg'],
                ':data_nascimento' => $item['data_nascimento'],
                ':idade' => $item['idade'],
                ':genero' => $item['genero'],
                ':status_cliente' => $item['status_cliente'],
                ':parentesco' => $item['parentesco'],
                ':registration_date' => $item['registration_date'],
                ':registration_date_update' => $item['registration_date_update'],
            ]);
            $total++;
        }

        return $total;
    }

    private function safeRegistrarSyncBase(int $baseId, string $status, string $mensagem): void
    {
        try {
            $this->registrarSyncBase($baseId, $status, $mensagem);
        } catch (Throwable $e) {
            // Evita que falha no log derrube a sincronização inteira.
        }
    }

    private function registrarSyncBase(int $baseId, string $status, string $mensagem): void
    {
        $mensagem = function_exists('mb_substr')
            ? mb_substr((string)$mensagem, 0, 1000)
            : substr((string)$mensagem, 0, 1000);

        $this->sql->query("
            UPDATE tb_bases_consulta
            SET ultima_sincronizacao = NOW(), ultimo_status = :status, ultima_mensagem = :mensagem
            WHERE id = :id
        ", [
            ':id' => $baseId,
            ':status' => strtoupper($status),
            ':mensagem' => $mensagem,
        ]);

        $this->sql->query("
            INSERT INTO tb_bases_consulta_sync_log (id_base, status_execucao, mensagem, data_execucao)
            VALUES (:id_base, :status_execucao, :mensagem, NOW())
        ", [
            ':id_base' => $baseId,
            ':status_execucao' => strtoupper($status),
            ':mensagem' => $mensagem,
        ]);
    }

    private function normalizeDate($date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $value = trim((string)$date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 10);
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            $dt = DateTime::createFromFormat('d/m/Y', $value);
            return $dt instanceof DateTime ? $dt->format('Y-m-d') : null;
        }

        return null;
    }
}
