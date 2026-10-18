<?php if (!defined('APP_DIR')) { http_response_code(404); exit; } ?>
<link rel="stylesheet" href="/res/admin/gadsan/module.css">
<main class="app-main gadsan-module">
  <div class="app-content px-3 px-md-4 py-4">
    <div class="gadsan-shell">
      <section class="gadsan-hero" aria-labelledby="gadsan-page-title">
        <div class="gadsan-hero-copy">
          <span class="gadsan-eyebrow"><i class="bi bi-people-fill" aria-hidden="true"></i> Gestão de pessoas · GADSAN</span>
          <h1 id="gadsan-page-title">Colaboradores e cadastros</h1>
          <p>Organize dados pessoais, vínculos profissionais e formação em um só lugar.</p>
        </div>
        <div class="gadsan-hero-icon" aria-hidden="true"><i class="bi bi-person-vcard"></i></div>
      </section>

      <nav class="gadsan-tabs" aria-label="Seções GADSAN">
        <?php if ($canView): ?><a href="/admin/gadsan"><i class="bi bi-person-lines-fill" aria-hidden="true"></i><span>Colaboradores</span></a><?php endif ?>
        <?php if ($canAux): ?><a href="/admin/gadsan/auxiliares"><i class="bi bi-sliders2" aria-hidden="true"></i><span>Cadastros auxiliares</span></a><?php endif ?>
        <?php if ($canImport): ?><a href="/admin/gadsan/importacoes"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i><span>Importações e revisão</span></a><?php endif ?>
      </nav>

      <?php if ($flash): ?><div class="gadsan-alert gadsan-alert-success" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span><?= ge($flash) ?></span></div><?php endif ?>
