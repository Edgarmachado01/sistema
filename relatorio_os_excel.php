<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_relatorio_os_query.php';

requireLogin();

function hfRelOsCsvLabel($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    return ucfirst(str_replace('_', ' ', $value));
}

function hfRelOsCsvDate($value, $withTime = false)
{
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    $ts = strtotime((string)$value);
    if (!$ts) {
        return '';
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

function hfRelOsCsvMoney($value)
{
    return number_format((float)$value, 2, ',', '.');
}

function hfRelOsCsvOutput(array $rows)
{
    $filename = 'relatorio_os_' . date('Ymd_Hi') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    if (!$out) {
        error_log('relatorio_os_excel.php: nao foi possivel abrir php://output');
        echo "Nao foi possivel gerar o arquivo.\n";
        return;
    }

    fputcsv($out, [
        'Nº OS',
        'Cliente',
        'Status',
        'Prioridade',
        'Técnico',
        'Data abertura',
        'Total',
        'Valor pago',
        'Saldo',
        'Status financeiro',
        'Forma pagamento',
        'Data pagamento',
    ], ';');

    foreach ($rows as $row) {
        $total = (float)($row['total'] ?? 0);
        $valorPago = (float)($row['valor_pago'] ?? 0);
        $saldo = max(0, $total - $valorPago);
        $abertura = $row['data_abertura'] ?: ($row['created_at'] ?? '');
        $numero = (int)($row['numero'] ?: $row['id']);

        fputcsv($out, [
            $numero,
            (string)($row['cliente'] ?? ''),
            hfRelOsCsvLabel($row['status'] ?? ''),
            hfRelOsCsvLabel($row['prioridade'] ?? ''),
            (string)($row['tecnico'] ?? ''),
            hfRelOsCsvDate($abertura, true),
            hfRelOsCsvMoney($total),
            hfRelOsCsvMoney($valorPago),
            hfRelOsCsvMoney($saldo),
            hfRelOsCsvLabel($row['status_financeiro'] ?? ''),
            hfRelOsCsvLabel($row['forma_pagto'] ?? ''),
            hfRelOsCsvDate($row['data_pagto'] ?? ''),
        ], ';');
    }

    fclose($out);
}

try {
    $pdo = db();
    $tid = (int)tenantId();
    $result = hfRelOsFetch($pdo, $tid);

    if (!$result['ok']) {
        error_log('relatorio_os_excel.php: ' . ($result['message'] ?? 'falha ao carregar relatorio'));
        hfRelOsCsvOutput([]);
        exit;
    }

    hfRelOsCsvOutput($result['rows']);
} catch (Throwable $e) {
    error_log('relatorio_os_excel.php: ' . $e->getMessage());
    hfRelOsCsvOutput([]);
}

exit;
