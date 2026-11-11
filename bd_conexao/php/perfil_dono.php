
<?php
session_start();

// Verifica se existe um usuário identificado na sessão
if (!isset($_SESSION["id_dono"])) {
    echo "<script>
        alert('Faça login para acessar seu perfil.');
        window.location.href = 'login.html';
    </script>";
    exit;
}

// Se estiver logado, abre a página do perfil
header("Location: ../../incial/pages/clinica/clinica/index.html");
exit;
?>
