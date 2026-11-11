<?php
session_start();
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . "/conexao.php"; 

// Garante compatibilidade caso a sessão use 'id' ou 'id_veterinario'
$id_veterinario = $_SESSION["id"] ?? $_SESSION["id_veterinario"] ?? null;

if (empty($id_veterinario)) {
    exit("Não autorizado");
}

$id_veterinario = (int) $id_veterinario;

try {
    // 1. Busca o nome do veterinário
    $stmt = $conn->prepare("SELECT nome FROM veterinarios WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id_veterinario);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $nome_completo = $resultado ? $resultado['nome'] : 'Veterinário';
    $primeiro_nome = explode(' ', trim($nome_completo))[0];

    // 2. Conta as clínicas contratadas (ativas)
    $stmtContratadas = $conn->prepare("SELECT COUNT(*) as total FROM contrato_veterinario WHERE id_veterinario = ? AND status = 'contratado'");
    $stmtContratadas->bind_param("i", $id_veterinario);
    $stmtContratadas->execute();
    $total_contratadas = $stmtContratadas->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtContratadas->close();

    // 3. Conta as clínicas demitidas (encerradas)
    $stmtDemitidas = $conn->prepare("SELECT COUNT(*) as total FROM contrato_veterinario WHERE id_veterinario = ? AND status = 'demitido'");
    $stmtDemitidas->bind_param("i", $id_veterinario);
    $stmtDemitidas->execute();
    $total_demitidas = $stmtDemitidas->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtDemitidas->close();

    // Retorna limpo: Nome Completo | Primeiro Nome | Clínicas Contratadas | Clínicas Demitidas
    echo $nome_completo . "|" . $primeiro_nome . "|" . $total_contratadas . "|" . $total_demitidas;

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}