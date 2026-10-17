<?php

use Hcode\DB\Sql;

class CadastroUnificadoService
{
    /** @var Sql */
    private $sql;
    /** @var AccessScopeService */
    private $scope;

    public function __construct(Sql $sql, ?AccessScopeService $scope = null)
    {
        $this->sql = $sql;
        $this->scope = $scope ?: new AccessScopeService($sql);
    }

    public function listBases(bool $onlyActive = false): array
    {
        $where = $onlyActive ? ['ativo = 1'] : [];
        $params = [];
        [$where, $params] = $this->scope->appendConstraint($where, $params, $this->scope->baseConstraint('tb_bases_consulta', 'bases_list'));
        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->sql->select("
            SELECT
                id, nome_unidade, identificador, host, porta, nome_banco, usuario, ativo, ultima_sincronizacao, ultimo_status, ultima_mensagem
            FROM tb_bases_consulta
            {$whereSql}
            ORDER BY nome_unidade ASC, nome_banco ASC
        ", $params);
    }

    public function getResumo(): array
    {
        $where = [];
        $params = [];
        [$where, $params] = $this->scope->appendConstraint($where, $params, $this->scope->dataConstraint('tb_cadastros_unificados', 'tb_cadastros_unificados', 'cad_resumo'));
        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $totais = $this->sql->select("
            SELECT
                COUNT(*) AS total_registros,
                COUNT(DISTINCT origem_banco) AS total_bases,
                SUM(CASE WHEN tipo_cadastro = 'TITULAR' THEN 1 ELSE 0 END) AS total_titulares,
                SUM(CASE WHEN tipo_cadastro = 'DEPENDENTE' THEN 1 ELSE 0 END) AS total_dependentes
            FROM tb_cadastros_unificados
            {$whereSql}
        ", $params);

        $atualizacao = $this->sql->select("
            SELECT MAX(data_sync) AS ultima_sync
            FROM tb_cadastros_unificados
            {$whereSql}
        ", $params);

        return [
            'total_registros' => (int)($totais[0]['total_registros'] ?? 0),
            'total_bases' => (int)($totais[0]['total_bases'] ?? 0),
            'total_titulares' => (int)($totais[0]['total_titulares'] ?? 0),
            'total_dependentes' => (int)($totais[0]['total_dependentes'] ?? 0),
            'ultima_sync' => portal_format_manaus($atualizacao[0]['ultima_sync'] ?? null),
        ];
    }

    public function pesquisar(array $filtros): array
    {
        $page = max(1, (int)($filtros['page'] ?? 1));
        $maxPageSize = max(1, min(50000, (int)($filtros['maxPageSize'] ?? 100)));
        $pageSize = max(1, min($maxPageSize, (int)($filtros['pageSize'] ?? 25)));
        $offset = ($page - 1) * $pageSize;

        $busca = trim((string)($filtros['busca'] ?? ''));
        $tipo = strtoupper(trim((string)($filtros['tipo'] ?? '')));
        $origemBanco = trim((string)($filtros['origem_banco'] ?? ''));
        $origemBancos = $this->normalizeBancoFilters($filtros['origem_bancos'] ?? []);
        $origemUnidade = trim((string)($filtros['origem_unidade'] ?? ''));
        $somenteAtivos = isset($filtros['somente_ativos']) ? ((int)$filtros['somente_ativos'] === 1) : false;

        $where = [];
        $params = [];

        if ($busca !== '') {
            $buscaCpf = preg_replace('/\D+/', '', $busca);
            $where[] = "(
                nome LIKE :busca_nome
                OR cpf_normalizado LIKE :busca_cpf
                OR rg LIKE :busca_rg
                OR telefone LIKE :busca_telefone
                OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefone, ''), '(', ''), ')', ''), '-', ''), ' ', '') LIKE :busca_cpf
            )";
            $params[':busca_nome'] = '%' . $busca . '%';
            $params[':busca_cpf'] = '%' . $buscaCpf . '%';
            $params[':busca_rg'] = '%' . $busca . '%';
            $params[':busca_telefone'] = '%' . $busca . '%';
        }

        if (in_array($tipo, ['TITULAR', 'DEPENDENTE'], true)) {
            $where[] = 'tipo_cadastro = :tipo';
            $params[':tipo'] = $tipo;
        }

        if (!empty($origemBancos)) {
            $placeholders = [];
            foreach ($origemBancos as $i => $banco) {
                $key = ':origem_banco_' . $i;
                $placeholders[] = $key;
                $params[$key] = $banco;
            }
            $where[] = 'origem_banco IN (' . implode(',', $placeholders) . ')';
        } elseif ($origemBanco !== '') {
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

        [$where, $params] = $this->scope->appendConstraint($where, $params, $this->scope->dataConstraint('tb_cadastros_unificados', 'tb_cadastros_unificados', 'cad_lista'));

        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = $this->sql->select("SELECT COUNT(*) AS total FROM tb_cadastros_unificados {$whereSql}", $params);
        $items = $this->sql->select("
            SELECT
                id, id_base_consulta, origem_banco, origem_unidade, origem_tabela, origem_id, origem_titular_id, tipo_cadastro,
                nome, cpf, cpf_normalizado, rg, telefone, data_nascimento, idade, genero,
                status_cliente, parentesco, registration_date, registration_date_update, data_sync
            FROM tb_cadastros_unificados
            {$whereSql}
            ORDER BY origem_banco ASC, COALESCE(origem_titular_id, origem_id) ASC, tipo_cadastro DESC, nome ASC, id DESC
            LIMIT {$offset}, {$pageSize}
        ", $params);

        foreach ($items as &$item) {
            $item['registration_date'] = portal_format_manaus($item['registration_date'] ?? null);
            $item['registration_date_update'] = portal_format_manaus($item['registration_date_update'] ?? null);
            $item['data_sync'] = portal_format_manaus($item['data_sync'] ?? null);
        }
        unset($item);

        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => (int)($total[0]['total'] ?? 0),
            'pages' => $pageSize > 0 ? (int)ceil(((int)($total[0]['total'] ?? 0)) / $pageSize) : 1,
            'items' => $items,
        ];
    }

    public function exportar(array $filtros): array
    {
        $filtros['page'] = 1;
        $filtros['pageSize'] = 50000;
        $filtros['maxPageSize'] = 50000;
        $resultado = $this->pesquisar($filtros);

        return [
            'total' => $resultado['total'],
            'items' => $resultado['items'],
            'limit' => 50000,
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
        $requestedBaseIds = array_values(array_filter(array_map('intval', $baseIds), function ($id) {
            return $id > 0;
        }));
        $baseIds = $this->scope->filterRequestedBaseIds($requestedBaseIds);

        if (!$this->scope->canViewAll() && !empty($requestedBaseIds) && empty($baseIds)) {
            return [];
        }

        $scopeConstraint = $this->scope->baseConstraint('tb_bases_consulta', 'bases_sync');

        if (count($baseIds) > 0) {
            $params = [];
            $placeholders = [];

            foreach ($baseIds as $i => $id) {
                $key = ':id_' . $i;
                $placeholders[] = $key;
                $params[$key] = $id;
            }

            $where = ['ativo = 1', 'id IN (' . implode(',', $placeholders) . ')'];
            [$where, $params] = $this->scope->appendConstraint($where, $params, $scopeConstraint);

            return $this->sql->select("
                SELECT *
                FROM tb_bases_consulta
                WHERE " . implode(' AND ', $where) . "
                ORDER BY nome_unidade ASC, nome_banco ASC
            ", $params);
        }

        $where = ['ativo = 1'];
        $params = [];
        [$where, $params] = $this->scope->appendConstraint($where, $params, $scopeConstraint);

        return $this->sql->select("
            SELECT *
            FROM tb_bases_consulta
            WHERE " . implode(' AND ', $where) . "
            ORDER BY nome_unidade ASC, nome_banco ASC
        ", $params);
    }

    private function normalizeBancoFilters($value): array
    {
        if (!is_array($value)) {
            $value = explode(',', (string)$value);
        }

        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $items[$item] = true;
            }
        }

        return array_keys($items);
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

    private function remotePhoneExpression(PDO $pdo, string $dbName, string $table): string
    {
        foreach (['telefone', 'celular', 'fone', 'whatsapp', 'contato', 'nrphone'] as $column) {
            if ($this->remoteColumnExists($pdo, $dbName, $table, $column)) {
                return $column . ' AS telefone';
            }
        }

        return 'NULL AS telefone';
    }

    private function fetchRemoteTitulares(PDO $pdo, array $base): array
    {
        $campos = [
            'id',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'nome_completo') ? 'nome_completo' : 'NULL AS nome_completo',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'cpf') ? 'cpf' : 'NULL AS cpf',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_titular', 'rg') ? 'rg' : 'NULL AS rg',
            $this->remotePhoneExpression($pdo, $base['nome_banco'], 'tb_titular'),
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
                'id_base_consulta' => (int)$base['id'],
                'origem_banco' => $base['nome_banco'],
                'origem_unidade' => $base['nome_unidade'],
                'origem_tabela' => 'tb_titular',
                'origem_id' => (int)$row['id'],
                'origem_titular_id' => null,
                'tipo_cadastro' => 'TITULAR',
                'nome' => $row['nome_completo'] ?? null,
                'cpf' => $row['cpf'] ?? null,
                'cpf_normalizado' => preg_replace('/\D+/', '', (string)($row['cpf'] ?? '')),
                'rg' => $row['rg'] ?? null,
                'telefone' => $row['telefone'] ?? null,
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
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'id_titular') ? 'id_titular' : 'NULL AS id_titular',
            $nomeExpr,
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'cpf') ? 'cpf' : 'NULL AS cpf',
            $this->remoteColumnExists($pdo, $base['nome_banco'], 'tb_dependentes', 'rg') ? 'rg' : 'NULL AS rg',
            $this->remotePhoneExpression($pdo, $base['nome_banco'], 'tb_dependentes'),
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
                'id_base_consulta' => (int)$base['id'],
                'origem_banco' => $base['nome_banco'],
                'origem_unidade' => $base['nome_unidade'],
                'origem_tabela' => 'tb_dependentes',
                'origem_id' => (int)$row['id'],
                'origem_titular_id' => $row['id_titular'] !== null ? (int)$row['id_titular'] : null,
                'tipo_cadastro' => 'DEPENDENTE',
                'nome' => $row['nome'] ?? null,
                'cpf' => $row['cpf'] ?? null,
                'cpf_normalizado' => preg_replace('/\D+/', '', (string)($row['cpf'] ?? '')),
                'rg' => $row['rg'] ?? null,
                'telefone' => $row['telefone'] ?? null,
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
                    id_base_consulta, origem_banco, origem_unidade, origem_tabela, origem_id, origem_titular_id, tipo_cadastro,
                    nome, cpf, cpf_normalizado, rg, telefone, data_nascimento, idade, genero,
                    status_cliente, parentesco, registration_date, registration_date_update, data_sync
                ) VALUES (
                    :id_base_consulta, :origem_banco, :origem_unidade, :origem_tabela, :origem_id, :origem_titular_id, :tipo_cadastro,
                    :nome, :cpf, :cpf_normalizado, :rg, :telefone, :data_nascimento, :idade, :genero,
                    :status_cliente, :parentesco, :registration_date, :registration_date_update, UTC_TIMESTAMP()
                )
                ON DUPLICATE KEY UPDATE
                    id_base_consulta = VALUES(id_base_consulta),
                    origem_unidade = VALUES(origem_unidade),
                    origem_titular_id = VALUES(origem_titular_id),
                    tipo_cadastro = VALUES(tipo_cadastro),
                    nome = VALUES(nome),
                    cpf = VALUES(cpf),
                    cpf_normalizado = VALUES(cpf_normalizado),
                    rg = VALUES(rg),
                    telefone = VALUES(telefone),
                    data_nascimento = VALUES(data_nascimento),
                    idade = VALUES(idade),
                    genero = VALUES(genero),
                    status_cliente = VALUES(status_cliente),
                    parentesco = VALUES(parentesco),
                    registration_date = VALUES(registration_date),
                    registration_date_update = VALUES(registration_date_update),
                    data_sync = UTC_TIMESTAMP()
            ", [
                ':id_base_consulta' => isset($item['id_base_consulta']) ? (int)$item['id_base_consulta'] : null,
                ':origem_banco' => $item['origem_banco'],
                ':origem_unidade' => $item['origem_unidade'],
                ':origem_tabela' => $item['origem_tabela'],
                ':origem_id' => $item['origem_id'],
                ':origem_titular_id' => $item['origem_titular_id'] ?? null,
                ':tipo_cadastro' => $item['tipo_cadastro'],
                ':nome' => $item['nome'],
                ':cpf' => $item['cpf'],
                ':cpf_normalizado' => $item['cpf_normalizado'],
                ':rg' => $item['rg'],
                ':telefone' => $item['telefone'] ?? null,
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
            SET ultima_sincronizacao = UTC_TIMESTAMP(), ultimo_status = :status, ultima_mensagem = :mensagem
            WHERE id = :id
        ", [
            ':id' => $baseId,
            ':status' => strtoupper($status),
            ':mensagem' => $mensagem,
        ]);

        $this->sql->query("
            INSERT INTO tb_bases_consulta_sync_log (id_base, status_execucao, mensagem, data_execucao)
            VALUES (:id_base, :status_execucao, :mensagem, UTC_TIMESTAMP())
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
            [$year, $month, $day] = array_map('intval', explode('-', $value));
            return ($year >= 1900 && checkdate($month, $day, $year)) ? $value : null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $value)) {
            $date = substr($value, 0, 10);
            [$year, $month, $day] = array_map('intval', explode('-', $date));
            return ($year >= 1900 && checkdate($month, $day, $year)) ? $date : null;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            [$day, $month, $year] = array_map('intval', explode('/', $value));
            if ($year < 1900 || !checkdate($month, $day, $year)) {
                return null;
            }
            $dt = DateTime::createFromFormat('d/m/Y', $value);
            return $dt instanceof DateTime ? $dt->format('Y-m-d') : null;
        }

        return null;
    }
}
