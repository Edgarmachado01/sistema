<?php
require_once __DIR__ . '/auth.php';
requireAdmin();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_relatorio_financeiro_query.php';

function hfRelFinCsvClean($value)
{
    $value = (string)$value;
    $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
}

function hfRelFinCsvLabel($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    return ucfirst(str_replace('_', ' ', $value));
}

function hfRelFinCsvDate($value)
{
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    $ts = strtotime((string)$value);
    if (!$ts) {
        return '';
    }

    $hasTime = preg_match('/\d{2}:\d{2}/', (string)$value);
    return date($hasTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

function hfRelFinCsvMoney($value)
{
    return number_format((float)$value, 2, ',', '.');
}

function hfRelFinCsvOutput(array $rows)
{
    $filename = 'relatorio_financeiro_' . date('Ymd_Hi') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    if (!$out) {
        error_log('relatorio_financeiro_excel.php: nao foi possivel abrir php://output');
        echo "Nao foi possivel gerar o arquivo.\n";
        return;
    }

    fputcsv($out, [
        'Origem',
        'Nº OS',
        'Cliente/Descrição',
        'Tipo',
        'Data emissão/lançamento',
        'Vencimento',
        'Pagamento',
        'Forma pagamento',
        'Valor previsto',
        'Valor pago',
        'Saldo',
        'Status',
        'Situação',
    ], ';');

    foreach ($rows as $row) {
        fputcsv($out, [
            hfRelFinCsvClean($row['origem'] ?? ''),
            hfRelFinCsvClean($row['numero_os'] ?? ''),
            hfRelFinCsvClean($row['cliente_descricao'] ?? ''),
            hfRelFinCsvLabel($row['tipo'] ?? ''),
            hfRelFinCsvDate($row['data_emissao'] ?? ''),
            hfRelFinCsvDate($row['vencimento'] ?? ''),
            hfRelFinCsvDate($row['pagamento'] ?? ''),
            hfRelFinCsvClean($row['forma_pagamento'] ?? ''),
            hfRelFinCsvMoney($row['valor_previsto'] ?? 0),
            hfRelFinCsvMoney($row['valor_pago'] ?? 0),
            hfRelFinCsvMoney($row['saldo'] ?? 0),
            hfRelFinCsvLabel($row['status'] ?? ''),
            hfRelFinCsvLabel($row['situacao'] ?? ''),
        ], ';');
    }

    fclose($out);
}

try {
    $pdo = db();
    $tid = (int)tenantId();
    $result = hfRelFinFetch($pdo, $tid);

    if (!$result['ok']) {
        error_log('relatorio_financeiro_excel.php: ' . ($result['message'] ?? 'falha ao carregar relatorio'));
        hfRelFinCsvOutput([]);
        exit;
    }

    hfRelFinCsvOutput($result['rows']);
} catch (Throwable $e) {
    error_log('relatorio_financeiro_excel.php: ' . $e->getMessage());
    hfRelFinCsvOutput([]);
}

exit;
