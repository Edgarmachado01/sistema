<?php
require_once __DIR__.'/auth.php';
requireLogin();
if (!hasRole('SYS_ADMIN') && !hasRole('TENANT_ADMIN')) { http_response_code(403); exit('Sem permissão'); }
require_once __DIR__.'/db.php';

$msg = $err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $temp  = trim($_POST['nova']  ?? ''); // ex: gerar manualmente uma provisória
  if ($email==='' || $temp==='') { $err = 'Preencha e-mail e nova senha temporária.'; }
  else {
    // restringe ao mesmo tenant, exceto SYS_ADMIN global
    $tenant = tenantId();
    if ($tenant) {
      $st = db()->prepare("SELECT id FROM users WHERE email=? AND tenant_id <=> ?");
      $st->execute([$email, $tenant]);
    } else {
      $st = db()->prepare("SELECT id FROM users WHERE email=?");
      $st->execute([$email]);
    }
    $u = $st->fetch();
    if (!$u) { $err = 'Usuário não encontrado neste escopo.'; }
    else {
      $hash = password_hash($temp, PASSWORD_DEFAULT);
      $up = db()->prepare("UPDATE users SET password_hash=?, is_active=1 WHERE id=?");
      $up->execute([$hash, $u['id']]);
      $msg = "Senha temporária definida para {$email}.";
      // (opcional) enviar por e-mail essa temporária
    }
  }
}
?>
<!doctype html><html lang="pt-br"><head>
<meta charset="utf-8"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<title>Resetar senha (admin)</title></head><body class="bg-light">
<div class="container py-4" style="max-width:520px">
  <h5>Resetar senha (admin)</h5>
  <?php if($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
  <?php if($err): ?><div class="alert alert-danger"><?=$err?></div><?php endif; ?>
  <form method="post">
    <div class="mb-3"><label class="form-label">E-mail do usuário</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Senha temporária</label><input type="text" name="nova" class="form-control" placeholder="Ex: CJweb@2025!" required></div>
    <button class="btn btn-warning">Resetar</button>
  </form>
</div>
</body></html>
