<?php
require_once __DIR__.'/_admin_auth.php';
requireSaasAdmin();

$adminName = trim((string)($_SESSION['SAAS_ADMIN_NAME'] ?? ''));
$adminEmail = trim((string)($_SESSION['SAAS_ADMIN_EMAIL'] ?? ''));

if ($adminName === '' && $adminEmail !== '') {
    $adminName = trim(strstr($adminEmail, '@', true) ?: $adminEmail);
}
if ($adminName === '') {
    $adminName = 'Admin SaaS';
}

$adminInitial = strtoupper(substr($adminName, 0, 1));
if ($adminInitial === '') {
    $adminInitial = 'A';
}

$adminPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

function hfAdminNavActive($page, array $matches)
{
    return in_array($page, $matches, true) ? 'active' : '';
}
?><!doctype html>
<html lang="pt-br" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin SaaS - HelpDesk Facil</title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --hf-admin-bg: #f4f7fb;
      --hf-admin-ink: #0f172a;
      --hf-admin-muted: #64748b;
      --hf-admin-line: rgba(148, 163, 184, .24);
      --hf-admin-primary: #0d6efd;
      --hf-admin-primary-rgb: 13, 110, 253;
      --hf-admin-blue: #0d6efd;
      --hf-admin-green: #16a34a;
      --hf-admin-orange: #f97316;
      --hf-admin-red: #dc2626;
      --hf-admin-sidebar: #0f172a;
    }

    body {
      min-height: 100vh;
      margin: 0;
      background: var(--hf-admin-bg);
      color: var(--hf-admin-ink);
    }

    .btn-primary {
      --bs-btn-bg: var(--hf-admin-primary);
      --bs-btn-border-color: var(--hf-admin-primary);
      --bs-btn-hover-bg: #0b5ed7;
      --bs-btn-hover-border-color: #0a58ca;
      --bs-btn-active-bg: #0a58ca;
      --bs-btn-active-border-color: #0a53be;
    }

    .btn-outline-primary {
      --bs-btn-color: var(--hf-admin-primary);
      --bs-btn-border-color: var(--hf-admin-primary);
      --bs-btn-hover-bg: var(--hf-admin-primary);
      --bs-btn-hover-border-color: var(--hf-admin-primary);
      --bs-btn-active-bg: var(--hf-admin-primary);
      --bs-btn-active-border-color: var(--hf-admin-primary);
    }

    .text-primary {
      color: var(--hf-admin-primary) !important;
    }

    .hf-admin-shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 270px minmax(0, 1fr);
    }

    .hf-admin-sidebar {
      position: sticky;
      top: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      padding: 1rem;
      color: #fff;
      background:
        radial-gradient(circle at 20% 8%, rgba(var(--hf-admin-primary-rgb), .30), transparent 18rem),
        linear-gradient(180deg, #0f172a 0%, #111827 100%);
      box-shadow: 10px 0 30px rgba(15, 23, 42, .10);
    }

    .hf-admin-brand {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: .55rem;
      min-height: 70px;
      padding: .75rem .7rem 1.2rem;
      border-bottom: 1px solid rgba(255, 255, 255, .10);
      font-weight: 900;
    }

    .hf-admin-brand-mark {
      width: 100%;
      min-height: 58px;
      display: inline-flex;
      align-items: center;
      justify-content: flex-start;
      overflow: hidden;
      border-radius: .95rem;
      padding: .55rem .7rem;
      background: rgba(255, 255, 255, .96);
      box-shadow: 0 12px 28px rgba(0, 0, 0, .16);
    }

    .hf-admin-brand-logo {
      max-width: 180px;
      max-height: 46px;
      object-fit: contain;
    }

    .hf-admin-brand-title {
      padding-left: .15rem;
    }

    .hf-admin-brand small {
      display: block;
      color: rgba(255, 255, 255, .56);
      font-size: .74rem;
      font-weight: 700;
    }

    .hf-admin-nav {
      display: grid;
      gap: .35rem;
      padding-top: 1rem;
    }

    .hf-admin-nav a {
      display: flex;
      align-items: center;
      gap: .7rem;
      min-height: 42px;
      padding: .65rem .75rem;
      border-radius: .78rem;
      color: rgba(255, 255, 255, .72);
      text-decoration: none;
      font-weight: 750;
      transition: background .16s ease, color .16s ease;
    }

    .hf-admin-nav a:hover,
    .hf-admin-nav a.active {
      color: #fff;
      background: rgba(255, 255, 255, .11);
    }

    .hf-admin-nav i {
      width: 1.25rem;
      text-align: center;
    }

    .hf-admin-sidebar-foot {
      margin-top: auto;
      padding-top: 1rem;
      border-top: 1px solid rgba(255, 255, 255, .10);
    }

    .hf-admin-profile {
      display: flex;
      align-items: center;
      gap: .7rem;
      padding: .65rem;
      border-radius: .9rem;
      background: rgba(255, 255, 255, .08);
    }

    .hf-admin-avatar {
      width: 38px;
      height: 38px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 38px;
      border-radius: 50%;
      color: #0f172a;
      background: #fff;
      font-weight: 900;
    }

    .hf-admin-profile strong {
      display: block;
      color: #fff;
      font-size: .88rem;
      line-height: 1.2;
    }

    .hf-admin-profile small {
      display: block;
      max-width: 155px;
      overflow: hidden;
      color: rgba(255, 255, 255, .56);
      font-size: .74rem;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .hf-admin-main {
      min-width: 0;
      display: flex;
      flex-direction: column;
    }

    .hf-admin-topbar {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      min-height: 68px;
      padding: .85rem 1.5rem;
      border-bottom: 1px solid var(--hf-admin-line);
      background: rgba(255, 255, 255, .86);
      backdrop-filter: blur(10px);
    }

    .hf-admin-topbar h1 {
      margin: 0;
      font-size: 1.08rem;
      font-weight: 900;
    }

    .hf-admin-topbar span {
      color: var(--hf-admin-muted);
      font-size: .86rem;
    }

    .hf-admin-content {
      width: 100%;
      max-width: 1280px;
      margin: 0 auto;
      padding: 1.5rem;
    }

    .hf-admin-card {
      border: 1px solid var(--hf-admin-line);
      border-radius: 1rem;
      background: #fff;
      box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
    }

    .hf-admin-kpis {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 1rem;
    }

    .hf-admin-kpi {
      position: relative;
      overflow: hidden;
      padding: 1.1rem;
    }

    .hf-admin-kpi::after {
      content: "";
      position: absolute;
      right: -2rem;
      top: -2rem;
      width: 7rem;
      height: 7rem;
      border-radius: 50%;
      background: rgba(var(--hf-admin-primary-rgb), .08);
    }

    .hf-admin-kpi small {
      display: flex;
      align-items: center;
      gap: .45rem;
      color: var(--hf-admin-muted);
      font-size: .78rem;
      font-weight: 850;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .hf-admin-kpi strong {
      display: block;
      margin-top: .65rem;
      font-size: 1.85rem;
      font-weight: 950;
      letter-spacing: 0;
    }

    .hf-admin-kpi span {
      display: block;
      margin-top: .15rem;
      color: var(--hf-admin-muted);
      font-size: .85rem;
    }

    .hf-admin-table {
      margin: 0;
      vertical-align: middle;
    }

    .hf-admin-table th {
      color: var(--hf-admin-muted);
      font-size: .74rem;
      font-weight: 850;
      text-transform: uppercase;
      letter-spacing: .04em;
      border-bottom-color: var(--hf-admin-line);
    }

    .hf-admin-table td {
      border-bottom-color: rgba(226, 232, 240, .86);
    }

    .hf-status-pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .28rem .55rem;
      border-radius: 999px;
      background: #eef2ff;
      color: #3730a3;
      font-size: .76rem;
      font-weight: 850;
      white-space: nowrap;
    }

    .hf-status-pill.is-ativo { background: #dcfce7; color: #166534; }
    .hf-status-pill.is-trial { background: #e0f2fe; color: #075985; }
    .hf-status-pill.is-bloqueado { background: #fee2e2; color: #991b1b; }
    .hf-status-pill.is-vencido { background: #ffedd5; color: #9a3412; }
    .hf-status-pill.is-cancelado { background: #f1f5f9; color: #475569; }

    @media (max-width: 1080px) {
      .hf-admin-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 860px) {
      .hf-admin-shell {
        grid-template-columns: 1fr;
      }

      .hf-admin-sidebar {
        position: static;
        height: auto;
        border-radius: 0 0 1.1rem 1.1rem;
      }

      .hf-admin-nav {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .hf-admin-topbar {
        position: static;
        align-items: flex-start;
        flex-direction: column;
      }
    }

    @media (max-width: 600px) {
      .hf-admin-content {
        padding: 1rem;
      }

      .hf-admin-kpis,
      .hf-admin-nav {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="hf-admin-shell">
    <aside class="hf-admin-sidebar">
      <div class="hf-admin-brand">
        <span class="hf-admin-brand-mark">
          <img src="/logo.png" alt="HelpDesk Facil" class="hf-admin-brand-logo">
        </span>
        <div class="hf-admin-brand-title">
          HelpDesk Facil
          <small>Admin SaaS</small>
        </div>
      </div>

      <nav class="hf-admin-nav" aria-label="Navegacao admin">
        <a class="<?= hfAdminNavActive($adminPage, ['admin_dashboard.php']) ?>" href="/admin_dashboard.php">
          <i class="bi bi-speedometer2"></i>Dashboard
        </a>
        <a class="<?= hfAdminNavActive($adminPage, ['admin_tenants.php', 'admin_tenant_form.php']) ?>" href="/admin_tenants.php">
          <i class="bi bi-buildings"></i>Empresas
        </a>
        <a class="<?= hfAdminNavActive($adminPage, ['admin_subscriptions.php']) ?>" href="/admin_subscriptions.php">
          <i class="bi bi-credit-card-2-front"></i>Assinaturas
        </a>
        <a href="/admin_logout.php">
          <i class="bi bi-box-arrow-right"></i>Logout
        </a>
      </nav>

      <div class="hf-admin-sidebar-foot">
        <div class="hf-admin-profile">
          <span class="hf-admin-avatar"><?= htmlspecialchars($adminInitial, ENT_QUOTES, 'UTF-8') ?></span>
          <div>
            <strong><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?></small>
          </div>
        </div>
      </div>
    </aside>

    <main class="hf-admin-main">
      <header class="hf-admin-topbar">
        <div>
          <h1>Central Admin SaaS</h1>
          <span>Empresas, trials, planos e saude comercial da plataforma.</span>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="/admin_tenants.php">
          <i class="bi bi-buildings me-1"></i>Ver empresas
        </a>
      </header>

      <div class="hf-admin-content">
