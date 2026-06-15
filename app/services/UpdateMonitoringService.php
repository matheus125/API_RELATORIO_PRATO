<?php

use Hcode\DB\Sql;

class UpdateMonitoringService
{
    private $sql;

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public function ensureSchema(): void
    {
        $required = [
            'tb_atualizacoes',
            'tb_atualizacoes_unidades',
            'tb_logs_atualizacoes',
            'tb_status_atualizacao',
        ];

        foreach ($required as $table) {
            $rows = $this->sql->select("SHOW TABLES LIKE :table", [':table' => $table]);
            if (empty($rows)) {
                throw new Exception('Tabela central de monitoramento ausente: ' . $table);
            }
        }
    }

    public function receiveStatus(array $payload, array $server = []): array
    {
        $this->ensureSchema();

        $statusRaw = strtolower(trim((string)($payload['status'] ?? '')));
        if (!$this->isAllowedStatus($statusRaw)) {
            throw new InvalidArgumentException('Status de atualizacao invalido: ' . $statusRaw);
        }
        $status = $this->normalizeStatus($statusRaw);
        $unidadeId = $this->cleanText($payload['unidade_id'] ?? '', 120);
        if ($unidadeId === '') {
            $unidadeId = $this->inferUnitId($payload, $server);
        }

        $nomeUnidade = $this->cleanText($payload['nome_unidade'] ?? '', 180);
        $sistema = $this->cleanText($payload['sistema'] ?? 'prato_web', 80) ?: 'prato_web';
        $versaoAtual = $this->cleanText($payload['versao_atual'] ?? '', 50);
        $versaoDisponivel = $this->cleanText($payload['versao_disponivel'] ?? '', 50);
        $etapa = $this->cleanText($payload['etapa'] ?? '', 80);
        $mensagem = $this->cleanText($payload['mensagem'] ?? '', 5000);
        $stackTrace = $this->cleanText($payload['stack_trace'] ?? '', 20000);
        $arquivosAusentes = $this->normalizeMissingFiles($payload['arquivos_ausentes'] ?? []);
        $dataHoraLocal = $this->normalizeDateTime($payload['data_hora_local'] ?? '');
        $tempoInstalacao = isset($payload['tempo_instalacao_segundos']) ? (int)$payload['tempo_instalacao_segundos'] : null;
        $ip = substr((string)($server['REMOTE_ADDR'] ?? ''), 0, 64);
        $ua = substr((string)($server['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $payloadJson = $this->encodePayload($payload);

        $this->registerUpdateVersion($versaoDisponivel, $payload);

        $this->sql->query("
            INSERT INTO tb_logs_atualizacoes (
                unidade_id, nome_unidade, sistema, versao_atual, versao_disponivel, status, etapa,
                mensagem, stack_trace, arquivos_ausentes, data_hora_local, ip_origem, user_agent,
                tempo_instalacao_segundos, payload_json, created_at
            ) VALUES (
                :unidade_id, :nome_unidade, :sistema, :versao_atual, :versao_disponivel, :status, :etapa,
                :mensagem, :stack_trace, :arquivos_ausentes, :data_hora_local, :ip_origem, :user_agent,
                :tempo_instalacao_segundos, :payload_json, NOW()
            )
        ", [
            ':unidade_id' => $unidadeId,
            ':nome_unidade' => $nomeUnidade,
            ':sistema' => $sistema,
            ':versao_atual' => $versaoAtual,
            ':versao_disponivel' => $versaoDisponivel,
            ':status' => $status,
            ':etapa' => $etapa,
            ':mensagem' => $mensagem,
            ':stack_trace' => $stackTrace,
            ':arquivos_ausentes' => json_encode($arquivosAusentes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':data_hora_local' => $dataHoraLocal,
            ':ip_origem' => $ip,
            ':user_agent' => $ua,
            ':tempo_instalacao_segundos' => $tempoInstalacao,
            ':payload_json' => $payloadJson,
        ]);

        $installedVersion = $status === 'instalado' && $versaoDisponivel !== '' ? $versaoDisponivel : $versaoAtual;
        $ultimoDownload = in_array($status, ['download_concluido'], true) ? 'NOW()' : 'ultimo_download';
        $ultimaInstalacao = $status === 'instalado' ? 'NOW()' : 'ultima_instalacao';

        $this->sql->query("
            INSERT INTO tb_atualizacoes_unidades (
                unidade_id, nome_unidade, municipio, tipo_unidade, sistema, versao_instalada,
                versao_disponivel, status_atual, status_label, etapa, mensagem, arquivos_ausentes,
                stack_trace,
                ultima_comunicacao, ultimo_download, ultima_instalacao, data_hora_local,
                ip_origem, user_agent, tempo_instalacao_segundos, payload_json, created_at
            ) VALUES (
                :unidade_id, :nome_unidade, :municipio, :tipo_unidade, :sistema, :versao_instalada,
                :versao_disponivel, :status_atual, :status_label, :etapa, :mensagem, :arquivos_ausentes,
                :stack_trace,
                NOW(), " . ($ultimoDownload === 'NOW()' ? 'NOW()' : 'NULL') . ", " . ($ultimaInstalacao === 'NOW()' ? 'NOW()' : 'NULL') . ", :data_hora_local,
                :ip_origem, :user_agent, :tempo_instalacao_segundos, :payload_json, NOW()
            )
            ON DUPLICATE KEY UPDATE
                nome_unidade = COALESCE(NULLIF(VALUES(nome_unidade), ''), nome_unidade),
                municipio = COALESCE(NULLIF(VALUES(municipio), ''), municipio),
                tipo_unidade = COALESCE(NULLIF(VALUES(tipo_unidade), ''), tipo_unidade),
                versao_instalada = COALESCE(NULLIF(VALUES(versao_instalada), ''), versao_instalada),
                versao_disponivel = COALESCE(NULLIF(VALUES(versao_disponivel), ''), versao_disponivel),
                status_atual = VALUES(status_atual),
                status_label = VALUES(status_label),
                etapa = VALUES(etapa),
                mensagem = VALUES(mensagem),
                stack_trace = VALUES(stack_trace),
                arquivos_ausentes = VALUES(arquivos_ausentes),
                ultima_comunicacao = NOW(),
                ultimo_download = IF(:is_download = 1, NOW(), ultimo_download),
                ultima_instalacao = IF(:is_installed = 1, NOW(), ultima_instalacao),
                data_hora_local = VALUES(data_hora_local),
                ip_origem = VALUES(ip_origem),
                user_agent = VALUES(user_agent),
                tempo_instalacao_segundos = VALUES(tempo_instalacao_segundos),
                payload_json = VALUES(payload_json)
        ", [
            ':unidade_id' => $unidadeId,
            ':nome_unidade' => $nomeUnidade,
            ':municipio' => $this->cleanText($payload['municipio'] ?? '', 120),
            ':tipo_unidade' => $this->cleanText($payload['tipo_unidade'] ?? '', 80),
            ':sistema' => $sistema,
            ':versao_instalada' => $installedVersion,
            ':versao_disponivel' => $versaoDisponivel,
            ':status_atual' => $status,
            ':status_label' => $this->statusLabel($status),
            ':etapa' => $etapa,
            ':mensagem' => $mensagem,
            ':stack_trace' => $stackTrace,
            ':arquivos_ausentes' => json_encode($arquivosAusentes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':data_hora_local' => $dataHoraLocal,
            ':ip_origem' => $ip,
            ':user_agent' => $ua,
            ':tempo_instalacao_segundos' => $tempoInstalacao,
            ':payload_json' => $payloadJson,
            ':is_download' => $status === 'download_concluido' ? 1 : 0,
            ':is_installed' => $status === 'instalado' ? 1 : 0,
        ]);

        return [
            'success' => true,
            'message' => 'Status registrado.',
            'unidade_id' => $unidadeId,
            'status' => $status,
        ];
    }

    public function dashboard(): array
    {
        $this->ensureSchema();
        $threshold = $this->offlineThresholdMinutes();
        $latest = $this->latestPublishedVersion();
        $items = $this->units([]);

        $summary = [
            'total' => count($items),
            'consultaram' => 0,
            'baixaram' => 0,
            'instalaram' => 0,
            'erro' => 0,
            'offline' => 0,
            'versoes_antigas' => 0,
            'taxa_sucesso' => 0,
            'ultima_publicada' => $latest['versao'] ?? '-',
        ];

        foreach ($items as $item) {
            if (!empty($item['consultou'])) $summary['consultaram']++;
            if (!empty($item['baixou'])) $summary['baixaram']++;
            if (!empty($item['instalou'])) $summary['instalaram']++;
            if (!empty($item['falhou'])) $summary['erro']++;
            if (!empty($item['offline'])) $summary['offline']++;
            if (!empty($item['versao_antiga'])) $summary['versoes_antigas']++;
        }

        $summary['atualizadas'] = $summary['instalaram'];
        $summary['pendentes'] = max(0, $summary['total'] - $summary['instalaram'] - $summary['erro']);
        $summary['atualizando'] = max(0, $summary['baixaram'] - $summary['instalaram']);
        $summary['taxa_sucesso'] = $summary['total'] > 0 ? round(($summary['instalaram'] / $summary['total']) * 100, 1) : 0;

        return [
            'summary' => $summary,
            'latest' => $latest,
            'by_version' => $this->byVersion(),
            'errors' => $this->recentErrors(),
            'threshold_minutes' => $threshold,
        ];
    }

    public function units(array $filters = []): array
    {
        $this->ensureSchema();
        $where = [];
        $params = [];

        if (!empty($filters['unidade'])) {
            $where[] = '(u.nome_unidade LIKE :unidade OR u.unidade_id LIKE :unidade)';
            $params[':unidade'] = '%' . $this->cleanText($filters['unidade'], 120) . '%';
        }
        if (!empty($filters['municipio'])) {
            $where[] = 'u.municipio LIKE :municipio';
            $params[':municipio'] = '%' . $this->cleanText($filters['municipio'], 120) . '%';
        }
        if (!empty($filters['tipo_unidade'])) {
            $where[] = 'u.tipo_unidade = :tipo_unidade';
            $params[':tipo_unidade'] = $this->cleanText($filters['tipo_unidade'], 120);
        }
        if (!empty($filters['sistema'])) {
            $where[] = 'u.sistema = :sistema';
            $params[':sistema'] = $this->cleanText($filters['sistema'], 80);
        }
        if (!empty($filters['versao_instalada'])) {
            $where[] = 'u.versao_instalada = :versao_instalada';
            $params[':versao_instalada'] = $this->cleanText($filters['versao_instalada'], 50);
        }
        if (!empty($filters['data_inicio'])) {
            $where[] = 'u.ultima_comunicacao >= :data_inicio';
            $params[':data_inicio'] = $this->normalizeDateTime($filters['data_inicio']) ?: date('Y-m-d 00:00:00');
        }
        if (!empty($filters['data_fim'])) {
            $where[] = 'u.ultima_comunicacao <= :data_fim';
            $params[':data_fim'] = $this->normalizeDateTime($filters['data_fim']) ?: date('Y-m-d 23:59:59');
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $rows = $this->sql->select("
            SELECT
                u.*,
                lg.status AS ultimo_log_status,
                lg.etapa AS ultimo_log_etapa,
                lg.mensagem AS ultimo_log_mensagem,
                lg.stack_trace AS ultimo_log_stack_trace,
                lg.arquivos_ausentes AS ultimo_log_arquivos_ausentes,
                lg.payload_json AS ultimo_log_payload_json,
                lg.created_at AS ultimo_log_em
            FROM tb_atualizacoes_unidades u
            LEFT JOIN (
                SELECT l.*
                FROM tb_logs_atualizacoes l
                INNER JOIN (
                    SELECT unidade_id, sistema, MAX(id_log_update) AS id_log_update
                    FROM tb_logs_atualizacoes
                    GROUP BY unidade_id, sistema
                ) ult ON ult.id_log_update = l.id_log_update
            ) lg ON lg.unidade_id = u.unidade_id AND lg.sistema = u.sistema
            {$whereSql}
            ORDER BY u.ultima_comunicacao DESC, u.nome_unidade ASC
        ", $params);

        $latest = $this->latestPublishedVersion();
        $databaseNow = $this->databaseNow();
        $items = array_map(function ($row) use ($latest, $databaseNow) {
            return $this->formatUnit($row, $latest, $databaseNow);
        }, $rows);

        return $this->filterCalculatedUnits($items, $filters);
    }

    public function logs(array $filters = []): array
    {
        $this->ensureSchema();
        $where = [];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $this->normalizeStatus($filters['status']);
        }
        if (!empty($filters['unidade'])) {
            $where[] = '(nome_unidade LIKE :unidade OR unidade_id LIKE :unidade)';
            $params[':unidade'] = '%' . $this->cleanText($filters['unidade'], 120) . '%';
        }
        if (!empty($filters['versao'])) {
            $where[] = '(versao_atual = :versao OR versao_disponivel = :versao)';
            $params[':versao'] = $this->cleanText($filters['versao'], 50);
        }
        if (!empty($filters['sistema'])) {
            $where[] = 'sistema = :sistema';
            $params[':sistema'] = $this->cleanText($filters['sistema'], 80);
        }
        if (!empty($filters['data_inicio'])) {
            $where[] = 'created_at >= :data_inicio';
            $params[':data_inicio'] = $this->normalizeDateTime($filters['data_inicio']) ?: date('Y-m-d 00:00:00');
        }
        if (!empty($filters['data_fim'])) {
            $where[] = 'created_at <= :data_fim';
            $params[':data_fim'] = $this->normalizeDateTime($filters['data_fim']) ?: date('Y-m-d 23:59:59');
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->sql->select("
            SELECT *
            FROM tb_logs_atualizacoes
            {$whereSql}
            ORDER BY created_at DESC
            LIMIT 200
        ", $params);
    }

    public function updateConfig($minutes): void
    {
        $this->ensureSchema();
        $minutes = max(5, (int)$minutes);
        $this->sql->query("
            INSERT INTO tb_status_atualizacao (chave, valor, descricao)
            VALUES ('offline_threshold_minutes', :valor, 'Minutos sem comunicacao para considerar unidade offline')
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ", [':valor' => (string)$minutes]);
    }

    public function unitDetail($unidadeId, $sistema = ''): array
    {
        $this->ensureSchema();
        $units = $this->units([
            'unidade' => $this->cleanText($unidadeId, 120),
            'sistema' => $this->cleanText($sistema, 80),
        ]);
        $unit = [];
        foreach ($units as $item) {
            if ((string)$item['unidade_id'] === (string)$unidadeId && ($sistema === '' || (string)$item['sistema'] === (string)$sistema)) {
                $unit = $item;
                break;
            }
        }

        $logs = $this->logs([
            'unidade' => $this->cleanText($unidadeId, 120),
            'sistema' => $this->cleanText($sistema, 80),
        ]);

        return ['unit' => $unit, 'logs' => $logs];
    }

    public function versionDetail($version): array
    {
        $this->ensureSchema();
        $version = $this->cleanText($version, 50);
        $rows = $this->sql->select("
            SELECT *
            FROM tb_atualizacoes
            WHERE versao = :versao
            LIMIT 1
        ", [':versao' => $version]);

        $units = $this->units(['versao_disponivel' => $version]);
        if (empty($units)) {
            $units = $this->units([]);
            $units = array_values(array_filter($units, function ($item) use ($version) {
                return (string)($item['versao_instalada'] ?? '') === $version || (string)($item['versao_disponivel'] ?? '') === $version;
            }));
        }

        $summary = ['total' => count($units), 'instaladas' => 0, 'pendentes' => 0, 'erro' => 0, 'offline' => 0, 'percentual_instalacao' => 0];
        foreach ($units as $item) {
            if (!empty($item['instalou'])) $summary['instaladas']++;
            if (!empty($item['falhou'])) $summary['erro']++;
            if (!empty($item['offline'])) $summary['offline']++;
        }
        $summary['pendentes'] = max(0, $summary['total'] - $summary['instaladas'] - $summary['erro']);
        $summary['percentual_instalacao'] = $summary['total'] > 0 ? round(($summary['instaladas'] / $summary['total']) * 100, 1) : 0;

        return ['version' => $rows[0] ?? ['versao' => $version], 'summary' => $summary, 'units' => $units];
    }

    public function filterOptions(): array
    {
        $this->ensureSchema();
        return [
            'municipios' => $this->distinctColumn('municipio'),
            'tipos' => $this->distinctColumn('tipo_unidade'),
            'sistemas' => $this->distinctColumn('sistema'),
            'versoes_instaladas' => $this->distinctColumn('versao_instalada'),
            'versoes_disponiveis' => $this->distinctAvailableVersions(),
        ];
    }

    private function formatUnit(array $row, array $latest = [], string $databaseNow = ''): array
    {
        $latestVersion = (string)($latest['versao'] ?? '');
        $versaoDisponivel = (string)($row['versao_disponivel'] ?: $latestVersion);
        $ultimoStatus = $this->normalizeStatus($row['ultimo_log_status'] ?? ($row['status_atual'] ?? ''));
        $statusAtual = $this->normalizeStatus($row['status_atual'] ?? '');
        $consultou = !empty($row['ultima_comunicacao']) || in_array($ultimoStatus, ['consultou', 'sem_atualizacao', 'atualizacao_disponivel'], true);
        $baixou = !empty($row['ultimo_download']) || $ultimoStatus === 'download_concluido';
        $instalou = !empty($row['ultima_instalacao']) || $statusAtual === 'instalado' || $this->sameVersion($row['versao_instalada'] ?? '', $latestVersion);
        $falhou = $statusAtual === 'erro' || $ultimoStatus === 'erro';
        $offline = $this->isOffline($row['ultima_comunicacao'] ?? null, $databaseNow);
        $versaoAntiga = $this->isOldVersion($row['versao_instalada'] ?? '', $latestVersion);
        $status = $this->calculatedStatus($row, [
            'consultou' => $consultou,
            'baixou' => $baixou,
            'instalou' => $instalou,
            'falhou' => $falhou,
            'offline' => $offline,
            'versao_antiga' => $versaoAntiga,
            'ultimo_status' => $ultimoStatus,
        ]);

        $missingRaw = $falhou && !empty($row['ultimo_log_arquivos_ausentes']) ? $row['ultimo_log_arquivos_ausentes'] : ($row['arquivos_ausentes'] ?? '[]');
        $missing = json_decode((string)$missingRaw, true);
        if (!is_array($missing)) $missing = [];
        $mensagem = $falhou && !empty($row['ultimo_log_mensagem']) ? $row['ultimo_log_mensagem'] : ($row['mensagem'] ?? '');
        $etapa = $falhou && !empty($row['ultimo_log_etapa']) ? $row['ultimo_log_etapa'] : ($row['etapa'] ?? '');
        $stackTrace = $falhou && !empty($row['ultimo_log_stack_trace']) ? $row['ultimo_log_stack_trace'] : ($row['stack_trace'] ?? '');
        $payloadJson = !empty($row['ultimo_log_payload_json']) ? $row['ultimo_log_payload_json'] : ($row['payload_json'] ?? '');

        return [
            'unidade_id' => $row['unidade_id'] ?? '',
            'nome_unidade' => $row['nome_unidade'] ?: $row['unidade_id'],
            'municipio' => $row['municipio'] ?: '-',
            'tipo_unidade' => $row['tipo_unidade'] ?: '-',
            'sistema' => $row['sistema'] ?: 'prato_web',
            'versao_instalada' => $row['versao_instalada'] ?: '-',
            'versao_disponivel' => $versaoDisponivel ?: '-',
            'status_atual' => $row['status_atual'] ?? '',
            'status_label' => $row['status_label'] ?: $this->statusLabel($statusAtual),
            'status_calculado' => $status,
            'status_class' => $this->statusClass($status),
            'etapa' => $etapa ?: '',
            'ultima_comunicacao' => $row['ultima_comunicacao'] ?: '-',
            'ultimo_download' => $row['ultimo_download'] ?: '-',
            'ultima_instalacao' => $row['ultima_instalacao'] ?: '-',
            'data_hora_local' => $row['data_hora_local'] ?: '-',
            'ip_origem' => $row['ip_origem'] ?: '-',
            'tempo_instalacao_segundos' => $row['tempo_instalacao_segundos'] ?? '',
            'tempo_sem_comunicacao' => $this->timeAgo($row['ultima_comunicacao'] ?? null, $databaseNow),
            'mensagem' => $mensagem ?: '',
            'stack_trace' => $this->redactSensitive($stackTrace ?: ''),
            'arquivos_ausentes' => implode(', ', $missing),
            'payload_json' => $this->redactSensitive($payloadJson ?: ''),
            'ultimo_log_status' => $ultimoStatus,
            'ultimo_log_em' => $row['ultimo_log_em'] ?: '',
            'consultou' => $consultou,
            'baixou' => $baixou,
            'instalou' => $instalou,
            'falhou' => $falhou,
            'offline' => $offline,
            'versao_antiga' => $versaoAntiga,
        ];
    }

    private function calculatedStatus(array $row, array $flags): string
    {
        if (!empty($flags['falhou'])) return 'Falhou';
        if (!empty($flags['offline'])) return 'Offline';
        if (!empty($flags['instalou'])) return 'Instalou';
        if (!empty($flags['baixou'])) return 'Baixou';

        $status = $flags['ultimo_status'] ?? $this->normalizeStatus($row['status_atual'] ?? '');
        if ($status === 'instalacao_iniciada') return 'Instalando';
        if ($status === 'download_iniciado') return 'Baixando';
        if ($status === 'atualizacao_disponivel') return 'Consultou';
        if ($status === 'sem_atualizacao') return 'Consultou';
        if (!empty($flags['consultou'])) return 'Consultou';
        return 'Pendente';
    }

    private function byVersion(): array
    {
        return $this->sql->select("
            SELECT COALESCE(NULLIF(versao_instalada, ''), 'sem versao') AS versao, COUNT(*) AS total
            FROM tb_atualizacoes_unidades
            GROUP BY COALESCE(NULLIF(versao_instalada, ''), 'sem versao')
            ORDER BY total DESC, versao DESC
        ");
    }

    private function recentErrors(): array
    {
        return $this->sql->select("
            SELECT unidade_id, nome_unidade, versao_atual, versao_disponivel, etapa, mensagem, created_at
            FROM tb_logs_atualizacoes
            WHERE status = 'erro'
            ORDER BY created_at DESC
            LIMIT 20
        ");
    }

    private function latestPublishedVersion(): array
    {
        $rows = $this->sql->select("
            SELECT *
            FROM tb_atualizacoes
            ORDER BY publicado_em DESC, created_at DESC, id_atualizacao DESC
            LIMIT 1
        ");
        return $rows[0] ?? [];
    }

    private function registerUpdateVersion(string $version, array $payload): void
    {
        if ($version === '') {
            return;
        }

        $this->sql->query("
            INSERT INTO tb_atualizacoes (versao, arquivo_zip, url_download, changelog, checksum_sha256, publicado_em)
            VALUES (:versao, :arquivo_zip, :url_download, :changelog, :checksum, :publicado_em)
            ON DUPLICATE KEY UPDATE
                url_download = COALESCE(NULLIF(VALUES(url_download), ''), url_download),
                checksum_sha256 = COALESCE(NULLIF(VALUES(checksum_sha256), ''), checksum_sha256)
        ", [
            ':versao' => $version,
            ':arquivo_zip' => $this->cleanText($payload['arquivo_zip'] ?? '', 255),
            ':url_download' => $this->cleanText($payload['url_download'] ?? '', 500),
            ':changelog' => $this->cleanText($payload['changelog'] ?? '', 5000),
            ':checksum' => $this->cleanText($payload['checksum_sha256'] ?? '', 120),
            ':publicado_em' => $this->normalizeDateTime($payload['publicado_em'] ?? '') ?: null,
        ]);
    }

    private function normalizeStatus($status): string
    {
        $status = strtolower(trim((string)$status));
        return $this->isAllowedStatus($status) ? $status : 'consultou';
    }

    private function isAllowedStatus($status): bool
    {
        return in_array((string)$status, [
            'consultou',
            'atualizacao_disponivel',
            'download_iniciado',
            'download_concluido',
            'instalacao_iniciada',
            'instalado',
            'sem_atualizacao',
            'erro'
        ], true);
    }

    private function statusLabel($status): string
    {
        $labels = [
            'consultou' => 'Consultou',
            'atualizacao_disponivel' => 'Atualização disponível',
            'download_iniciado' => 'Download iniciado',
            'download_concluido' => 'Download concluído',
            'instalacao_iniciada' => 'Instalação iniciada',
            'instalado' => 'Instalado',
            'sem_atualizacao' => 'Sem atualização',
            'erro' => 'Erro',
        ];
        return $labels[$status] ?? 'Consultou';
    }

    private function statusClass(string $status): string
    {
        if ($status === 'Instalou') return 'success';
        if ($status === 'Falhou') return 'danger';
        if ($status === 'Offline') return 'secondary';
        if ($status === 'Baixou' || $status === 'Baixando' || $status === 'Instalando') return 'primary';
        return 'warning';
    }

    private function offlineThresholdMinutes(): int
    {
        $rows = $this->sql->select("SELECT valor FROM tb_status_atualizacao WHERE chave = 'offline_threshold_minutes' LIMIT 1");
        return max(5, (int)($rows[0]['valor'] ?? 60));
    }

    private function isOffline($datetime, string $referenceNow = ''): bool
    {
        if (!$datetime) return true;
        $ts = strtotime((string)$datetime);
        $ref = $referenceNow !== '' ? strtotime($referenceNow) : time();
        return $ts <= 0 || $ref <= 0 || ($ref - $ts) > ($this->offlineThresholdMinutes() * 60);
    }

    private function timeAgo($datetime, string $referenceNow = ''): string
    {
        if (!$datetime) return '-';
        $ref = $referenceNow !== '' ? strtotime($referenceNow) : time();
        $delta = max(0, $ref - strtotime((string)$datetime));
        if ($delta < 60) return $delta . 's';
        if ($delta < 3600) return floor($delta / 60) . 'min';
        if ($delta < 86400) return floor($delta / 3600) . 'h';
        return floor($delta / 86400) . 'd';
    }

    private function databaseNow(): string
    {
        $rows = $this->sql->select("SELECT NOW() AS agora");
        return (string)($rows[0]['agora'] ?? date('Y-m-d H:i:s'));
    }

    private function normalizeMissingFiles($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return $this->cleanMissingFiles($decoded);
            return $value !== '' ? $this->cleanMissingFiles([$value]) : [];
        }
        return is_array($value) ? $this->cleanMissingFiles($value) : [];
    }

    private function normalizeDateTime($value)
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $ts = strtotime($value);
        return $ts > 0 ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function inferUnitId(array $payload, array $server = []): string
    {
        $base = $this->cleanText($payload['nome_unidade'] ?? 'unidade', 180);
        return substr(sha1($base . '|' . ($server['REMOTE_ADDR'] ?? '')), 0, 16);
    }

    private function cleanText($value, int $max): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim((string)$value));
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }
        return substr($value, 0, $max);
    }

    private function cleanMissingFiles(array $files): array
    {
        $clean = [];
        foreach (array_slice($files, 0, 100) as $file) {
            $name = str_replace('\\', '/', $this->cleanText($file, 255));
            if ($name !== '') {
                $clean[] = $name;
            }
        }
        return array_values(array_unique($clean));
    }

    private function filterCalculatedUnits(array $items, array $filters): array
    {
        $status = strtolower(trim((string)($filters['status'] ?? '')));
        $versaoDisponivel = trim((string)($filters['versao_disponivel'] ?? ''));

        if ($status !== '') {
            $items = array_values(array_filter($items, function ($item) use ($status) {
                if ($status === 'consultou') return !empty($item['consultou']);
                if ($status === 'baixou') return !empty($item['baixou']);
                if ($status === 'instalou' || $status === 'instalado') return !empty($item['instalou']);
                if ($status === 'erro' || $status === 'falhou') return !empty($item['falhou']);
                if ($status === 'offline') return !empty($item['offline']);
                if ($status === 'versao_antiga') return !empty($item['versao_antiga']);
                return strtolower((string)($item['status_atual'] ?? '')) === $status || strtolower((string)($item['ultimo_log_status'] ?? '')) === $status;
            }));
        }

        if ($versaoDisponivel !== '') {
            $items = array_values(array_filter($items, function ($item) use ($versaoDisponivel) {
                return (string)($item['versao_disponivel'] ?? '') === $versaoDisponivel;
            }));
        }

        return $items;
    }

    private function sameVersion($installed, string $latest): bool
    {
        $installed = trim((string)$installed);
        if ($installed === '' || $installed === '-' || $latest === '') {
            return false;
        }
        return version_compare($installed, $latest, '==');
    }

    private function isOldVersion($installed, string $latest): bool
    {
        $installed = trim((string)$installed);
        if ($latest === '') {
            return false;
        }
        if ($installed === '' || $installed === '-') {
            return true;
        }
        return version_compare($installed, $latest, '!=');
    }

    private function distinctColumn(string $column): array
    {
        $allowed = ['municipio', 'tipo_unidade', 'sistema', 'versao_instalada'];
        if (!in_array($column, $allowed, true)) {
            return [];
        }
        $rows = $this->sql->select("
            SELECT DISTINCT {$column} AS valor
            FROM tb_atualizacoes_unidades
            WHERE {$column} IS NOT NULL AND {$column} <> ''
            ORDER BY {$column} ASC
        ");
        return array_map(function ($row) {
            return $row['valor'];
        }, $rows);
    }

    private function distinctAvailableVersions(): array
    {
        $rows = $this->sql->select("
            SELECT versao AS valor FROM tb_atualizacoes WHERE versao IS NOT NULL AND versao <> ''
            UNION
            SELECT versao_disponivel AS valor FROM tb_atualizacoes_unidades WHERE versao_disponivel IS NOT NULL AND versao_disponivel <> ''
            ORDER BY valor DESC
        ");
        return array_map(function ($row) {
            return $row['valor'];
        }, $rows);
    }

    private function redactSensitive(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $decoded = $this->redactArray($decoded);
            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
            return $encoded ?: '';
        }

        return preg_replace('/(token|senha|password|secret|authorization)(["\'\s:=]+)([^,\s"}]+)/i', '$1$2[REDACTED]', $value);
    }

    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/token|senha|password|secret|authorization/i', (string)$key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redactArray($value);
            }
        }
        return $data;
    }

    private function encodePayload(array $payload): string
    {
        $safe = $payload;
        if (isset($safe['stack_trace'])) {
            $safe['stack_trace'] = $this->cleanText($safe['stack_trace'], 20000);
        }
        if (isset($safe['mensagem'])) {
            $safe['mensagem'] = $this->cleanText($safe['mensagem'], 5000);
        }

        $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = json_encode(['erro_payload' => json_last_error_msg()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $this->cleanText($json ?: '{}', 65000);
    }

}
