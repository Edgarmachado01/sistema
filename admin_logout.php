<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['SAAS_ADMIN_ID'],
    $_SESSION['SAAS_ADMIN_AUTHED'],
    $_SESSION['SAAS_ADMIN_ROLES'],
    $_SESSION['SAAS_ADMIN_NAME'],
    $_SESSION['SAAS_ADMIN_EMAIL'],
    $_SESSION['SAAS_ADMIN_CSRF']
);

header('Location: /admin_login.php');
exit;
