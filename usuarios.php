<?php
// usuarios.php — Gestão de usuários por empresa (TENANT_ADMIN / ATENDENTE)

require_once __DIR__.'/auth.php';
requireAdmin();

require_once __DIR__.'/db.php';

$pdo = db();

// ===== Tenant atual =====
$tenantId = function_exists('tenantId')
    ? tenantId()
    : ($_SESSION['TENANT_ID'] ?? $_SESSION['tenant_id'] ?? null);

$tenantId = ($tenantId === '' || $tenantId === null) ? null : (int)$tenantId;

// ===== CSRF =====
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ===== Roles permitidos na tela =====
$sqlRoles = "SELECT id, role_key, label_pt
               FROM roles
              WHERE role_key IN ('TENANT_ADMIN', 'ATENDENTE')
              ORDER BY id";
$rolesStmt = $pdo->query($sqlRoles);
$roles     = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$roleLabels = [];
foreach ($roles as $r) {
    $roleLabels[$r['role_key']] = $r['label_pt'] ?: $r['role_key'];
}

// ================= POST: salvar usuário (com redirect SEMPRE) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['acao'] ?? '') === 'salvar_usuario') {

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken    = $_POST['csrf_token'] ?? '';

    if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
        $_SESSION['HF_USERS_FLASH_ERROR'] = 'Sessão expirada. Recarregue a página e tente novamente.';
        header('Location: usuarios.php?m=usuarios');
        exit;
    }

    $idRaw    = $_POST['id'] ?? '';
    $id       = ($idRaw !== '' && ctype_digit((string)$idRaw)) ? (int)$idRaw : null;
    $nome     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $senha    = (string)($_POST['password'] ?? '');
    $roleKey  = $_POST['role_key'] ?? 'ATENDENTE';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $flashError = '';
    $flashOk    = '';

    if ($nome === '' || $email === '') {
        $flashError = 'Nome e e-mail são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flashError = 'E-mail inválido.';
    } elseif (!in_array($roleKey, ['TENANT_ADMIN', 'ATENDENTE'], true)) {
        $flashError = 'Papel inválido.';
    } elseif (!$id && $senha === '') {
        $flashError = 'Senha é obrigatória para novo usuário.';
    } elseif ($senha !== '' && strlen($senha) < 8) {
        $flashError = 'A senha deve ter no mínimo 8 caracteres.';
    }

    if ($flashError === '' && $id) {
        try {
            $stTarget = $pdo->prepare("
                SELECT id
                  FROM users
                 WHERE id = :id
                   AND (tenant_id <=> :tenant_id)
                 LIMIT 1
            ");
            $stTarget->execute([
                ':id'        => $id,
                ':tenant_id' => $tenantId,
            ]);

            if (!$stTarget->fetchColumn()) {
                $flashError = 'Usuário não encontrado neste escopo.';
            }
        } catch (Exception $e) {
            error_log('usuarios.php validar tenant usuário: '.$e->getMessage());
            $flashError = 'Erro ao validar usuário. Tente novamente.';
        }
    }

    if ($flashError === '') {
        try {
            $pdo->beginTransaction();

            // role_id correspondente ao role_key
            $sqlRoleId = "SELECT id FROM roles WHERE role_key = :rk LIMIT 1";
            $stRoleId  = $pdo->prepare($sqlRoleId);
            $stRoleId->execute([':rk' => $roleKey]);
            $roleId = (int)$stRoleId->fetchColumn();

            if (!$roleId) {
                throw new Exception('Role não encontrado.');
            }

            if ($id) {
                // ===== UPDATE =====
                // checa se já existe outro usuário com o mesmo e-mail neste tenant
                $sqlCheck = "SELECT id
                               FROM users
                              WHERE email = :email
                                AND (tenant_id <=> :tenant_id)
                                AND id <> :id";
                $stCheck = $pdo->prepare($sqlCheck);
                $stCheck->execute([
                    ':email'     => $email,
                    ':tenant_id' => $tenantId,
                    ':id'        => $id,
                ]);
                if ($stCheck->fetchColumn()) {
                    $flashError = 'Já existe um usuário com este e-mail nesta empresa.';
                } else {
                    $params = [
                        ':name'      => $nome,
                        ':email'     => $email,
                        ':is_active' => $isActive,
                        ':id'        => $id,
                        ':tenant_id' => $tenantId,
                    ];

                    $sqlUpd = "UPDATE users
                                  SET name = :name,
                                      email = :email,
                                      is_active = :is_active";

                    if ($senha !== '') {
                        $hash     = password_hash($senha, PASSWORD_DEFAULT);
                        $sqlUpd  .= ", password_hash = :password_hash";
                        $params[':password_hash'] = $hash;
                    }

                    $sqlUpd .= " WHERE id = :id
                                   AND (tenant_id <=> :tenant_id)";

                    $stUpd = $pdo->prepare($sqlUpd);
                    $stUpd->execute($params);

                    // Atualiza role somente do usuário já validado no tenant atual
                    $pdo->prepare("
                        DELETE ur
                          FROM user_roles ur
                          JOIN users u ON u.id = ur.user_id
                         WHERE ur.user_id = :uid
                           AND (u.tenant_id <=> :tenant_id)
                    ")->execute([
                        ':uid'       => $id,
                        ':tenant_id' => $tenantId,
                    ]);

                    $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)")
                        ->execute([':uid' => $id, ':rid' => $roleId]);

                    $flashOk = 'Usuário atualizado com sucesso.';
                }

            } else {
                // ===== INSERT =====
                $hash = password_hash($senha, PASSWORD_DEFAULT);

                // Checa duplicidade de e-mail por tenant
                $sqlCheck = "SELECT id
                               FROM users
                              WHERE email = :email
                                AND (tenant_id <=> :tenant_id)";
                $stCheck = $pdo->prepare($sqlCheck);
                $stCheck->execute([
                    ':email'     => $email,
                    ':tenant_id' => $tenantId,
                ]);
                if ($stCheck->fetchColumn()) {
                    $flashError = 'Já existe um usuário com este e-mail nesta empresa.';
                } else {
                    $sqlIns = "INSERT INTO users (name, email, password_hash, tenant_id, is_active, created_at)
                               VALUES (:name, :email, :password_hash, :tenant_id, :is_active, NOW())";
                    $stIns = $pdo->prepare($sqlIns);
                    $stIns->execute([
                        ':name'          => $nome,
                        ':email'         => $email,
                        ':password_hash' => $hash,
                        ':tenant_id'     => $tenantId,
                        ':is_active'     => $isActive,
                    ]);
                    $newUserId = (int)$pdo->lastInsertId();

                    $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)")
                        ->execute([':uid' => $newUserId, ':rid' => $roleId]);

                    $flashOk = 'Usuário criado com sucesso.';
                }
            }

            if ($flashError !== '') {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('usuarios.php salvar_usuario: '.$e->getMessage());

            $msg = $e->getMessage();
            if (strpos($msg, 'uq_tenant_email') !== false) {
                $flashError = 'Já existe um usuário com este e-mail nesta empresa.';
            } else {
                $flashError = 'Erro ao salvar usuário. Tente novamente.';
            }
        }
    }

    // guarda mensagens na sessão e redireciona SEMPRE
    if ($flashError !== '') {
        $_SESSION['HF_USERS_FLASH_ERROR'] = $flashError;
    } elseif ($flashOk !== '') {
        $_SESSION['HF_USERS_FLASH_OK'] = $flashOk;
    }

    header('Location: usuarios.php?m=usuarios');
    exit;
}

// ================= GET: lê mensagens flash =================
$mensagem = '';
$erro     = '';

if (!empty($_SESSION['HF_USERS_FLASH_OK'])) {
    $mensagem = $_SESSION['HF_USERS_FLASH_OK'];
    unset($_SESSION['HF_USERS_FLASH_OK']);
}
if (!empty($_SESSION['HF_USERS_FLASH_ERROR'])) {
    $erro = $_SESSION['HF_USERS_FLASH_ERROR'];
    unset($_SESSION['HF_USERS_FLASH_ERROR']);
}

// ===== Buscar usuários do tenant =====
$paramsList = [':tid' => $tenantId];
$sqlUsers = "
    SELECT u.id,
           u.name,
           u.email,
           u.is_active,
           GROUP_CONCAT(r.role_key ORDER BY r.id SEPARATOR ',') AS roles
      FROM users u
 LEFT JOIN user_roles ur ON ur.user_id = u.id
 LEFT JOIN roles r       ON r.id     = ur.role_id
     WHERE (u.tenant_id <=> :tid)
     GROUP BY u.id, u.name, u.email, u.is_active
     ORDER BY u.name
";

$stUsers = $pdo->prepare($sqlUsers);
$stUsers->execute($paramsList);
$usuarios = $stUsers->fetchAll(PDO::FETCH_ASSOC);

// ===== Usuário em edição (GET ?edit=) =====
$editUser = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($usuarios as $u) {
        if ((int)$u['id'] === $editId) {
            $editUser = $u;
            break;
        }
    }
    if ($editUser) {
        $rolesUser = explode(',', $editUser['roles'] ?? '');
        $editUser['role_key'] = $rolesUser[0] ?: 'ATENDENTE';
    }
}

// ===== Dados do formulário =====
$formName      = '';
$formEmail     = '';
$formRoleKey   = 'ATENDENTE';
$formIsActive  = true;

if ($editUser) {
    $formName     = htmlspecialchars($editUser['name']  ?? '', ENT_QUOTES, 'UTF-8');
    $formEmail    = htmlspecialchars($editUser['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $formRoleKey  = $editUser['role_key'] ?? 'ATENDENTE';
    $formIsActive = !empty($editUser['is_active']);
}

include __DIR__.'/_layout_start.php';
include __DIR__.'/_sidebar.php';
?>

<main class="hf-content hf-users-page">
  <div class="container-fluid py-4 hf-users-wrap">

    <div class="hf-users-hero d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="hf-page-kicker">Administração</div>
        <h4 class="mb-0">
          <i class="bi bi-people-fill me-2"></i>Usuários da Empresa
        </h4>
        <div class="hf-page-subtitle">Gerencie acessos, papéis e status dos usuários.</div>
      </div>

      <a href="usuarios.php?m=usuarios" class="btn btn-sm btn-outline-secondary hf-btn-new-user">
        <i class="bi bi-plus-lg me-1"></i>Novo usuário
      </a>
    </div>

    <?php if ($erro): ?>
      <div class="alert alert-danger hf-users-alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php elseif ($mensagem): ?>
      <div class="alert alert-success hf-users-alert">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Formulário -->
    <div class="hf-users-card mb-3">
      <div class="hf-users-card-head">
        <div class="hf-users-icon">
          <i class="bi <?= $editUser ? 'bi-pencil-square' : 'bi-person-plus' ?>"></i>
        </div>
        <div>
          <h5><?= $editUser ? 'Editar usuário' : 'Novo usuário' ?></h5>
          <p><?= $editUser ? 'Atualize os dados, papel, senha e status do usuário.' : 'Cadastre um novo acesso para esta empresa.' ?></p>
        </div>
      </div>

      <div class="hf-users-card-body">
        <form method="post" autocomplete="off">
          <input type="hidden" name="acao" value="salvar_usuario">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="id"
                 value="<?= $editUser ? (int)$editUser['id'] : '' ?>">

          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Nome</label>
              <div class="hf-input-icon">
                <i class="bi bi-person"></i>
                <input type="text" name="name" class="form-control"
                       autocomplete="off"
                       value="<?=$formName?>">
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label">E-mail</label>
              <div class="hf-input-icon">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" class="form-control"
                       autocomplete="off"
                       value="<?=$formEmail?>">
              </div>
            </div>

            <div class="col-md-2">
              <label class="form-label">
                Senha <?= $editUser ? '(deixe em branco p/ manter)' : '' ?>
              </label>
              <div class="hf-input-icon">
                <i class="bi bi-key"></i>
                <input type="password" name="password" class="form-control"
                       autocomplete="new-password">
              </div>
            </div>

            <div class="col-md-2">
              <label class="form-label">Papel</label>
              <select name="role_key" class="form-select">
                <?php foreach ($roles as $r):
                    $rk = $r['role_key'];
                ?>
                  <option value="<?=$rk?>" <?=$rk === $formRoleKey ? 'selected' : ''?>>
                    <?= htmlspecialchars($r['label_pt'] ?: $rk) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-1">
              <div class="hf-active-box">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                       <?=$formIsActive ? 'checked' : ''?>>
                <label class="form-check-label" for="is_active">
                  Ativo
                </label>
              </div>
            </div>

            <div class="col-md-1">
              <button class="btn btn-primary w-100 hf-btn-save-user">
                <i class="bi bi-save d-md-none d-lg-inline me-1"></i>Salvar
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Lista de usuários -->
    <div class="hf-users-card">
      <div class="hf-users-card-head">
        <div class="hf-users-icon">
          <i class="bi bi-list-check"></i>
        </div>
        <div>
          <h5>Usuários cadastrados</h5>
          <p>Lista de acessos vinculados à empresa atual.</p>
        </div>
      </div>

      <div class="hf-users-card-body p-0">

        <!-- DESKTOP: tabela -->
        <div class="table-responsive d-none d-md-block">
          <table class="table table-sm align-middle mb-0 hf-users-table">
            <thead>
              <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Papel</th>
                <th>Status</th>
                <th style="width:120px">Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
              <?php
                $rolesUser = array_filter(explode(',', $u['roles'] ?? ''));
                $labels = [];
                foreach ($rolesUser as $rk) {
                    $labels[] = $roleLabels[$rk] ?? $rk;
                }
                $roleText = implode(', ', $labels);
              ?>
              <tr>
                <td>
                  <div class="hf-user-cell">
                    <div class="hf-user-avatar">
                      <?= strtoupper(substr((string)$u['name'], 0, 1)) ?>
                    </div>
                    <div>
                      <div class="hf-user-name"><?=htmlspecialchars($u['name'])?></div>
                      <div class="hf-user-id">ID #<?=(int)$u['id']?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="hf-user-email"><?=htmlspecialchars($u['email'])?></span>
                </td>
                <td>
                  <span class="hf-role-badge"><?=htmlspecialchars($roleText)?></span>
                </td>
                <td>
                  <?php if (!empty($u['is_active'])): ?>
                    <span class="badge bg-success hf-status-badge">Ativo</span>
                  <?php else: ?>
                    <span class="badge bg-secondary hf-status-badge">Inativo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="usuarios.php?m=usuarios&edit=<?=(int)$u['id']?>"
                     class="btn btn-sm btn-outline-primary hf-edit-btn">
                    <i class="bi bi-pencil-square me-1"></i>Editar
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- MOBILE: cards -->
        <div class="d-md-none hf-users-mobile-list">
          <?php foreach ($usuarios as $u): ?>
            <?php
              $rolesUser = array_filter(explode(',', $u['roles'] ?? ''));
              $labels = [];
              foreach ($rolesUser as $rk) {
                  $labels[] = $roleLabels[$rk] ?? $rk;
              }
              $roleText = implode(', ', $labels);
              $ativo = !empty($u['is_active']);
            ?>
            <div class="hf-user-mobile-card">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div class="hf-user-cell">
                  <div class="hf-user-avatar">
                    <?= strtoupper(substr((string)$u['name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div class="hf-user-name"><?=htmlspecialchars($u['name'])?></div>
                    <div class="hf-user-email"><?=htmlspecialchars($u['email'])?></div>
                  </div>
                </div>

                <?php if ($ativo): ?>
                  <span class="badge bg-success hf-status-badge">Ativo</span>
                <?php else: ?>
                  <span class="badge bg-secondary hf-status-badge">Inativo</span>
                <?php endif; ?>
              </div>

              <div class="hf-user-mobile-meta">
                <span>Papel</span>
                <strong><?=htmlspecialchars($roleText)?></strong>
              </div>

              <div class="text-end mt-3">
                <a href="usuarios.php?m=usuarios&edit=<?=(int)$u['id']?>"
                   class="btn btn-sm btn-outline-primary hf-edit-btn">
                  <i class="bi bi-pencil-square me-1"></i>Editar
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>

  </div>
</main>

<style>
.hf-users-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-users-wrap {
  max-width: 1480px;
}

.hf-users-hero {
  gap: 1rem;
  padding: .25rem .1rem .55rem;
}

.hf-page-kicker {
  font-size: .74rem;
  font-weight: 800;
  color: rgba(var(--bs-primary-rgb), .88);
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: .12rem;
}

.hf-page-subtitle {
  margin-top: .2rem;
  color: #64748b;
  font-size: .9rem;
}

.hf-btn-new-user,
.hf-btn-save-user,
.hf-edit-btn {
  min-height: 38px;
  border-radius: .72rem;
  font-weight: 800;
}

.hf-btn-new-user {
  white-space: nowrap;
  background: rgba(255, 255, 255, .8);
  border-color: rgba(148, 163, 184, .45);
  box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
}

.hf-btn-save-user {
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

.hf-users-alert {
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: .9rem;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.hf-users-card {
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-users-card-head {
  display: flex;
  align-items: flex-start;
  gap: .85rem;
  padding: 1.1rem 1.15rem;
  border-bottom: 1px solid rgba(226, 232, 240, .9);
  background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .95));
}

.hf-users-card-head h5 {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 800;
}

.hf-users-card-head p {
  margin: .18rem 0 0;
  color: #64748b;
  font-size: .86rem;
}

.hf-users-icon {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: .85rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .10);
  font-size: 1.15rem;
}

.hf-users-card-body {
  padding: 1.15rem;
}

.hf-users-card .form-label {
  margin-bottom: .35rem;
  font-size: .76rem;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.hf-users-card .form-control,
.hf-users-card .form-select {
  min-height: 42px;
  border-radius: .72rem;
  border-color: #dbe3ee;
  background-color: #f8fafc;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
}

.hf-users-card .form-control:focus,
.hf-users-card .form-select:focus {
  border-color: rgba(var(--bs-primary-rgb), .55);
  box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
  background-color: #fff;
}

.hf-input-icon {
  position: relative;
}

.hf-input-icon > i {
  position: absolute;
  left: .85rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.hf-input-icon .form-control {
  padding-left: 2.45rem;
}

.hf-active-box {
  min-height: 42px;
  display: flex;
  align-items: center;
  gap: .45rem;
  padding: .45rem .65rem;
  border: 1px solid #dbe3ee;
  border-radius: .72rem;
  background: #f8fafc;
}

.hf-active-box .form-check-input {
  margin: 0;
}

.hf-active-box .form-check-label {
  color: #475569;
  font-weight: 750;
}

.hf-users-table {
  --bs-table-bg: transparent;
}

.hf-users-table thead th {
  padding: .95rem .9rem;
  border-bottom: 1px solid rgba(148, 163, 184, .28);
  background: #f1f5f9;
  color: #475569;
  font-size: .74rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .055em;
  white-space: nowrap;
}

.hf-users-table tbody td {
  padding: .9rem;
  border-color: rgba(226, 232, 240, .82);
  color: #334155;
}

.hf-users-table tbody tr {
  transition: background-color .14s ease, box-shadow .14s ease;
}

.hf-users-table tbody tr:hover {
  background: rgba(var(--bs-primary-rgb), .045);
  box-shadow: inset 3px 0 0 rgba(var(--bs-primary-rgb), .56);
}

.hf-user-cell {
  display: flex;
  align-items: center;
  gap: .72rem;
  min-width: 0;
}

.hf-user-avatar {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  display: grid;
  place-items: center;
  border-radius: 999px;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .11);
  font-weight: 900;
}

.hf-user-name {
  color: #0f172a;
  font-weight: 800;
  line-height: 1.15;
}

.hf-user-id,
.hf-user-email {
  color: #64748b;
  font-size: .84rem;
}

.hf-role-badge {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  border-radius: 999px;
  padding: .28rem .58rem;
  color: #075985;
  background: #e0f2fe;
  font-size: .78rem;
  font-weight: 800;
}

.hf-status-badge {
  border-radius: 999px;
  padding: .42rem .62rem;
  font-weight: 800;
}

.hf-status-badge.bg-success {
  color: #047857 !important;
  background: #d1fae5 !important;
}

.hf-status-badge.bg-secondary {
  color: #475569 !important;
  background: #e2e8f0 !important;
}

.hf-edit-btn {
  border-color: rgba(var(--bs-primary-rgb), .34);
  background: rgba(var(--bs-primary-rgb), .04);
}

.hf-edit-btn:hover {
  color: #fff;
  background: var(--bs-primary);
  border-color: var(--bs-primary);
}

.hf-users-mobile-list {
  padding: .85rem;
}

.hf-user-mobile-card {
  padding: .95rem;
  border: 1px solid rgba(226, 232, 240, .9);
  border-radius: .95rem;
  background: rgba(248, 250, 252, .82);
  box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
}

.hf-user-mobile-card + .hf-user-mobile-card {
  margin-top: .75rem;
}

.hf-user-mobile-meta {
  display: grid;
  gap: .12rem;
  padding-top: .75rem;
  border-top: 1px solid rgba(226, 232, 240, .9);
}

.hf-user-mobile-meta span {
  color: #64748b;
  font-size: .74rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.hf-user-mobile-meta strong {
  color: #334155;
  font-size: .94rem;
}

@media (max-width: 767.98px) {
  .hf-users-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-users-hero {
    align-items: flex-start !important;
  }

  .hf-users-card-head,
  .hf-users-card-body {
    padding: 1rem;
  }

  .hf-btn-new-user {
    padding: .44rem .62rem;
  }

  .hf-btn-save-user {
    width: 100%;
  }

  .hf-active-box {
    justify-content: flex-start;
  }
}

[data-bs-theme="dark"] .hf-users-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-users-card {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-users-card-head {
  background: linear-gradient(180deg, rgba(30, 41, 59, .95), rgba(17, 24, 39, .95));
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-users-card-head h5,
[data-bs-theme="dark"] .hf-user-name {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-users-card .form-control,
[data-bs-theme="dark"] .hf-users-card .form-select,
[data-bs-theme="dark"] .hf-active-box {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .hf-users-table thead th {
  background: rgba(30, 41, 59, .95);
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-users-table tbody td,
[data-bs-theme="dark"] .hf-user-mobile-meta strong {
  color: #cbd5e1;
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-user-mobile-card {
  background: rgba(15, 23, 42, .82);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-user-mobile-meta {
  border-color: rgba(51, 65, 85, .9);
}
</style>

<?php include __DIR__.'/_layout_end.php'; ?>
