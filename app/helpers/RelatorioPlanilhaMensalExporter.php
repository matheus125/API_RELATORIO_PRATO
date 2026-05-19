<?php

use Hcode\DB\Sql;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exporta os dados de tb_relatorios para a planilha mensal "RESULTADOS ALCANÇADOS 2026 (Jan - Dez).xlsx".
 *
 * O script faz o mapeamento automático das unidades lendo o layout da própria aba mensal,
 * então não depende de linhas fixas hardcoded para cada restaurante/cozinha.
 *
 * Observação importante:
 * - A linha "Novos cadastros" existe na planilha, mas a tabela tb_relatorios informada não possui
 *   esse campo. Por isso, neste script ela é preenchida com 0.
 * - Se depois você criar a coluna "novos_cadastros" na tabela, basta trocar o valor no método
 *   montarMapaCampos().
 */
class RelatorioPlanilhaMensalExporter
{
    /** @var Sql */
    protected $sql;

    /**
     * Prefixos que aparecem no Excel e não devem atrapalhar o match com nome_banco.
     * @var string[]
     */
    protected $prefixosCabecalho = [
        'RESTAURANTE POPULAR ',
        'RESTAURANTE ',
        'COZINHA ',
    ];

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    /**
     * Gera a planilha preenchida e salva no caminho informado.
     *
     * @param string $templatePath Caminho do arquivo modelo .xlsx
     * @param string $outputPath Caminho do arquivo de saída .xlsx
     * @param int    $ano Ano a exportar, ex: 2026
     * @param int    $mes Mês a exportar, 1 a 12
     * @param bool   $somenteFechados Se true, exporta só registros fechados
     *
     * @return string Caminho final do arquivo gerado
     *
     * @throws Exception
     */
    public function exportar($templatePath, $outputPath, $ano, $mes, $somenteFechados = true)
    {
        $ano = (int)$ano;
        $mes = (int)$mes;

        if ($ano < 2000 || $ano > 2100) {
            throw new Exception('Ano inválido para exportação.');
        }

        if ($mes < 1 || $mes > 12) {
            throw new Exception('Mês inválido para exportação.');
        }

        if (!file_exists($templatePath)) {
            throw new Exception('Arquivo modelo não encontrado: ' . $templatePath);
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheetName = (string)$mes;
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (!$sheet instanceof Worksheet) {
            throw new Exception('A aba do mês ' . $mes . ' não foi encontrada na planilha.');
        }

        $registros = $this->buscarDados($ano, $mes, $somenteFechados);
        $mapaUnidadesNaPlanilha = $this->mapearUnidadesNaPlanilha($sheet);
        $mapaCampos = $this->montarMapaCampos();

        foreach ($registros as $registro) {
            $data = !empty($registro['data']) ? $registro['data'] : null;
            if (!$data) {
                continue;
            }

            $dia = (int)date('j', strtotime($data));
            if ($dia < 1 || $dia > 31) {
                continue;
            }

            $coluna = $this->obterColunaDoDia($dia);
            $nomeBanco = isset($registro['nome_banco']) ? $registro['nome_banco'] : '';
            $chaveUnidade = $this->normalizarNomeUnidade($nomeBanco);

            if (!isset($mapaUnidadesNaPlanilha[$chaveUnidade])) {
                // Se quiser depurar unidades não encontradas, habilite este log.
                // error_log('Unidade não encontrada na planilha: ' . $nomeBanco . ' | chave=' . $chaveUnidade);
                continue;
            }

            $linhaBase = (int)$mapaUnidadesNaPlanilha[$chaveUnidade];

            foreach ($mapaCampos as $nomeCampo => $config) {
                $offset = (int)$config['offset'];
                $valor = $config['resolver']($registro);
                $linha = $linhaBase + $offset;

                $sheet->setCellValue($coluna . $linha, $this->normalizarNumero($valor));
            }
        }

        $diretorioSaida = dirname($outputPath);
        if (!is_dir($diretorioSaida)) {
            @mkdir($diretorioSaida, 0775, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        return $outputPath;
    }

    /**
     * Busca os registros da tb_relatorios por mês/ano.
     *
     * @param int  $ano
     * @param int  $mes
     * @param bool $somenteFechados
     * @return array
     */
    protected function buscarDados($ano, $mes, $somenteFechados = true)
    {
        $where = [
            'YEAR(data) = :ano',
            'MONTH(data) = :mes'
        ];

        $params = [
            ':ano' => (int)$ano,
            ':mes' => (int)$mes,
        ];

        if ($somenteFechados) {
            $where[] = 'fechado = 1';
        }

        $sql = "
            SELECT
                id,
                Idade_3a17Masculino,
                Idade_3a17Masculino_PCD,
                Idade_3a17Feminino,
                Idade_3a17Feminino_PCD,
                Idade_18a59Masculino,
                Idade_18a59Masculino_PCD,
                Idade_17a59Feminino,
                Idade_17a59Feminino_PCD,
                Idade_60Masculino,
                Idade_60Masculino_PCD,
                Idade_60Feminino,
                Idade_60Feminino_PCD,
                Situacao_risco_masculino,
                Situacao_risco_Feminino,
                Deficientes,
                senhas_genericas,
                Total_pessoas_atendidas,
                qtd_refeicoes_servidas,
                ocorrencias,
                cardapio,
                nome_banco,
                refeicoes_ofertadas,
                sobra_refeicoes,
                sobra_senhas,
                data,
                fechado
            FROM tb_relatorios
            WHERE " . implode(' AND ', $where) . "
            ORDER BY data ASC, nome_banco ASC
        ";

        return $this->sql->select($sql, $params);
    }

    /**
     * Faz o scan da coluna A da aba do mês e monta o mapa [UNIDADE_NORMALIZADA => linhaBase].
     *
     * Exemplo:
     * - CENTRO => 4
     * - COMPENSA => 27
     * - JORGE TEIXEIRA => 50
     *
     * @param Worksheet $sheet
     * @return array
     */
    protected function mapearUnidadesNaPlanilha(Worksheet $sheet)
    {
        $ultimaLinha = (int)$sheet->getHighestRow();
        $mapa = [];

        for ($linha = 1; $linha <= $ultimaLinha; $linha++) {
            $valorA = trim((string)$sheet->getCell('A' . $linha)->getValue());
            $valorB = trim((string)$sheet->getCell('B' . $linha)->getValue());

            if ($valorA === '') {
                continue;
            }

            $textoCabecalho = str_replace(["\r", "\n", "\t"], ' ', $valorA);
            $textoCabecalho = preg_replace('/\s+/', ' ', $textoCabecalho);

            // Para evitar capturar textos aleatórios, só aceita linhas do bloco da planilha.
            if (
                stripos($textoCabecalho, 'Restaurante') === false &&
                stripos($textoCabecalho, 'Cozinha') === false
            ) {
                continue;
            }

            if (strcasecmp($valorB, 'Novos cadastros') !== 0) {
                continue;
            }

            $chave = $this->normalizarNomeUnidade($textoCabecalho);
            $mapa[$chave] = $linha;
        }

        if (empty($mapa)) {
            throw new Exception('Nenhuma unidade foi encontrada no layout da planilha.');
        }

        return $mapa;
    }

    /**
     * Mapa de offsets relativos à linha base da unidade na planilha.
     *
     * @return array
     */
    protected function montarMapaCampos()
    {
        return [
            // A planilha tem esta linha, mas a tabela informada não possui o campo correspondente.
            'novos_cadastros' => [
                'offset' => 0,
                'resolver' => function (array $registro) {
                    return 0;
                }
            ],
            'Idade_3a17Masculino' => [
                'offset' => 1,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_3a17Masculino');
                }
            ],
            'Idade_3a17Masculino_PCD' => [
                'offset' => 2,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_3a17Masculino_PCD');
                }
            ],
            'Idade_3a17Feminino' => [
                'offset' => 3,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_3a17Feminino');
                }
            ],
            'Idade_3a17Feminino_PCD' => [
                'offset' => 4,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_3a17Feminino_PCD');
                }
            ],
            'Idade_18a59Masculino' => [
                'offset' => 5,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_18a59Masculino');
                }
            ],
            'Idade_18a59Masculino_PCD' => [
                'offset' => 6,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_18a59Masculino_PCD');
                }
            ],
            'Idade_17a59Feminino' => [
                'offset' => 7,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_17a59Feminino');
                }
            ],
            'Idade_17a59Feminino_PCD' => [
                'offset' => 8,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_17a59Feminino_PCD');
                }
            ],
            'Idade_60Masculino' => [
                'offset' => 9,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_60Masculino');
                }
            ],
            'Idade_60Masculino_PCD' => [
                'offset' => 10,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_60Masculino_PCD');
                }
            ],
            'Idade_60Feminino' => [
                'offset' => 11,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_60Feminino');
                }
            ],
            'Idade_60Feminino_PCD' => [
                'offset' => 12,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Idade_60Feminino_PCD');
                }
            ],
            'Situacao_risco_masculino' => [
                'offset' => 13,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Situacao_risco_masculino');
                }
            ],
            'Situacao_risco_Feminino' => [
                'offset' => 14,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Situacao_risco_Feminino');
                }
            ],
            'senhas_genericas' => [
                'offset' => 15,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'senhas_genericas');
                }
            ],
            'Total_pessoas_atendidas' => [
                'offset' => 16,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Total_pessoas_atendidas');
                }
            ],
            'qtd_refeicoes_servidas' => [
                'offset' => 17,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'qtd_refeicoes_servidas');
                }
            ],
            'refeicoes_ofertadas' => [
                'offset' => 18,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'refeicoes_ofertadas');
                }
            ],
            'sobra_refeicoes' => [
                'offset' => 19,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'sobra_refeicoes');
                }
            ],
            'sobra_senhas' => [
                'offset' => 20,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'sobra_senhas');
                }
            ],
            'Deficientes' => [
                'offset' => 21,
                'resolver' => function (array $registro) {
                    return $this->getInt($registro, 'Deficientes');
                }
            ],
        ];
    }

    /**
     * Converte o dia do mês na coluna da planilha.
     *
     * A estrutura da planilha começa assim:
     * A = bloco da unidade
     * B = descrição principal
     * C = subdescrição
     * D = dia 1
     * E = dia 2
     * F = dia 3
     * ...
     *
     * @param int $dia
     * @return string
     */
    protected function obterColunaDoDia($dia)
    {
        // D = 4
        $indiceColuna = 3 + (int)$dia;
        return Coordinate::stringFromColumnIndex($indiceColuna);
    }

    /**
     * Normaliza o nome da unidade para casar banco e planilha.
     *
     * @param string $nome
     * @return string
     */
    protected function normalizarNomeUnidade($nome)
    {
        $nome = (string)$nome;
        $nome = str_replace(["\r", "\n", "\t"], ' ', $nome);
        $nome = preg_replace('/\s+/', ' ', $nome);
        $nome = trim($nome);
        $nome = mb_strtoupper($nome, 'UTF-8');

        foreach ($this->prefixosCabecalho as $prefixo) {
            if (strpos($nome, $prefixo) === 0) {
                $nome = trim(substr($nome, strlen($prefixo)));
                break;
            }
        }

        $nome = $this->removerAcentos($nome);
        $nome = str_replace(['(', ')', '.', ',', ';', ':'], '', $nome);
        $nome = preg_replace('/\s+/', ' ', $nome);
        $nome = trim($nome);

        // Corrige variações conhecidas do modelo.
        $substituicoes = [
            'VIVER MEHLOR' => 'VIVER MELHOR',
            'COZINHAPARQUE SAO PEDRO' => 'PARQUE SAO PEDRO',
            'PARQUE SAO PEDRO' => 'PARQUE SAO PEDRO',
            'BAIRRO DA UNIAO' => 'BAIRRO DA UNIAO',
            'RIACHO DOCE CIDADE NOVA' => 'RIACHO DOCE CIDADE NOVA',
        ];

        if (isset($substituicoes[$nome])) {
            $nome = $substituicoes[$nome];
        }

        return $nome;
    }

    /**
     * @param mixed $valor
     * @return int
     */
    protected function normalizarNumero($valor)
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        return (int)$valor;
    }

    /**
     * @param array  $row
     * @param string $key
     * @return int
     */
    protected function getInt(array $row, $key)
    {
        return isset($row[$key]) && $row[$key] !== null && $row[$key] !== '' ? (int)$row[$key] : 0;
    }

    /**
     * @param string $texto
     * @return string
     */
    protected function removerAcentos($texto)
    {
        $mapa = [
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ç' => 'C', 'ç' => 'c',
            'Ñ' => 'N', 'ñ' => 'n',
        ];

        return strtr($texto, $mapa);
    }
}

/*
|--------------------------------------------------------------------------
| EXEMPLO DE USO SIMPLES
|--------------------------------------------------------------------------
|
| require_once('vendor/autoload.php');
|
| $sql = new Sql();
| $exportador = new RelatorioPlanilhaMensalExporter($sql);
|
| $arquivoModelo = $_SERVER['DOCUMENT_ROOT'] . '/res/excel/RESULTADOS ALCANÇADOS 2026 (Jan - Dez).xlsx';
| $arquivoSaida  = $_SERVER['DOCUMENT_ROOT'] . '/tmp/relatorio_mensal_2026_03.xlsx';
|
| $caminhoFinal = $exportador->exportar($arquivoModelo, $arquivoSaida, 2026, 3, true);
| echo 'Arquivo gerado em: ' . $caminhoFinal;
|
*/

/*
|--------------------------------------------------------------------------
| EXEMPLO DE ROTA SLIM 2 PARA DOWNLOAD
|--------------------------------------------------------------------------
|
| $app->get('/admin/api/relatorio/planilha', function () use ($app) {
|
|     try {
|         $ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
|         $mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
|
|         $sql = new Sql();
|         $exportador = new RelatorioPlanilhaMensalExporter($sql);
|
|         $arquivoModelo = $_SERVER['DOCUMENT_ROOT'] . '/res/excel/RESULTADOS ALCANÇADOS 2026 (Jan - Dez).xlsx';
|         $arquivoSaida  = $_SERVER['DOCUMENT_ROOT'] . '/tmp/relatorio_mensal_' . $ano . '_' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.xlsx';
|
|         $caminhoFinal = $exportador->exportar($arquivoModelo, $arquivoSaida, $ano, $mes, true);
|
|         if (!file_exists($caminhoFinal)) {
|             throw new Exception('Arquivo não foi gerado corretamente.');
|         }
|
|         header('Content-Description: File Transfer');
|         header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
|         header('Content-Disposition: attachment; filename="' . basename($caminhoFinal) . '"');
|         header('Expires: 0');
|         header('Cache-Control: must-revalidate');
|         header('Pragma: public');
|         header('Content-Length: ' . filesize($caminhoFinal));
|         readfile($caminhoFinal);
|         exit;
|
|     } catch (Exception $e) {
|         http_response_code(500);
|         header('Content-Type: application/json; charset=utf-8');
|         echo json_encode([
|             'success' => false,
|             'message' => $e->getMessage()
|         ], JSON_UNESCAPED_UNICODE);
|         exit;
|     }
| });
|
*/
