<?php

use Hcode\DB\Sql;

class UnidadesAtualizadasService
{
    /** @var Sql */
    private $sql;

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public function pesquisar(array $filtros = []): array
    {
        if (!$this->tableExists('tb_bases_consulta')) {
            return $this->emptyResult();
        }

        $where = [];
        $params = [];

        $unidade = trim((string)($filtros['unidade'] ?? ''));
        if ($unidade !== '') {
            $where[] = '(b.nome_unidade LIKE :unidade OR b.nome_banco LIKE :unidade OR b.identificador LIKE :unidade)';
            $params[':unidade'] = '%' . $unidade . '%';
        }

        $tipoUnidade = trim((string)($filtros['tipo_unidade'] ?? ''));
        if ($tipoUnidade !== '') {
            $where[] = "'Unidade de atendimento' = :tipo_unidade";
            $params[':tipo_unidade'] = $tipoUnidade;
        }

        $dataAtualizacao = trim((string)($filtros['data_atualizacao'] ?? ''));
        if ($dataAtualizacao !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAtualizacao)) {
            $where[] = 'DATE(b.ultima_sincronizacao) = :data_atualizacao';
            $params[':data_atualizacao'] = $dataAtualizacao;
        }

        $status = $this->normalizeStatus($filtros['status'] ?? '');
        if ($status !== '') {
            $where[] = $this->statusExpression() . ' = :status';
            $params[':status'] = $status;
        }

        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = $this->sql->select("
            SELECT
                b.id,
                b.nome_unidade,
                'Unidade de atendimento' AS tipo_unidade,
                b.identificador,
                b.nome_banco,
                b.ultima_sincronizacao,
                DATE_FORMAT(b.ultima_sincronizacao, '%d/%m/%Y') AS data_atualizacao,
                DATE_FORMAT(b.ultima_sincronizacao, '%H:%i:%s') AS hora_atualizacao,
                {$this->statusExpression()} AS status_unidade,
                b.ultimo_status,
                b.ultima_mensagem
            FROM tb_bases_consulta b
            {$whereSql}
            ORDER BY
                CASE {$this->statusExpression()}
                    WHEN 'ATUALIZADA' THEN 1
                    WHEN 'PENDENTE' THEN 2
                    WHEN 'ERRO' THEN 3
                    ELSE 4
                END,
                b.ultima_sincronizacao DESC,
                b.nome_unidade ASC
        ", $params);

        $items = array_map([$this, 'formatRow'], $rows);

        return [
            'items' => $items,
            'resumo' => $this->resumo($items),
        ];
    }

    public function getDashboardResumo(): array
    {
        $resultado = $this->pesquisar([]);
        return $resultado['resumo'];
    }

    private function resumo(array $items): array
    {
        $resumo = [
            'total' => count($items),
            'atualizadas' => 0,
            'pendentes' => 0,
            'erros' => 0,
        ];

        foreach ($items as $item) {
            if ($item['status_unidade'] === 'ATUALIZADA') {
                $resumo['atualizadas']++;
            } elseif ($item['status_unidade'] === 'PENDENTE') {
                $resumo['pendentes']++;
            } elseif ($item['status_unidade'] === 'ERRO') {
                $resumo['erros']++;
            }
        }

        return $resumo;
    }

    private function formatRow(array $row): array
    {
        $status = $row['status_unidade'] ?? 'PENDENTE';

        return [
            'id' => (int)($row['id'] ?? 0),
            'nome_unidade' => $row['nome_unidade'] ?? '-',
            'tipo_unidade' => $row['tipo_unidade'] ?? 'Unidade de atendimento',
            'identificador' => $row['identificador'] ?? '',
            'nome_banco' => $row['nome_banco'] ?? '',
            'ultima_sincronizacao' => $row['ultima_sincronizacao'] ?? null,
            'data_atualizacao' => $row['data_atualizacao'] ?: '-',
            'hora_atualizacao' => $row['hora_atualizacao'] ?: '-',
            'status_unidade' => $status,
            'status_label' => $this->statusLabel($status),
            'status_class' => $this->statusClass($status),
            'ultimo_status' => $row['ultimo_status'] ?? '',
            'ultima_mensagem' => $row['ultima_mensagem'] ?? '',
        ];
    }

    private function statusExpression(): string
    {
        return "CASE
            WHEN UPPER(COALESCE(b.ultimo_status, '')) = 'ERRO' THEN 'ERRO'
            WHEN b.ultima_sincronizacao IS NOT NULL
                AND UPPER(COALESCE(b.ultimo_status, '')) IN ('SUCESSO', 'OK', 'ATUALIZADA', 'ATUALIZADO', 'GERADO_PLANILHA')
                THEN 'ATUALIZADA'
            WHEN b.ultima_sincronizacao IS NOT NULL AND COALESCE(b.ultimo_status, '') = '' THEN 'ATUALIZADA'
            ELSE 'PENDENTE'
        END";
    }

    private function normalizeStatus($status): string
    {
        $status = strtoupper(trim((string)$status));
        if (in_array($status, ['ATUALIZADA', 'PENDENTE', 'ERRO'], true)) {
            return $status;
        }

        return '';
    }

    private function statusLabel(string $status): string
    {
        if ($status === 'ATUALIZADA') return 'Atualizada';
        if ($status === 'ERRO') return 'Erro';
        return 'Pendente';
    }

    private function statusClass(string $status): string
    {
        if ($status === 'ATUALIZADA') return 'success';
        if ($status === 'ERRO') return 'danger';
        return 'warning';
    }

    private function tableExists(string $table): bool
    {
        try {
            return count($this->sql->select("SHOW TABLES LIKE :table_name", [':table_name' => $table])) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function emptyResult(): array
    {
        return [
            'items' => [],
            'resumo' => [
                'total' => 0,
                'atualizadas' => 0,
                'pendentes' => 0,
                'erros' => 0,
            ],
        ];
    }
}
