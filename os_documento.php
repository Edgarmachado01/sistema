<?php
// os_documento.php — Documento completo da OS

// Se quiser ligar debug em dev, descomenta:
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) {
    die('Tenant inválido.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$erro  = '';
$os    = null;
$itens = [];

if ($id <= 0) {
    $erro = 'OS inválida.';
} else {

    // === OS + Cliente (usando os campos reais da hf_os) ===
    $sqlOs = "
        SELECT 
            o.*,
            c.nome AS cliente_nome
        FROM hf_os o
        JOIN hf_clientes c ON c.id = o.cliente_id
        WHERE o.tenant_id  = :tid
          AND o.deleted_at IS NULL
          AND c.deleted_at IS NULL
          AND o.id         = :id
        LIMIT 1
    ";
    $st = $pdo->prepare($sqlOs);
    $st->execute([
        ':tid' => $tid,
        ':id'  => $id,
    ]);
    $os = $st->fetch(PDO::FETCH_ASSOC);

    if (!$os) {
        $erro = 'OS não encontrada.';
    } else {

        // === Itens da OS (hf_os_itens) ===
        $sqlItens = "
            SELECT 
                tipo,
                descricao,
                qtd,
                valor_unit,
                total
            FROM hf_os_itens
            WHERE os_id = :id
            ORDER BY id
        ";
        $sti = $pdo->prepare($sqlItens);
        $sti->execute([':id' => $id]);
        $itens = $sti->fetchAll(PDO::FETCH_ASSOC);
    }
}

// helpers
function hfData($dt) {
    if (!$dt) return '-';
    return date('d/m/Y H:i', strtotime($dt));
}
function hfDataSimples($dt) {
    if (!$dt) return '-';
    return date('d/m/Y', strtotime($dt));
}
function hfMoeda($v) {
    if ($v === null) return '-';
    return 'R$ '.number_format((float)$v, 2, ',', '.');
}

include __DIR__.'/_sidebar.php';
?>
<main class="hf-content">

  <?php if ($erro): ?>
    <div class="hf-card p-4">
      <h4 class="mb-2">Documento da OS</h4>
      <p class="text-danger mb-0"><?= htmlspecialchars($erro) ?></p>
    </div>
  <?php else: ?>

    <div class="hf-card p-3 p-md-4 os-doc-card">
      <!-- Cabeçalho da OS -->
      <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
        <div>
          <h4 class="mb-1">Ordem de Serviço #<?= (int)($os['numero'] ?? $os['id']); ?></h4>
          <div class="text-muted" style="font-size:.9rem;">
            Abertura: 
            <strong><?= hfData($os['data_abertura'] ?? $os['created_at'] ?? null); ?></strong><br>
            Status: 
            <strong><?= htmlspecialchars(ucfirst(str_replace('_',' ',$os['status'] ?? ''))); ?></strong>
          </div>
        </div>
        <div class="text-end">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print();">
            <i class="bi bi-printer me-1"></i>Imprimir / PDF
          </button>
        </div>
      </div>

      <!-- Dados do Cliente -->
      <section class="mb-3">
        <h6 class="text-uppercase text-muted mb-2" style="letter-spacing:.06em;">Dados do Cliente</h6>
        <div style="font-size:.95rem;">
          <div><strong>Nome:</strong> <?= htmlspecialchars($os['cliente_nome'] ?? ''); ?></div>
          <!-- quando tiver telefone, documento, endereço, a gente pluga aqui -->
        </div>
      </section>

      <!-- Informações da OS -->
      <section class="mb-3">
        <h6 class="text-uppercase text-muted mb-2" style="letter-spacing:.06em;">Informações da OS</h6>
        <div class="row g-2" style="font-size:.95rem;">
          <div class="col-md-6">
            <div><strong>Prioridade:</strong> <?= htmlspecialchars(ucfirst($os['prioridade'] ?? '')); ?></div>
            <div><strong>Técnico:</strong> <?= htmlspecialchars($os['tecnico'] ?? ''); ?></div>
          </div>
          <div class="col-md-6 text-md-end">
            <div>
              <strong>Garantia:</strong>
              <?php
                $gDias = (int)($os['garantia_dias'] ?? 0);
                $gAte  = $os['garantia_ate'] ?? null;
                if ($gDias > 0 && $gAte) {
                    echo $gDias.' dias (até '.hfDataSimples($gAte).')';
                } elseif ($gDias > 0) {
                    echo $gDias.' dias';
                } elseif ($gAte) {
                    echo 'Até '.hfDataSimples($gAte);
                } else {
                    echo '-';
                }
              ?>
            </div>
            <div><strong>Total da OS:</strong> <?= hfMoeda($os['total'] ?? 0); ?></div>
          </div>
        </div>
      </section>

      <!-- Detalhes do Serviço -->
      <section class="mb-3">
        <h6 class="text-uppercase text-muted mb-2" style="letter-spacing:.06em;">Detalhes do Serviço</h6>

        <div class="mb-2" style="font-size:.95rem;">
          <strong>Defeito reclamado:</strong>
          <div class="border rounded p-2 mt-1">
            <?= nl2br(htmlspecialchars($os['defeito'] ?? '')); ?>
          </div>
        </div>

        <div class="mb-2" style="font-size:.95rem;">
          <strong>Laudo técnico / serviço executado:</strong>
          <div class="border rounded p-2 mt-1">
            <?= nl2br(htmlspecialchars($os['laudo'] ?? '')); ?>
          </div>
        </div>

        <div class="mb-2" style="font-size:.95rem;">
          <strong>Observações:</strong>
          <div class="border rounded p-2 mt-1">
            <?= nl2br(htmlspecialchars($os['observacoes'] ?? '')); // se criar esse campo no futuro já fica pronto ?>
          </div>
        </div>
      </section>

      <!-- Itens: serviços / produtos -->
      <?php if (!empty($itens)): ?>
        <section class="mb-3">
          <h6 class="text-uppercase text-muted mb-2" style="letter-spacing:.06em;">Itens da OS (Serviços / Produtos)</h6>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th style="width:12%;">Tipo</th>
                  <th>Descrição</th>
                  <th class="text-center" style="width:10%;">Qtde</th>
                  <th class="text-end" style="width:15%;">Vlr Unit.</th>
                  <th class="text-end" style="width:15%;">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $subtotalItens = 0;
                  foreach ($itens as $it):
                    $linhaTotal = (float)($it['total'] ?? ($it['qtd'] * $it['valor_unit']));
                    $subtotalItens += $linhaTotal;
                ?>
                  <tr>
                    <td><?= htmlspecialchars($it['tipo'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($it['descricao'] ?? ''); ?></td>
                    <td class="text-center"><?= (float)($it['qtd'] ?? 0); ?></td>
                    <td class="text-end"><?= hfMoeda($it['valor_unit'] ?? 0); ?></td>
                    <td class="text-end"><?= hfMoeda($linhaTotal); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <?php
                  $maoObra  = (float)($os['valor_mao_obra'] ?? 0);
                  $desconto = (float)($os['desconto'] ?? 0);
                  $acresc   = (float)($os['acrescimo'] ?? 0);
                  $totalOs  = (float)($os['total'] ?? 0);
                ?>
                <tr>
                  <th colspan="4" class="text-end">Subtotal itens</th>
                  <th class="text-end"><?= hfMoeda($subtotalItens); ?></th>
                </tr>
                <tr>
                  <th colspan="4" class="text-end">Mão de obra</th>
                  <th class="text-end"><?= hfMoeda($maoObra); ?></th>
                </tr>
                <tr>
                  <th colspan="4" class="text-end">Acréscimo</th>
                  <th class="text-end"><?= hfMoeda($acresc); ?></th>
                </tr>
                <tr>
                  <th colspan="4" class="text-end">Desconto</th>
                  <th class="text-end">- <?= hfMoeda($desconto); ?></th>
                </tr>
                <tr>
                  <th colspan="4" class="text-end">Total da OS</th>
                  <th class="text-end"><?= hfMoeda($totalOs); ?></th>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <!-- Pagamento -->
      <section class="mb-3">
        <h6 class="text-uppercase text-muted mb-2" style="letter-spacing:.06em;">Pagamento</h6>
        <?php
          $sf = $os['status_financeiro'] ?? 'pendente';
          $sfMap = [
            'pendente' => 'Pendente',
            'parcial'  => 'Parcial',
            'pago'     => 'Pago',
          ];
          $sfLabel = $sfMap[$sf] ?? ucfirst($sf);

          $fp = $os['forma_pagto'] ?? '';
          $fpMap = [
            ''               => 'Não informado',
            'dinheiro'       => 'Dinheiro',
            'cartao'         => 'Cartão',
            'pix'            => 'Pix',
            'boleto'         => 'Boleto',
            'transferencia'  => 'Transferência',
          ];
          $fpLabel = $fpMap[$fp] ?? ucfirst($fp);

          $valorPago = hfMoeda($os['valor_pago'] ?? 0);
          $dataPagto = !empty($os['data_pagto']) ? hfDataSimples($os['data_pagto']) : '-';
        ?>
        <div class="row g-2" style="font-size:.95rem;">
          <div class="col-md-4">
            <div><strong>Status financeiro:</strong> <?= htmlspecialchars($sfLabel); ?></div>
          </div>
          <div class="col-md-4">
            <div><strong>Forma de pagamento:</strong> <?= htmlspecialchars($fpLabel); ?></div>
          </div>
          <div class="col-md-2">
            <div><strong>Valor pago:</strong> <?= $valorPago; ?></div>
          </div>
          <div class="col-md-2">
            <div><strong>Data pagamento:</strong> <?= $dataPagto; ?></div>
          </div>
        </div>
      </section>

      <!-- Termo + Assinaturas -->
      <section class="mt-4 pt-3 border-top">
        <div class="mb-3" style="font-size:.8rem;color:#6c757d;">
          Ao assinar este documento, o cliente declara estar ciente dos serviços realizados, valores informados
          e condições de garantia. Qualquer intervenção de terceiros ou uso inadequado pode acarretar perda de garantia.
        </div>

        <div class="row mt-3" style="font-size:.8rem;">
          <div class="col-md-6 text-center mb-4 mb-md-0">
            <div class="assinatura-linha">___________________________________________</div>
            <div>Assinatura do Cliente</div>
          </div>
          <div class="col-md-6 text-center">
            <div class="assinatura-linha">___________________________________________</div>
            <div>Assinatura da Empresa</div>
          </div>
        </div>

        <div class="text-center text-muted mt-3" style="font-size:.75rem;">
          Documento gerado pelo sistema de Assistência Técnica.
        </div>
      </section>
    </div>

  <?php endif; ?>

</main>

<?php require_once __DIR__.'/_layout_end.php'; ?>

<style>
.os-doc-card{
  max-width: 980px;
  margin: 0 auto;
  background:#fff;
}
.assinatura-linha{
  margin-bottom:4px;
}
@media print {
  body{
    background:#fff !important;
  }
  .hf-sidebar,
  .hf-topbar,
  .btn,
  .btn *{
    display:none !important;
  }
  .hf-content{
    margin:0 !important;
    padding:0 !important;
  }
  .os-doc-card{
    box-shadow:none !important;
    border:none !important;
    margin:0 !important;
    padding:0.5cm !important;
  }
}
</style>
