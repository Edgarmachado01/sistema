<?php
require_once __DIR__ . '/auth.php';
requireLogin();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_relatorio_clientes_query.php';

function hfRelCliCsvClean($value)
{
    $value = (string)$value;
    $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
}

function hfRelCliCsvLabel($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $labels = [
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
        'novo' => 'Novo',
        'recorrente' => 'Recorrente',
        'sem_os' => 'Sem OS',
        'com_os' => 'Com OS',
        'sem_os_periodo' => 'Sem OS no período',
        'aberta' => 'Aberta',
        'aberto' => 'Aberto',
        'em_andamento' => 'Em andamento',
        'andamento' => 'Em andamento',
        'concluida' => 'Concluída',
        'concluído' => 'Concluído',
        'cancelada' => 'Cancelada',
        'cancelado' => 'Cancelado',
    ];

    $key = strtolower($value);
    if (isset($labels[$key])) {
        return $labels[$key];
    }

    return ucfirst(str_replace('_', ' ', $value));
}

function hfRelCliCsvDate($value)
{
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    $ts = strtotime((string)$value);
    if (!$ts) {
        return '';
    }

    return date('d/m/Y', $ts);
}

function hfRelCliCsvMoney($value)
{
    return number_format((float)$value, 2, ',', '.');
}

function hfRelCliCsvOutput(array $rows)
{
    $filename = 'relatorio_clientes_' . date('Ymd_Hi') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    if (!$out) {
        error_log('relatorio_clientes_excel.php: nao foi possivel abrir php://output');
        echo "Nao foi possivel gerar o arquivo.\n";
        return;
    }

    fputcsv($out, [
        'Cliente',
        'Documento',
        'Telefone',
        'Cidade',
        'Bairro',
        'UF',
        'Status',
        'Total OS',
        'OS no período',
        'Última OS',
        'Status última OS',
        'Total faturado',
        'Valor recebido',
        'Saldo aberto',
        'Ticket médio',
        'Perfil',
    ], ';');

    foreach ($rows as $row) {
        fputcsv($out, [
            hfRelCliCsvClean($row['cliente'] ?? ''),
            hfRelCliCsvClean($row['documento'] ?? ''),
            hfRelCliCsvClean($row['telefone'] ?? ''),
            hfRelCliCsvClean($row['cidade'] ?? ''),
            hfRelCliCsvClean($row['bairro'] ?? ''),
            hfRelCliCsvClean($row['uf'] ?? ''),
            hfRelCliCsvLabel($row['status_cliente'] ?? ''),
            (int)($row['total_os'] ?? 0),
            (int)($row['os_periodo'] ?? 0),
            hfRelCliCsvDate($row['ultima_os'] ?? ''),
            hfRelCliCsvLabel($row['status_ultima_os'] ?? ''),
            hfRelCliCsvMoney($row['total_faturado'] ?? 0),
            hfRelCliCsvMoney($row['valor_recebido'] ?? 0),
            hfRelCliCsvMoney($row['saldo_aberto'] ?? 0),
            hfRelCliCsvMoney($row['ticket_medio'] ?? 0),
            hfRelCliCsvLabel($row['perfil_cliente'] ?? ''),
        ], ';');
    }

    fclose($out);
}

try {
    $pdo = db();
    $tid = (int)tenantId();
    $result = hfRelCliFetch($pdo, $tid);

    if (!$result['ok']) {
        error_log('relatorio_clientes_excel.php: ' . ($result['message'] ?? 'falha ao carregar relatorio'));
        hfRelCliCsvOutput([]);
        exit;
    }

    hfRelCliCsvOutput($result['rows']);
} catch (Throwable $e) {
    error_log('relatorio_clientes_excel.php: ' . $e->getMessage());
    hfRelCliCsvOutput([]);
}

exit;
