<?php

use Hcode\DB\Sql;
use Hcode\Model\Funcionarios;

class AccessScopeService
{
    /** @var Sql */
    private $sql;
    private $tableCache = [];
    private $columnCache = [];
    private $allowedBaseIds = null;
    private $allowedBancoNames = null;
    private $allowedUnitTokens = null;

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public function canViewAll(): bool
    {
        $perfil = strtoupper((string)($_SESSION[Funcionarios::SESSION]['perfil'] ?? ''));
        if (in_array($perfil, ['ADMIN', 'SUPERVISOR'], true)) {
            return true;
        }

        $permissions = $_SESSION[Funcionarios::SESSION]['permissions'] ?? [];
        if (is_array($permissions) && in_array('DASHBOARD_VIEW_ALL', $permissions, true)) {
            return true;
        }

        return false;
    }

    public function currentUserId(): int
    {
        return (int)($_SESSION[Funcionarios::SESSION]['id_usuario'] ?? 0);
    }

    public function allowedBaseIds(): array
    {
        if ($this->allowedBaseIds !== null) {
            return $this->allowedBaseIds;
        }

        if ($this->canViewAll()) {
            $rows = $this->sql->select("
                SELECT id
                FROM tb_bases_consulta
                WHERE ativo = 1
                ORDER BY id
            ");
            return $this->allowedBaseIds = array_map('intval', array_column($rows, 'id'));
        }

        if (
            !$this->tableExists('tb_bases_consulta') ||
            !$this->tableExists('tb_usuario_regioes') ||
            !$this->columnExists('tb_bases_consulta', 'id_regiao')
        ) {
            return $this->allowedBaseIds = [];
        }

        $userId = $this->currentUserId();
        if ($userId <= 0) {
            return $this->allowedBaseIds = [];
        }

        $hasUserUnits = $this->tableExists('tb_usuario_unidades');
        $joinUserUnits = $hasUserUnits
            ? "LEFT JOIN tb_usuario_unidades uu ON uu.id_base_consulta = b.id AND uu.id_usuario = :id_usuario_unidade"
            : "";
        $unitCondition = $hasUserUnits ? " OR uu.id_usuario IS NOT NULL" : "";
        $params = [':id_usuario_regiao' => $userId];
        if ($hasUserUnits) {
            $params[':id_usuario_unidade'] = $userId;
        }

        $rows = $this->sql->select("
            SELECT DISTINCT b.id
            FROM tb_bases_consulta b
            LEFT JOIN tb_usuario_regioes ur
                ON ur.id_regiao = b.id_regiao
                AND ur.id_usuario = :id_usuario_regiao
            {$joinUserUnits}
            WHERE b.ativo = 1
              AND (ur.id_usuario IS NOT NULL{$unitCondition})
            ORDER BY b.id
        ", $params);

        return $this->allowedBaseIds = array_map('intval', array_column($rows, 'id'));
    }

    public function allowedBancoNames(): array
    {
        if ($this->allowedBancoNames !== null) {
            return $this->allowedBancoNames;
        }

        $ids = $this->allowedBaseIds();
        if (empty($ids)) {
            return $this->allowedBancoNames = [];
        }

        $filter = $this->inClause('id', $ids, 'base_name');
        $rows = $this->sql->select("
            SELECT nome_banco
            FROM tb_bases_consulta
            WHERE {$filter['sql']}
              AND nome_banco IS NOT NULL
              AND nome_banco <> ''
        ", $filter['params']);

        return $this->allowedBancoNames = array_values(array_filter(array_map('strval', array_column($rows, 'nome_banco'))));
    }

    public function allowedUnitTokens(): array
    {
        if ($this->allowedUnitTokens !== null) {
            return $this->allowedUnitTokens;
        }

        $ids = $this->allowedBaseIds();
        if (empty($ids)) {
            return $this->allowedUnitTokens = [];
        }

        $filter = $this->inClause('id', $ids, 'unit_tokens');
        $rows = $this->sql->select("
            SELECT nome_banco, identificador, nome_unidade
            FROM tb_bases_consulta
            WHERE {$filter['sql']}
        ", $filter['params']);

        $tokens = [];
        foreach ($rows as $row) {
            foreach (['nome_banco', 'identificador', 'nome_unidade'] as $column) {
                $value = trim((string)($row[$column] ?? ''));
                if ($value !== '') {
                    $tokens[$value] = $value;
                }
            }
        }

        return $this->allowedUnitTokens = array_values($tokens);
    }

    public function baseConstraint(string $alias = 'b', string $prefix = 'scope_base'): array
    {
        if ($this->canViewAll()) {
            return ['sql' => '', 'params' => []];
        }

        $ids = $this->allowedBaseIds();
        if (empty($ids)) {
            return ['sql' => '1 = 0', 'params' => []];
        }

        return $this->inClause($this->quoteAlias($alias, 'id'), $ids, $prefix);
    }

    public function dataConstraint(string $table, string $alias, string $prefix = 'scope_data'): array
    {
        if ($this->canViewAll()) {
            return ['sql' => '', 'params' => []];
        }

        $ids = $this->allowedBaseIds();
        if ($this->columnExists($table, 'id_base_consulta')) {
            if (empty($ids)) {
                return ['sql' => '1 = 0', 'params' => []];
            }
            $baseConstraint = $this->inClause($this->quoteAlias($alias, 'id_base_consulta'), $ids, $prefix . '_base');
            $nameColumn = $this->firstColumn($table, ['nome_banco', 'origem_banco']);
            $tokens = $this->allowedUnitTokens();

            if (!$nameColumn || empty($tokens)) {
                return $baseConstraint;
            }

            $nameConstraint = $this->inClause($this->quoteAlias($alias, $nameColumn), $tokens, $prefix . '_token');
            return [
                'sql' => '(' . $baseConstraint['sql'] . ' OR ' . $nameConstraint['sql'] . ')',
                'params' => array_merge($baseConstraint['params'], $nameConstraint['params']),
            ];
        }

        $nameColumn = $this->firstColumn($table, ['nome_banco', 'origem_banco']);
        $tokens = $this->allowedUnitTokens();
        if ($nameColumn && !empty($tokens)) {
            return $this->inClause($this->quoteAlias($alias, $nameColumn), $tokens, $prefix . '_name');
        }

        return ['sql' => '1 = 0', 'params' => []];
    }

    public function appendConstraint(array $where, array $params, array $constraint): array
    {
        if (!empty($constraint['sql'])) {
            $where[] = $constraint['sql'];
            $params = array_merge($params, $constraint['params']);
        }

        return [$where, $params];
    }

    public function normalizeRegionCode(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        return in_array($codigo, ['CAPITAL', 'INTERIOR'], true) ? $codigo : '';
    }

    public function regionConstraint(string $table, string $alias, string $prefix, string $codigo): array
    {
        $codigo = $this->normalizeRegionCode($codigo);
        if ($codigo === '' || !$this->tableExists('tb_regioes') || !$this->tableExists('tb_bases_consulta') || !$this->columnExists($table, 'id_base_consulta')) {
            return ['sql' => '', 'params' => []];
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_]+/', '_', $prefix);
        $baseAlias = $safePrefix . '_base';
        $regionAlias = $safePrefix . '_regiao';
        $param = ':' . $safePrefix . '_codigo';

        return [
            'sql' => 'EXISTS (SELECT 1 FROM tb_bases_consulta ' . $this->quoteIdentifier($baseAlias)
                . ' JOIN tb_regioes ' . $this->quoteIdentifier($regionAlias)
                . ' ON ' . $this->quoteIdentifier($regionAlias) . '.id_regiao = ' . $this->quoteIdentifier($baseAlias) . '.id_regiao'
                . ' WHERE ' . $this->quoteIdentifier($baseAlias) . '.id = ' . $this->quoteAlias($alias, 'id_base_consulta')
                . ' AND ' . $this->quoteIdentifier($regionAlias) . '.codigo = ' . $param . ')',
            'params' => [$param => $codigo],
        ];
    }

    public function filterRequestedBaseIds(array $baseIds): array
    {
        $baseIds = array_values(array_filter(array_map('intval', $baseIds), function ($id) {
            return $id > 0;
        }));

        if ($this->canViewAll()) {
            return $baseIds;
        }

        $allowed = array_flip($this->allowedBaseIds());
        return array_values(array_filter($baseIds, function ($id) use ($allowed) {
            return isset($allowed[$id]);
        }));
    }

    public function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableCache)) {
            try {
                $this->tableCache[$table] = count($this->sql->select("SHOW TABLES LIKE :table", [':table' => $table])) > 0;
            } catch (Throwable $e) {
                $this->tableCache[$table] = false;
            }
        }

        return $this->tableCache[$table];
    }

    public function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->columnCache)) {
            try {
                $rows = $this->sql->select("SHOW COLUMNS FROM {$this->quoteIdentifier($table)} LIKE :column", [':column' => $column]);
                $this->columnCache[$key] = count($rows) > 0;
            } catch (Throwable $e) {
                $this->columnCache[$key] = false;
            }
        }

        return $this->columnCache[$key];
    }

    private function firstColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function inClause(string $columnSql, array $values, string $prefix): array
    {
        $placeholders = [];
        $params = [];

        foreach (array_values($values) as $idx => $value) {
            $key = ':' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $prefix) . '_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $value;
        }

        if (empty($placeholders)) {
            return ['sql' => '1 = 0', 'params' => []];
        }

        return [
            'sql' => $columnSql . ' IN (' . implode(',', $placeholders) . ')',
            'params' => $params,
        ];
    }

    private function quoteAlias(string $alias, string $column): string
    {
        return $this->quoteIdentifier($alias) . '.' . $this->quoteIdentifier($column);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
