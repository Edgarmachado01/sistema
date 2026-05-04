<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

// carrega branding se existir brand.php
$brand = ['name'=>'Help Fácil','primary'=>'#0d6efd','mode'=>'light','logo'=>null,'slug'=>null];
if (file_exists(__DIR__.'/brand.php')) {
  require_once __DIR__.'/brand.php';
  $brand = brandFromRequest();
}

$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $slug  = trim($_POST['empresa'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['senha'] ?? '');

  $tenant = $slug !== '' ? findTenantBySlug($slug) : null;
  $tenantId = $tenant['id'] ?? null;

  $user = findUserByEmail($tenantId, $email);
  if ($user && password_verify($pass, $user['password_hash'])) {
    $_SESSION['USER_ID']   = $user['id'];
    $_SESSION['TENANT_ID'] = $user['tenant_id'];              // null => SYS_ADMIN
    $_SESSION['ROLES']     = getUserRoles($user['id']);
    $_SESSION['TENANT_SLUG'] = $tenant['slug'] ?? null;
    header('Location: /home.php');
    exit;
  } else {
    $err = 'Usuário/senha/empresa inválidos ou inativos.';
  }
}
?><!doctype html>
<html lang="pt-br" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — Help Fácil</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f8fb}
    .hf-card{width:100%;max-width:420px;border:1px solid rgba(0,0,0,.06);border-radius:14px}
    .brand-dot{width:14px;height:14px;border-radius:100%;display:inline-block;margin-right:.5rem;background:var(--bs-primary)}
  </style>
  <?php if (function_exists('echoBrandStyle')) echoBrandStyle($brand); ?>
</head>
<body>
  <div class="card shadow-sm hf-card">
    <div class="card-body p-4">
      <div class="text-center mb-3">
        <?php if (!empty($brand['logo'])): ?>
          <img src="<?=htmlspecialchars($brand['logo'])?>" alt="Logo" style="max-height:48px">
        <?php else: ?>
          <span class="brand-dot"></span><span class="fw-semibold"><?=htmlspecialchars($brand['name'])?></span>
        <?php endif; ?>
      </div>
      <?php if($err): ?><div class="alert alert-danger py-2"><?=$err?></div><?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Empresa (slug)</label>
          <input name="empresa" class="form-control" placeholder="ex: cjweb (deixe vazio para SYS_ADMIN)">
        </div>
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input name="email" type="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Senha</label>
          <input name="senha" type="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Entrar</button>
      </form>
      <div class="text-center mt-3">
        <small class="text-muted">Tema detectado para <b><?=htmlspecialchars($brand['slug'] ?? 'padrão')?></b></small>
      </div>
    </div>
  </div>
</body>
</html>
