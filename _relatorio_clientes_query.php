<?php

function hfRelCliDateOrDefault($value, $default)
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

function hfRelCliCleanText($value, $max = 150)
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

function hfRelCliColumnExists(PDO $pdo, $table, $column)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
            LIMIT 1
        ");
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('_relatorio_clientes_query.php column check: ' . $e->getMessage());
        return false;
    }
}

function hfRelCliReadFilters($hasCreatedAt = false)
{
    $hoje = date('Y-m-d');
    $primeiroMes = date('Y-m-01');

    $dataIni = hfRelCliDateOrDefault($_GET['data_ini'] ?? '', $primeiroMes);
    $dataFim = hfRelCliDateOrDefault($_GET['data_fim'] ?? '', $hoje);

    if ($dataIni > $dataFim) {
        $tmp = $dataIni;
        $dataIni = $dataFim;
        $dataFim = $tmp;
    }

    $basePeriodoPermitidas = ['os_abertura'];
    if ($hasCreatedAt) {
        $basePeriodoPermitidas[] = 'cliente_cadastro';
    }

    $basePeriodo = hfRelCliCleanText($_GET['base_periodo'] ?? 'os_abertura', 40);
    if (!in_array($basePeriodo, $basePeriodoPermitidas, true)) {
        $basePeriodo = 'os_abertura';
    }

    $statusPermitidos = ['', 'ativo', 'inativo'];
    $statusCliente = hfRelCliCleanText($_GET['status_cliente'] ?? '', 30);
    if (!in_array($statusCliente, $statusPermitidos, true)) {
        $statusCliente = '';
    }

    $perfilPermitidos = ['todos', 'novos', 'recorrentes', 'sem_os', 'com_os', 'sem_os_periodo'];
    $perfil = hfRelCliCleanText($_GET['perfil'] ?? 'todos', 40);
    if (!in_array($perfil, $perfilPermitidos, true)) {
        $perfil = 'todos';
    }

    return [
        'data_ini' => $dataIni,
        'data_fim' => $dataFim,
        'base_periodo' => $basePeriodo,
        'status_cliente' => $statusCliente,
        'cidade' => hfRelCliCleanText($_GET['cidade'] ?? '', 100),
        'bairro' => hfRelCliCleanText($_GET['bairro'] ?? '', 80),
        'uf' => strtoupper(hfRelCliCleanText($_GET['uf'] ?? '', 2)),
        'perfil' => $perfil,
        'busca' => hfRelCliCleanText($_GET['busca'] ?? '', 150),
        'has_created_at' => $hasCreatedAt,
    ];
}

function hfRelCliEmptySummary()
{
    return [
        'total_clientes' => 0,
        'clientes_com_os' => 0,
        'clientes_novos' => 0,
        'clientes_recorrentes' => 0,
        'clientes_sem_os' => 0,
        'ticket_medio_geral' => 0.0,
        'cidade_top' => '',
        'bairro_top' => '',
    ];
}

function hfRelCliBuildBaseWhere($tenantId, array $filters)
{
    $where = [
        'c.tenant_id = :tid',
        'c.deleted_at IS NULL',
    ];

    $params = [
        ':tid' => (int)$tenantId,
    ];

    if ($filters['status_cliente'] === 'ativo') {
        $where[] = 'c.status = 1';
    } elseif ($filters['status_cliente'] === 'inativo') {
        $where[] = 'c.status = 0';
    }

    if ($filters['cidade'] !== '') {
        $where[] = 'c.cidade LIKE :cidade';
        $params[':cidade'] = '%' . $filters['cidade'] . '%';
    }

    if ($filters['bairro'] !== '') {
        $where[] = 'c.bairro LIKE :bairro';
        $params[':bairro'] = '%' . $filters['bairro'] . '%';
    }

    if ($filters['uf'] !== '') {
        $where[] = 'c.uf = :uf';
        $params[':uf'] = $filters['uf'];
    }

    if ($filters['busca'] !== '') {
        $where[] = '(c.nome LIKE :busca OR c.documento LIKE :busca OR c.telefone LIKE :busca OR c.celular LIKE :busca OR c.cidade LIKE :busca)';
        $params[':busca'] = '%' . $filters['busca'] . '%';
    }

    if ($filters['base_periodo'] === 'cliente_cadastro' && !empty($filters['has_created_at'])) {
        $where[] = 'c.created_at >= :cadastro_ini';
        $where[] = 'c.created_at <= :cadastro_fim';
        $params[':cadastro_ini'] = $filters['data_ini'] . ' 00:00:00';
        $params[':cadastro_fim'] = $filters['data_fim'] . ' 23:59:59';
    }

    return [
        'sql' => implode(' AND ', $where),
        'params' => $params,
    ];
}

function hfRelCliFetchRows(PDO $pdo, $tenantId, array $filters)
{
    $base = hfRelCliBuildBaseWhere($tenantId, $filters);
    $periodIni = $filters['data_ini'] . ' 00:00:00';
    $periodFim = $filters['data_fim'] . ' 23:59:59';

    $params = $base['params'];
    $params[':period_ini'] = $periodIni;
    $params[':period_fim'] = $periodFim;

    $createdSelect = !empty($filters['has_created_at']) ? 'c.created_at' : 'NULL AS created_at';

    $sql = "
        SELECT
            c.id,
            c.nome,
            c.documento,
            c.telefone,
            c.celular,
            c.cidade,
            c.bairro,
            c.uf,
            c.status,
            {$createdSelect},
            COUNT(o.id) AS total_os,
            SUM(CASE WHEN COALESCE(o.data_abertura, o.created_at) BETWEEN :period_ini AND :period_fim THEN 1 ELSE 0 END) AS os_periodo,
            MAX(COALESCE(o.data_abertura, o.created_at)) AS ultima_os,
            SUM(COALESCE(o.total, 0)) AS total_faturado,
            SUM(COALESCE(o.valor_pago, 0)) AS valor_recebido,
            SUM(GREATEST(COALESCE(o.total, 0) - COALESCE(o.valor_pago, 0), 0)) AS saldo_aberto
        FROM hf_clientes c
        LEFT JOIN hf_os o
          ON o.cliente_id = c.id
         AND o.tenant_id = c.tenant_id
         AND o.deleted_at IS NULL
        WHERE {$base['sql']}
        GROUP BY
            c.id,
            c.nome,
            c.documento,
            c.telefone,
            c.celular,
            c.cidade,
            c.bairro,
            c.uf,
            c.status,
            c.created_at
        ORDER BY c.nome ASC
    ";

    if (empty($filters['has_created_at'])) {
        $sql = str_replace(",\n            c.created_at", '', $sql);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($items as $item) {
        $totalOs = (int)($item['total_os'] ?? 0);
        $osPeriodo = (int)($item['os_periodo'] ?? 0);
        $totalFaturado = (float)($item['total_faturado'] ?? 0);
        $valorRecebido = (float)($item['valor_recebido'] ?? 0);
        $saldoAberto = (float)($item['saldo_aberto'] ?? 0);
        $ultimaOs = (string)($item['ultima_os'] ?? '');
        $statusUltimaOs = '';

        if ($ultimaOs !== '') {
            $statusUltimaOs = hfRelCliFetchLastOsStatus($pdo, (int)$tenantId, (int)$item['id'], $ultimaOs);
        }

        $perfilCliente = hfRelCliProfile($item, $totalOs, $osPeriodo, $filters);

        $row = [
            'cliente' => (string)($item['nome'] ?? ''),
            'documento' => (string)($item['documento'] ?? ''),
            'telefone' => (string)(($item['telefone'] ?? '') ?: ($item['celular'] ?? '')),
            'cidade' => (string)($item['cidade'] ?? ''),
            'bairro' => (string)($item['bairro'] ?? ''),
            'uf' => (string)($item['uf'] ?? ''),
            'status_cliente' => ((int)($item['status'] ?? 0) === 1) ? 'ativo' : 'inativo',
            'total_os' => $totalOs,
            'os_periodo' => $osPeriodo,
            'ultima_os' => $ultimaOs,
            'status_ultima_os' => $statusUltimaOs,
            'total_faturado' => $totalFaturado,
            'valor_recebido' => $valorRecebido,
            'saldo_aberto' => $saldoAberto,
            'ticket_medio' => $totalOs > 0 ? ($totalFaturado / $totalOs) : 0.0,
            'perfil_cliente' => $perfilCliente,
        ];

        if (!hfRelCliPassProfileFilter($row, $filters)) {
            continue;
        }

        $rows[] = $row;
    }

    return [
        'rows' => $rows,
        'params' => $params,
    ];
}

function hfRelCliFetchLastOsStatus(PDO $pdo, $tenantId, $clienteId, $ultimaOs)
{
    try {
        $stmt = $pdo->prepare("
            SELECT status
            FROM hf_os
            WHERE tenant_id = :tid
              AND cliente_id = :cliente_id
              AND deleted_at IS NULL
              AND COALESCE(data_abertura, created_at) = :ultima_os
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':tid' => (int)$tenantId,
            ':cliente_id' => (int)$clienteId,
            ':ultima_os' => $ultimaOs,
        ]);
        return (string)($stmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        error_log('_relatorio_clientes_query.php last status: ' . $e->getMessage());
        return '';
    }
}

function hfRelCliProfile(array $item, $totalOs, $osPeriodo, array $filters)
{
    if ($totalOs <= 0) {
        return 'sem_os';
    }

    if (!empty($filters['has_created_at']) && !empty($item['created_at'])) {
        $createdAt = substr((string)$item['created_at'], 0, 10);
        if ($createdAt >= $filters['data_ini'] && $createdAt <= $filters['data_fim']) {
            return 'novo';
        }
    } elseif ($totalOs === $osPeriodo && $osPeriodo > 0) {
        return 'novo';
    }

    if ($totalOs >= 2) {
        return 'recorrente';
    }

    return 'com_os';
}

function hfRelCliPassProfileFilter(array $row, array $filters)
{
    $perfil = $filters['perfil'];

    if ($perfil === 'todos') {
        return true;
    }

    if ($perfil === 'novos') {
        return $row['perfil_cliente'] === 'novo';
    }

    if ($perfil === 'recorrentes') {
        return $row['total_os'] >= 2;
    }

    if ($perfil === 'sem_os') {
        return $row['total_os'] === 0;
    }

    if ($perfil === 'com_os') {
        return $row['total_os'] > 0;
    }

    if ($perfil === 'sem_os_periodo') {
        return $row['os_periodo'] === 0;
    }

    return true;
}

function hfRelCliSummary(array $rows)
{
    $summary = hfRelCliEmptySummary();
    $summary['total_clientes'] = count($rows);

    $totalFaturado = 0.0;
    $totalOs = 0;
    $cidadeMap = [];
    $bairroMap = [];

    foreach ($rows as $row) {
        $rowTotalOs = (int)$row['total_os'];
        $totalOs += $rowTotalOs;
        $totalFaturado += (float)$row['total_faturado'];

        if ($rowTotalOs > 0) {
            $summary['clientes_com_os']++;
        } else {
            $summary['clientes_sem_os']++;
        }

        if ($rowTotalOs >= 2) {
            $summary['clientes_recorrentes']++;
        }

        if ($row['perfil_cliente'] === 'novo') {
            $summary['clientes_novos']++;
        }

        $cidade = trim((string)$row['cidade']);
        if ($cidade !== '' && $rowTotalOs > 0) {
            $key = $cidade . (($row['uf'] ?? '') ? '/' . $row['uf'] : '');
            $cidadeMap[$key] = ($cidadeMap[$key] ?? 0) + $rowTotalOs;
        }

        $bairro = trim((string)$row['bairro']);
        if ($bairro !== '' && $rowTotalOs > 0) {
            $bairroMap[$bairro] = ($bairroMap[$bairro] ?? 0) + $rowTotalOs;
        }
    }

    $summary['ticket_medio_geral'] = $totalOs > 0 ? ($totalFaturado / $totalOs) : 0.0;
    $summary['cidade_top'] = hfRelCliTopKey($cidadeMap);
    $summary['bairro_top'] = hfRelCliTopKey($bairroMap);

    return $summary;
}

function hfRelCliTopKey(array $map)
{
    if (!$map) {
        return '';
    }

    arsort($map);
    return (string)array_key_first($map);
}

function hfRelCliFetch(PDO $pdo, $tenantId, array $filters = null)
{
    $tenantId = (int)$tenantId;
    $hasCreatedAt = hfRelCliColumnExists($pdo, 'hf_clientes', 'created_at');
    $filters = $filters ?: hfRelCliReadFilters($hasCreatedAt);

    if ($tenantId <= 0) {
        return [
            'ok' => false,
            'message' => 'Tenant invalido.',
            'filters' => $filters,
            'params' => [],
            'rows' => [],
            'resumo' => hfRelCliEmptySummary(),
        ];
    }

    try {
        $result = hfRelCliFetchRows($pdo, $tenantId, $filters);
        $rows = $result['rows'];

        return [
            'ok' => true,
            'message' => '',
            'filters' => $filters,
            'params' => $result['params'],
            'rows' => $rows,
            'resumo' => hfRelCliSummary($rows),
        ];
    } catch (Throwable $e) {
        error_log('_relatorio_clientes_query.php fetch: ' . $e->getMessage());
        return [
            'ok' => false,
            'message' => 'Nao foi possivel carregar o relatorio de clientes.',
            'filters' => $filters,
            'params' => [],
            'rows' => [],
            'resumo' => hfRelCliEmptySummary(),
        ];
    }
}

