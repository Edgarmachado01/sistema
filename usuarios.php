<?php
// usuarios.php — Gestão de usuários por empresa (TENANT_ADMIN / ATENDENTE)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/auth.php';
requireLogin();
if (!isAdminLoja()) {
    http_response_code(403);
    header('Location: /dashboard.php');
    exit('Acesso negado.');
}

require_once __DIR__.'/db.php';

$pdo = db();

// ===== Tenant atual =====
$tenantId = function_exists('tenantId')
    ? tenantId()
    : ($_SESSION['TENANT_ID'] ?? $_SESSION['tenant_id'] ?? null);

$tenantId = ($tenantId === '' || $tenantId === null) ? null : (int)$tenantId;

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

    $id       = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
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
    } else {
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
                    throw new Exception('Já existe um usuário com este e-mail nesta empresa.');
                }

                $params = [
                    ':name'      => $nome,
                    ':email'     => $email,
                    ':is_active' => $isActive,
                    ':id'        => $id,
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

                $sqlUpd .= " WHERE id = :id";

                $stUpd = $pdo->prepare($sqlUpd);
                $stUpd->execute($params);

                // Atualiza role
                $pdo->prepare("DELETE FROM user_roles WHERE user_id = :uid")
                    ->execute([':uid' => $id]);

                $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)")
                    ->execute([':uid' => $id, ':rid' => $roleId]);

                $flashOk = 'Usuário atualizado com sucesso.';

            } else {
                // ===== INSERT =====
                if ($senha === '') {
                    throw new Exception('Senha é obrigatória para novo usuário.');
                }

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
                    throw new Exception('Já existe um usuário com este e-mail nesta empresa.');
                }

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

            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = $e->getMessage();
            if (strpos($msg, 'Já existe um usuário com este e-mail nesta empresa.') !== false ||
                strpos($msg, 'uq_tenant_email') !== false) {
                $flashError = 'Já existe um usuário com este e-mail nesta empresa.';
            } else {
                $flashError = 'Erro ao salvar usuário: '.$msg;
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

<main class="hf-content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Usuários da Empresa</h4>
    <a href="usuarios.php?m=usuarios" class="btn btn-sm btn-outline-secondary">Novo usuário</a>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?=$erro?></div>
  <?php elseif ($mensagem): ?>
    <div class="alert alert-success"><?=$mensagem?></div>
  <?php endif; ?>

  <!-- Formulário -->
  <div class="card mb-3">
    <div class="card-body">
      <form method="post" autocomplete="off">
        <input type="hidden" name="acao" value="salvar_usuario">
        <input type="hidden" name="id"
               value="<?= $editUser ? (int)$editUser['id'] : '' ?>">

        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Nome</label>
            <input type="text" name="name" class="form-control"
                   autocomplete="off"
                   value="<?=$formName?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control"
                   autocomplete="off"
                   value="<?=$formEmail?>">
          </div>

          <div class="col-md-2">
            <label class="form-label">
              Senha <?= $editUser ? '(deixe em branco p/ manter)' : '' ?>
            </label>
            <input type="password" name="password" class="form-control"
                   autocomplete="new-password">
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
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                     <?=$formIsActive ? 'checked' : ''?>>
              <label class="form-check-label" for="is_active">
                Ativo
              </label>
            </div>
          </div>

          <div class="col-md-1">
            <button class="btn btn-primary w-100">Salvar</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Lista de usuários -->
  <div class="card">
    <div class="card-body">

      <!-- DESKTOP: tabela -->
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0">
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
              <td><?=htmlspecialchars($u['name'])?></td>
              <td><?=htmlspecialchars($u['email'])?></td>
              <td><?=htmlspecialchars($roleText)?></td>
              <td>
                <?php if (!empty($u['is_active'])): ?>
                  <span class="badge bg-success">Ativo</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inativo</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="usuarios.php?m=usuarios&edit=<?=(int)$u['id']?>"
                   class="btn btn-sm btn-outline-primary">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- MOBILE: cards -->
      <div class="d-md-none">
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
          <div class="border rounded-3 p-3 mb-2">
            <div class="fw-semibold mb-1"><?=htmlspecialchars($u['name'])?></div>
            <div class="small text-muted mb-1"><?=htmlspecialchars($u['email'])?></div>
            <div class="small mb-1">
              <strong>Papel:</strong> <?=htmlspecialchars($roleText)?>
            </div>
            <div class="mb-2">
              <?php if ($ativo): ?>
                <span class="badge bg-success">Ativo</span>
              <?php else: ?>
                <span class="badge bg-secondary">Inativo</span>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <a href="usuarios.php?m=usuarios&edit=<?=(int)$u['id']?>"
                 class="btn btn-sm btn-outline-primary">Editar</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </div>
</main>

<?php include __DIR__.'/_layout_end.php'; ?>
