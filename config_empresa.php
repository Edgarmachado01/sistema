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
        'cor_secundaria' => $_POST['cor_secundaria'] ?? '#6c757d',

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

include __DIR__.'/_layout_start.php';
include __DIR__.'/_sidebar.php';
?>

<main class="hf-content">
  <div class="container-fluid py-3">

    <h4 class="mb-3">
      <i class="bi bi-gear-fill me-2"></i>Configurações da Empresa
    </h4>

    <?php if ($erro): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="hf-card">

        <div class="hf-card-header px-3 pt-3 pb-1">
          <strong><i class="bi bi-pencil-square me-1"></i>Edição de Configurações</strong>
        </div>

        <!-- ABAS -->
        <div class="px-3">
          <ul class="nav nav-tabs" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="tab-dados" data-bs-toggle="tab"
                      data-bs-target="#pane-dados" type="button" role="tab">
                <i class="bi bi-building me-1"></i>Dados da Empresa
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-sla" data-bs-toggle="tab"
                      data-bs-target="#pane-sla" type="button" role="tab">
                <i class="bi bi-speedometer2 me-1"></i>SLA / Atendimento
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="tab-branding" data-bs-toggle="tab"
                      data-bs-target="#pane-branding" type="button" role="tab">
                <i class="bi bi-brush me-1"></i>Branding / Logo
              </button>
            </li>
          </ul>
        </div>

        <div class="hf-card-body p-3">
          <div class="tab-content">

            <!-- ===== ABA DADOS ===== -->
            <div class="tab-pane fade show active" id="pane-dados">
              <div class="row mb-3">
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
              </div>

              <div class="row mb-3">
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

              <div class="row mb-3">
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

              <div class="row mb-3">
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
              </div>

              <div class="row mb-3">
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
            <!-- FIM ABA DADOS -->

            <!-- ===== ABA SLA ===== -->
            <div class="tab-pane fade" id="pane-sla">
              <div class="row mb-3">
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

              <h6 class="fw-bold mt-3">SLA por prioridade</h6>

              <div class="row mb-3">
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

              <h6 class="fw-bold mt-3">Horário de atendimento</h6>

              <div class="row mb-3">
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
              </div>

              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="considera_sabado"
                       name="considera_sabado" <?php echo !empty($config['considera_sabado']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="considera_sabado">Considera sábado útil</label>
              </div>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="considera_domingo"
                       name="considera_domingo" <?php echo !empty($config['considera_domingo']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="considera_domingo">Considera domingo útil</label>
              </div>
            </div>
            <!-- FIM ABA SLA -->

            <!-- ===== ABA BRANDING ===== -->
            <div class="tab-pane fade" id="pane-branding">
              <div class="row mb-3">
                <div class="col-md-4">
                  <label class="form-label">Logo (2MB máx)</label>
                  <input type="file" class="form-control" name="logo" accept="image/*">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Cor Primária</label>
                  <input type="color" class="form-control form-control-color"
                         name="cor_primaria"
                         value="<?php echo htmlspecialchars($config['cor_primaria']); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Cor Secundária</label>
                  <input type="color" class="form-control form-control-color"
                         name="cor_secundaria"
                         value="<?php echo htmlspecialchars($config['cor_secundaria']); ?>">
                </div>
              </div>

              <?php if (!empty($config['logo_path'])): ?>
                <div class="mb-3">
                  <label class="form-label d-block">Logo atual:</label>
                  <img src="<?php echo htmlspecialchars($config['logo_path']); ?>"
                       alt="Logo atual"
                       style="max-height:80px;border-radius:6px;">
                </div>
              <?php endif; ?>
            </div>
            <!-- FIM ABA BRANDING -->

          </div>
        </div>

        <div class="hf-card-footer text-end p-3">
          <button class="btn btn-primary">
            <i class="bi bi-save me-1"></i>Salvar Configurações
          </button>
        </div>

      </div>
    </form>

  </div>
</main>

<?php include __DIR__.'/_layout_end.php'; ?>
