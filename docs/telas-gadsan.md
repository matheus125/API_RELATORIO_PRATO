# Módulo de cadastros GADSAN

O módulo está integrado ao Slim, ao menu, ao layout administrativo e ao MySQL local `portal_relatorios`. Não altera as telas de usuários/funcionários responsáveis por login. Dados pessoais e acessos de autenticação continuam separados.

## Acesso

Com o servidor PHP na porta 8080, entre no sistema e acesse:

- Colaboradores: `http://127.0.0.1:8080/admin/gadsan`.
- Novo cadastro: `http://127.0.0.1:8080/admin/gadsan/colaboradores/novo`.
- Cadastros auxiliares: `http://127.0.0.1:8080/admin/gadsan/auxiliares`.
- Importações: `http://127.0.0.1:8080/admin/gadsan/importacoes`.

As quatro permissões são registradas na estrutura de permissões já existente e concedidas por padrão somente ao perfil ADMIN. Outros perfis podem recebê-las na tela de permissões:

| Permissão | Acesso |
| --- | --- |
| GADSAN_VIEW | Lista e consulta de pessoas, incluindo dados pessoais no cadastro individual. |
| GADSAN_EDIT | Criação e edição de pessoas e relações. Conceder também VIEW para navegar no cadastro salvo. |
| GADSAN_AUX | Criação, edição e inativação de opções dos dez catálogos. |
| GADSAN_IMPORT | Upload, acompanhamento de cargas, consulta de valores originais e revisão de inconsistências. |

Cada rota verifica sua permissão explicitamente e atualiza as permissões da sessão. Ocultar o menu não é a proteção de acesso. Requisições POST exigem token CSRF da sessão. As respostas usam Cache-Control no-store e todas as informações exibidas são escapadas em HTML.

## Cadastro de colaboradores

A lista tem busca por nome, paginação de 20 itens, situação e número de alocações ativas. Documentos, endereço, nascimento e contato não aparecem na listagem.

O formulário possui três seções:

1. Dados pessoais: nome obrigatório; CPF, RG, nascimento, e-mail, telefone, endereço e tipo sanguíneo opcionais; ativo/inativo.
2. Alocações: múltiplos cargos/instituições/projetos/lotações/vínculos/naturezas/turnos, com matrícula, carga semanal e datas de admissão, início e fim.
3. Escolaridade e formação: vários níveis/situações e múltiplos cursos vinculados.

Uma pessoa pode ser cadastrada somente com o nome. Blocos novos vazios não geram alocações. Alocações persistidas nunca são apagadas implicitamente: para encerrar uma delas, informar a data final e marcar como inativa. Não há exclusão física de colaboradores na tela.

Escolaridades e formações são atualizadas de acordo com a seleção explícita no formulário. A remoção de uma associação educacional não remove o catálogo nem os valores originais da importação.

A gravação de pessoa e relações é transacional. Um hash do estado carregado impede sobrescrever alterações concorrentes. Um marcador no final do formulário detecta truncamento do POST antes de salvar, por exemplo ao exceder max_input_vars. Há limite de 50 itens por seção.

CPF novo/informado manualmente deve ser válido ou ficar vazio; duplicidade não gera fusão. Um CPF inválido recebido pelo importador pode ser mantido sem alteração enquanto outras informações são corrigidas. O importador continua aceitando registros com CPF inválido e registrando inconsistências. Nomes iguais não são fundidos. Dados válidos só são alterados mediante envio explícito da edição.

Datas e períodos são validados no servidor. Carga horária aceita inteiro entre 1 e 168. Telefone fica somente com dígitos, sem inventar DDD. E-mail é aparado e convertido para minúsculas sem correção de domínio. O formulário não confirma titularidade de telefone/e-mail.

## Cadastros auxiliares

A mesma tela atende cargos, instituições, projetos, lotações, municípios, vínculos, naturezas de contratação, turnos, escolaridades e formações.

Municípios recebem nome, UF e código IBGE opcional de sete dígitos. Lotações recebem nome, tipo e município opcional. Todas as opções permitem inativação, sem perder referências existentes. O servidor rejeita novas associações a opções inativas; vínculos já cadastrados com elas são preservados.

No formulário de colaborador, o link para auxiliares abre outra aba. Depois de adicionar uma opção, o botão **Atualizar opções** recarrega os catálogos e as formações sem apagar o que já foi digitado. Também atualiza os modelos usados para adicionar novas alocações.

## Importações, origens e inconsistências

Essas três tabelas não recebem registros digitados manualmente: são preenchidas pelo importador. A tela aceita XLSX de até 10 MB e exige confirmação do envio. Também valem os limites upload_max_filesize/post_max_size do PHP. A soma dos tamanhos declarados dentro do ZIP é limitada a 64 MB, e a carga é limitada a 10.000 linhas de dados.

O arquivo é recebido em diretório temporário privado, fora do diretório público. O nome original é validado; o diretório e a cópia temporária são removidos ao concluir ou falhar. A planilha original no computador do usuário não é alterada. Reutiliza Workbook, Normalizer e Importer, incluindo hash/idempotência, validações, transação e preservação de conflitos. Não há importação automática ao abrir a tela.

As últimas 100 cargas são listadas. A página da carga mostra contadores, resumo das pendências e origens paginadas em grupos de 25. Cada origem mostra os valores originais e suas inconsistências. É possível abrir o colaborador correspondente, corrigir o cadastro e marcar um alerta como resolvido ou reabri-lo. Marcar como resolvido não modifica o valor recebido no Excel.

O acesso aos valores originais exige GADSAN_IMPORT, pois eles contêm informações pessoais. As ações de salvar, importar e revisar registram evento na auditoria existente, com entidade e ID interno, sem copiar CPF, RG, endereço, telefone ou nascimento para os eventos.

## Arquivos e validação

- `app/services/GadsanService.php`: regras, consultas parametrizadas, transações, catálogos e vínculos.
- `app/routes/admin-gadsan.php`: rotas, permissões, CSRF, upload e renderização.
- `app/views/admin/gadsan/`: telas e componentes PHP escapados, integrados ao PageAdmin.
- `public/res/admin/gadsan/`: estilos responsivos e interação dos formulários.
- `tests/gadsan/service.php`: 26 verificações transacionais, com rollback de registros sintéticos.
- `tests/gadsan/browser.cjs`: 30 verificações de navegação e gravação, CSRF, XSS, POST incompleto, permissões, celular, importação sintética e revisão.

Os testes no navegador usam servidor/sessões isolados na porta 8082. Seus registros sintéticos são removidos por ID após a verificação; nenhum dado do Excel real foi carregado durante esses testes. O servidor principal continua na porta 8080.

O teste de serviço usa a configuração DB_* local e pode ser executado com `php tests/gadsan/service.php`; só cria dados dentro de uma transação externa desfeita ao terminar. O teste de navegador exige Playwright disponível em PLAYWRIGHT_MODULE, sessões de teste e fixture XLSX preparados em /tmp; não utiliza nem altera senhas de usuários.


Para reproduzir a validação de navegador em ambiente local:

```bash
php tests/gadsan/browser-fixture.php prepare
php -d session.save_path=/tmp/gadsan-ui-sessions -S 127.0.0.1:8082 -t public
# Em outro terminal, apontar PLAYWRIGHT_MODULE para o módulo instalado:
PLAYWRIGHT_MODULE=/caminho/node_modules/playwright node tests/gadsan/browser.cjs
php tests/gadsan/browser-fixture.php cleanup
```

Execute cleanup inclusive se o teste falhar, antes de repetir a suíte. A preparação cria sessões de teste somente no diretório isolado /tmp/gadsan-ui-sessions; use esse diretório exclusivamente no servidor de validação da porta 8082. Não use essas sessões no servidor principal. Ao terminar, encerre o servidor de teste.

## Correção da paginação de importações

O objeto de layout em `gadsanRender` usa `$adminLayout`, separado do número de página recebido pela tela. Isso evita o erro 500 causado quando o layout sobrescrevia `$page`. A correção não altera cargas ou registros.

Regressão: `php tests/gadsan/import-pagination.php` executa 12 verificações sem banco, cobrindo primeira página, intermediária, última página e conclusão do layout. A suíte de navegador também detecta respostas HTTP 500 de documentos e confere o status e o paginador após o upload.

## Máscaras de documentos e contato

O módulo aplica máscaras na abertura do cadastro e durante digitação/colagem:

- CPF: `000.000.000-00`, mantendo zeros iniciais.
- Telefone local: `0000-0000` ou `00000-0000`, sem acrescentar DDD.
- Telefone com DDD: `(00) 0000-0000` ou `(00) 00000-0000`.
- Prefixo brasileiro informado: `+55 (00) 00000-0000` (também aceita fixo).
- E-mail: espaços nas extremidades removidos e letras convertidas para minúsculas ao sair do campo; domínio não é corrigido.

RG e matrícula continuam como texto livre, preservando letras e zeros. Valores importados maiores que o formato esperado não são truncados pela máscara. A formatação visual não substitui a validação do servidor; CPF e telefone continuam sendo normalizados para dígitos ao gravar.

`public/res/admin/gadsan/masks.js` não depende de biblioteca externa. `tests/gadsan/masks.cjs` passou em 30 verificações no navegador, incluindo valores preexistentes, digitação, colagem, remoção de pontuação, edição no meio do campo e preservação de zeros, letras e números longos, sem acesso ao banco.
