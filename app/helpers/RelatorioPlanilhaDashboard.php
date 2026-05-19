<?php

use Hcode\DB\Sql;
use Hcode\Model\Funcionarios;

class RelatorioPlanilhaDashboard
{
    /** @var Sql */
    protected $sql;

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public function canExport(): bool
    {
        if (!Funcionarios::checkLogin()) {
            return false;
        }

        try {
            if (!$this->tableExists('tb_permissions')) {
                return Funcionarios::hasPermission('DASHBOARD_VIEW');
            }

            $rows = $this->sql->select(
                "SELECT COUNT(*) AS total FROM tb_permissions WHERE permission_key = :permission_key",
                [':permission_key' => 'PLANILHA_EXPORT']
            );

            $exists = (int)($rows[0]['total'] ?? 0) > 0;
            if (!$exists) {
                return Funcionarios::hasPermission('DASHBOARD_VIEW');
            }

            return Funcionarios::hasPermission('PLANILHA_EXPORT');
        } catch (Throwable $e) {
            return Funcionarios::hasPermission('DASHBOARD_VIEW');
        }
    }

    public function getPainel(int $ano, int $mes, bool $somenteFechados = true): array
    {
        $totalDiasMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
        $rows = $this->buscarRelatorios($ano, $mes, $somenteFechados);

        $diasComRegistros = [];
        $diasFechados = [];
        $chartMap = [];
        $rankingMap = [];
        $totalRefeicoes = 0;
        $totalPessoas = 0;
        $unidadesAtivas = [];

        foreach ($rows as $row) {
            $data = (string)($row['data'] ?? '');
            if ($data === '') {
                continue;
            }

            $dia = (int)date('j', strtotime($data));
            if ($dia <= 0) {
                continue;
            }

            $nomeBanco = trim((string)($row['nome_banco'] ?? 'Sem unidade'));
            if ($nomeBanco === '') {
                $nomeBanco = 'Sem unidade';
            }

            $fechado = (int)($row['fechado'] ?? 0) === 1;
            $refeicoes = (int)($row['qtd_refeicoes_servidas'] ?? 0);
            $pessoas = (int)($row['Total_pessoas_atendidas'] ?? 0);

            $diasComRegistros[$dia] = true;
            if ($fechado) {
                $diasFechados[$dia] = true;
            }

            if (!isset($chartMap[$dia])) {
                $chartMap[$dia] = [
                    'refeicoes' => 0,
                    'pessoas' => 0,
                    'dias_fechados' => 0,
                ];
            }

            $chartMap[$dia]['refeicoes'] += $refeicoes;
            $chartMap[$dia]['pessoas'] += $pessoas;
            if ($fechado) {
                $chartMap[$dia]['dias_fechados']++;
            }

            if (!isset($rankingMap[$nomeBanco])) {
                $rankingMap[$nomeBanco] = [
                    'unidade' => $nomeBanco,
                    'refeicoes' => 0,
                    'pessoas' => 0,
                    'registros' => 0,
                    'fechados' => 0,
                ];
            }

            $rankingMap[$nomeBanco]['refeicoes'] += $refeicoes;
            $rankingMap[$nomeBanco]['pessoas'] += $pessoas;
            $rankingMap[$nomeBanco]['registros']++;
            if ($fechado) {
                $rankingMap[$nomeBanco]['fechados']++;
            }

            $totalRefeicoes += $refeicoes;
            $totalPessoas += $pessoas;
            $unidadesAtivas[$nomeBanco] = true;
        }

        ksort($chartMap);
        $labels = [];
        $chartRefeicoes = [];
        $chartPessoas = [];
        $chartFechados = [];
        for ($dia = 1; $dia <= $totalDiasMes; $dia++) {
            $labels[] = str_pad((string)$dia, 2, '0', STR_PAD_LEFT);
            $chartRefeicoes[] = (int)($chartMap[$dia]['refeicoes'] ?? 0);
            $chartPessoas[] = (int)($chartMap[$dia]['pessoas'] ?? 0);
            $chartFechados[] = (int)($chartMap[$dia]['dias_fechados'] ?? 0);
        }

        uasort($rankingMap, function ($a, $b) {
            if ($b['refeicoes'] === $a['refeicoes']) {
                return $b['pessoas'] <=> $a['pessoas'];
            }
            return $b['refeicoes'] <=> $a['refeicoes'];
        });

        $ranking = array_slice(array_values($rankingMap), 0, 8);

        $diasComRegistrosCount = count($diasComRegistros);
        $diasFechadosCount = count($diasFechados);
        $mediaRefeicoes = $diasComRegistrosCount > 0 ? round($totalRefeicoes / $diasComRegistrosCount) : 0;
        $taxaFechamento = $totalDiasMes > 0 ? round(($diasFechadosCount / $totalDiasMes) * 100, 1) : 0;

        return [
            'resumo' => [
                'total_dias_mes' => $totalDiasMes,
                'dias_com_registros' => $diasComRegistrosCount,
                'dias_fechados' => $diasFechadosCount,
                'total_refeicoes' => $totalRefeicoes,
                'total_pessoas' => $totalPessoas,
                'media_refeicoes' => $mediaRefeicoes,
                'unidades_ativas' => count($unidadesAtivas),
                'taxa_fechamento' => $taxaFechamento,
            ],
            'grafico' => [
                'labels' => $labels,
                'refeicoes' => $chartRefeicoes,
                'pessoas' => $chartPessoas,
                'fechados' => $chartFechados,
            ],
            'ranking' => $ranking,
            'historico' => $this->buscarHistoricoPlanilhas(8),
            'meta' => [
                'ano' => $ano,
                'mes' => $mes,
                'somente_fechados' => $somenteFechados,
                'pode_exportar' => $this->canExport(),
            ],
        ];
    }

    public function registrarGeracao(array $payload): void
    {
        if (!$this->tableExists('tb_relatorios_pdf')) {
            return;
        }

        $columns = $this->getColumns('tb_relatorios_pdf');
        if (empty($columns)) {
            return;
        }

        $map = [
            'data_relatorio' => $payload['data_relatorio'] ?? date('Y-m-d'),
            'nome_arquivo' => $payload['nome_arquivo'] ?? null,
            'url_publica' => $payload['url_publica'] ?? null,
            'caminho_remoto' => $payload['caminho_remoto'] ?? null,
            'status_upload' => $payload['status_upload'] ?? 'GERADO_PLANILHA',
            'mensagem_erro' => $payload['mensagem_erro'] ?? null,
            'responsavel' => $payload['responsavel'] ?? null,
            'cpf_responsavel' => $payload['cpf_responsavel'] ?? null,
            'data_geracao' => $payload['data_geracao'] ?? date('Y-m-d H:i:s'),
            'data_upload' => $payload['data_upload'] ?? date('Y-m-d H:i:s'),
        ];

        $insertCols = [];
        $insertParams = [];
        $placeholders = [];
        foreach ($map as $col => $value) {
            if (!in_array($col, $columns, true)) {
                continue;
            }
            $insertCols[] = $col;
            $placeholders[] = ':' . $col;
            $insertParams[':' . $col] = $value;
        }

        if (empty($insertCols)) {
            return;
        }

        $sql = sprintf(
            'INSERT INTO tb_relatorios_pdf (%s) VALUES (%s)',
            implode(', ', $insertCols),
            implode(', ', $placeholders)
        );

        try {
            $this->sql->query($sql, $insertParams);
        } catch (Throwable $e) {
            // não quebra a exportação principal se o histórico falhar
        }
    }

    public function localizarArquivoHistorico(?int $id = null, ?string $file = null): ?array
    {
        if ($id && $this->tableExists('tb_relatorios_pdf')) {
            $columns = $this->getColumns('tb_relatorios_pdf');
            $select = [];
            foreach (['id', 'nome_arquivo', 'caminho_remoto', 'url_publica', 'data_geracao', 'status_upload'] as $col) {
                if (in_array($col, $columns, true)) {
                    $select[] = $col;
                }
            }
            if (!empty($select)) {
                try {
                    $rows = $this->sql->select(
                        'SELECT ' . implode(', ', $select) . ' FROM tb_relatorios_pdf WHERE id = :id LIMIT 1',
                        [':id' => $id]
                    );
                    if (!empty($rows[0])) {
                        $row = $rows[0];
                        $nome = basename((string)($row['nome_arquivo'] ?? ''));
                        $path = $this->resolverCaminhoArquivo($row['caminho_remoto'] ?? $nome);
                        if ($path && is_file($path)) {
                            return [
                                'path' => $path,
                                'name' => basename($path),
                                'row' => $row,
                            ];
                        }
                    }
                } catch (Throwable $e) {
                    // fallback para busca por arquivo
                }
            }
        }

        if ($file) {
            $path = $this->resolverCaminhoArquivo($file);
            if ($path && is_file($path)) {
                return [
                    'path' => $path,
                    'name' => basename($path),
                    'row' => null,
                ];
            }
        }

        return null;
    }

    protected function buscarRelatorios(int $ano, int $mes, bool $somenteFechados = true): array
    {
        if (!$this->tableExists('tb_relatorios')) {
            return [];
        }

        $columns = $this->getColumns('tb_relatorios');
        if (!in_array('data', $columns, true)) {
            return [];
        }

        $select = [
            'DATE(data) AS data',
            in_array('nome_banco', $columns, true) ? 'nome_banco' : '"Sem unidade" AS nome_banco',
            in_array('fechado', $columns, true) ? 'COALESCE(fechado, 0) AS fechado' : '0 AS fechado',
            in_array('Total_pessoas_atendidas', $columns, true) ? 'COALESCE(Total_pessoas_atendidas, 0) AS Total_pessoas_atendidas' : '0 AS Total_pessoas_atendidas',
            in_array('qtd_refeicoes_servidas', $columns, true) ? 'COALESCE(qtd_refeicoes_servidas, 0) AS qtd_refeicoes_servidas' : '0 AS qtd_refeicoes_servidas',
        ];

        $where = ['YEAR(data) = :ano', 'MONTH(data) = :mes'];
        $params = [':ano' => $ano, ':mes' => $mes];

        if ($somenteFechados && in_array('fechado', $columns, true)) {
            $where[] = 'COALESCE(fechado, 0) = 1';
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM tb_relatorios WHERE ' . implode(' AND ', $where) . ' ORDER BY data ASC, nome_banco ASC';
        return $this->sql->select($sql, $params);
    }

    protected function buscarHistoricoPlanilhas(int $limit = 8): array
    {
        $items = [];

        if ($this->tableExists('tb_relatorios_pdf')) {
            $columns = $this->getColumns('tb_relatorios_pdf');
            $select = [];
            foreach (['id', 'data_relatorio', 'nome_arquivo', 'url_publica', 'caminho_remoto', 'status_upload', 'responsavel', 'data_geracao', 'data_upload'] as $col) {
                if (in_array($col, $columns, true)) {
                    $select[] = $col;
                }
            }

            if (!empty($select) && in_array('nome_arquivo', $columns, true)) {
                try {
                    $sql = 'SELECT ' . implode(', ', $select) . "\n"
                        . "FROM tb_relatorios_pdf\n"
                        . "WHERE nome_arquivo LIKE :xlsx\n"
                        . "ORDER BY " . (in_array('id', $columns, true) ? 'id' : 'nome_arquivo') . " DESC\n"
                        . 'LIMIT ' . (int)$limit;
                    $items = $this->sql->select($sql, [':xlsx' => '%.xlsx']);
                } catch (Throwable $e) {
                    $items = [];
                }
            }
        }

        if (!empty($items)) {
            foreach ($items as &$item) {
                $item['download_url'] = '/admin/api/relatorio/planilha/arquivo?id=' . (int)($item['id'] ?? 0);
                $item['exists_local'] = (bool)$this->resolverCaminhoArquivo($item['caminho_remoto'] ?? $item['nome_arquivo'] ?? '');
            }
            return $items;
        }

        $dir = ROOT_DIR . '/storage/relatorios_planilha';
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.xlsx') ?: [];
        rsort($files);
        $files = array_slice($files, 0, $limit);

        foreach ($files as $path) {
            $items[] = [
                'id' => null,
                'data_relatorio' => date('Y-m-d', filemtime($path)),
                'nome_arquivo' => basename($path),
                'url_publica' => null,
                'caminho_remoto' => basename($path),
                'status_upload' => 'LOCAL',
                'responsavel' => null,
                'data_geracao' => date('Y-m-d H:i:s', filemtime($path)),
                'data_upload' => null,
                'download_url' => '/admin/api/relatorio/planilha/arquivo?file=' . rawurlencode(basename($path)),
                'exists_local' => true,
            ];
        }

        return $items;
    }

    protected function resolverCaminhoArquivo($input): ?string
    {
        $file = trim((string)$input);
        if ($file === '') {
            return null;
        }

        if (strpos($file, ROOT_DIR . '/storage/relatorios_planilha/') === 0 && is_file($file)) {
            return $file;
        }

        $file = basename($file);
        $path = ROOT_DIR . '/storage/relatorios_planilha/' . $file;
        return is_file($path) ? $path : null;
    }

    protected function tableExists(string $table): bool
    {
        try {
            $rows = $this->sql->select("SHOW TABLES LIKE :table", [':table' => $table]);
            return count($rows) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    protected function getColumns(string $table): array
    {
        try {
            $rows = $this->sql->select('SHOW COLUMNS FROM ' . $table);
            return array_values(array_map(function ($row) {
                return $row['Field'] ?? '';
            }, $rows));
        } catch (Throwable $e) {
            return [];
        }
    }
}
