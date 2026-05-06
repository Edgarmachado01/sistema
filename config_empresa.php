<?php
// config_empresa.php — Configurações da Empresa / SLA / Branding

require_once __DIR__.'/auth.php';
requireAdmin();

require_once __DIR__.'/db.php';

$pdo = db();

// ===== Tenant =====
$tid = function_exists('tenantId')
    ? (int) tenantId()
    : (int) ($_SESSION['tenant_id'] ?? 0);

if ($tid <= 0) {
    http_response_code(400);
    echo 'Tenant inválido.';
    exit;
}

// ===== Carrega config existente =====
$stmt = $pdo->prepare("SELECT * FROM tenant_config WHERE tenant_id = :tid LIMIT 1");
$stmt->execute([':tid' => $tid]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    $config = [
        'id'                         => null,
        'razao_social'              => '',
        'nome_fantasia'             => '',
        'cnpj'                      => '',
        'ie'                        => '',
        'im'                        => '',
        'telefone'                  => '',
        'whatsapp'                  => '',
        'email'                     => '',
        'site'                      => '',
        'cep'                       => '',
        'endereco'                  => '',
        'numero'                    => '',
        'complemento'               => '',
        'bairro'                    => '',
        'cidade'                    => '',
        'uf'                        => '',
        'logo_path'                 => '',
        'cor_primaria'              => '#0d6efd',
        'cor_secundaria'            => '#6c757d',
        'sla_prazo_resposta_min'    => 30,
        'sla_prazo_solucao_padrao'  => 48,
        'sla_baixa_horas'           => 72,
        'sla_media_horas'           => 48,
        'sla_alta_horas'            => 24,
        'sla_critica_horas'         => 4,
        'horario_inicio'            => '08:00:00',
        'horario_fim'               => '18:00:00',
        'considera_sabado'          => 0,
        'considera_domingo'         => 0,
    ];
}

$erro    = '';
$sucesso = '';

// ===== POST (salvar) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'razao_social'   => $_POST['razao_social']   ?? '',
        'nome_fantasia'  => $_POST['nome_fantasia']  ?? '',
        'cnpj'           => $_POST['cnpj']           ?? '',
        'ie'             => $_POST['ie']             ?? '',
        'im'             => $_POST['im']             ?? '',
        'telefone'       => $_POST['telefone']       ?? '',
        'whatsapp'       => $_POST['whatsapp']       ?? '',
        'email'          => $_POST['email']          ?? '',
        'site'           => $_POST['site']           ?? '',
        'cep'            => $_POST['cep']            ?? '',
        'endereco'       => $_POST['endereco']       ?? '',
        'numero'         => $_POST['numero']         ?? '',
        'complemento'    => $_POST['complemento']    ?? '',
        'bairro'         => $_POST['bairro']         ?? '',
        'cidade'         => $_POST['cidade']         ?? '',
        'uf'             => $_POST['uf']             ?? '',
        'cor_primaria'   => $_POST['cor_primaria']   ?? '#0d6efd',
        'cor_secundaria' => $_POST['cor_secundaria'] ?? ($config['cor_secundaria'] ?? '#6c757d'),

        'sla_prazo_resposta_min'    => (int)($_POST['sla_prazo_resposta_min']    ?? 30),
        'sla_prazo_solucao_padrao'  => (int)($_POST['sla_prazo_solucao_padrao'] ?? 48),
        'sla_baixa_horas'           => (int)($_POST['sla_baixa_horas']          ?? 72),
        'sla_media_horas'           => (int)($_POST['sla_media_horas']          ?? 48),
        'sla_alta_horas'            => (int)($_POST['sla_alta_horas']           ?? 24),
        'sla_critica_horas'         => (int)($_POST['sla_critica_horas']        ?? 4),
        'horario_inicio'            => $_POST['horario_inicio']                 ?? '08:00',
        'horario_fim'               => $_POST['horario_fim']                    ?? '18:00',
        'considera_sabado'          => isset($_POST['considera_sabado']) ? 1 : 0,
        'considera_domingo'         => isset($_POST['considera_domingo']) ? 1 : 0,
    ];

    // ===== Upload do logo (apenas sobrescrever) =====
    $logo_path = $config['logo_path'] ?? null;

    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/logos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $tmpName = $_FILES['logo']['tmp_name'];
        $size    = (int) $_FILES['logo']['size'];
        $name    = $_FILES['logo']['name'];

        if ($size > (2 * 1024 * 1024)) {
            $erro = 'Logo maior que 2MB. Reduza o tamanho da imagem.';
        } else {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $extValidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $extValidas)) {
                $erro = 'Formato de logo inválido. Use JPG, PNG, GIF ou WEBP.';
            } else {
                $novoNome      = 'logo_tenant_'.$tid.'.'.$ext;
                $destino       = $uploadDir.'/'.$novoNome;
                $novoCaminhoRel = 'uploads/logos/'.$novoNome;

                // se já existe logo, tenta apagar arquivo antigo (independente da extensão)
                if (!empty($logo_path)) {
                    $antigoAbs = __DIR__ . '/' . $logo_path;
                    if (is_file($antigoAbs)) {
                        @unlink($antigoAbs);
                    }
                }

                if (move_uploaded_file($tmpName, $destino)) {
                    $logo_path = $novoCaminhoRel;
                } else {
                    $erro = 'Falha ao salvar logo.';
                }
            }
        }
    }

    // mantém o logo atual se não mandou nada ou se deu erro
    $dados['logo_path'] = $logo_path;

    if (!$erro) {
        if (!empty($config['id'])) {
            $sql = "UPDATE tenant_config SET
                razao_social = :razao_social,
                nome_fantasia = :nome_fantasia,
                cnpj = :cnpj,
                ie = :ie,
                im = :im,
                telefone = :telefone,
                whatsapp = :whatsapp,
                email = :email,
                site = :site,
                cep = :cep,
                endereco = :endereco,
                numero = :numero,
                complemento = :complemento,
                bairro = :bairro,
                cidade = :cidade,
                uf = :uf,
                logo_path = :logo_path,
                cor_primaria = :cor_primaria,
                cor_secundaria = :cor_secundaria,
                sla_prazo_resposta_min = :sla_prazo_resposta_min,
                sla_prazo_solucao_padrao = :sla_prazo_solucao_padrao,
                sla_baixa_horas = :sla_baixa_horas,
                sla_media_horas = :sla_media_horas,
                sla_alta_horas = :sla_alta_horas,
                sla_critica_horas = :sla_critica_horas,
                horario_inicio = :horario_inicio,
                horario_fim = :horario_fim,
                considera_sabado = :considera_sabado,
                considera_domingo = :considera_domingo
            WHERE tenant_id = :tenant_id";
        } else {
            $sql = "INSERT INTO tenant_config (
                tenant_id, razao_social, nome_fantasia, cnpj, ie, im, telefone,
                whatsapp, email, site, cep, endereco, numero, complemento,
                bairro, cidade, uf, logo_path, cor_primaria, cor_secundaria,
                sla_prazo_resposta_min, sla_prazo_solucao_padrao,
                sla_baixa_horas, sla_media_horas, sla_alta_horas, sla_critica_horas,
                horario_inicio, horario_fim, considera_sabado, considera_domingo
            ) VALUES (
                :tenant_id, :razao_social, :nome_fantasia, :cnpj, :ie, :im, :telefone,
                :whatsapp, :email, :site, :cep, :endereco, :numero, :complemento,
                :bairro, :cidade, :uf, :logo_path, :cor_primaria, :cor_secundaria,
                :sla_prazo_resposta_min, :sla_prazo_solucao_padrao,
                :sla_baixa_horas, :sla_media_horas, :sla_alta_horas, :sla_critica_horas,
                :horario_inicio, :horario_fim, :considera_sabado, :considera_domingo
            )";
        }

        $stmtSave = $pdo->prepare($sql);
        $params = $dados;
        $params['tenant_id'] = $tid;

        try {
            $stmtSave->execute($params);
            $sucesso = 'Configurações salvas com sucesso.';

            $stmtReload = $pdo->prepare("SELECT * FROM tenant_config WHERE tenant_id = :tid LIMIT 1");
            $stmtReload->execute([':tid' => $tid]);
            $config = $stmtReload->fetch(PDO::FETCH_ASSOC) ?: $config;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar configurações: '.$e->getMessage();
        }
    }
}

$corPrimariaInput = trim((string)($config['cor_primaria'] ?? ''));
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $corPrimariaInput)) {
    $corPrimariaInput = '#0d6efd';
}

include __DIR__.'/_layout_start.php';
include __DIR__.'/_sidebar.php';
?>

<main class="hf-content hf-config-page">
  <div class="container-fluid py-4 hf-config-wrap">

    <div class="hf-config-hero d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="hf-page-kicker">Administração</div>
        <h4 class="mb-0">
          <i class="bi bi-gear-fill me-2"></i>Configurações da Empresa
        </h4>
        <div class="hf-page-subtitle">Dados cadastrais, atendimento, SLA e identidade visual</div>
      </div>
    </div>

    <?php if ($erro): ?>
      <div class="alert alert-danger hf-alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($erro); ?>
      </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
      <div class="alert alert-success hf-alert">
        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($sucesso); ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="hf-config-shell">

        <div class="hf-config-card">
          <div class="hf-config-card-head">
            <div class="hf-config-icon"><i class="bi bi-building"></i></div>
            <div>
              <h5>Dados da empresa</h5>
              <p>Identificação fiscal e nome exibido no sistema.</p>
            </div>
          </div>

          <div class="hf-config-card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Razão Social</label>
                <input class="form-control" name="razao_social"
                       value="<?php echo htmlspecialchars($config['razao_social']); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Nome Fantasia</label>
                <input class="form-control" name="nome_fantasia"
                       value="<?php echo htmlspecialchars($config['nome_fantasia']); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">CNPJ</label>
                <input class="form-control" name="cnpj"
                       value="<?php echo htmlspecialchars($config['cnpj']); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Inscrição Estadual</label>
                <input class="form-control" name="ie"
                       value="<?php echo htmlspecialchars($config['ie']); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Inscrição Municipal</label>
                <input class="form-control" name="im"
                       value="<?php echo htmlspecialchars($config['im']); ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="hf-config-card">
          <div class="hf-config-card-head">
            <div class="hf-config-icon"><i class="bi bi-chat-dots"></i></div>
            <div>
              <h5>Contato</h5>
              <p>Canais comerciais e de atendimento.</p>
            </div>
          </div>

          <div class="hf-config-card-body">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Telefone</label>
                <input class="form-control" name="telefone"
                       value="<?php echo htmlspecialchars($config['telefone']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">WhatsApp</label>
                <input class="form-control" name="whatsapp"
                       value="<?php echo htmlspecialchars($config['whatsapp']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">E-mail</label>
                <input class="form-control" name="email"
                       value="<?php echo htmlspecialchars($config['email']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Site</label>
                <input class="form-control" name="site"
                       value="<?php echo htmlspecialchars($config['site']); ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="hf-config-card">
          <div class="hf-config-card-head">
            <div class="hf-config-icon"><i class="bi bi-geo-alt"></i></div>
            <div>
              <h5>Endereço</h5>
              <p>Localização da empresa para documentos e cadastros.</p>
            </div>
          </div>

          <div class="hf-config-card-body">
            <div class="row g-3">
              <div class="col-md-2">
                <label class="form-label">CEP</label>
                <input class="form-control" name="cep"
                       value="<?php echo htmlspecialchars($config['cep']); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Endereço</label>
                <input class="form-control" name="endereco"
                       value="<?php echo htmlspecialchars($config['endereco']); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Número</label>
                <input class="form-control" name="numero"
                       value="<?php echo htmlspecialchars($config['numero']); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Complemento</label>
                <input class="form-control" name="complemento"
                       value="<?php echo htmlspecialchars($config['complemento']); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Bairro</label>
                <input class="form-control" name="bairro"
                       value="<?php echo htmlspecialchars($config['bairro']); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Cidade</label>
                <input class="form-control" name="cidade"
                       value="<?php echo htmlspecialchars($config['cidade']); ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">UF</label>
                <input class="form-control" name="uf"
                       value="<?php echo htmlspecialchars($config['uf']); ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="hf-config-card">
          <div class="hf-config-card-head">
            <div class="hf-config-icon"><i class="bi bi-sliders"></i></div>
            <div>
              <h5>Preferências/configurações</h5>
              <p>SLA, horário de atendimento, cores e logo do painel.</p>
            </div>
          </div>

          <div class="hf-config-card-body">
            <div class="hf-config-section-title">
              <i class="bi bi-speedometer2"></i>SLA geral
            </div>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Prazo resposta (minutos)</label>
                <input type="number" class="form-control" name="sla_prazo_resposta_min"
                       value="<?php echo htmlspecialchars($config['sla_prazo_resposta_min']); ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Prazo solução padrão (horas)</label>
                <input type="number" class="form-control" name="sla_prazo_solucao_padrao"
                       value="<?php echo htmlspecialchars($config['sla_prazo_solucao_padrao']); ?>">
              </div>
            </div>

            <div class="hf-config-section-title mt-4">
              <i class="bi bi-flag"></i>SLA por prioridade
            </div>

            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Baixa (horas)</label>
                <input type="number" class="form-control" name="sla_baixa_horas"
                       value="<?php echo htmlspecialchars($config['sla_baixa_horas']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Média (horas)</label>
                <input type="number" class="form-control" name="sla_media_horas"
                       value="<?php echo htmlspecialchars($config['sla_media_horas']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Alta (horas)</label>
                <input type="number" class="form-control" name="sla_alta_horas"
                       value="<?php echo htmlspecialchars($config['sla_alta_horas']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Crítica (horas)</label>
                <input type="number" class="form-control" name="sla_critica_horas"
                       value="<?php echo htmlspecialchars($config['sla_critica_horas']); ?>">
              </div>
            </div>

            <div class="hf-config-section-title mt-4">
              <i class="bi bi-clock"></i>Horário de atendimento
            </div>

            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label">Início</label>
                <input type="time" class="form-control" name="horario_inicio"
                       value="<?php echo htmlspecialchars(substr($config['horario_inicio'], 0, 5)); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Fim</label>
                <input type="time" class="form-control" name="horario_fim"
                       value="<?php echo htmlspecialchars(substr($config['horario_fim'], 0, 5)); ?>">
              </div>
              <div class="col-md-6">
                <div class="hf-switch-panel">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="considera_sabado"
                           name="considera_sabado" <?php echo !empty($config['considera_sabado']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="considera_sabado">Considera sábado útil</label>
                  </div>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="considera_domingo"
                           name="considera_domingo" <?php echo !empty($config['considera_domingo']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="considera_domingo">Considera domingo útil</label>
                  </div>
                </div>
              </div>
            </div>

            <div class="hf-config-section-title mt-4">
              <i class="bi bi-brush"></i>Branding / Logo
            </div>

            <div class="row g-3">
              <div class="col-lg-5">
                <div class="hf-logo-box">
                  <div class="hf-logo-preview">
                    <?php if (!empty($config['logo_path'])): ?>
                      <img src="<?php echo htmlspecialchars($config['logo_path']); ?>"
                           alt="Logo atual">
                    <?php else: ?>
                      <div class="hf-logo-placeholder">
                        <i class="bi bi-image"></i>
                        <span>Logo atual</span>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="hf-logo-upload">
                    <label class="form-label">Logo (2MB máx)</label>
                    <input type="file" class="form-control" name="logo" accept="image/*">
                    <small class="text-muted">Use JPG, PNG, GIF ou WEBP.</small>
                  </div>
                </div>
              </div>

              <div class="col-lg-7">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Cor Primária</label>
                    <div class="hf-color-field">
                      <input type="color" class="form-control form-control-color"
                            name="cor_primaria"
                            value="<?php echo htmlspecialchars($corPrimariaInput); ?>">
                      <span><?php echo htmlspecialchars($corPrimariaInput); ?></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="hf-config-actions">
          <button type="reset" class="btn btn-outline-secondary hf-btn-cancel">
            <i class="bi bi-x-lg me-1"></i>Cancelar
          </button>
          <button class="btn btn-primary hf-btn-save">
            <i class="bi bi-save me-1"></i>Salvar Configurações
          </button>
        </div>

      </div>
    </form>

  </div>
</main>

<style>
.hf-config-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-config-wrap {
  max-width: 1480px;
}

.hf-config-hero {
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

.hf-alert {
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: .9rem;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.hf-config-shell {
  display: grid;
  gap: 1rem;
}

.hf-config-card {
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-config-card-head {
  display: flex;
  align-items: flex-start;
  gap: .85rem;
  padding: 1.1rem 1.15rem;
  border-bottom: 1px solid rgba(226, 232, 240, .9);
  background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .95));
}

.hf-config-card-head h5 {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 800;
}

.hf-config-card-head p {
  margin: .18rem 0 0;
  color: #64748b;
  font-size: .86rem;
}

.hf-config-icon {
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

.hf-config-card-body {
  padding: 1.15rem;
}

.hf-config-card .form-label {
  margin-bottom: .35rem;
  font-size: .76rem;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.hf-config-card .form-control {
  min-height: 42px;
  border-radius: .72rem;
  border-color: #dbe3ee;
  background-color: #f8fafc;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
}

.hf-config-card .form-control:focus {
  border-color: rgba(var(--bs-primary-rgb), .55);
  box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
  background-color: #fff;
}

.hf-config-section-title {
  display: flex;
  align-items: center;
  gap: .45rem;
  margin-bottom: .8rem;
  color: #334155;
  font-size: .84rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: .055em;
}

.hf-config-section-title i {
  color: var(--bs-primary);
}

.hf-switch-panel {
  display: flex;
  flex-wrap: wrap;
  gap: .75rem 1rem;
  min-height: 42px;
  align-items: center;
  padding: .55rem .75rem;
  border: 1px solid rgba(219, 227, 238, .95);
  border-radius: .72rem;
  background: #f8fafc;
}

.hf-switch-panel .form-check {
  margin: 0;
}

.hf-switch-panel .form-check-label {
  color: #475569;
  font-weight: 650;
}

.hf-logo-box {
  display: grid;
  grid-template-columns: 128px minmax(0, 1fr);
  gap: 1rem;
  align-items: center;
  padding: .85rem;
  border: 1px dashed rgba(148, 163, 184, .55);
  border-radius: .95rem;
  background: #f8fafc;
}

.hf-logo-preview {
  width: 128px;
  height: 96px;
  display: grid;
  place-items: center;
  overflow: hidden;
  border: 1px solid rgba(226, 232, 240, .95);
  border-radius: .85rem;
  background: #fff;
}

.hf-logo-preview img {
  max-width: 100%;
  max-height: 80px;
  object-fit: contain;
}

.hf-logo-placeholder {
  display: grid;
  place-items: center;
  gap: .25rem;
  color: #94a3b8;
  font-size: .78rem;
  font-weight: 700;
}

.hf-logo-placeholder i {
  font-size: 1.6rem;
}

.hf-logo-upload small {
  display: block;
  margin-top: .45rem;
}

.hf-color-field {
  display: flex;
  align-items: center;
  gap: .75rem;
  min-height: 42px;
  padding: .35rem .55rem;
  border: 1px solid #dbe3ee;
  border-radius: .72rem;
  background: #f8fafc;
}

.hf-color-field .form-control-color {
  width: 52px;
  height: 34px;
  min-height: 34px;
  padding: .15rem;
  border-radius: .55rem;
  background: #fff;
}

.hf-color-field span {
  color: #475569;
  font-weight: 750;
  font-size: .9rem;
}

.hf-config-actions {
  position: sticky;
  bottom: 0;
  z-index: 10;
  display: flex;
  justify-content: flex-end;
  gap: .65rem;
  padding: 1rem;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .92);
  box-shadow: 0 -8px 26px rgba(15, 23, 42, .08);
  backdrop-filter: blur(8px);
}

.hf-btn-save,
.hf-btn-cancel {
  min-height: 40px;
  border-radius: .72rem;
  font-weight: 800;
  padding-left: .95rem;
  padding-right: .95rem;
}

.hf-btn-save {
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

@media (max-width: 767.98px) {
  .hf-config-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-config-hero {
    align-items: flex-start !important;
  }

  .hf-config-card-head,
  .hf-config-card-body {
    padding: 1rem;
  }

  .hf-logo-box {
    grid-template-columns: 1fr;
  }

  .hf-logo-preview {
    width: 100%;
  }

  .hf-config-actions {
    flex-direction: column-reverse;
  }

  .hf-config-actions .btn {
    width: 100%;
  }
}

[data-bs-theme="dark"] .hf-config-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-config-card,
[data-bs-theme="dark"] .hf-config-actions {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-config-card-head {
  background: linear-gradient(180deg, rgba(30, 41, 59, .95), rgba(17, 24, 39, .95));
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-config-card-head h5 {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-config-section-title,
[data-bs-theme="dark"] .hf-switch-panel .form-check-label {
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-config-card .form-control,
[data-bs-theme="dark"] .hf-switch-panel,
[data-bs-theme="dark"] .hf-logo-box,
[data-bs-theme="dark"] .hf-color-field {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .hf-logo-preview {
  background: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .hf-color-field span {
  color: #cbd5e1;
}
</style>

<?php include __DIR__.'/_layout_end.php'; ?>
