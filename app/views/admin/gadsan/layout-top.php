<?php if (!defined('APP_DIR')) { http_response_code(404); exit; } ?>
<link rel="stylesheet" href="/res/admin/gadsan/module.css">
<main class="app-main gadsan-module"><div class="app-content px-3 px-md-4 py-4">
<section class="portal-page-header">
  <span class="portal-kicker">Gestão de pessoas · GADSAN</span>
  <h1 class="portal-page-title">Colaboradores e cadastros</h1>
  <p class="portal-page-subtitle">Organize os dados pessoais, as atividades profissionais e a formação de cada colaborador.</p>
</section>
<nav class="gadsan-tabs" aria-label="Seções GADSAN">
  <?php if ($canView): ?><a href="/admin/gadsan">Colaboradores</a><?php endif ?>
  <?php if ($canAux): ?><a href="/admin/gadsan/auxiliares">Cadastros auxiliares</a><?php endif ?>
  <?php if ($canImport): ?><a href="/admin/gadsan/importacoes">Importações e revisão</a><?php endif ?>
</nav>
<?php if ($flash): ?><div class="alert alert-success" role="status"><?= ge($flash) ?></div><?php endif ?>
