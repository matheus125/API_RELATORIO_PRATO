# Auditoria tecnica - API_RELATORIO_PRATO

Data: 2026-06-16

## 1. Resumo geral

Foi realizada auditoria estrutural do portal central `API_RELATORIO_PRATO`, com foco em rotas administrativas, permissoes, logs, banco de dados, dashboards de relatorios, monitoramento de atualizacoes e publicacao de pacotes para as unidades.

Tambem foram implementados dois recursos solicitados:

- Sino de notificacoes persistentes para eventos relevantes, inicialmente integrado ao envio/registro de relatorios e publicacao de atualizacoes.
- Sugestao automatica de versao na tela `Publicar atualizacao`, com validacao contra versoes ja existentes.

## 2. Estrutura analisada

Principais areas revisadas:

- Rotas Slim em `app/routes/admin.php` e `app/routes/admin-funcionarios.php`.
- Controllers/fluxos implementados diretamente nas rotas administrativas.
- Services em `app/services`, especialmente `CentralUpdatePublishService`, `UpdateMonitoringService`, `UnidadesAtualizadasService` e `IntelligentAnalysisService`.
- Helpers de relatorios em `app/helpers`, especialmente `RelatorioPlanilhaDashboard`.
- Views administrativas em `app/views/admin`.
- Models em `vendor/API/php-classes/src/Model`.
- Middleware de perfil/permissao em `vendor/API/php-classes/src/Middleware/PerfilMiddleware.php`.
- Banco de dados via migrations em `database/sql`.
- Bootstrap da aplicacao em `public/index.php` e configuracoes em `config/env.php`.

## 3. Falhas e riscos encontrados

- `public/index.php` estava com debug ligado diretamente no codigo. Isso poderia expor erros internos em producao.
- O model `Notification` antigo trabalhava apenas com sessao, sem persistencia em banco. Notificacoes eram perdidas e nao serviam para auditoria.
- Nao existia tabela `tb_notificacoes` no banco.
- A rota de permissao parametrizada no middleware tinha uma falha: a substituicao de `:id` era sobrescrita na linha seguinte. Algumas rotas com parametros poderiam escapar do controle esperado.
- A tela `Publicar atualizacao` exigia digitacao manual da versao, aumentando o risco de erro operacional.
- A publicacao de atualizacao usava comportamento de upsert para `tb_atualizacoes`, o que poderia mascarar uma tentativa de publicar versao duplicada.
- O fluxo de relatorios registrava historico em `tb_relatorios_pdf`, mas nao gerava aviso administrativo persistente.
- Ha token hardcoded em `vendor/API/php-classes/src/backup/UploadService.php`. Recomenda-se mover para `.env` e rotacionar o segredo.
- O `README.md` aparenta estar desatualizado e descreve outro contexto de portal.
- A validacao completa de login real nao foi concluida porque as credenciais documentadas nao autenticaram no banco atual. A validacao de tela foi feita com sessao administrativa simulada.

## 4. Melhorias aplicadas

- Criada a migration `database/sql/2026-06-16_notificacoes_publicacao.sql`.
- Criada a tabela `tb_notificacoes` com indices para leitura por status, tipo, unidade, relatorio e notificacoes nao lidas.
- Criadas permissoes `NOTIFICACOES_VIEW` e `NOTIFICACOES_UPDATE`.
- Reescrito o model `Hcode\Model\Notification` para persistir notificacoes em banco, manter fallback de sessao e registrar falhas em log.
- Adicionado sino no header administrativo com contador, listagem recente, acao de marcar uma notificacao como lida e marcar todas como lidas.
- Adicionadas APIs:
  - `GET /admin/api/notificacoes`
  - `POST /admin/api/notificacoes/:id/lida`
  - `POST /admin/api/notificacoes/marcar-todas-lidas`
  - `GET /admin/api/update-publish/suggest-version`
- Integrado o helper `RelatorioPlanilhaDashboard` para gerar notificacao quando um relatorio/planilha for registrado com sucesso em `tb_relatorios_pdf`.
- Integrada notificacao de publicacao de atualizacao concluida.
- Implementada sugestao automatica de versao em `CentralUpdatePublishService`.
- A versao atual agora prioriza a ultima versao publicada em `tb_atualizacoes`, com fallback para arquivos JSON existentes.
- A sugestao incrementa o ultimo segmento numerico preservando largura quando aplicavel. Exemplo: `1.052` vira `1.053`; `1.0.66` vira `1.0.67`.
- A publicacao impede versao duplicada consultando banco, arquivos JSON e ZIP local.
- A view `publicar-atualizacao.html` passou a preencher a nova versao automaticamente e ganhou botao para recalcular a sugestao.
- A publicacao passou a registrar log de auditoria com usuario, CPF, versao, ZIP e quantidade de arquivos.
- O debug do Slim passou a respeitar `APP_DEBUG`; por padrao fica desligado.
- Erros PHP passaram a ser enviados para `storage/logs/app-errors.log`.
- Corrigida a conversao de rotas parametrizadas no middleware de perfil.
- Sincronizada a classe antiga `Hcode\Security\Permissions` com as novas permissoes e rotas, para evitar divergencia em fluxos legados.

## 5. Banco de dados

Nova tabela:

```sql
tb_notificacoes
```

Campos principais:

- `id`
- `titulo`
- `mensagem`
- `tipo`
- `unidade_id`
- `relatorio_id`
- `link_destino`
- `status`
- `lida`
- `created_at`
- `updated_at`
- `read_at`

Indices criados:

- `idx_notificacoes_lida_created`
- `idx_notificacoes_tipo`
- `idx_notificacoes_unidade`
- `idx_notificacoes_relatorio`
- `idx_notificacoes_status`

Migration aplicada com sucesso no ambiente local em 2026-06-16.

## 6. Como funciona o sino de notificacoes

Quando um relatorio e registrado com sucesso no historico central, o sistema grava uma notificacao do tipo `relatorio_enviado` em `tb_notificacoes`.

No painel administrativo, o header busca as notificacoes recentes e exibe:

- contador de nao lidas;
- titulo;
- mensagem com unidade, tipo/status e horario;
- link de destino quando disponivel;
- botao para marcar uma notificacao como lida;
- botao para marcar todas como lidas.

Notificacoes lidas ficam fora do contador porque `Notification::getTotal()` considera apenas `lida = 0`.

## 7. Como funciona a versao automatica

Na abertura da tela `Publicar atualizacao`, o sistema consulta a ultima versao publicada em `tb_atualizacoes`.

Depois calcula a proxima versao livre:

1. Incrementa o ultimo bloco numerico da versao.
2. Confere se a versao existe no banco.
3. Confere se existe nos arquivos `version.json` e `version-lite.json`.
4. Confere se existe ZIP local com o mesmo nome.
5. Se encontrar duplicidade, incrementa novamente ate achar uma versao livre.

No ambiente auditado:

- versao atual detectada: `1.0.66`;
- proxima versao sugerida: `1.0.67`.

O usuario ainda pode revisar a versao antes de publicar, mas o backend bloqueia duplicidade.

## 8. Arquivos alterados nesta auditoria

- `app/helpers/RelatorioPlanilhaDashboard.php`
- `app/routes/admin.php`
- `app/services/CentralUpdatePublishService.php`
- `app/views/admin/header.html`
- `app/views/admin/publicar-atualizacao.html`
- `config/env.php`
- `public/index.php`
- `vendor/API/php-classes/src/Middleware/PerfilMiddleware.php`
- `vendor/API/php-classes/src/Model/Notification.php`
- `vendor/API/php-classes/src/Model/Permissions.php`
- `vendor/API/php-classes/src/Security/Permissions.php`
- `database/sql/2026-06-16_notificacoes_publicacao.sql`

Observacao: o worktree ja possuia outras alteracoes antes desta auditoria, incluindo arquivos de escopo por unidade/capital/interior. Elas nao foram revertidas.

## 9. Validacoes executadas

- Lint PHP dos arquivos alterados principais: aprovado.
- Lint PHP recursivo em `app`, `config` e `public`: aprovado durante a auditoria.
- Verificacao da migration: tabela `tb_notificacoes` criada com 12 colunas e permissoes novas presentes.
- Teste do model de notificacao com insercao temporaria, leitura e remocao: aprovado.
- Renderizacao do header com sessao administrativa simulada: sino renderizado e sem erro PHP no HTML.
- Teste da sugestao de versao: `1.0.66` para `1.0.67`.
- Servidor local respondeu `/admin/login` com HTTP 200 e `/admin` redirecionou sem sessao, como esperado.

## 10. Como testar

### Notificacoes

1. Acessar o painel como usuario com `NOTIFICACOES_VIEW`.
2. Gerar um relatorio/planilha que registre linha em `tb_relatorios_pdf`.
3. Conferir se o sino mostra contador maior que zero.
4. Abrir o sino e conferir titulo, mensagem, horario e link.
5. Clicar em marcar como lida.
6. Conferir se o contador diminui.
7. Gerar mais de uma notificacao e usar `Marcar todas`.

### APIs de notificacao

Com sessao autenticada:

- `GET /admin/api/notificacoes`
- `POST /admin/api/notificacoes/{id}/lida`
- `POST /admin/api/notificacoes/marcar-todas-lidas`

### Publicacao de atualizacao

1. Acessar `/admin/publicar-atualizacao` com usuario autorizado.
2. Conferir o campo `Nova versao` preenchido automaticamente.
3. Clicar no botao de recalcular sugestao.
4. Tentar publicar com versao ja existente e confirmar que o backend bloqueia.
5. Publicar uma versao valida em ambiente de homologacao.
6. Conferir `tb_atualizacoes`, arquivos `version.json`/`version-lite.json`, ZIP gerado e log de publicacao.
7. Conferir se uma notificacao `atualizacao_publicada` aparece no sino.

## 11. Melhorias recomendadas para proxima fase

- Remover token hardcoded de upload de backup e mover para variavel de ambiente.
- Criar uma rota/servico central unico para recebimento de relatorios das unidades, garantindo que qualquer insercao em `tb_relatorios_pdf` gere notificacao.
- Criar testes automatizados para permissao de rotas administrativas e APIs JSON.
- Padronizar todos os endpoints JSON com o mesmo contrato: `success`, `message`, `data`, `errors`.
- Criar migrations formais para todos os indices atualmente dependentes de estrutura existente no banco.
- Revisar e atualizar o `README.md`.
- Adicionar protecao CSRF em formularios administrativos sensiveis.
- Revisar permissoes antigas `NOTIFICACOES_CLEAR` e `NOTIFICACAO_TESTE_CREATE`, mantendo apenas se ainda forem usadas.
- Auditar exposicao publica de `storage`, backups, logs e temporarios no servidor de producao.
- Criar rotina de limpeza/arquivamento para logs operacionais.
- Criar seeds de usuario administrador e ambiente de homologacao para testes reproduziveis.

## 12. Riscos identificados

- As credenciais documentadas nao autenticaram no banco atual, entao a validacao visual autenticada foi feita por simulacao de sessao.
- O recebimento de relatorios pode ocorrer por caminho nao localizado nesta auditoria; se houver insercao direta externa em `tb_relatorios_pdf`, ela ainda nao disparara notificacao automaticamente.
- Existem alteracoes pendentes no worktree anteriores a esta auditoria. Antes de publicar, revisar o diff completo para separar escopo por commit.
- Publicacao real de atualizacao nao foi executada para evitar impacto nas unidades.

## 13. Checklist final

- [x] Rotas principais de relatorios, monitoramento, publicacao e notificacoes revisadas.
- [x] Tabela de notificacoes criada.
- [x] Permissoes de notificacao criadas.
- [x] Sino de notificacoes implementado no painel administrativo.
- [x] APIs para listar e marcar notificacoes implementadas.
- [x] Geracao de notificacao ao registrar relatorio/planilha integrada.
- [x] Geracao de notificacao ao publicar atualizacao integrada.
- [x] Versao automatica implementada na tela `Publicar atualizacao`.
- [x] Validacao contra duplicidade de versao reforcada.
- [x] Middleware de permissao parametrizada corrigido.
- [x] Debug em producao desativado por padrao.
- [x] Logs de erro PHP direcionados para arquivo.
- [x] Lint PHP executado nos arquivos alterados.
- [x] Relatorio tecnico gerado.

## 14. Auditoria de segurança e consistência — 17/10/2026

Foram corrigidos os pontos de maior impacto encontrados na revisão:

- Sessões agora usam cookie `HttpOnly`, `SameSite=Lax`, modo estrito e regeneração do identificador após login.
- Logout limpa os dados da sessão e o cookie; a operação passou de GET para POST protegido.
- Operações administrativas de funcionários, bloqueio de usuários e alteração de senha exigem token CSRF. As requisições AJAX autenticadas recebem o token automaticamente pelo cabeçalho `X-CSRF-Token`.
- Exclusão de funcionário deixou de ser acionada por link GET. O usuário conectado não pode bloquear ou excluir a própria conta.
- Senhas novas exigem no mínimo oito caracteres com letras e números, com validação no servidor.
- A documentação e o script de instalação deixaram de fornecer uma credencial administrativa conhecida.
- O script ACL foi alinhado ao schema efetivamente consumido pela aplicação (`perfil` e os quatro perfis, incluindo CONSULTA), removendo módulos e colunas obsoletos.
- `.htaccess` passou a bloquear acesso direto a configurações, código-fonte, banco, armazenamento, logs e arquivos de backup quando a raiz do repositório for usada como document root.
- O endpoint público de status de atualizações agora falha fechado quando `UPDATE_MONITOR_TOKEN` não está configurado, e o bootstrap envia cabeçalhos básicos de proteção HTTP.
- A auditoria de dependências identificou alertas no Slim 2.0, no Dompdf 3.1.5, no PhpSpreadsheet 2.4.3, no phpseclib 3.0.52 e no firebase/php-jwt 6.11.1. A atualização completa foi deixada para uma etapa isolada, pois o repositório versiona `vendor/` e a troca geraria uma alteração ampla de dependências; o Slim 2.0 exige migração de framework dedicada.
- O segredo JWT deixou de ser fixo no código e passou a exigir `JWT_SECRET` no ambiente; sem essa configuração, a autenticação falha fechada.

Validação desta rodada: lint PHP recursivo aprovado, Composer válido, `git diff --check` aprovado, catálogo ACL 46/46, MySQL ACL 61/61, rotas/menu/exportação 111/111, GADSAN 26/26 + 12/12 + 25/25.
