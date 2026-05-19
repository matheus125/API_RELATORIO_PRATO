<?php

use Hcode\DB\Sql;

class IntelligentAnalysisService
{
    /** @var Sql */
    private $sql;

    private $tableCache = [];
    private $columnCache = [];

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public function getResumo(array $filters = []): array
    {
        $empty = [
            'total_atendimentos' => 0,
            'total_refeicoes_servidas' => 0,
            'total_refeicoes_ofertadas' => 0,
            'total_sobras' => 0,
            'total_senhas_pessoas_atendidas' => 0,
            'total_pdfs' => 0,
            'ultimo_relatorio' => null,
            'ultimo_backup' => null,
            'unidades_ativas' => 0,
        ];

        if (!$this->tableExists('tb_relatorios')) {
            return $empty;
        }

        $dateCol = $this->firstColumn('tb_relatorios', ['data', 'data_relatorio', 'created_at']);
        $where = $this->buildWhere('r', 'tb_relatorios', $filters, $dateCol);
        $metric = $this->metricExpressions('r');

        $rows = $this->sql->select("
            SELECT
                {$metric['atendimentos']} AS total_atendimentos,
                {$metric['servidas']} AS total_refeicoes_servidas,
                {$metric['ofertadas']} AS total_refeicoes_ofertadas,
                {$metric['sobras']} AS total_sobras,
                {$metric['atendimentos']} AS total_senhas_pessoas_atendidas
            FROM tb_relatorios r
            {$where['sql']}
        ", $where['params']);

        $resumo = array_merge($empty, $this->numericRow($rows[0] ?? []));
        $resumo['ultimo_relatorio'] = $this->latestDate('tb_relatorios', ['data_recebimento', 'created_at', 'data']);
        $resumo['ultimo_backup'] = $this->latestBackupDate();
        $resumo['total_pdfs'] = $this->countPdfs($filters);
        $resumo['unidades_ativas'] = $this->countActiveUnits();

        return $resumo;
    }

    public function getComparativo(string $tipo = 'mensal', array $filters = []): array
    {
        $periods = $this->resolvePeriods($tipo, $filters);
        $current = $this->periodStats($periods['atual']['inicio'], $periods['atual']['fim'], $filters);
        $previous = $this->periodStats($periods['anterior']['inicio'], $periods['anterior']['fim'], $filters);

        $atendimentosVariation = $this->variationPercent($current['total_atendimentos'], $previous['total_atendimentos']);
        $refeicoesVariation = $this->variationPercent($current['total_refeicoes'], $previous['total_refeicoes']);

        return [
            'periodo_atual' => $current,
            'periodo_anterior' => $previous,
            'variacao_atendimentos_percentual' => $atendimentosVariation,
            'variacao_refeicoes_percentual' => $refeicoesVariation,
            'diferenca_atendimentos' => $current['total_atendimentos'] - $previous['total_atendimentos'],
            'diferenca_refeicoes' => $current['total_refeicoes'] - $previous['total_refeicoes'],
            'texto' => $this->comparisonText('Atendimentos', $atendimentosVariation, $tipo),
            'texto_refeicoes' => $this->comparisonText('Refeicoes servidas', $refeicoesVariation, $tipo),
        ];
    }

    public function getRankingUnidades(array $filters = []): array
    {
        if (!$this->tableExists('tb_relatorios')) {
            return [];
        }

        $dateCol = $this->firstColumn('tb_relatorios', ['data', 'data_relatorio', 'created_at']);
        $where = $this->buildWhere('r', 'tb_relatorios', $filters, $dateCol);
        $metric = $this->metricExpressions('r');
        $unitExpr = $this->unitExpression('r');

        $rows = $this->sql->select("
            SELECT
                {$unitExpr} AS unidade,
                {$metric['atendimentos']} AS total_atendimentos,
                {$metric['servidas']} AS total_refeicoes,
                {$metric['sobras']} AS sobra_refeicoes,
                {$metric['ofertadas']} AS refeicoes_ofertadas,
                COUNT(*) AS total_relatorios
            FROM tb_relatorios r
            {$where['sql']}
            GROUP BY {$unitExpr}
            ORDER BY total_atendimentos DESC, total_refeicoes DESC
            LIMIT 30
        ", $where['params']);

        foreach ($rows as &$row) {
            $row = $this->numericRow($row);
            $row['unidade'] = $row['unidade'] ?: 'Unidade nao identificada';
            $row['aproveitamento_percentual'] = $this->percent($row['total_refeicoes'], $row['refeicoes_ofertadas']);
        }

        return $rows;
    }

    public function getAlertas(array $filters = []): array
    {
        $alerts = [];
        $comparison = $this->getComparativo($filters['periodo'] ?? 'mensal', $filters);
        $current = $comparison['periodo_atual'];
        $variation = $comparison['variacao_atendimentos_percentual'];

        if ($variation <= -20) {
            $alerts[] = $this->alert('queda_atendimento', 'atencao', 'Queda de atendimentos', 'Os atendimentos cairam ' . abs($variation) . '% em relacao ao periodo anterior.', abs($variation));
        } elseif ($variation >= 20) {
            $alerts[] = $this->alert('aumento_atendimento', 'positivo', 'Aumento de atendimentos', 'Os atendimentos aumentaram ' . $variation . '% em relacao ao periodo anterior.', $variation);
        }

        if ($current['percentual_sobra'] > 15) {
            $alerts[] = $this->alert('sobra_alta', 'critico', 'Sobra de refeicoes acima do esperado', 'A sobra de refeicoes ficou em ' . $current['percentual_sobra'] . '%, acima do limite de 15%.', $current['percentual_sobra']);
        }

        if ($current['dias_sem_atendimento'] > 0) {
            $alerts[] = $this->alert('dias_sem_atendimento', 'atencao', 'Dias sem atendimento registrado', $current['dias_sem_atendimento'] . ' dia(s) do periodo nao possuem atendimento registrado.', $current['dias_sem_atendimento']);
        }

        if ($current['pico_incomum'] && $current['maior_dia']) {
            $alerts[] = $this->alert('pico_incomum', 'informativo', 'Pico incomum de atendimento', 'Foi identificado pico de atendimento em ' . $this->formatDate($current['maior_dia']['data']) . ', acima da media do periodo.', $current['maior_dia']['total_atendimentos']);
        }

        $pdfAlert = $this->pdfAlert($filters);
        if ($pdfAlert) {
            $alerts[] = $pdfAlert;
        }

        if (!$alerts) {
            $alerts[] = $this->alert('sem_alertas', 'positivo', 'Indicadores estaveis', 'Nenhum alerta critico foi identificado para o periodo selecionado.', 0);
        }

        return $alerts;
    }

    public function getAnaliseInteligente(array $filters = []): array
    {
        $period = $filters['periodo'] ?? 'mensal';
        $comparison = $this->getComparativo($period, $filters);
        $summary = $this->getResumo($filters);
        $ranking = $this->getRankingUnidades($filters);
        $alerts = $this->getAlertas($filters);
        $current = $comparison['periodo_atual'];

        return [
            'resumo' => $summary,
            'comparativo' => $comparison,
            'alertas' => $alerts,
            'ranking_unidades' => $ranking,
            'cards' => [
                $this->insightCard('Tendencia de atendimentos', $current['total_atendimentos'], $comparison['variacao_atendimentos_percentual'], $this->trendStatus($comparison['variacao_atendimentos_percentual']), $this->comparisonText('Os atendimentos', $comparison['variacao_atendimentos_percentual'], $period)),
                $this->insightCard('Tendencia de refeicoes', $current['total_refeicoes'], $comparison['variacao_refeicoes_percentual'], $this->trendStatus($comparison['variacao_refeicoes_percentual']), $this->comparisonText('As refeicoes servidas', $comparison['variacao_refeicoes_percentual'], $period)),
                $this->insightCard('Aproveitamento das refeicoes', $current['aproveitamento_percentual'] . '%', $current['percentual_sobra'], $current['percentual_sobra'] > 15 ? 'critico' : 'positivo', $this->mealUsageText($current)),
                $this->insightCard('Unidades em destaque', count($ranking), 0, count($ranking) ? 'informativo' : 'atencao', count($ranking) ? 'Ranking calculado com base nos relatorios recebidos no periodo.' : 'Ainda nao ha unidades com relatorio no periodo selecionado.'),
            ],
            'series' => $this->dailySeries($current['inicio'], $current['fim'], $filters),
            'texto_geral' => $this->explainIndicators($comparison, $alerts),
        ];
    }

    public function getUnidades(): array
    {
        if (!$this->tableExists('tb_relatorios')) {
            return [];
        }

        $nameCol = $this->firstColumn('tb_relatorios', ['nome_banco', 'origem_banco']);
        if (!$nameCol) {
            return [];
        }

        return $this->sql->select("
            SELECT {$this->quote($nameCol)} AS id, {$this->quote($nameCol)} AS nome
            FROM tb_relatorios
            WHERE {$this->quote($nameCol)} IS NOT NULL AND {$this->quote($nameCol)} <> ''
            GROUP BY {$this->quote($nameCol)}
            ORDER BY {$this->quote($nameCol)}
        ");
    }

    private function periodStats(string $start, string $end, array $filters): array
    {
        $series = $this->dailySeries($start, $end, $filters);
        $days = max(1, $this->daysBetween($start, $end));
        $totals = [
            'inicio' => $start,
            'fim' => $end,
            'total_atendimentos' => 0,
            'total_refeicoes' => 0,
            'total_refeicoes_ofertadas' => 0,
            'total_sobras' => 0,
            'media_diaria' => 0,
            'maior_dia' => null,
            'menor_dia' => null,
            'dias_sem_atendimento' => 0,
            'dias_baixa_movimentacao' => 0,
            'aproveitamento_percentual' => 0,
            'percentual_sobra' => 0,
            'diferenca_ofertadas_servidas' => 0,
            'pico_incomum' => false,
        ];

        foreach ($series as $day) {
            $totals['total_atendimentos'] += $day['total_atendimentos'];
            $totals['total_refeicoes'] += $day['qtd_refeicoes_servidas'];
            $totals['total_refeicoes_ofertadas'] += $day['refeicoes_ofertadas'];
            $totals['total_sobras'] += $day['sobra_refeicoes'];

            if ($totals['maior_dia'] === null || $day['total_atendimentos'] > $totals['maior_dia']['total_atendimentos']) {
                $totals['maior_dia'] = ['data' => $day['data'], 'total_atendimentos' => $day['total_atendimentos']];
            }
            if ($totals['menor_dia'] === null || $day['total_atendimentos'] < $totals['menor_dia']['total_atendimentos']) {
                $totals['menor_dia'] = ['data' => $day['data'], 'total_atendimentos' => $day['total_atendimentos']];
            }
            if ($day['total_atendimentos'] <= 0) {
                $totals['dias_sem_atendimento']++;
            }
        }

        $totals['media_diaria'] = round($totals['total_atendimentos'] / $days, 2);
        $totals['aproveitamento_percentual'] = $this->percent($totals['total_refeicoes'], $totals['total_refeicoes_ofertadas']);
        $totals['percentual_sobra'] = $this->percent($totals['total_sobras'], $totals['total_refeicoes_ofertadas']);
        $totals['diferenca_ofertadas_servidas'] = $totals['total_refeicoes_ofertadas'] - $totals['total_refeicoes'];

        foreach ($series as $day) {
            if ($totals['media_diaria'] > 0 && $day['total_atendimentos'] > 0 && $day['total_atendimentos'] < ($totals['media_diaria'] * 0.5)) {
                $totals['dias_baixa_movimentacao']++;
            }
        }

        if ($totals['maior_dia'] && $totals['media_diaria'] > 0) {
            $totals['pico_incomum'] = $totals['maior_dia']['total_atendimentos'] >= ($totals['media_diaria'] * 1.6);
        }

        return $totals;
    }

    private function dailySeries(string $start, string $end, array $filters): array
    {
        $calendar = [];
        foreach ($this->dateRange($start, $end) as $date) {
            $calendar[$date] = [
                'data' => $date,
                'total_atendimentos' => 0,
                'qtd_refeicoes_servidas' => 0,
                'refeicoes_ofertadas' => 0,
                'sobra_refeicoes' => 0,
            ];
        }

        if (!$this->tableExists('tb_relatorios')) {
            return array_values($calendar);
        }

        $dateCol = $this->firstColumn('tb_relatorios', ['data', 'data_relatorio', 'created_at']);
        if (!$dateCol) {
            return array_values($calendar);
        }

        $localFilters = array_merge($filters, ['inicio' => $start, 'fim' => $end]);
        $where = $this->buildWhere('r', 'tb_relatorios', $localFilters, $dateCol);
        $metric = $this->metricExpressions('r');

        $rows = $this->sql->select("
            SELECT
                DATE(r.{$this->quote($dateCol)}) AS data,
                {$metric['atendimentos']} AS total_atendimentos,
                {$metric['servidas']} AS qtd_refeicoes_servidas,
                {$metric['ofertadas']} AS refeicoes_ofertadas,
                {$metric['sobras']} AS sobra_refeicoes
            FROM tb_relatorios r
            {$where['sql']}
            GROUP BY DATE(r.{$this->quote($dateCol)})
            ORDER BY data
        ", $where['params']);

        foreach ($rows as $row) {
            $date = $row['data'];
            if (isset($calendar[$date])) {
                $calendar[$date] = $this->numericRow(array_merge($calendar[$date], $row));
            }
        }

        return array_values($calendar);
    }

    private function resolvePeriods(string $tipo, array $filters): array
    {
        if (!empty($filters['inicio']) && !empty($filters['fim'])) {
            $start = new DateTime($filters['inicio']);
            $end = new DateTime($filters['fim']);
            $days = $start->diff($end)->days + 1;
            $prevEnd = (clone $start)->modify('-1 day');
            $prevStart = (clone $prevEnd)->modify('-' . ($days - 1) . ' days');
            return $this->periodArray($start, $end, $prevStart, $prevEnd);
        }

        $today = new DateTime('today');
        if ($tipo === 'semanal' || $tipo === 'semana') {
            $start = (clone $today)->modify('monday this week');
            $end = (clone $start)->modify('+6 days');
            return $this->periodArray($start, $end, (clone $start)->modify('-7 days'), (clone $end)->modify('-7 days'));
        }

        if ($tipo === 'anual' || $tipo === 'ano') {
            $start = new DateTime($today->format('Y') . '-01-01');
            $end = new DateTime($today->format('Y') . '-12-31');
            return $this->periodArray($start, $end, (clone $start)->modify('-1 year'), (clone $end)->modify('-1 year'));
        }

        $start = new DateTime($today->format('Y-m-01'));
        $end = new DateTime($today->format('Y-m-t'));
        return $this->periodArray($start, $end, (clone $start)->modify('first day of previous month'), (clone $start)->modify('last day of previous month'));
    }

    private function periodArray(DateTime $start, DateTime $end, DateTime $prevStart, DateTime $prevEnd): array
    {
        return [
            'atual' => ['inicio' => $start->format('Y-m-d'), 'fim' => $end->format('Y-m-d')],
            'anterior' => ['inicio' => $prevStart->format('Y-m-d'), 'fim' => $prevEnd->format('Y-m-d')],
        ];
    }

    private function metricExpressions(string $alias): array
    {
        return [
            'atendimentos' => $this->sumExpr($alias, ['Total_pessoas_atendidas', 'total_pessoas_atendidas', 'total_atendimentos']),
            'servidas' => $this->sumExpr($alias, ['qtd_refeicoes_servidas', 'refeicoes_servidas']),
            'ofertadas' => $this->sumExpr($alias, ['refeicoes_ofertadas', 'qtd_refeicoes_ofertadas']),
            'sobras' => $this->sumExpr($alias, ['sobra_refeicoes', 'sobras_refeicoes']),
        ];
    }

    private function sumExpr(string $alias, array $candidates): string
    {
        $column = $this->firstColumn('tb_relatorios', $candidates);
        return $column ? "SUM(COALESCE({$alias}.{$this->quote($column)}, 0))" : '0';
    }

    private function buildWhere(string $alias, string $table, array $filters, ?string $dateCol): array
    {
        $clauses = [];
        $params = [];

        if ($dateCol) {
            if (!empty($filters['inicio'])) {
                $clauses[] = "DATE({$alias}.{$this->quote($dateCol)}) >= :inicio";
                $params[':inicio'] = $filters['inicio'];
            }
            if (!empty($filters['fim'])) {
                $clauses[] = "DATE({$alias}.{$this->quote($dateCol)}) <= :fim";
                $params[':fim'] = $filters['fim'];
            }
        }

        if (!empty($filters['unidade_id'])) {
            $unitCol = $this->firstColumn($table, ['nome_banco', 'origem_banco']);
            if ($unitCol) {
                $clauses[] = "{$alias}.{$this->quote($unitCol)} = :unidade";
                $params[':unidade'] = $filters['unidade_id'];
            }
        }

        return [
            'sql' => $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '',
            'params' => $params,
        ];
    }

    private function unitExpression(string $alias): string
    {
        $column = $this->firstColumn('tb_relatorios', ['nome_banco', 'origem_banco']);
        return $column ? "COALESCE({$alias}.{$this->quote($column)}, 'Unidade nao identificada')" : "'Unidade nao identificada'";
    }

    private function countPdfs(array $filters): int
    {
        if (!$this->tableExists('tb_relatorios_pdf')) {
            return 0;
        }

        $dateCol = $this->firstColumn('tb_relatorios_pdf', ['data_upload', 'data_geracao', 'data_relatorio', 'created_at']);
        $where = $this->buildWhere('p', 'tb_relatorios_pdf', $filters, $dateCol);
        $rows = $this->sql->select("SELECT COUNT(*) AS total FROM tb_relatorios_pdf p {$where['sql']}", $where['params']);
        return (int)($rows[0]['total'] ?? 0);
    }

    private function pdfAlert(array $filters): ?array
    {
        if (!$this->tableExists('tb_relatorios_pdf')) {
            return null;
        }

        $dateCol = $this->firstColumn('tb_relatorios_pdf', ['data_upload', 'data_geracao', 'data_relatorio', 'created_at']);
        if (!$dateCol) {
            return null;
        }

        $today = (new DateTime('today'))->format('Y-m-d');
        $where = $this->buildWhere('p', 'tb_relatorios_pdf', array_merge($filters, ['inicio' => $today, 'fim' => $today]), $dateCol);
        $rows = $this->sql->select("SELECT COUNT(*) AS total FROM tb_relatorios_pdf p {$where['sql']}", $where['params']);
        if ((int)($rows[0]['total'] ?? 0) === 0) {
            return $this->alert('dia_sem_pdf', 'atencao', 'Dia sem PDF recebido', 'Nenhum PDF foi recebido hoje no portal.', 0);
        }

        return null;
    }

    private function latestDate(string $table, array $columns): ?string
    {
        if (!$this->tableExists($table)) {
            return null;
        }

        $column = $this->firstColumn($table, $columns);
        if (!$column) {
            return null;
        }

        $rows = $this->sql->select("SELECT MAX({$this->quote($column)}) AS latest FROM {$table}");
        return $rows[0]['latest'] ?? null;
    }

    private function latestBackupDate(): ?string
    {
        foreach (['tb_backups', 'tb_backup'] as $table) {
            if ($this->tableExists($table)) {
                return $this->latestDate($table, ['data_backup', 'data_upload', 'created_at']);
            }
        }

        return null;
    }

    private function countActiveUnits(): int
    {
        if (!$this->tableExists('tb_relatorios')) {
            return 0;
        }

        $column = $this->firstColumn('tb_relatorios', ['nome_banco', 'origem_banco']);
        if (!$column) {
            return 0;
        }

        $rows = $this->sql->select("SELECT COUNT(DISTINCT {$this->quote($column)}) AS total FROM tb_relatorios WHERE {$this->quote($column)} IS NOT NULL AND {$this->quote($column)} <> ''");
        return (int)($rows[0]['total'] ?? 0);
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $rows = $this->sql->select('SHOW TABLES LIKE :table', [':table' => $table]);
            $this->tableCache[$table] = count($rows) > 0;
        } catch (Throwable $e) {
            $this->tableCache[$table] = false;
        }

        return $this->tableCache[$table];
    }

    private function firstColumn(string $table, array $candidates): ?string
    {
        if (!$this->tableExists($table)) {
            return null;
        }

        if (!isset($this->columnCache[$table])) {
            try {
                $rows = $this->sql->select('SHOW COLUMNS FROM ' . $table);
            } catch (Throwable $e) {
                $rows = [];
            }

            $this->columnCache[$table] = [];
            foreach ($rows as $row) {
                $field = $row['Field'] ?? '';
                if ($field !== '') {
                    $this->columnCache[$table][strtolower($field)] = $field;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $key = strtolower($candidate);
            if (isset($this->columnCache[$table][$key])) {
                return $this->columnCache[$table][$key];
            }
        }

        return null;
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function numericRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_numeric($value)) {
                $row[$key] = strpos((string)$value, '.') !== false ? (float)$value : (int)$value;
            }
        }

        return $row;
    }

    private function percent($value, $base): float
    {
        $base = (float)$base;
        if ($base <= 0) {
            return 0;
        }

        return round(((float)$value / $base) * 100, 2);
    }

    private function variationPercent($current, $previous): float
    {
        $previous = (float)$previous;
        $current = (float)$current;
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function daysBetween(string $start, string $end): int
    {
        return (new DateTime($start))->diff(new DateTime($end))->days + 1;
    }

    private function dateRange(string $start, string $end): array
    {
        $period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }
        return $dates;
    }

    private function comparisonText(string $label, float $variation, string $tipo): string
    {
        $periodText = $this->periodText($tipo);
        if ($variation > 0) {
            return $label . ' aumentaram ' . abs($variation) . '% em relacao ao ' . $periodText . '.';
        }
        if ($variation < 0) {
            return $label . ' diminuiram ' . abs($variation) . '% em relacao ao ' . $periodText . '.';
        }
        return $label . ' ficaram estaveis em relacao ao ' . $periodText . '.';
    }

    private function periodText(string $tipo): string
    {
        if ($tipo === 'semanal' || $tipo === 'semana') {
            return 'periodo semanal anterior';
        }
        if ($tipo === 'anual' || $tipo === 'ano') {
            return 'ano anterior';
        }
        return 'mes anterior';
    }

    private function mealUsageText(array $current): string
    {
        if ($current['percentual_sobra'] > 15) {
            return 'A sobra de refeicoes ficou acima do esperado, indicando possivel necessidade de ajuste na oferta.';
        }
        return 'O aproveitamento das refeicoes esta dentro de uma faixa operacional saudavel para o periodo.';
    }

    private function explainIndicators(array $comparison, array $alerts): string
    {
        $current = $comparison['periodo_atual'];
        if ($current['pico_incomum'] && $current['maior_dia']) {
            return 'Foi identificado pico de atendimento no dia ' . $this->formatDate($current['maior_dia']['data']) . ', acima da media do periodo. Isso pode indicar evento especifico, mutirao, falha de registro ou aumento pontual de demanda.';
        }

        foreach ($alerts as $alert) {
            if (($alert['nivel'] ?? '') === 'critico') {
                return $alert['mensagem'];
            }
        }

        return $comparison['texto'] . ' ' . $this->mealUsageText($current);
    }

    private function insightCard(string $title, $number, float $variation, string $status, string $text): array
    {
        return [
            'titulo' => $title,
            'numero_principal' => $number,
            'percentual_variacao' => $variation,
            'status' => $status,
            'texto' => $text,
        ];
    }

    private function trendStatus(float $variation): string
    {
        if ($variation >= 20) {
            return 'positivo';
        }
        if ($variation <= -20) {
            return 'atencao';
        }
        return 'informativo';
    }

    private function alert(string $type, string $level, string $title, string $message, $value): array
    {
        return [
            'tipo' => $type,
            'nivel' => $level,
            'titulo' => $title,
            'mensagem' => $message,
            'valor' => $value,
        ];
    }

    private function formatDate(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        return (new DateTime($date))->format('d/m/Y');
    }
}
