# Analise de recebimento Capital/Interior

Data: 2026-06-23
Projeto: `API_RELATORIO_PRATO`
Banco online: `mat06153_portal_relatorios`

## Objetivo

Entender como o portal/API deve receber dados da Capital e do Interior quando o `prato_web` for implantado nas unidades do interior.

## Situacao atual do banco online

Tabelas principais com dados reais:

| Tabela | Total real |
| --- | ---: |
| `tb_bases_consulta` | 4 |
| `tb_cadastros_unificados` | 11313 |
| `tb_relatorios` | 566 |
| `tb_relatorios_pdf` | 993 |
| `tb_resumodia` | 196 |
| `tb_atualizacoes_unidades` | 19 |
| `tb_logs_atualizacoes` | 18156 |

Regioes cadastradas:

- `CAPITAL`
- `INTERIOR`

Bases cadastradas hoje:

| Regiao | Unidade | Identificador | Municipio | Banco |
| --- | --- | --- | --- | --- |
| CAPITAL | PRATO CHEIO CENTRO | `centro` | Manaus | `mat06153_centro` |
| CAPITAL | PRATO CHEIO COMPENSA | `compensa` | Manaus | `mat06153_compensa` |
| CAPITAL | PRATO CHEIO UNIAO | `uniao` | Manaus | `mat06153_uniao` |
| CAPITAL | PRATO CHEIO VIVER MELHOR | `viver_melhor` | Manaus | `mat06153_viver_melhor` |

Conclusao: a estrutura Capital/Interior existe, mas ainda nao ha base do Interior cadastrada em `tb_bases_consulta`.

Premissa confirmada: o sistema/projeto pode continuar com o nome padrao `prato_web` nas instalacoes. A separacao entre unidades nao deve depender do nome do sistema, e sim da identidade da unidade cadastrada no banco central, seguindo o mesmo modelo usado na Capital. Assim, cada unidade deve ter uma identidade unica no portal, por exemplo `parintins`, `itacoatiara`, `manacapuru`, etc.

## Como os dados chegam hoje

### 1. Cadastros unificados

Fluxo atual:

1. O portal lista bases em `tb_bases_consulta`.
2. A rota `/admin/api/consulta-unificada/sincronizar` chama `CadastroUnificadoService`.
3. O serviço conecta no banco de cada unidade configurada.
4. Busca `tb_titular` e `tb_dependentes`.
5. Grava/atualiza em `tb_cadastros_unificados`.

Esse fluxo e adequado para Capital e Interior, desde que cada unidade do interior esteja cadastrada em `tb_bases_consulta` com:

- `id_regiao` apontando para `INTERIOR`;
- `municipio`;
- `tipo_unidade`;
- `host`;
- `porta`;
- `nome_banco`;
- `usuario`;
- `senha`;
- `identificador` unico.

Ponto de atencao: o codigo atual do `CadastroUnificadoService` nao insere `id_base_consulta` diretamente no upsert. Hoje os registros online estao vinculados, mas novos registros podem entrar sem `id_base_consulta` se nao houver uma reconciliacao posterior.

### 2. Relatorios de fechamento

Fluxo atual:

1. O `prato_web` fecha o dia.
2. Gera/atualiza `tb_relatorios` e `tb_fechamento_dia` localmente.
3. Com `RELATORIO_REMOTE_DB_ENABLED=true`, conecta direto no banco online.
4. Faz upsert em `tb_relatorios` e `tb_fechamento_dia`.

Ponto critico: o envio remoto nao grava `id_base_consulta`. Ele depende de `nome_banco`.

Hoje existem muitos registros antigos sem base:

| Tabela | Sem `id_base_consulta` |
| --- | ---: |
| `tb_relatorios` | 466 |
| `tb_relatorios_pdf` | 993 |
| `tb_resumodia` | 196 |
| `tb_cadastros_unificados` | 0 |

Em `tb_relatorios`, 29 registros sem base ainda podem ser vinculados automaticamente por `identificador` (`centro`, `compensa`, `uniao`, `viver_melhor`). O restante usa unidades que ainda nao existem em `tb_bases_consulta`, como:

- `alfredo_nascimento`
- `parque_sao_pedro`
- `rio piorini`
- `aleixo`
- `mauazinho`
- `braga_mendes`
- `sao_jose`
- `jesus me deu`
- `jorge_teixeira`
- `novo_israel`
- `alvorada`
- `coroado`
- `petropolis`
- `riacho_doce`

### 3. PDFs de relatorio

Os PDFs sao enviados para o servidor por SFTP/FTP e registrados em `tb_relatorios_pdf`.

Problema atual: `tb_relatorios_pdf.nome_banco` esta `NULL` em todos os registros consultados, entao o portal nao consegue separar por Capital/Interior pela coluna de base.

Mesmo assim, o nome do arquivo permite inferir a unidade em muitos casos:

- `centro_2026-06-23.pdf`
- `compensa_2026-06-23.pdf`
- `aleixo_2026-06-23.pdf`
- `rio_piorini_2026-06-23.pdf`

Recomendacao: o `prato_web` deve enviar/gravar explicitamente `nome_banco` e, idealmente, `id_base_consulta` ou `identificador` da unidade ao registrar o PDF.

### 4. Resumo diario de cadastros

O `ResumoDiaService` do `prato_web` sincroniza `tb_resumodia` direto no banco online.

Ponto critico: ele usa `SELECT DATABASE()` para montar `nome_banco`. Como o sistema instalado pode ter sempre o nome padrao `prato_web`, o correto e usar a identidade da unidade cadastrada/configurada, e nao depender do nome do sistema ou do schema local.

As variaveis existem no `.env` do `prato_web`:

- `RELATORIO_UNIDADE_ID`
- `RELATORIO_UNIDADE_NOME`
- `RELATORIO_NOME_BANCO`

Mas o codigo atual ainda nao usa essas variaveis como fonte principal para `nome_banco`/unidade. Essa e a mudanca mais importante para o Interior: `RELATORIO_NOME_BANCO` deve representar a unidade no portal, nao necessariamente o nome fisico do banco local.

### 5. Monitoramento de atualizacoes

Esse fluxo ja esta mais bem preparado:

1. O `prato_web` chama `/api/updates/status`.
2. O portal grava em `tb_logs_atualizacoes`.
3. O status consolidado fica em `tb_atualizacoes_unidades`.
4. O payload aceita `unidade_id`, `nome_unidade`, `municipio` e `tipo_unidade`.

Recomendacao: padronizar `unidade_id` com o mesmo valor de `tb_bases_consulta.identificador`.

## Modelo correto para Capital e Interior

`tb_regioes` deve ser a tabela pai:

- `CAPITAL`
- `INTERIOR`

`tb_bases_consulta` deve representar cada unidade implantada:

- uma linha por unidade;
- `id_regiao` define Capital/Interior;
- `identificador` define o slug tecnico da unidade;
- `nome_banco` define o banco real usado para sincronizacao por pull;
- `municipio` permite filtros do interior;
- `ativo` controla se entra nas telas e sincronizacoes.

As tabelas operacionais devem apontar para `tb_bases_consulta.id`:

- `tb_cadastros_unificados.id_base_consulta`
- `tb_relatorios.id_base_consulta`
- `tb_relatorios_pdf.id_base_consulta`
- `tb_resumodia.id_base_consulta`

## Riscos antes de implantar no Interior

1. Sem cadastrar bases do Interior, os dados novos entram como `SEM_REGIAO` ou nao entram na consulta unificada.
2. Se o `prato_web` nao usar o identificador proprio da unidade, relatorios e resumo diario podem cair com `nome_banco` generico, como `prato_web` ou `prato_cheio`, mesmo que a unidade tenha nome proprio no banco central.
3. PDFs atualmente nao carregam `nome_banco`, dificultando filtro por unidade/regiao.
4. `CadastroUnificadoService` precisa gravar `id_base_consulta` no upsert para novos registros.
5. Registros antigos precisam de reconciliacao por `identificador` e por cadastro das unidades faltantes.

## Plano recomendado

1. Cadastrar todas as unidades do Interior em `tb_bases_consulta` com `id_regiao = INTERIOR`.
2. Padronizar em cada instalacao do `prato_web`:
   - `RELATORIO_UNIDADE_ID`: mesmo valor de `tb_bases_consulta.identificador`;
   - `RELATORIO_UNIDADE_NOME`: nome exibido da unidade;
   - `RELATORIO_NOME_BANCO`: identidade da unidade usada nos relatórios e vinculos, preferencialmente igual a `tb_bases_consulta.identificador`.
3. Ajustar `prato_web` para usar essas variaveis em vez de `SELECT DATABASE()` quando preencher `nome_banco`.
4. Ajustar envio de `tb_relatorios`, `tb_relatorios_pdf` e `tb_resumodia` para preencher `id_base_consulta` ou permitir resolucao segura por `identificador`.
5. Ajustar `CadastroUnificadoService` para inserir e atualizar `id_base_consulta`.
6. Criar rotina de reconciliacao dos registros antigos sem base.
7. Testar filtros de escopo por usuario:
   - usuario Capital ve somente bases Capital;
   - usuario Interior ve somente bases Interior;
   - Admin/Supervisor com permissao geral ve tudo.

## Conclusao

A base estrutural para Capital/Interior ja existe no portal. Como cada unidade do Interior tera nome proprio no cadastro central, nao e preciso criar outra arquitetura; basta garantir que essa identidade unica seja usada em todos os fluxos, mesmo que o sistema instalado continue se chamando `prato_web`.

O caminho mais seguro e tratar `tb_bases_consulta` como cadastro mestre das unidades e fazer todos os dados recebidos apontarem para ela por `id_base_consulta`.
