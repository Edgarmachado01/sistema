<?php

function hfRelOsDateOrDefault($value, $default)
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

function hfRelOsCleanText($value, $max = 120)
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

function hfRelOsReadFilters()
{
    $hoje = date('Y-m-d');
    $primeiroMes = date('Y-m-01');

    $dataIni = hfRelOsDateOrDefault($_GET['data_ini'] ?? '', $primeiroMes);
    $dataFim = hfRelOsDateOrDefault($_GET['data_fim'] ?? '', $hoje);

    if ($dataIni > $dataFim) {
        $tmp = $dataIni;
        $dataIni = $dataFim;
        $dataFim = $tmp;
    }

    $statusPermitidos = ['', 'aberta', 'em_andamento', 'concluida', 'cancelada'];
    $status = hfRelOsCleanText($_GET['status'] ?? '', 40);
    if (!in_array($status, $statusPermitidos, true)) {
        $status = '';
    }

    $statusFinPermitidos = ['', 'pendente', 'parcial', 'pago'];
    $statusFinanceiro = hfRelOsCleanText($_GET['status_financeiro'] ?? '', 40);
    if (!in_array($statusFinanceiro, $statusFinPermitidos, true)) {
        $statusFinanceiro = '';
    }

    $prioridadesPermitidas = ['', 'baixa', 'media', 'alta'];
    $prioridade = hfRelOsCleanText($_GET['prioridade'] ?? '', 40);
    if (!in_array($prioridade, $prioridadesPermitidas, true)) {
        $prioridade = '';
    }

    return [
        'data_ini' => $dataIni,
        'data_fim' => $dataFim,
        'status' => $status,
        'status_financeiro' => $statusFinanceiro,
        'prioridade' => $prioridade,
        'tecnico' => hfRelOsCleanText($_GET['tecnico'] ?? '', 120),
        'busca' => hfRelOsCleanText($_GET['busca'] ?? '', 150),
    ];
}

function hfRelOsBuildWhere($tenantId, array $filters)
{
    $where = [
        'o.tenant_id = :tid',
        'o.deleted_at IS NULL',
        'c.deleted_at IS NULL',
    ];

    $params = [
        ':tid' => $tenantId,
        ':data_ini' => $filters['data_ini'] . ' 00:00:00',
        ':data_fim' => $filters['data_fim'] . ' 23:59:59',
    ];

    $where[] = 'COALESCE(o.data_abertura, o.created_at) BETWEEN :data_ini AND :data_fim';

    if ($filters['status'] !== '') {
        $where[] = 'o.status = :status';
        $params[':status'] = $filters['status'];
    }

    if ($filters['status_financeiro'] !== '') {
        $where[] = 'o.status_financeiro = :status_financeiro';
        $params[':status_financeiro'] = $filters['status_financeiro'];
    }

    if ($filters['prioridade'] !== '') {
        $where[] = 'o.prioridade = :prioridade';
        $params[':prioridade'] = $filters['prioridade'];
    }

    if ($filters['tecnico'] !== '') {
        $where[] = 'o.tecnico LIKE :tecnico';
        $params[':tecnico'] = '%' . $filters['tecnico'] . '%';
    }

    if ($filters['busca'] !== '') {
        $where[] = '(CAST(o.numero AS CHAR) LIKE :busca OR CAST(o.id AS CHAR) LIKE :busca OR c.nome LIKE :busca)';
        $params[':busca'] = '%' . $filters['busca'] . '%';
    }

    return [
        'sql' => implode(' AND ', $where),
        'params' => $params,
    ];
}

function hfRelOsFetch(PDO $pdo, $tenantId, array $filters = null)
{
    $tenantId = (int)$tenantId;
    if ($tenantId <= 0) {
        return [
            'ok' => false,
            'message' => 'Tenant invalido.',
            'filters' => $filters ?: hfRelOsReadFilters(),
            'rows' => [],
            'summary' => hfRelOsEmptySummary(),
        ];
    }

    $filters = $filters ?: hfRelOsReadFilters();
    $where = hfRelOsBuildWhere($tenantId, $filters);

    $sql = "
        SELECT
            o.id,
            o.numero,
            o.status,
            o.prioridade,
            o.tecnico,
            o.data_abertura,
            o.created_at,
            o.total,
            o.valor_pago,
            o.status_financeiro,
            o.forma_pagto,
            o.data_pagto,
            c.nome AS cliente
        FROM hf_os o
        JOIN hf_clientes c
          ON c.id = o.cliente_id
         AND c.tenant_id = o.tenant_id
        WHERE {$where['sql']}
        ORDER BY COALESCE(o.data_abertura, o.created_at) DESC, o.id DESC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('_relatorio_os_query.php fetch: ' . $e->getMessage());
        return [
            'ok' => false,
            'message' => 'Nao foi possivel carregar o relatorio.',
            'filters' => $filters,
            'rows' => [],
            'summary' => hfRelOsEmptySummary(),
        ];
    }

    return [
        'ok' => true,
        'message' => '',
        'filters' => $filters,
        'rows' => $rows,
        'summary' => hfRelOsSummary($rows),
    ];
}

function hfRelOsEmptySummary()
{
    return [
        'total_os' => 0,
        'valor_total' => 0.0,
        'valor_recebido' => 0.0,
        'saldo_aberto' => 0.0,
        'abertas' => 0,
        'em_andamento' => 0,
        'concluidas' => 0,
        'ticket_medio' => 0.0,
    ];
}

function hfRelOsSummary(array $rows)
{
    $summary = hfRelOsEmptySummary();
    $summary['total_os'] = count($rows);

    foreach ($rows as $row) {
        $total = (float)($row['total'] ?? 0);
        $pago = (float)($row['valor_pago'] ?? 0);
        $saldo = max(0, $total - $pago);
        $status = (string)($row['status'] ?? '');

        $summary['valor_total'] += $total;
        $summary['valor_recebido'] += $pago;
        $summary['saldo_aberto'] += $saldo;

        if ($status === 'aberta') {
            $summary['abertas']++;
        } elseif ($status === 'em_andamento') {
            $summary['em_andamento']++;
        } elseif ($status === 'concluida') {
            $summary['concluidas']++;
        }
    }

    if ($summary['total_os'] > 0) {
        $summary['ticket_medio'] = $summary['valor_total'] / $summary['total_os'];
    }

    return $summary;
}