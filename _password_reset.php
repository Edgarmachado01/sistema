<?php
require_once __DIR__.'/db.php';

function hfResetTokenFormatoValido($token)
{
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/i', $token) === 1;
}

function hfResetTokenHash($token)
{
    $token = strtolower(trim((string)$token));
    return hash('sha256', $token);
}

function hfResetBuildAbsoluteUrl($path, array $query = [])
{
    $path = '/'.ltrim((string)$path, '/');

    $baseUrl = '';
    if (defined('APP_BASE_URL') && is_string(APP_BASE_URL) && trim(APP_BASE_URL) !== '') {
        $baseUrl = trim(APP_BASE_URL);
    } elseif (defined('APP_URL') && is_string(APP_URL) && trim(APP_URL) !== '') {
        $baseUrl = trim(APP_URL);
    }

    if ($baseUrl !== '') {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl.$path;
        if (!empty($query)) {
            $url .= '?'.http_build_query($query);
        }
        return $url;
    }

    $host = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '')));
    if (strpos($host, ',') !== false) {
        $host = trim(explode(',', $host)[0]);
    }

    if ($host === '') {
        return $path.(empty($query) ? '' : '?'.http_build_query($query));
    }

    $scheme = 'http';
    $forwardedProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto !== '') {
        $scheme = strtolower(explode(',', $forwardedProto)[0]);
    } elseif (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    }

    if ($scheme !== 'http' && $scheme !== 'https') {
        $scheme = 'https';
    }

    $url = $scheme.'://'.$host.$path;
    if (!empty($query)) {
        $url .= '?'.http_build_query($query);
    }

    return $url;
}

function hfResetFindUserByTenantAndEmail($tenantCode, $email)
{
    $tenantCode = trim((string)$tenantCode);
    $email = strtolower(trim((string)$email));

    if ($tenantCode === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $st = db()->prepare("
        SELECT u.id, u.email, u.tenant_id
        FROM users u
        INNER JOIN tenants t ON t.id = u.tenant_id
        WHERE t.slug = :slug
          AND t.is_active = 1
          AND u.email = :email
          AND u.is_active = 1
        LIMIT 1
    ");
    $st->execute([
        ':slug' => $tenantCode,
        ':email' => $email,
    ]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hfResetNormalizeDocumento($documento)
{
    return preg_replace('/\D+/', '', trim((string)$documento));
}

function hfResetDocumentoValido($documento)
{
    $len = strlen((string)$documento);
    return $len === 11 || $len === 14;
}

function hfResetFindTenantByDocumentoAndAdminEmail($documento, $email)
{
    $documento = hfResetNormalizeDocumento($documento);
    $email = strtolower(trim((string)$email));

    if (!hfResetDocumentoValido($documento) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $st = db()->prepare("
        SELECT
            t.id,
            t.slug,
            t.name,
            tc.cnpj,
            tc.email AS config_email
        FROM tenants t
        INNER JOIN tenant_config tc ON tc.tenant_id = t.id
        WHERE tc.cnpj = :documento
          AND (
                LOWER(COALESCE(tc.email, '')) = :email
                OR EXISTS (
                    SELECT 1
                    FROM users u
                    INNER JOIN user_roles ur ON ur.user_id = u.id
                    INNER JOIN roles r ON r.id = ur.role_id
                    WHERE u.tenant_id = t.id
                      AND u.is_active = 1
                      AND LOWER(u.email) = :email
                      AND r.role_key = 'TENANT_ADMIN'
                )
                OR EXISTS (
                    SELECT 1
                    FROM users u2
                    WHERE u2.tenant_id = t.id
                      AND u2.is_active = 1
                      AND LOWER(u2.email) = :email
                )
          )
        ORDER BY t.id DESC
        LIMIT 1
    ");
    $st->execute([
        ':documento' => $documento,
        ':email' => $email,
    ]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hfResetCreateRequest($userId, $ttlMinutes = 60)
{
    $userId = (int)$userId;
    $ttlMinutes = (int)$ttlMinutes;
    if ($userId <= 0) {
        throw new InvalidArgumentException('Usuario invalido para reset de senha.');
    }
    if ($ttlMinutes <= 0) {
        $ttlMinutes = 60;
    }

    $pdo = db();
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hfResetTokenHash($rawToken);
    $ttlMinutes = max(1, (int)$ttlMinutes);
    $ttlSql = (int)$ttlMinutes;

    $pdo->beginTransaction();
    try {
        $stInvalidate = $pdo->prepare("
            UPDATE password_resets
            SET used = 1
            WHERE user_id = :user_id
              AND used = 0
        ");
        $stInvalidate->execute([':user_id' => $userId]);

        $stInsert = $pdo->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, used)
            VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL {$ttlSql} MINUTE), 0)
        ");
        $stInsert->execute([
            ':user_id' => $userId,
            ':token' => $tokenHash,
        ]);

        $resetId = (int)$pdo->lastInsertId();
        $stExpires = $pdo->prepare("
            SELECT expires_at
            FROM password_resets
            WHERE id = :id
            LIMIT 1
        ");
        $stExpires->execute([':id' => $resetId]);
        $expiresAt = (string)($stExpires->fetchColumn() ?: '');

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'raw_token' => $rawToken,
        'expires_at' => $expiresAt,
    ];
}

function hfResetFindValidRequestByToken($rawToken)
{
    if (!hfResetTokenFormatoValido($rawToken)) {
        return null;
    }

    $rawToken = strtolower(trim((string)$rawToken));
    $tokenHash = hfResetTokenHash($rawToken);
    $st = db()->prepare("
        SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.email
        FROM password_resets pr
        INNER JOIN users u ON u.id = pr.user_id
        WHERE (pr.token = :token_hash OR pr.token = :token_raw)
          AND pr.used = 0
          AND pr.expires_at >= NOW()
          AND u.is_active = 1
        LIMIT 1
    ");
    $st->execute([
        ':token_hash' => $tokenHash,
        ':token_raw' => $rawToken,
    ]);

    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function hfResetConsumeTokenAndUpdatePassword($rawToken, $newPassword)
{
    if (!hfResetTokenFormatoValido($rawToken)) {
        return false;
    }

    $rawToken = strtolower(trim((string)$rawToken));
    $tokenHash = hfResetTokenHash($rawToken);
    $passwordHash = password_hash((string)$newPassword, PASSWORD_DEFAULT);
    $pdo = db();

    $pdo->beginTransaction();
    try {
        $stRequest = $pdo->prepare("
            SELECT pr.id, pr.user_id
            FROM password_resets pr
            INNER JOIN users u ON u.id = pr.user_id
            WHERE (pr.token = :token_hash OR pr.token = :token_raw)
              AND pr.used = 0
              AND pr.expires_at >= NOW()
              AND u.is_active = 1
            LIMIT 1
            FOR UPDATE
        ");
        $stRequest->execute([
            ':token_hash' => $tokenHash,
            ':token_raw' => $rawToken,
        ]);
        $request = $stRequest->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $pdo->rollBack();
            return false;
        }

        $stUser = $pdo->prepare("
            UPDATE users
            SET password_hash = :password_hash
            WHERE id = :id
              AND is_active = 1
            LIMIT 1
        ");
        $stUser->execute([
            ':password_hash' => $passwordHash,
            ':id' => (int)$request['user_id'],
        ]);

        if ($stUser->rowCount() < 1) {
            throw new RuntimeException('Falha ao atualizar a senha do usuario no reset.');
        }

        $stUse = $pdo->prepare("
            UPDATE password_resets
            SET used = 1
            WHERE id = :id
              AND used = 0
            LIMIT 1
        ");
        $stUse->execute([':id' => (int)$request['id']]);

        if ($stUse->rowCount() < 1) {
            throw new RuntimeException('Falha ao marcar token como usado.');
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
