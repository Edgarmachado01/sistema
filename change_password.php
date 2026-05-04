<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curr = $_POST['senha_atual'] ?? '';
    $new1 = $_POST['nova_senha'] ?? '';
    $new2 = $_POST['confirmar'] ?? '';

    if ($new1 !== $new2) {
        $err = 'Confirmação diferente da nova senha.';
    } else {
        // pega usuário atual
        $st = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = ? AND is_active = 1");
        $st->execute([$_SESSION['USER_ID']]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u || !password_verify($curr, $u['password_hash'])) {
            $err = 'Senha atual inválida.';
        } else {
            $hash = password_hash($new1, PASSWORD_DEFAULT);
            $up = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $up->execute([$hash, $u['id']]);
            $msg = 'Senha atualizada com sucesso.';
        }
    }
}

// Layout padrão
include __DIR__.'/_layout_start.php';
include __DIR__.'/_sidebar.php';
?>

<main class="hf-content">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0">Trocar senha</h4>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
  <?php endif; ?>

  <?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($err) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
  <?php endif; ?>

  <!-- Container para centralizar o card -->
  <div class="d-flex justify-content-center mt-3">
    <div class="hf-card p-3 p-md-4 w-100" style="max-width: 520px;">
      <form method="post" autocomplete="off">
        <div class="mb-3">
          <label class="form-label">Senha atual</label>
          <input type="password" name="senha_atual" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Nova senha</label>
          <input type="password" name="nova_senha" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Confirmar nova senha</label>
          <input type="password" name="confirmar" class="form-control" required>
        </div>

        <div class="d-flex justify-content-end">
          <button class="btn btn-primary">
            <i class="bi bi-key me-1"></i> Salvar nova senha
          </button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include __DIR__.'/_layout_end.php'; ?>
