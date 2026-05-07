<?php
if (!isset($siteTitle) || trim((string)$siteTitle) === '') {
  $siteTitle = 'HelpDesk Facil';
}

$siteDescription = $siteDescription ?? 'Sistema online para assistencias tecnicas organizarem OS, clientes, financeiro e relatorios.';
$siteBodyClass = $siteBodyClass ?? '';
?><!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="/assets/site.css?v=1" rel="stylesheet">
</head>
<body class="hf-site-body <?= htmlspecialchars($siteBodyClass, ENT_QUOTES, 'UTF-8') ?>">
  <header class="hf-site-header">
    <nav class="navbar navbar-expand-lg hf-site-navbar" aria-label="Navegacao publica">
      <div class="container hf-site-nav-inner">
        <a class="navbar-brand hf-site-brand" href="/">
          <img src="/logo.png" alt="HelpDesk Facil" class="hf-site-logo">
        </a>

        <button class="navbar-toggler hf-site-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#hfSiteNav" aria-controls="hfSiteNav" aria-expanded="false" aria-label="Abrir menu">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="hfSiteNav">
          <ul class="navbar-nav mx-lg-auto hf-site-links">
            <li class="nav-item">
              <a class="nav-link" href="/#recursos">Recursos</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/planos.php">Planos</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/#faq">FAQ</a>
            </li>
          </ul>

          <div class="hf-site-actions">
            <a class="btn btn-link hf-site-login" href="/login.php">Entrar</a>
            <a class="btn btn-primary hf-btn-primary" href="/cadastro.php">
              Come&ccedil;ar teste gr&aacute;tis
              <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
            </a>
          </div>
        </div>
      </div>
    </nav>
  </header>

  <main class="hf-site-main">
