<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once "conexao.php";

// Garante a codificação UTF-8
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome_completo = trim($_POST["nome_completo"] ?? "");
    $email         = trim($_POST["email"] ?? "");
    $senha_pura    = trim($_POST["senha"] ?? "");

    if ($nome_completo === "" || $email === "" || $senha_pura === "") {
        echo "<script>alert('Preencha todos os campos obrigatórios.'); window.history.back();</script>";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Informe um e-mail válido.'); window.history.back();</script>";
        exit;
    }

    // Verificar se o e-mail já está cadastrado na tabela administrador
    $sql_verifica = "SELECT id_administrador FROM administrador WHERE email = ? LIMIT 1";
    $stmt_v = $conn->prepare($sql_verifica);
    $stmt_v->bind_param("s", $email);
    $stmt_v->execute();
    $res_v = $stmt_v->get_result();

    if ($res_v->num_rows > 0) {
        $stmt_v->close();
        echo "<script>alert('Este e-mail já está cadastrado para outro administrador.'); window.history.back();</script>";
        exit;
    }
    $stmt_v->close();

    // Criptografar a senha com segurança
    $senha_hash = password_hash($senha_pura, PASSWORD_DEFAULT);

    // Inserir novo administrador no banco
    $sql_insert = "INSERT INTO administrador (nome_completo, email, senha, ativo) VALUES (?, ?, ?, 1)";
    $stmt_i = $conn->prepare($sql_insert);
    $stmt_i->bind_param("sss", $nome_completo, $email, $senha_hash);

    if ($stmt_i->execute()) {
        echo "<script>alert('Administrador cadastrado com sucesso!'); window.location.href = '../../incial/pages/sistema/painel_adm/pages/cadastrar_adm/index.html';</script>";
    } else {
        echo "<script>alert('Erro ao cadastrar administrador: " . addslashes($stmt_i->error) . "'); window.history.back();</script>";
    }

    $stmt_i->close();
    $conn->close();
} else {
    header("Location: ../../incial/pages/sistema/painel_adm/pages/cadastrar_adm/index.html");
    exit;
}