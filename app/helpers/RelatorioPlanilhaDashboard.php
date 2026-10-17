<?php

use Hcode\DB\Sql;
use Hcode\Model\Funcionarios;
use Hcode\Model\Notification;

class RelatorioPlanilhaDashboard
{
    /** @var Sql */
    protected $sql;
    /** @var AccessScopeService */
    protected $scope;

    public function __construct(Sql $sql, ?AccessScopeService $scope = null)
    {
        $this->sql = $sql;
        $this->scope = $scope ?: new AccessScopeService($sql);
    }

    public function canExport(): bool
    {
        return Funcionarios::checkLogin()
            && Funcionarios::hasPermission('PLANILHA_VIEW')
            && Funcionarios::hasPermission('PLANILHA_EXPORT');
    }

    public function getPainel(int $ano, int $mes, bool $somenteFechados = true, string $regiao = ''): array
    {
        $totalDiasMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
        $regiao = $this->scope->normalizeRegionCode($regiao);
        $rows = $this->buscarRelatorios($ano, $mes, $somenteFechados, $regiao);

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
            'historico' => $this->buscarHistoricoPlanilhas(8, $regiao),
            'meta' => [
                'ano' => $ano,
                'mes' => $mes,
                'somente_fechados' => $somenteFechados,
                'regiao' => $regiao,
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
            'data_geracao' => $payload['data_geracao'] ?? portal_utc_now(),
            'data_upload' => $payload['data_upload'] ?? portal_utc_now(),
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
            $idRows = $this->sql->select('SELECT LAST_INSERT_ID() AS id');
            $relatorioId = isset($idRows[0]['id']) ? (int)$idRows[0]['id'] : null;
            if ($relatorioId) {
                $payload['id'] = $relatorioId;
                $payload['status_upload'] = $map['status_upload'] ?? ($payload['status_upload'] ?? 'SUCESSO');
                Notification::addReportNotification($payload);
            }
        } catch (Throwable $e) {
            $this->logErro('Falha ao registrar histórico/notificação da planilha: ' . $e->getMessage());
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
                    $where = ['id = :id'];
                    $params = [':id' => $id];
                    $where = $this->historicoArquivoWhere($where, $params);

                    $rows = $this->sql->select(
                        'SELECT ' . implode(', ', $select) . ' FROM tb_relatorios_pdf WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
                        $params
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

        if ($file && $this->scope->canViewAll()) {
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

    protected function buscarRelatorios(int $ano, int $mes, bool $somenteFechados = true, string $regiao = ''): array
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

        [$where, $params] = $this->scope->appendConstraint($where, $params, $this->scope->dataConstraint('tb_relatorios', 'tb_relatorios', 'planilha_relatorios'));
        [$where, $params] = $this->scope->appendConstraint($where, $params, $this->scope->regionConstraint('tb_relatorios', 'tb_relatorios', 'planilha_relatorios_regiao', $regiao));

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM tb_relatorios WHERE ' . implode(' AND ', $where) . ' ORDER BY data ASC, nome_banco ASC';
        return $this->sql->select($sql, $params);
    }

    protected function buscarHistoricoPlanilhas(int $limit = 8, string $regiao = ''): array
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
                    $where = ['nome_arquivo LIKE :xlsx'];
                    $params = [':xlsx' => '%.xlsx'];
                    [$where, $params] = $this->scope->appendConstraint($where, $params, $this->scope->dataConstraint('tb_relatorios_pdf', 'tb_relatorios_pdf', 'planilha_historico'));
                    [$where, $params] = $this->scope->appendConstraint($where, $params, $this->scope->regionConstraint('tb_relatorios_pdf', 'tb_relatorios_pdf', 'planilha_historico_regiao', $regiao));
                    $sql = 'SELECT ' . implode(', ', $select) . "\n"
                        . "FROM tb_relatorios_pdf\n"
                        . "WHERE " . implode(' AND ', $where) . "\n"
                        . "ORDER BY " . (in_array('id', $columns, true) ? 'id' : 'nome_arquivo') . " DESC\n"
                        . 'LIMIT ' . (int)$limit;
                    $items = $this->sql->select($sql, $params);
                } catch (Throwable $e) {
                    $items = [];
                }
            }
        }

        if (!empty($items)) {
            foreach ($items as &$item) {
                $item['download_url'] = '/admin/api/relatorio/planilha/arquivo?id=' . (int)($item['id'] ?? 0);
                $item['exists_local'] = (bool)$this->resolverCaminhoArquivo($item['caminho_remoto'] ?? $item['nome_arquivo'] ?? '');
                if (!empty($item['data_geracao'])) {
                    $item['data_geracao'] = portal_format_manaus($item['data_geracao']);
                }
                if (!empty($item['data_upload'])) {
                    $item['data_upload'] = portal_format_manaus($item['data_upload']);
                }
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

    private function historicoArquivoWhere(array $where, array &$params): array
    {
        [$where, $params] = $this->scope->appendConstraint(
            $where,
            $params,
            $this->scope->dataConstraint('tb_relatorios_pdf', 'tb_relatorios_pdf', 'planilha_arquivo')
        );

        return $where;
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

    private function logErro(string $message): void
    {
        $dir = defined('LOG_DIR') ? LOG_DIR : (ROOT_DIR . '/storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'relatorio-planilha.log', '[' . portal_log_timestamp() . '] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
