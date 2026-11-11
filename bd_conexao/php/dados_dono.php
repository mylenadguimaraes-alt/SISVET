<?php
session_start();
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . "/conexao.php";

if (empty($_SESSION["id_dono"])) {
    exit("Não autorizado");
}

$id_dono = (int) $_SESSION["id_dono"];

try {
    // 1. Buscar nome completo e primeiro nome do dono da clínica
    $stmtDono = $conn->prepare("SELECT nome_completo FROM dono_clinica WHERE id_dono = ? LIMIT 1");
    $stmtDono->bind_param("i", $id_dono);
    $stmtDono->execute();
    $dono = $stmtDono->get_result()->fetch_assoc();
    $stmtDono->close();

    $nome_completo = $dono ? $dono['nome_completo'] : 'Dono';
    $primeiro_nome = explode(' ', trim($nome_completo))[0];

    // 2. Contar clínicas "em_espera" (status_cadastro = 'em_espera')
    $stmtEspera = $conn->prepare("SELECT COUNT(*) as total FROM clinica WHERE id_dono = ? AND status_cadastro = 'em_espera'");
    $stmtEspera->bind_param("i", $id_dono);
    $stmtEspera->execute();
    $total_espera = $stmtEspera->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtEspera->close();

    // 3. Contar clínicas "autorizadas" (status_cadastro = 'autorizada')
    $stmtAutorizadas = $conn->prepare("SELECT COUNT(*) as total FROM clinica WHERE id_dono = ? AND status_cadastro = 'autorizada'");
    $stmtAutorizadas->bind_param("i", $id_dono);
    $stmtAutorizadas->execute();
    $total_autorizadas = $stmtAutorizadas->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtAutorizadas->close();

    // 4. Contar clínicas "suspensas" (status_cadastro = 'suspensa')
    $stmtSuspensas = $conn->prepare("SELECT COUNT(*) as total FROM clinica WHERE id_dono = ? AND status_cadastro = 'suspensa'");
    $stmtSuspensas->bind_param("i", $id_dono);
    $stmtSuspensas->execute();
    $total_suspensas = $stmtSuspensas->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtSuspensas->close();

    // Retorna os dados separados por pipe (|)
    // Ordem: NomeCompleto | PrimeiroNome | TotalEspera | TotalAutorizadas | TotalSuspensas
    echo $nome_completo . "|" . $primeiro_nome . "|" . $total_espera . "|" . $total_autorizadas . "|" . $total_suspensas;

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>