# Modelagem e importação GADSAN

## Configuração local aplicada — 28/09/2026

A conexão principal foi alterada para `127.0.0.1:3306`, banco `portal_relatorios`, usuário `dev`. A senha fica exclusivamente no `.env` local, ignorado pelo Git. A classe `Hcode\DB\Sql` carrega esse arquivo inclusive quando chamada por CLI e não possui mais fallback para o servidor remoto nem senha embutida.

Foram aplicados `database/schema.sql` e `database/seed.sql` no MySQL local: **17 tabelas novas**, preservando as **25 tabelas existentes**, totalizando **42**. O seed criou 3 turnos, 4 escolaridades, 2 instituições e 1 natureza de contratação. Nesta etapa foram instaladas as tabelas e os auxiliares; os colaboradores do Excel ainda não foram carregados neste banco local. A carga de 191 pessoas descrita abaixo refere-se ao teste isolado anterior.

O importador agora carrega `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD` do `.env` por padrão. Para importar no banco local configurado, basta executar o comando com `--file=... --apply`, sem repetir credenciais no terminal. Se `GADSAN_DSN` for definido explicitamente, `GADSAN_DB_USER` e `GADSAN_DB_PASSWORD` também são obrigatórios, evitando enviar a senha local a um destino alternativo por engano.

As telas do CRUD existente continuam usando suas tabelas anteriores. A criação do schema não muda automaticamente essas telas.

## Escopo e execução realizada

Solução PHP CLI com o PhpSpreadsheet já instalado no projeto. Não altera o CRUD legado, suas tabelas `tb_*`, credenciais ou o Excel. O schema deve ser instalado em **banco dedicado MySQL 8.0.16+** (versão mínima com CHECK efetivo). Foi validado em MySQL 8.4.11 temporário, sem rede e sem usar o `.env` do portal. Não houve implantação no banco do portal.

O relatório agregado reproduzível está em [analise-gadsan.json](analise-gadsan.json). Não contém dados pessoais. Foram gravados efetivamente 191 colaboradores, 191 alocações, 192 origens e 282 inconsistências no banco isolado de validação. Uma linha sem nome não gerou pessoa. Zero registros existentes foram atualizados. Isso não representa carga em produção.

## Análise anterior à modelagem

Arquivo: `Banco de Dados GADSAN 2025 18.02.2025.xlsx`.

SHA-256 antes e depois: `7622f94f2d5dc45edf268aef34750224ad9c1f293f1533850c479af4fde0bbd5`.

| Aba | Cabeçalhos reais | Linhas de dados | Pessoas importadas |
| --- | --- | ---: | ---: |
| GADSAN | 3 | 9 | 9 |
| GADSAN - PRATO CHEIO CAPITAL | 3 e 27 | 95 | 95 |
| GADSAN - PRATO CHEIO INTERIOR | 3 e 37 | 88 | 87 |
| Total | | 192 | 191 |

A aba Capital tem um espaço final no nome, preservado na rastreabilidade. O Interior tem um cabeçalho `Ligação` na coluna correspondente a `Vínculo`; ambos são reconhecidos. Cabeçalhos verdadeiros são reconhecidos por Nome, CPF e pelo menos oito rótulos conhecidos, sem depender de uma linha fixa. Títulos mesclados, separadores, cabeçalhos repetidos e linhas vazias são ignorados. A formatação residual da Capital alcança XFB: não é conteúdo tabular. O leitor percorre os dados A:Z e rejeita conteúdo efetivo além desse limite, em vez de descartá-lo silenciosamente. Não propaga valores de células mescladas para outras pessoas.

A linha 4 do Interior possui cargo e informações funcionais, mas nenhum nome: é preservada com inconsistência, sem inventar um colaborador. São 21 linhas estruturais/vazias ignoradas nas três abas, além dessa linha de dados pendente.

Valores recorrentes justificam catálogos de cargos, instituições, projetos, lotações, vínculos, turnos, escolaridades e formações. A carga final encontrou 18 cargos, 2 instituições, 11 projetos, 60 lotações, 8 vínculos e 28 formações distintas após as regras conservadoras de normalização. Variantes meramente de caixa e espaços compartilham registro; grafias, abreviações, acentos e significados diferentes não são equiparados indiscriminadamente.

Exemplos concretos:

- `matutino`/`Matutino` e `integral`/`Integral` são equivalentes.
- `Ampliação`, `Ampliação Capital`, `Ampliação Interior` e `Implantação` permanecem projetos diferentes. `Vínculo Suas` permanece projeto porque é assim que está preenchido, sem inferir sigla ou relação.
- `Allimentação Adequada`, `PC Viiver melhor`, anotações com asterisco e outras grafias suspeitas geram revisão; não há correção aproximada.
- `Assessor`/`Assessora`, `Supervisor`/`Supervisora` e abreviações de cargos permanecem distintos até decisão do gestor.
- `Comissionado` aparece duas vezes como instituição e uma como projeto. É natureza de contratação; não define instituição, projeto nem substitui os códigos existentes de vínculo. São três classificações para revisão. Outros dois casos contêm `AADESAM` no campo vínculo: o vínculo fica pendente, sem inventar sigla.
- `AC`, `CB`, `SELET`, `RC`, `GL`, `JL`, `FS` e `AC - JULIANA` não recebem significados inventados. O último também gera aviso de anotação.
- Há formação múltipla explícita `Serviço Social / Enfermagem`: gera duas relações. Expressões compostas ambíguas, asteriscos, situação de curso e ocupações no campo formação ficam somente na origem para conferência.
- Escolaridade diferencia nível e situação. `Superior` não significa automaticamente `Superior Completo`; `Cursando` e `Incompleto` são preservados como situações distintas. Níveis misturados com curso/ocupação ficam pendentes.
- `à disposição` não identifica lotação, portanto produz NULL e uma pendência. Expressões como `GADSAN/PC Capital` permanecem literais, com revisão, sem inventar duas unidades.

## Tabelas e relacionamentos

Todas as 17 tabelas usam InnoDB, utf8mb4, PK interna `BIGINT UNSIGNED AUTO_INCREMENT` e timestamp de criação. Catálogos e cadastros editáveis também possuem `updated_at`. Eventos de origem e de inconsistência mantêm o timestamp original. Não há senha ou identidade de autenticação nessas tabelas.

| Tabela | Papel e relacionamentos |
| --- | --- |
| colaboradores | Pessoa: nome, CPF, RG, nascimento, e-mail, telefone, endereço, tipo sanguíneo, ativo. CPF nunca é PK. |
| cargos | Catálogo de cargos; 1:N alocações. |
| instituicoes | Catálogo de instituições; 1:N alocações. |
| projetos | Catálogo de projetos, sem unificação por semelhança; 1:N alocações. |
| municipios | Município, UF e código IBGE opcional; 1:N lotações. |
| lotacoes | Unidade/lotação, tipo e município opcional; 1:N alocações. |
| vinculos | Rótulos/códigos informados, sem expansão de siglas; 1:N alocações. |
| naturezas_contratacao | Natureza como Comissionado, independente de instituição e código de vínculo. |
| turnos | Catálogo de turnos; 1:N alocações. |
| escolaridades | Nível educacional; N:N colaboradores pela tabela associativa. |
| formacoes | Formação acadêmica; N:N colaboradores pela tabela associativa. |
| colaborador_escolaridade | FK pessoa + FK nível + situação; UNIQUE dos três campos. |
| colaborador_formacao | FK pessoa + FK formação; UNIQUE do par. |
| alocacoes_colaborador | FK pessoa, cargo, projeto, lotação, instituição, vínculo, natureza, turno; matrícula, carga semanal, admissão e período. |
| importacoes | Arquivo, hash, versão do importador, data, contadores e status. |
| importacao_origens | FK importação, pessoa e alocação opcionais; arquivo, aba, linha, JSON original, resultado e data. |
| importacao_inconsistencias | FK importação e origem; campo, valor original, tipo, descrição, resolvido, data. |

O [schema.sql](../database/schema.sql) é o dicionário executável dos campos, tipos, obrigatoriedade, PKs, FKs, índices e CHECKs. O [diagrama ER](diagrama-er.md) representa todas as relações.

### Pessoa versus atividade profissional

Matrícula, instituição, cargo, projeto, vínculo, turno, carga semanal e admissão pertencem à **alocação**, não à pessoa. Uma pessoa pode ter várias alocações simultâneas ou históricas. Isso representa N:N entre pessoas e projetos, instituições, cargos e lotações sem perder a combinação concreta dos campos.

`data_admissao` é a informação de admissão recebida. Não prova o início do cargo/projeto atual: `data_inicio` e `data_fim` ficam NULL na carga. O futuro CRUD poderá estabelecer períodos confirmados; CHECK impede fim anterior ao início quando ambos existem. Não se inventa histórico nem se encerra alocação anterior automaticamente.

Matrícula é VARCHAR e indexada, mas **não UNIQUE**: foi observada repetição com CPFs diferentes e ela pode depender da instituição/período. Documentos e telefone são VARCHAR; datas são DATE; carga semanal é SMALLINT UNSIGNED entre 1 e 168. Ativo indica disponibilidade operacional do cadastro e começa verdadeiro, sem afirmar vínculo atual confirmado.

### Municípios e lotações

Município é geografia, lotação é unidade organizacional. O schema separa ambos; nome+UF e código IBGE são únicos em municípios. O Excel mistura municípios, setores, unidades e expressões compostas na mesma coluna e não fornece UF/código IBGE estruturados. Por isso a primeira carga mantém `municipios` vazio, `municipio_id=NULL` e `tipo=nao_classificada`. O gestor poderá cadastrar os municípios e classificar as lotações após confirmar os locais. Nenhuma UF ou código foi inventado e uma localidade não foi transformada silenciosamente em instituição.

`lotacoes.nome` é único no catálogo desta carga. Caso o futuro CRUD precise de duas unidades homônimas em municípios diferentes, deverá introduzir código de unidade e revisar essa chave antes de cadastrá-las.

## Regras de qualidade e identidade

1. CPF: somente dígitos; valida comprimento, sequências repetidas e os dois verificadores. CPF inválido permanece no cadastro quando cabe no campo, com `cpf_validado=0` e inconsistência. Não impede criar pessoa. CPF vazio é NULL. Zeros iniciais não são inventados.
2. A coluna gerada `cpf_unico` aplica UNIQUE apenas aos CPFs marcados válidos. Inválidos podem repetir para não fundir pessoas por identificador defeituoso. O CHECK verifica o formato; **o algoritmo de verificadores deve ser usado também no futuro CRUD** antes de marcar `cpf_validado`.
3. Procurar CPF válido primeiro. Depois, matrícula na mesma instituição, exigindo nome normalizado e nascimento idênticos e CPFs compatíveis. CPFs diferentes impedem associação por matrícula. CPF inválido nunca é identidade suficiente.
4. Nome igual gera indicação, nunca fusão. A normalização de nome serve para comparar, sem reescrever o nome armazenado. Nesta planilha não foram encontrados CPFs repetidos nem nomes repetidos de colaboradores após excluir os cabeçalhos. Uma matrícula repetida apresenta CPFs divergentes; os dois cadastros são mantidos.
5. Cadastro existente não é sobrescrito, nem seus campos vazios são preenchidos automaticamente. Qualquer valor pessoal recebido diferente do armazenado gera conflito; a linha original permanece para decisão humana. Informações educacionais de registro existente também exigem conferência. Alocação diferente não vira histórico automaticamente.
6. Telefone: somente dígitos; aceita formato local 8/9, nacional 10/11 ou internacional brasileiro 12/13 começando em 55. Sem DDD gera pendência. Letras, múltiplos números concatenados e outras formas podem resultar em formato inválido; não se inventa DDD. Validação é estrutural, não confirma titularidade/existência.
7. E-mail: trim + lowercase e validação básica. Domínios suspeitos são comparados a uma lista explícita no normalizador; não há correção nem consulta de entrega/DNS. A lista não é um detector universal de erros.
8. Datas: serial numérico Excel com calendário 1900/1904 ou texto `d/m/Y`, `d-m-Y`, `Y-m-d` com ano de quatro dígitos entre 1000 e 9999; rejeita datas impossíveis, serial fictício 60 do calendário 1900 e horários fracionários. Data inválida vira NULL no cadastro, preservando o original na origem e inconsistência. Data futura, nascimento com menos de 14 anos ou anterior a 1900 é sinalizado; não é corrigido. Suspeitas de idade usam a data da execução. Admissão anterior ao nascimento também gera pendência.
9. Campos opcionais vazios, `-`, `não se aplica` e `n/a` viram NULL. A versão literal é preservada no JSON. Somente nome é obrigatório para criar uma pessoa. Campos maiores que os limites documentados são preservados na origem e sinalizados; demais violações SQL abortam a transação, sem truncamento silencioso.
10. Identificadores armazenados numericamente no Excel geram alerta: o arquivo pode já ter perdido zeros. Existem 107 ocorrências desse alerta, não necessariamente 107 pessoas.

Catálogos usam collation `utf8mb4_0900_as_ci`: caixa insensível e acento sensível. A comparação de nomes para indício é mais permissiva, mas nunca é usada sozinha para fusão.

## Rastreabilidade, idempotência e transações

Cada linha de dados produz uma origem, inclusive sem nome ou com conflito. `dados_originais` contém os valores das células associados aos cabeçalhos, inclusive datas seriais e fórmulas literais; não executa fórmulas do Excel. Arquivo, aba original (inclusive espaço final), número real da linha e momento da carga são registrados. FK da origem aponta para pessoa/alocação quando identificadas. Inconsistências apontam para a mesma origem.

O importador adquire advisory lock por banco para serializar suas execuções, registra a importação e grava pessoas, auxiliares, associações, origens e inconsistências em transação. Em falha, desfaz essas gravações e mantém o controle com status `falhou`. Falha abrupta do processo pode deixar `em_andamento`; a execução seguinte, após adquirir o lock, pode tentar novamente. UNIQUE de hash+versão impede reprocessar carga concluída, mesmo que o nome do arquivo mude.

Contadores: `total_linhas` considera linhas de dados; `total_importados` conta pessoas novas; `total_atualizados` permanece zero pela política conservadora; `total_ignorados` conta linhas sem nova pessoa (existentes ou sem nome), cujas origens continuam preservadas. Linhas vazias/títulos são contadas separadamente no relatório por aba. `total_inconsistencias` conta eventos, podendo haver vários por pessoa.

A simulação usa as mesmas regras com estado em memória inicialmente vazio. Ela **não consulta cadastros já existentes**; portanto, números de uma carga num banco já preenchido podem diferir. A idempotência depende da versão: ao mudar regras, incremente `Importer::VERSION` e revise a carga anterior; a política de conflito continua protegendo dados existentes.

O lock coordena importadores, não edições concorrentes de um futuro CRUD. Execute cargas durante janela sem edição ou faça o CRUD respeitar o mesmo lock. FKs e UNIQUE permanecem a defesa de integridade no banco. Não há exclusão em cascata; o CRUD deve desativar cadastros preservando referências.

## Inconsistências encontradas

| Tipo | Ocorrências |
| --- | ---: |
| CPF inválido | 12 |
| CPF duplicado | 0 |
| Matrícula duplicada | 1 |
| Identificadores conflitantes | 1 (mesma repetição de matrícula) |
| Possível duplicidade por nome | 0 |
| Data inválida | 9 |
| Data suspeita | 2 |
| E-mail inválido | 2 |
| E-mail com domínio suspeito | 2 |
| Telefone inválido | 5 |
| Telefone sem DDD | 93 |
| Nome obrigatório ausente | 1 |
| Classificação de projeto/instituição/vínculo a revisar | 5 |
| Escolaridade ambígua | 6 |
| Formação ambígua | 10 |
| Ocupação preenchida como formação | 11 |
| Lotação ambígua | 9 |
| Identificador numérico no Excel | 107 |
| Grafia/anotação suspeita | 6 |
| **Total de eventos** | **282** |

Campos vazios e distribuição dos eventos por aba estão no JSON agregado. Os 5 CPFs vazios são distintos dos 12 inválidos. Os dados sensíveis necessários para corrigir cada caso ficam apenas nas tabelas protegidas de origem/inconsistência, não nesta documentação.

## Instalação e uso

Requisitos: PHP 8.1+ com as extensões requeridas pelo projeto, `intl`, `mbstring`, `zip`, XML e `pdo_mysql`; dependências do `composer.lock` instaladas. Não foi adicionada biblioteca. O servidor web não deve executar o importador: ele é exclusivamente CLI.

Na raiz do projeto, usando uma conta com permissão de criação:

```bash
mysql -u administrador -p -e "CREATE DATABASE gadsan CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_ci;"
mysql -u administrador -p gadsan < database/schema.sql
mysql -u administrador -p gadsan < database/seed.sql
```

`schema.sql` cria tabelas novas e falha se já existirem; não é uma migração destrutiva nem deve ser reaplicado. O seed pode ser repetido. Provisionar usuário de importação com SELECT, INSERT e UPDATE somente no banco `gadsan`. Credenciais locais são lidas do `.env`, ignorado pelo Git; um destino alternativo pode usar as variáveis GADSAN explícitas.

Simulação, sem banco:

```bash
php scripts/import-gadsan.php \
  --file='/home/matheus-mota/BACKUP_MATHEUS/Download/Matheus/DESKTOP/Banco de Dados GADSAN 2025 18.02.2025.xlsx'
```

Importação efetiva para um banco alternativo, com credenciais fornecidas explicitamente pelo ambiente:

```bash
export GADSAN_DSN='mysql:host=127.0.0.1;port=3306;dbname=gadsan;charset=utf8mb4'
export GADSAN_DB_USER='gadsan_importador'
read -r -s -p 'Senha do banco: ' GADSAN_DB_PASSWORD
export GADSAN_DB_PASSWORD
php scripts/import-gadsan.php \
  --file='/home/matheus-mota/BACKUP_MATHEUS/Download/Matheus/DESKTOP/Banco de Dados GADSAN 2025 18.02.2025.xlsx' \
  --apply
unset GADSAN_DB_PASSWORD
```

Também é possível usar `mysql:unix_socket=/caminho/mysql.sock;dbname=gadsan;charset=utf8mb4` no DSN. Conexões remotas devem usar o transporte seguro configurado no ambiente; a validação realizada usou socket local.

Verificação agregada (nenhum dado pessoal):

```bash
mysql -u gadsan_importador -p gadsan < database/import.sql
php tests/gadsan/run.php
```

Teste de integração opcional: `php tests/gadsan/mysql.php /caminho/arquivo.xlsx`, **somente** contra o socket temporário explícito `/tmp/gadsan-mysql-test/mysql.sock`, MySQL isolado sem rede e usuário root local sem senha. O teste cria bancos de validação com nomes novos, testa SQL/FKs/UNIQUE/rollback/idempotência e atualiza `docs/analise-gadsan.json` com contagens. Não é um comando para produção. Foram aprovadas 25 verificações sintéticas e 18 verificações MySQL.

## Privacidade e futura revisão no CRUD

CPF, RG, nascimento, endereço, telefone e e-mail, incluindo seus valores originais, precisam de controle de acesso. JSON original e `valor_original` são intencionalmente sensíveis; conceder acesso apenas a quem corrige a carga. O CLI imprime somente contagens e omite mensagens SQL/exceções que poderiam conter valores pessoais. Não salvar dumps ou cópias do Excel em diretório público ou versionamento; proteger backups e conexão de banco conforme ambiente.

A futura tela de inconsistências deverá mostrar origem, valor recebido e cadastro atual somente a usuários autorizados. Marcar `resolvido` após a correção aprovada e registrar auditoria do operador no sistema que implementar o CRUD. A modelagem suporta correção manual e novas alocações. O módulo web implementado posteriormente está documentado em [Telas GADSAN](telas-gadsan.md), com cadastro, edição, auxiliares, importação e revisão de pendências.

## Dicionário de campos

Convenções comuns: `id BIGINT UNSIGNED AUTO_INCREMENT` é PK em todas as tabelas. FKs são BIGINT UNSIGNED e indexadas pelo InnoDB. `created_at TIMESTAMP` tem valor inicial CURRENT_TIMESTAMP. `updated_at TIMESTAMP`, quando presente, é atualizado automaticamente. `ativo BOOLEAN` inicia TRUE. Nenhum documento é inteiro.

| Tabela/grupo | Campos além de id |
| --- | --- |
| cargos, instituicoes, projetos, vinculos, naturezas_contratacao, turnos, escolaridades, formacoes | nome VARCHAR(255) obrigatório UNIQUE; ativo; created_at; updated_at. |
| municipios | nome VARCHAR(120), uf CHAR(2) obrigatórios, UNIQUE conjunto; codigo_ibge VARCHAR(7) opcional UNIQUE; ativo; created_at; updated_at. |
| lotacoes | nome VARCHAR(255) obrigatório UNIQUE; tipo VARCHAR(30) obrigatório com padrão nao_classificada; municipio_id FK opcional; ativo; created_at; updated_at. |
| colaboradores | nome VARCHAR(255) obrigatório; cpf VARCHAR(32), rg VARCHAR(100), data_nascimento DATE, email VARCHAR(320), telefone VARCHAR(64), endereco TEXT e tipo_sanguineo VARCHAR(3) opcionais; cpf_validado BOOLEAN obrigatório padrão FALSE; cpf_unico VARCHAR(11) gerado UNIQUE; ativo; created_at; updated_at. |
| alocacoes_colaborador | colaborador_id FK obrigatório; cargo_id, projeto_id, lotacao_id, instituicao_id, vinculo_id, natureza_contratacao_id e turno_id FKs opcionais; matricula VARCHAR(100), carga_horaria_semanal SMALLINT UNSIGNED, data_admissao DATE, data_inicio DATE e data_fim DATE opcionais; ativo; created_at; updated_at. |
| colaborador_formacao | colaborador_id e formacao_id FKs obrigatórios, UNIQUE conjunto; created_at; updated_at. |
| colaborador_escolaridade | colaborador_id e escolaridade_id FKs obrigatórios; situacao VARCHAR(20) obrigatório padrão Nao informado; UNIQUE pessoa+nível+situação; created_at; updated_at. |
| importacoes | nome_arquivo VARCHAR(255), sha256 CHAR(64), versao_importador VARCHAR(20), data_importacao TIMESTAMP obrigatórios; contadores total_linhas, total_importados, total_atualizados, total_ignorados e total_inconsistencias INT UNSIGNED com padrão 0; status VARCHAR(30) com padrão em_andamento; created_at; UNIQUE sha256+versão. |
| importacao_origens | importacao_id FK obrigatório; colaborador_id e alocacao_id FKs opcionais; arquivo VARCHAR(255), aba VARCHAR(255), linha INT UNSIGNED, dados_originais JSON e resultado VARCHAR(30) obrigatórios; data_importacao TIMESTAMP; created_at; UNIQUE importação+aba+linha. |
| importacao_inconsistencias | importacao_id FK obrigatório; origem_id FK opcional; arquivo VARCHAR(255), aba VARCHAR(255), linha INT UNSIGNED, campo VARCHAR(100), tipo_inconsistencia VARCHAR(80), descricao TEXT obrigatórios; valor_original TEXT opcional; resolvido BOOLEAN obrigatório padrão FALSE; created_at. |

Índices adicionais: CPF e nome+nascimento em colaboradores; matrícula e pessoa+período em alocações; pessoa na origem; resolvido+tipo nas inconsistências. As constraints verificam formatos e intervalos estruturais; qualidade semântica e identidade são responsabilidade das rotinas de validação e da revisão humana.
