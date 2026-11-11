
<?php
session_start();

// Limpa os dados da sessão atual.
$_SESSION = [];

// Remove o cookie da sessão do navegador.
if (ini_get("session.use_cookies")) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parametros["path"],
        $parametros["domain"],
        $parametros["secure"],
        $parametros["httponly"]
    );
}

// Encerra a sessão.
session_destroy();

// Redireciona para o login principal do SISVET.
header("Location: /sisvet/incial");
exit;
