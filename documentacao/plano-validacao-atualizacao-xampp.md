# Plano de Validacao Real - Atualizacao Automatica Windows/XAMPP

Data: 2026-06-01

## Objetivo

Validar em uma unidade de teste Windows/XAMPP o fluxo completo de atualizacao do `prato_web` publicado pelo `API_RELATORIO_PRATO`, antes de liberar o pacote para as 18 unidades.

## Ambiente minimo

- Windows com XAMPP e Apache ativo.
- VHOST `prato.com.br` apontando para `C:/xampp/htdocs/prato_cheio/public`.
- Projeto `prato_web` com `.env` local da unidade.
- Acesso ao portal `API_RELATORIO_PRATO`.
- Banco central de homologacao ou base central com autorizacao de teste.
- Backup manual do diretório do sistema antes do teste.

## Preparacao

1. Confirmar que `prato.com.br/admin/login` retorna 200.
2. Confirmar que `prato.com.br/admin` sem sessao retorna 302 para login.
3. Registrar versao atual local em `storage/updates/version-local.json`.
4. Copiar `.env` local para area segura fora do projeto.
5. Confirmar que `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` pertencem a unidade e nao devem ser alterados.
6. Confirmar no portal que `version.json` e `version-lite.json` apontam para a mesma versao, ZIP e checksum.

## Teste ponta a ponta

1. Publicar uma versao de homologacao pelo portal central.
2. Confirmar que o ZIP publicado contem `.env.update`.
3. Confirmar que o ZIP nao contem `.env`, `.env.local`, `.zip`, `.log`, `.bak`, caminhos absolutos ou `../`.
4. Na unidade de teste, acessar `http://prato.com.br/admin`.
5. Iniciar ou aguardar a consulta automatica de atualizacao.
6. Validar nos logs da unidade:
   - consulta de atualizacao;
   - download iniciado;
   - download concluido;
   - checksum validado;
   - backup criado;
   - arquivos instalados;
   - `.env.update` aplicado;
   - status final `concluido` ou `ocioso`.
7. Confirmar que o backup foi criado em `storage/backup/updates`.
8. Comparar `.env` antes/depois:
   - `DB_*` preservados;
   - variaveis SMTP atualizadas;
   - variaveis de monitoramento atualizadas;
   - nenhuma senha foi gravada em log sem mascara.
9. Recarregar `http://prato.com.br/admin/login` e confirmar 200.
10. Acessar dashboard autenticado e confirmar que a tela carrega sem aviso permanente de atualizacao.

## Validacao API central

1. Confirmar que a unidade enviou status para `/api/updates/status`.
2. Confirmar registro em:
   - `tb_atualizacoes`;
   - `tb_atualizacoes_unidades`;
   - `tb_logs_atualizacoes`;
   - `tb_status_atualizacao`.
3. Confirmar no dashboard `Monitoramento de Atualizacoes`:
   - unidade identificada sem usar nome do banco;
   - versao instalada correta;
   - ultima comunicacao atualizada;
   - status final correto;
   - mensagens com acentos, `&`, `<`, `>`, aspas e caracteres especiais sem quebrar JavaScript.

## Validacao com falha da API central

1. Bloquear temporariamente acesso da unidade ao portal central.
2. Rodar consulta/instalacao de atualizacao.
3. Confirmar que a atualizacao local continua.
4. Confirmar que `storage/updates/update-monitor-queue.jsonl` recebe eventos pendentes.
5. Restaurar acesso ao portal.
6. Confirmar que a fila e reenviada e removida automaticamente.

## Validacao SQL em homologacao

1. Executar `database/sql/2026-06-01_update_monitoring.sql` na base central de homologacao.
2. Confirmar que nao ha `DROP`, `TRUNCATE`, `DELETE` ou `ALTER` destrutivo.
3. Confirmar criacao/compatibilidade das tabelas:
   - `tb_atualizacoes`;
   - `tb_atualizacoes_unidades`;
   - `tb_logs_atualizacoes`;
   - `tb_status_atualizacao`.
4. Confirmar indices:
   - unidade;
   - versao;
   - status;
   - data/comunicacao.
5. Inserir um status de teste e remover apenas esse registro.
6. Confirmar que as tabelas existem somente na base central do `API_RELATORIO_PRATO`.

## Evidencias a anexar

- Print de `/admin/login` com 200.
- Print do dashboard carregado apos atualizacao.
- Trecho de `storage/logs/update.log` da unidade.
- Trecho de `storage/logs/update-publish.log` do portal.
- Resultado da comparacao `.env` antes/depois com senhas mascaradas.
- Resultado SQL de `SHOW TABLES LIKE 'tb_%atualizac%'`.
- Resultado SQL de `SHOW INDEX FROM tb_atualizacoes_unidades`.
- Registro da unidade no dashboard central.

## Criterio de liberacao

Liberar para as 18 unidades somente se:

- unidade de teste atualizou sem erro fatal;
- dashboard da unidade carregou apos atualizar;
- `.env` preservou `DB_*`;
- SMTP e URLs globais chegaram via `.env.update`;
- status chegou ao banco central;
- dashboard central exibiu a unidade corretamente;
- API central offline nao bloqueou a atualizacao local.
