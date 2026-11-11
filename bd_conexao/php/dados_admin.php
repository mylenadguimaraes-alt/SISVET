<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once "conexao.php";

// Garante o charset UTF-8 na conexão
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// Verifica se o administrador está logado na sessão correta
if (!isset($_SESSION["id_administrador"]) && !isset($_SESSION["id_admin"])) {
    echo json_encode(["erro" => "Não autorizado"]);
    http_response_code(401);
    exit;
}

$id_admin = $_SESSION["id_administrador"] ?? $_SESSION["id_admin"];

// 1. Buscar o nome do administrador (coluna correta: nome_completo na tabela administrador)
$sql_admin = "SELECT nome_completo FROM administrador WHERE id_administrador = ? LIMIT 1";
$stmt_a = $conn->prepare($sql_admin);
$nome_admin = "Administrador(a)";

if ($stmt_a) {
    $stmt_a->bind_param("i", $id_admin);
    $stmt_a->execute();
    $res_a = $stmt_a->get_result()->fetch_assoc();
    if ($res_a && !empty($res_a["nome_completo"])) {
        // Pega apenas o primeiro nome ou o nome completo conforme preferir
        $nome_admin = $res_a["nome_completo"];
    }
    $stmt_a->close();
}

// 2. Contar Clínicas Autorizadas (status_cadastro = 'autorizada')
$autorizadas = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM clinica WHERE status_cadastro = 'autorizada'");
if ($res) {
    $autorizadas = (int) $res->fetch_assoc()['total'];
}

// 3. Contar Clínicas em Espera (status_cadastro = 'em_espera')
$espera = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM clinica WHERE status_cadastro = 'em_espera'");
if ($res) {
    $espera = (int) $res->fetch_assoc()['total'];
}

// 4. Contar Clínicas Suspensas (status_cadastro = 'suspensa')
$suspensas = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM clinica WHERE status_cadastro = 'suspensa'");
if ($res) {
    $suspensas = (int) $res->fetch_assoc()['total'];
}

// 5. Contar Denúncias (pendentes ou em análise)
$denuncias = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM denuncias WHERE status = 'pendente' OR status = 'em analise'");
if ($res) {
    $denuncias = (int) $res->fetch_assoc()['total'];
}

// Retorna os dados consolidados em JSON
echo json_encode([
    "sucesso" => true,
    "nome" => $nome_admin,
    "autorizadas" => $autorizadas,
    "espera" => $espera,
    "suspensas" => $suspensas,
    "denuncias" => $denuncias
], JSON_UNESCAPED_UNICODE);