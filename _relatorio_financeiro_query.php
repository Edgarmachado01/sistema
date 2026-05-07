<?php

function hfRelFinDateOrDefault($value, $default)
{
    $value = trim((string)$value);
    if ($value === '') {
        return $default;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return $default;
    }

    return $value;
}

function hfRelFinCleanText($value, $max = 150)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    return substr($value, 0, $max);
}

function hfRelFinReadFilters()
{
    $hoje = date('Y-m-d');
    $primeiroMes = date('Y-m-01');

    $dataIni = hfRelFinDateOrDefault($_GET['data_ini'] ?? '', $primeiroMes);
    $dataFim = hfRelFinDateOrDefault($_GET['data_fim'] ?? '', $hoje);

    if ($dataIni > $dataFim) {
        $tmp = $dataIni;
        $dataIni = $dataFim;
        $dataFim = $tmp;
    }

    $tipoDataPermitidos = ['vencimento', 'pagamento', 'lancamento'];
    $tipoData = hfRelFinCleanText($_GET['tipo_data'] ?? 'vencimento', 30);
    if (!in_array($tipoData, $tipoDataPermitidos, true)) {
        $tipoData = 'vencimento';
    }

    $origensPermitidas = ['todos', 'os', 'lancamentos'];
    $origem = hfRelFinCleanText($_GET['origem'] ?? 'todos', 30);
    if (!in_array($origem, $origensPermitidas, true)) {
        $origem = 'todos';
    }

    $tiposPermitidos = ['', 'entrada', 'saida'];
    $tipo = hfRelFinCleanText($_GET['tipo'] ?? '', 30);
    if (!in_array($tipo, $tiposPermitidos, true)) {
        $tipo = '';
    }

    $statusPermitidos = ['', 'aberto', 'parcial', 'pago', 'cancelado'];
    $status = hfRelFinCleanText($_GET['status'] ?? '', 30);
    if (!in_array($status, $statusPermitidos, true)) {
        $status = '';
    }

    return [
        'data_ini' => $dataIni,
        'data_fim' => $dataFim,
        'tipo_data' => $tipoData,
        'origem' => $origem,
        'tipo' => $tipo,
        'status' => $status,
        'forma_pagamento' => hfRelFinCleanText($_GET['forma_pagamento'] ?? '', 80),
        'busca' => hfRelFinCleanText($_GET['busca'] ?? '', 150),
    ];
}

function hfRelFinOsDateColumn($tipoData)
{
    if ($tipoData === 'pagamento') {
        return 'o.data_pagto';
    }

    if ($tipoData === 'lancamento') {
        return 'f.data_os';
    }

    return 'f.data_vencimento';
}

function hfRelFinLancDateColumn($tipoData)
{
    if ($tipoData === 'pagamento') {
        return 'l.data_pagamento';
    }

    if ($tipoData === 'lancamento') {
        return 'l.data_lancamento';
    }

    return 'l.data_vencimento';
}

function hfRelFinNormalizeStatus($status, $valorPrevisto = 0.0, $valorPago = 0.0)
{
    $status = strtolower(trim((string)$status));

    if (in_array($status, ['pago', 'liquidado', 'baixado'], true)) {
        return 'pago';
    }

    if (in_array($status, ['cancelado', 'cancelada'], true)) {
        return 'cancelado';
    }

    if ($status === 'parcial') {
        return 'parcial';
    }

    if ($valorPago > 0 && $valorPago < $valorPrevisto) {
        return 'parcial';
    }

    return 'aberto';
}

function hfRelFinSituation($status, $vencimento)
{
    $status = hfRelFinNormalizeStatus($status);

    if ($status === 'pago') {
        return 'pago';
    }

    if ($status === 'cancelado') {
        return 'cancelado';
    }

    if ($vencimento && $vencimento < date('Y-m-d')) {
        return 'atrasado';
    }

    if ($status === 'parcial') {
        return 'parcial';
    }

    return 'em_aberto';
}

function hfRelFinSafeMoney($value)
{
    $value = (float)$value;
    return $value > 0 ? $value : 0.0;
}

function hfRelFinFetchOs(PDO $pdo, $tenantId, array $filters)
{
    if ($filters['origem'] === 'lancamentos' || $filters['tipo'] === 'saida') {
        return ['rows' => [], 'params' => []];
    }

    $dateColumn = hfRelFinOsDateColumn($filters['tipo_data']);
    $where = [
        'f.tenant_id = :tid',
        'o.deleted_at IS NULL',
        "{$dateColumn} >= :data_ini",
        "{$dateColumn} <= :data_fim",
    ];

    $params = [
        ':tid' => (int)$tenantId,
        ':data_ini' => $filters['data_ini'] . ' 00:00:00',
        ':data_fim' => $filters['data_fim'] . ' 23:59:59',
    ];

    if ($filters['forma_pagamento'] !== '') {
        $where[] = 'o.forma_pagto LIKE :forma_pagamento';
        $params[':forma_pagamento'] = '%' . $filters['forma_pagamento'] . '%';
    }

    if ($filters['busca'] !== '') {
        $where[] = '(c.nome LIKE :busca OR CAST(f.os_id AS CHAR) LIKE :busca OR CAST(o.numero AS CHAR) LIKE :busca)';
        $params[':busca'] = '%' . $filters['busca'] . '%';
    }

    $sql = "
        SELECT
            f.id,
            f.os_id,
            f.data_os,
            f.data_vencimento,
            f.valor_total,
            o.numero,
            o.data_pagto,
            o.forma_pagto,
            o.valor_pago,
            o.status_financeiro,
            c.nome AS cliente_nome
        FROM os_financeiro f
        JOIN hf_os o
          ON o.id = f.os_id
         AND o.tenant_id = f.tenant_id
        LEFT JOIN hf_clientes c
          ON c.id = f.cliente_id
         AND c.tenant_id = f.tenant_id
         AND c.deleted_at IS NULL
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$dateColumn} ASC, f.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($items as $item) {
        $valorPrevisto = hfRelFinSafeMoney($item['valor_total'] ?? 0);
        $valorPago = hfRelFinSafeMoney($item['valor_pago'] ?? 0);
        $status = hfRelFinNormalizeStatus($item['status_financeiro'] ?? '', $valorPrevisto, $valorPago);

        if ($status === 'pago' && $valorPago <= 0 && $valorPrevisto > 0) {
            $valorPago = $valorPrevisto;
        }

        $saldo = max(0, $valorPrevisto - $valorPago);

        $row = [
            'origem' => 'OS',
            'numero_os' => (string)($item['numero'] ?: $item['os_id']),
            'cliente_descricao' => (string)($item['cliente_nome'] ?: ('OS #' . $item['os_id'])),
            'tipo' => 'entrada',
            'data_emissao' => $item['data_os'] ?: '',
            'vencimento' => $item['data_vencimento'] ?: '',
            'pagamento' => $item['data_pagto'] ?: '',
            'forma_pagamento' => (string)($item['forma_pagto'] ?? ''),
            'valor_previsto' => $valorPrevisto,
            'valor_pago' => $valorPago,
            'saldo' => $saldo,
            'status' => $status,
            'situacao' => hfRelFinSituation($status, $item['data_vencimento'] ?? ''),
        ];

        if ($filters['status'] !== '' && $row['status'] !== $filters['status']) {
            continue;
        }

        $rows[] = $row;
    }

    return ['rows' => $rows, 'params' => $params];
}

function hfRelFinFetchLancamentos(PDO $pdo, $tenantId, array $filters)
{
    if ($filters['origem'] === 'os') {
        return ['rows' => [], 'params' => []];
    }

    $dateColumn = hfRelFinLancDateColumn($filters['tipo_data']);
    $where = [
        'l.tenant_id = :tid',
        "{$dateColumn} >= :data_ini",
        "{$dateColumn} <= :data_fim",
    ];

    $params = [
        ':tid' => (int)$tenantId,
        ':data_ini' => $filters['data_ini'] . ' 00:00:00',
        ':data_fim' => $filters['data_fim'] . ' 23:59:59',
    ];

    if ($filters['tipo'] !== '') {
        $where[] = 'l.tipo_mov = :tipo';
        $params[':tipo'] = $filters['tipo'];
    }

    if ($filters['forma_pagamento'] !== '') {
        $where[] = 'l.forma_pagamento LIKE :forma_pagamento';
        $params[':forma_pagamento'] = '%' . $filters['forma_pagamento'] . '%';
    }

    if ($filters['busca'] !== '') {
        $where[] = 'l.descricao LIKE :busca';
        $params[':busca'] = '%' . $filters['busca'] . '%';
    }

    $sql = "
        SELECT
            l.id,
            l.tipo_mov,
            l.descricao,
            l.valor,
            l.data_lancamento,
            l.data_vencimento,
            l.status,
            l.data_pagamento,
            l.valor_pago,
            l.forma_pagamento
        FROM lancamentos l
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$dateColumn} ASC, l.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($items as $item) {
        $valorPrevisto = hfRelFinSafeMoney($item['valor'] ?? 0);
        $valorPago = hfRelFinSafeMoney($item['valor_pago'] ?? 0);
        $status = hfRelFinNormalizeStatus($item['status'] ?? '', $valorPrevisto, $valorPago);

        if ($status === 'pago' && $valorPago <= 0 && $valorPrevisto > 0) {
            $valorPago = $valorPrevisto;
        }

        $saldo = max(0, $valorPrevisto - $valorPago);

        $row = [
            'origem' => 'Lancamento',
            'numero_os' => '',
            'cliente_descricao' => (string)($item['descricao'] ?? ''),
            'tipo' => (string)($item['tipo_mov'] ?? ''),
            'data_emissao' => $item['data_lancamento'] ?: '',
            'vencimento' => $item['data_vencimento'] ?: '',
            'pagamento' => $item['data_pagamento'] ?: '',
            'forma_pagamento' => (string)($item['forma_pagamento'] ?? ''),
            'valor_previsto' => $valorPrevisto,
            'valor_pago' => $valorPago,
            'saldo' => $saldo,
            'status' => $status,
            'situacao' => hfRelFinSituation($status, $item['data_vencimento'] ?? ''),
        ];

        if ($filters['status'] !== '' && $row['status'] !== $filters['status']) {
            continue;
        }

        $rows[] = $row;
    }

    return ['rows' => $rows, 'params' => $params];
}

function hfRelFinEmptySummary()
{
    return [
        'recebido' => 0.0,
        'a_receber' => 0.0,
        'em_atraso' => 0.0,
        'saidas_pagas' => 0.0,
        'saldo_liquido' => 0.0,
        'previsto_liquido' => 0.0,
    ];
}

function hfRelFinSummary(array $rows)
{
    $summary = hfRelFinEmptySummary();

    foreach ($rows as $row) {
        $tipo = $row['tipo'];
        $status = $row['status'];
        $situacao = $row['situacao'];
        $previsto = (float)$row['valor_previsto'];
        $pago = (float)$row['valor_pago'];
        $saldo = (float)$row['saldo'];

        if ($status !== 'cancelado') {
            if ($tipo === 'entrada') {
                $summary['previsto_liquido'] += $previsto;
            } elseif ($tipo === 'saida') {
                $summary['previsto_liquido'] -= $previsto;
            }
        }

        if ($tipo === 'entrada') {
            if ($pago > 0 && $status !== 'cancelado') {
                $summary['recebido'] += $pago;
            }

            if ($saldo > 0 && in_array($status, ['aberto', 'parcial'], true)) {
                $summary['a_receber'] += $saldo;
            }
        }

        if ($tipo === 'saida' && $pago > 0 && $status !== 'cancelado') {
            $summary['saidas_pagas'] += $pago;
        }

        if ($saldo > 0 && $situacao === 'atrasado') {
            $summary['em_atraso'] += $saldo;
        }
    }

    $summary['saldo_liquido'] = $summary['recebido'] - $summary['saidas_pagas'];

    return $summary;
}

function hfRelFinSortRows(array $rows, array $filters)
{
    $field = 'vencimento';
    if ($filters['tipo_data'] === 'pagamento') {
        $field = 'pagamento';
    } elseif ($filters['tipo_data'] === 'lancamento') {
        $field = 'data_emissao';
    }

    usort($rows, function ($a, $b) use ($field) {
        $dateA = $a[$field] ?: '9999-12-31';
        $dateB = $b[$field] ?: '9999-12-31';
        if ($dateA === $dateB) {
            return strcmp($a['origem'] . $a['cliente_descricao'], $b['origem'] . $b['cliente_descricao']);
        }
        return strcmp($dateA, $dateB);
    });

    return $rows;
}

function hfRelFinFetch(PDO $pdo, $tenantId, array $filters = null)
{
    $tenantId = (int)$tenantId;
    $filters = $filters ?: hfRelFinReadFilters();

    if ($tenantId <= 0) {
        return [
            'ok' => false,
            'message' => 'Tenant invalido.',
            'filters' => $filters,
            'params' => ['os' => [], 'lancamentos' => []],
            'rows' => [],
            'resumo' => hfRelFinEmptySummary(),
        ];
    }

    try {
        $os = hfRelFinFetchOs($pdo, $tenantId, $filters);
        $lancamentos = hfRelFinFetchLancamentos($pdo, $tenantId, $filters);

        $rows = array_merge($os['rows'], $lancamentos['rows']);
        $rows = hfRelFinSortRows($rows, $filters);

        return [
            'ok' => true,
            'message' => '',
            'filters' => $filters,
            'params' => [
                'os' => $os['params'],
                'lancamentos' => $lancamentos['params'],
            ],
            'rows' => $rows,
            'resumo' => hfRelFinSummary($rows),
        ];
    } catch (Throwable $e) {
        error_log('_relatorio_financeiro_query.php fetch: ' . $e->getMessage());
        return [
            'ok' => false,
            'message' => 'Nao foi possivel carregar o relatorio financeiro.',
            'filters' => $filters,
            'params' => ['os' => [], 'lancamentos' => []],
            'rows' => [],
            'resumo' => hfRelFinEmptySummary(),
        ];
    }
}

