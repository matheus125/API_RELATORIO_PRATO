# Correção das permissões por perfil

## Problema e correção

Ao salvar a matriz, `Permissions::syncDefinitions()` recriava todas as concessões padrão. A rotina era chamada novamente ao salvar cada perfil e ao carregar a tela, desfazendo as desmarcações.

A sincronização agora aplica os padrões somente quando uma permissão é cadastrada pela primeira vez. Atualizar descrições e reler o catálogo não altera as concessões de permissões existentes.

`Funcionarios::getPermissions()` também foi ajustado:

- Uma lista vazia no banco permanece vazia, sem conceder os padrões novamente.
- A primeira consulta de cada requisição lê a ACL do banco, mesmo quando a sessão possui permissões antigas.
- As consultas seguintes da mesma requisição podem reutilizar o resultado; `refreshPermissions()` continua forçando nova leitura.
- Falhas na consulta não concedem acessos. A compatibilidade com os padrões é mantida apenas em instalações sem as tabelas de ACL.

## Validação local

Teste: `tests/permissions/mysql.php`.

O teste usa somente MySQL isolado em `127.0.0.1:33316`, banco `portal_acl_test`, e impede o carregamento do `.env` da aplicação. Requer que o banco descartável já exista; recria exclusivamente suas duas tabelas de ACL.

```sh
php tests/permissions/mysql.php
```

Resultados:

- Versão anterior: 52 falhas em 61 verificações, reproduzindo o defeito.
- Versão corrigida local: 61 verificações aprovadas.
- Arquivos exatos preparados para a hospedagem: 61 verificações aprovadas.
- Sintaxe PHP e `git diff --check`: aprovados.

Cobertura: salvamento sequencial dos quatro perfis, reabertura da matriz, perfil vazio, concessões adicionais, atualização do catálogo, inicialização de novas definições, sessões antigas em novos processos, atualização explícita, troca de usuário/perfil e falhas de consulta.

## Pacote e backup

Diretório: `storage/deploy/portal-permissions-20261016T023722Z/` (carimbo em UTC; data local 15/10/2026).

- `files/`: arquivos preparados para publicação.
- `backup/`: cópias originais recuperadas da hospedagem.

Arquivos da correção:

- `vendor/API/php-classes/src/Model/Permissions.php`
- `vendor/API/php-classes/src/Model/Funcionarios.php`

O pacote foi preparado sobre a versão remota. As definições GADSAN presentes apenas no ambiente local não fazem parte desta publicação. Configurações de conexão, rotas, views e demais alterações locais não entram no pacote.

Nenhuma migração de banco é necessária. As escolhas anteriormente desfeitas pelo defeito precisam ser selecionadas e salvas novamente na matriz.

## Publicação concluída

Publicação em `portal.ms-tecnologia.app.br` realizada por FTP com TLS (FTPS), em 15/10/2026 às 22:43 (America/Manaus), na raiz `/portal.ms-tecnologia.app.br`.

Antes de substituir cada arquivo, a cópia remota foi comparada com o backup. Os uploads foram preparados em nomes temporários, conferidos, renomeados e baixados novamente para confirmar o SHA-256. O registro dos hashes está em `manifest.json` no diretório do pacote.

Uma verificação temporária, protegida por token e removida ao término, executou os modelos publicados no PHP 8.3.35 da hospedagem. O teste utilizou uma única conexão MySQL com tabelas TEMPORARY vazias e uma dependência SQL isolada, sem alterar concessões reais nem sessões de usuários.

- Antes do deploy: seis checagens reproduziram o defeito.
- Depois do deploy: todas as 13 checagens passaram.
- Suporte ao perfil CONSULTA confirmado no schema remoto.
- OPcache dos dois arquivos invalidado após a publicação.
- Login público após deploy: HTTP 200.
- Matriz sem sessão autenticada: HTTP 302 para `/admin/login`, conforme esperado.

Resultados salvos em `probe-before.json` e `probe-after.json` no diretório do pacote. O teste remoto validou os modelos com dados temporários; não modificou a matriz real pela interface de um usuário.

## Catálogo organizado e publicação de 17/10/2026

O catálogo ativo foi alinhado às telas existentes do menu lateral. A matriz agora agrupa as permissões por módulo em cartões, permite alternar entre os quatro perfis, pesquisar ações e salvar uma matriz totalmente desmarcada sem reintroduzir concessões. A tela também deixou de exibir módulos legados sem rota ativa.

As rotas de análise inteligente, histórico PDF, planilha, unidades atualizadas, consulta unificada, monitoramento, funcionários e segurança passaram a usar suas permissões específicas. A exportação da planilha exige a permissão de visualização e a permissão de exportação. As concessões já existentes foram preservadas durante a atualização do catálogo.

O pacote `storage/deploy/portal-acl-catalog-20261017-0045/` foi publicado por FTPS em `/portal.ms-tecnologia.app.br` com dez arquivos, backup remoto e conferência SHA-256. A validação remota confirmou o catálogo ativo, a ocultação dos itens legados, a herança dos acessos existentes e a renderização da matriz organizada no PHP 8.3.35.

Validações finais: 46 verificações de catálogo, 61 verificações MySQL, 111 verificações de rotas/menu/exportação, sintaxe PHP e `git diff --check`; todas aprovadas. Após a publicação, o login respondeu HTTP 200 e a matriz sem sessão respondeu HTTP 302 para `/admin/login`.
