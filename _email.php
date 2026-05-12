<?php
/**
 * Helper incremental de e-mail.
 * Nesta fase usa mail() para compatibilidade com hospedagem comum.
 * Futuramente pode ser evoluido para SMTP (PHPMailer, etc.) sem alterar chamadas.
 */

function hfEmailSenderAddress()
{
    if (defined('MAIL_FROM_EMAIL') && is_string(MAIL_FROM_EMAIL) && MAIL_FROM_EMAIL !== '') {
        return MAIL_FROM_EMAIL;
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host);
    if ($host === '') {
        $host = 'localhost';
    }

    return 'no-reply@'.$host;
}

function hfEmailSenderName()
{
    if (defined('MAIL_FROM_NAME') && is_string(MAIL_FROM_NAME) && MAIL_FROM_NAME !== '') {
        return MAIL_FROM_NAME;
    }

    return 'HelpDesk Facil';
}

function hfEmailFormatHeaderName($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return '';
    }
    return '=?UTF-8?B?'.base64_encode($name).'?=';
}

function hfEmailEncodeSubject($subject)
{
    $subject = trim((string)$subject);
    if ($subject === '') {
        return '';
    }
    return '=?UTF-8?B?'.base64_encode($subject).'?=';
}

function hfSendEmail($toEmail, $subject, $htmlBody, $textBody = '')
{
    $toEmail = trim((string)$toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (!function_exists('mail')) {
        error_log('hfSendEmail: funcao mail() indisponivel neste ambiente.');
        return false;
    }

    $senderAddress = hfEmailSenderAddress();
    $senderName = hfEmailSenderName();
    $encodedSenderName = hfEmailFormatHeaderName($senderName);
    $encodedSubject = hfEmailEncodeSubject($subject);

    $boundary = 'b1_'.bin2hex(random_bytes(12));
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: '.$encodedSenderName.' <'.$senderAddress.'>';
    $headers[] = 'Reply-To: '.$senderAddress;
    $headers[] = 'Content-Type: multipart/alternative; boundary="'.$boundary.'"';

    $htmlBody = (string)$htmlBody;
    $textBody = trim((string)$textBody);
    if ($textBody === '') {
        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
    }

    $message = '';
    $message .= '--'.$boundary."\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $textBody."\r\n\r\n";
    $message .= '--'.$boundary."\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlBody."\r\n\r\n";
    $message .= '--'.$boundary."--\r\n";

    $ok = @mail($toEmail, $encodedSubject, $message, implode("\r\n", $headers));
    return (bool)$ok;
}

function hfSendWelcomeEmail(array $data)
{
    $empresaNome = trim((string)($data['empresa_nome'] ?? ''));
    $loginUrl = trim((string)($data['login_url'] ?? ''));
    $tenantCode = trim((string)($data['tenant_code'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));
    $planoNome = trim((string)($data['plano_nome'] ?? ''));
    $trialStatus = trim((string)($data['trial_status'] ?? 'trial'));
    $trialDays = (int)($data['trial_days'] ?? 0);

    if ($trialDays > 0) {
        $trialInfo = ucfirst($trialStatus).' - '.$trialDays.' dias';
    } else {
        $trialInfo = ucfirst($trialStatus);
    }

    $safeEmpresaNome = htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8');
    $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
    $safeTenantCode = htmlspecialchars($tenantCode, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePlanoNome = htmlspecialchars($planoNome, ENT_QUOTES, 'UTF-8');
    $safeTrialInfo = htmlspecialchars($trialInfo, ENT_QUOTES, 'UTF-8');

    $subject = 'Seu acesso ao HelpDesk Facil';

    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#0f172a;">';
    $html .= '<h2 style="margin-bottom:8px;">Bem-vindo ao HelpDesk Facil</h2>';
    $html .= '<p style="margin-top:0;">Seu ambiente foi criado com sucesso. Guarde seus dados de acesso:</p>';
    $html .= '<table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0;">';
    $html .= '<tr><td><strong>Empresa</strong></td><td>'.$safeEmpresaNome.'</td></tr>';
    $html .= '<tr><td><strong>URL de login</strong></td><td><a href="'.$safeLoginUrl.'">'.$safeLoginUrl.'</a></td></tr>';
    $html .= '<tr><td><strong>Codigo da empresa</strong></td><td>'.$safeTenantCode.'</td></tr>';
    $html .= '<tr><td><strong>E-mail de acesso</strong></td><td>'.$safeEmail.'</td></tr>';
    $html .= '<tr><td><strong>Plano</strong></td><td>'.$safePlanoNome.'</td></tr>';
    $html .= '<tr><td><strong>Status</strong></td><td>'.$safeTrialInfo.'</td></tr>';
    $html .= '</table>';
    $html .= '<p style="margin-top:16px;"><strong>Importante:</strong> guarde o codigo da empresa. Ele sera solicitado no login.</p>';
    $html .= '<p><a href="'.$safeLoginUrl.'" style="display:inline-block;padding:10px 16px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;">Acessar login</a></p>';
    $html .= '</body></html>';

    $text = "Bem-vindo ao HelpDesk Facil\n\n";
    $text .= "Seu ambiente foi criado com sucesso.\n\n";
    $text .= "Empresa: ".$empresaNome."\n";
    $text .= "URL de login: ".$loginUrl."\n";
    $text .= "Codigo da empresa: ".$tenantCode."\n";
    $text .= "E-mail de acesso: ".$email."\n";
    $text .= "Plano: ".$planoNome."\n";
    $text .= "Status: ".$trialInfo."\n\n";
    $text .= "Importante: guarde o codigo da empresa. Ele sera solicitado no login.\n";
    $text .= "Acessar login: ".$loginUrl."\n";

    return hfSendEmail($email, $subject, $html, $text);
}

function hfSendPasswordResetEmail(array $data)
{
    $toEmail = trim((string)($data['email'] ?? ''));
    $tenantCode = trim((string)($data['tenant_code'] ?? ''));
    $resetLink = trim((string)($data['reset_link'] ?? ''));
    $expiresLabel = trim((string)($data['expires_label'] ?? ''));

    if ($toEmail === '' || $resetLink === '') {
        return false;
    }

    $safeTenantCode = htmlspecialchars($tenantCode, ENT_QUOTES, 'UTF-8');
    $safeResetLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $safeExpiresLabel = htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8');

    $subject = 'Recuperacao de senha - HelpDesk Facil';

    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#0f172a;">';
    $html .= '<h2 style="margin-bottom:8px;">Recuperacao de senha</h2>';
    $html .= '<p style="margin-top:0;">Recebemos uma solicitacao para redefinir sua senha no HelpDesk Facil.</p>';
    if ($safeTenantCode !== '') {
        $html .= '<p><strong>Codigo da empresa:</strong> '.$safeTenantCode.'</p>';
    }
    $html .= '<p>Clique no botao abaixo para criar uma nova senha:</p>';
    $html .= '<p><a href="'.$safeResetLink.'" style="display:inline-block;padding:10px 16px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;">Redefinir senha</a></p>';
    $html .= '<p style="margin-top:10px;">Se o botao nao funcionar, copie e cole este link no navegador:</p>';
    $html .= '<p style="word-break:break-all;"><a href="'.$safeResetLink.'">'.$safeResetLink.'</a></p>';
    if ($safeExpiresLabel !== '') {
        $html .= '<p style="color:#475569;">Este link e valido ate '.$safeExpiresLabel.'.</p>';
    }
    $html .= '<p style="color:#64748b;">Se voce nao solicitou, ignore este e-mail.</p>';
    $html .= '</body></html>';

    $text = "Recuperacao de senha - HelpDesk Facil\n\n";
    $text .= "Recebemos uma solicitacao para redefinir sua senha.\n";
    if ($tenantCode !== '') {
        $text .= "Codigo da empresa: ".$tenantCode."\n";
    }
    $text .= "Redefinir senha: ".$resetLink."\n";
    if ($expiresLabel !== '') {
        $text .= "Valido ate: ".$expiresLabel."\n";
    }
    $text .= "\nSe voce nao solicitou, ignore este e-mail.\n";

    return hfSendEmail($toEmail, $subject, $html, $text);
}

function hfSendTenantCodeRecoveryEmail(array $data)
{
    $toEmail = trim((string)($data['email'] ?? ''));
    $tenantCode = trim((string)($data['tenant_code'] ?? ''));
    $tenantName = trim((string)($data['tenant_name'] ?? ''));
    $loginUrl = trim((string)($data['login_url'] ?? ''));

    if ($toEmail === '' || $tenantCode === '') {
        return false;
    }

    $safeTenantCode = htmlspecialchars($tenantCode, ENT_QUOTES, 'UTF-8');
    $safeTenantName = htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8');
    $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

    $subject = 'Recuperacao do codigo da empresa - HelpDesk Facil';

    $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#0f172a;">';
    $html .= '<h2 style="margin-bottom:8px;">Recuperacao do codigo da empresa</h2>';
    $html .= '<p style="margin-top:0;">Recebemos uma solicitacao para recuperar o codigo da empresa no HelpDesk Facil.</p>';
    if ($safeTenantName !== '') {
        $html .= '<p><strong>Empresa:</strong> '.$safeTenantName.'</p>';
    }
    $html .= '<p><strong>Codigo da empresa:</strong> '.$safeTenantCode.'</p>';
    if ($safeLoginUrl !== '') {
        $html .= '<p><a href="'.$safeLoginUrl.'" style="display:inline-block;padding:10px 16px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;">Ir para login</a></p>';
    }
    $html .= '<p style="color:#64748b;">Guarde este codigo em local seguro. Ele sera solicitado no login.</p>';
    $html .= '</body></html>';

    $text = "Recuperacao do codigo da empresa - HelpDesk Facil\n\n";
    if ($tenantName !== '') {
        $text .= "Empresa: ".$tenantName."\n";
    }
    $text .= "Codigo da empresa: ".$tenantCode."\n";
    if ($loginUrl !== '') {
        $text .= "Login: ".$loginUrl."\n";
    }
    $text .= "\nGuarde este codigo em local seguro.\n";

    return hfSendEmail($toEmail, $subject, $html, $text);
}
