# Deploy - portal.ms-tecnologia.app.br

Data: 2026-06-16

## Resumo

Deploy realizado no dominio `portal.ms-tecnologia.app.br`, com raiz confirmada em:

```text
/home2/mat06153/portal.ms-tecnologia.app.br
```

O envio foi incremental, sem exclusoes em massa e sem sobrescrever arquivos sensiveis de ambiente.

## Backup antes do deploy

Backup remoto criado antes da extracao:

```text
/home2/mat06153/deploy_backups/portal_backup_20260616_204035.tar.gz
```

Tamanho:

```text
295M
```

SHA256:

```text
3d9cb015700d86a47abe48e17b6a4f31855f8c5dcbc3dbeda835347edfb46b1a
```

## Pacote enviado

Pacote local:

```text
storage/deploy/portal_deploy_20260616_163956.tar.gz
```

Pacote remoto:

```text
/home2/mat06153/deploy_uploads/20260616_163956/portal_deploy_20260616_163956.tar.gz
```

Tamanho:

```text
33M
```

SHA256:

```text
1eea096cc04d943121e28378e80d81d6f969a9b992c12ecfa9460ba387417aff
```

## Arquivos ignorados no pacote

- `.git`
- `.github`
- `node_modules`
- `.env`
- `mysql.cnf`
- `error_log`
- `documentacao`
- `storage`
- `vendor/backup`
- arquivos `.bak`
- arquivos `.log`
- pastas de testes/documentacao/performance de dependencias quando identificadas

## Arquivos enviados

Manifesto completo local:

```text
storage/deploy/portal_deploy_20260616_163956.manifest
```

Manifesto completo remoto:

```text
/home2/mat06153/deploy_uploads/20260616_163956/portal_deploy_20260616_163956.manifest
```

Total:

```text
2655 arquivos enviados
```

## Arquivos substituidos

Lista remota:

```text
/home2/mat06153/deploy_uploads/20260616_163956/arquivos_substituidos.txt
```

Total:

```text
2652 arquivos substituidos
```

## Arquivos novos

Lista remota:

```text
/home2/mat06153/deploy_uploads/20260616_163956/arquivos_novos.txt
```

Arquivos novos:

```text
app/services/AccessScopeService.php
database/sql/2026-06-15_capital_interior_scope.sql
database/sql/2026-06-16_notificacoes_publicacao.sql
```

## Arquivos sensiveis preservados

Confirmado que estes arquivos nao foram enviados pelo pacote e permaneceram no servidor:

```text
/home2/mat06153/portal.ms-tecnologia.app.br/.env
/home2/mat06153/portal.ms-tecnologia.app.br/mysql.cnf
```

## Banco de dados

Estruturas novas verificadas em producao:

- `tb_notificacoes`
- `tb_regioes`
- `tb_usuario_regioes`
- `tb_usuario_unidades`
- `tb_bases_consulta.id_regiao`
- `tb_relatorios.id_base_consulta`
- `tb_relatorios_pdf.id_base_consulta`

Permissoes de notificacao sincronizadas:

```text
NOTIFICACOES_VIEW
NOTIFICACOES_UPDATE
```

## Permissoes e cache

Executado no servidor apos extracao:

- diretorios ajustados para `755`;
- arquivos ajustados para `644`;
- `storage` ajustado para `775`;
- cache de views RainTPL limpo em `storage/cache/views`.

## Validacoes executadas

Lint remoto:

- `public/index.php`: aprovado
- `app/routes/admin.php`: aprovado
- `app/services/CentralUpdatePublishService.php`: aprovado
- `vendor/API/php-classes/src/Model/Notification.php`: aprovado
- `vendor/API/php-classes/src/Middleware/PerfilMiddleware.php`: aprovado

HTTP:

- `https://portal.ms-tecnologia.app.br/admin/login`: HTTP 200
- `https://portal.ms-tecnologia.app.br/admin`: HTTP 302 para `/admin/login`
- asset CSS AdminLTE: HTTP 200

Banco/servicos:

- conexao com banco: OK
- `tb_notificacoes`: OK
- versao atual detectada: `1.0.66`
- proxima versao sugerida: `1.0.67`

Views autenticadas por render CLI com sessao simulada:

- `relatorio-planilha`: OK
- `publicar-atualizacao`: OK
- `index`: a rota real envia `dashboardChartData`; no teste reduzido a variavel faltou, mas a rota `/admin` possui o dado no controller.

Planilha modelo:

- hash local e remoto iguais;
- arquivo abre com PhpSpreadsheet no servidor;
- abas mensais `1` a `12` presentes.

## Logs pos-deploy

Verificado:

- `error_log`
- `storage/logs/app-errors.log`

Resultado:

- sem erros novos registrados durante a validacao;
- entradas encontradas no `error_log` eram antigas, de abril/maio, relacionadas a cache de template ja limpo.

## Limitacoes da validacao

Nao foi realizado login real com usuario/senha de producao, para evitar tentativa com credenciais desconhecidas. Foi validada a tela de login, o redirecionamento de area protegida e renderizacao de views autenticadas por sessao simulada no CLI.

## Status final

Sistema publicado e operacional nas validacoes executadas.
