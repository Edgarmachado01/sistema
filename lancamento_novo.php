<?php
require_once 'db.php';
require_once 'auth.php';

$pdo = db();
$tid = tenantId();

if ($_POST) {
    $tipo = $_POST['tipo'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $data = $_POST['data'];
    $forma_pagamento = $_POST['forma_pagamento'];
    $observacao = $_POST['observacao'];

    $stmt = $pdo->prepare("
        INSERT INTO lancamentos (tenant_id, tipo, descricao, valor, data, forma_pagamento, observacao)
        VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->execute([$tid,$tipo,$descricao,$valor,$data,$forma_pagamento,$observacao]);

    header("Location: lancamentos.php");
    exit;
}

require '_layout_start.php';
?>

<div class="container mt-4">

    <h4>Novo Lançamento</h4>

    <form method="POST" class="mt-3">

        <div class="form-group mb-3">
            <label>Tipo</label>
            <select name="tipo" class="form-control" required>
                <option value="entrada">Entrada</option>
                <option value="saida">Saída</option>
            </select>
        </div>

        <div class="form-group mb-3">
            <label>Descrição</label>
            <input type="text" name="descricao" class="form-control" required>
        </div>

        <div class="form-group mb-3">
            <label>Valor</label>
            <input type="number" step="0.01" name="valor" class="form-control" required>
        </div>

        <div class="form-group mb-3">
            <label>Data</label>
            <input type="date" name="data" class="form-control" required>
        </div>

        <div class="form-group mb-3">
            <label>Forma de Pagamento</label>
            <input type="text" name="forma_pagamento" class="form-control">
        </div>

        <div class="form-group mb-3">
            <label>Observação</label>
            <textarea name="observacao" class="form-control"></textarea>
        </div>

        <button class="btn btn-success btn-block">Salvar</button>

    </form>
</div>

<?php require '_layout_end.php'; ?>
