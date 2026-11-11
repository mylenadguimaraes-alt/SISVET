<?php
session_start();
header('Content-Type: text/plain; charset=UTF-8');

// Ajuste o caminho para o seu ficheiro de conexão real conforme a estrutura da sua árvore de pastas
require_once __DIR__ . "/conexao.php"; 

// Garante compatibilidade caso a sessão use 'id' ou 'id_recepcionista'
$id_recepcionista = $_SESSION["id"] ?? $_SESSION["id_recepcionista"] ?? null;

if (empty($id_recepcionista)) {
    exit("Não autorizado");
}

$id_recepcionista = (int) $id_recepcionista;

try {
    // 1. Busca o nome da recepcionista
    $stmt = $conn->prepare("SELECT nome FROM recepcionista WHERE id_recepcionista = ? LIMIT 1");
    $stmt->bind_param("i", $id_recepcionista);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $nome_completo = $resultado ? $resultado['nome'] : 'Recepcionista';
    $primeiro_nome = explode(' ', trim($nome_completo))[0];

    // 2. Conta as clínicas contratadas (ativas)
    $stmtContratadas = $conn->prepare("SELECT COUNT(*) as total FROM contrato_recepcionista WHERE id_recepcionista = ? AND status = 'contratada'");
    $stmtContratadas->bind_param("i", $id_recepcionista);
    $stmtContratadas->execute();
    $total_contratadas = $stmtContratadas->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtContratadas->close();

    // 3. Conta as clínicas demitidas (encerradas)
    $stmtDemitidas = $conn->prepare("SELECT COUNT(*) as total FROM contrato_recepcionista WHERE id_recepcionista = ? AND status = 'demitida'");
    $stmtDemitidas->bind_param("i", $id_recepcionista);
    $stmtDemitidas->execute();
    $total_demitidas = $stmtDemitidas->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtDemitidas->close();

    // Retorna limpo: Nome Completo | Primeiro Nome | Clínicas Contratadas | Clínicas Demitidas
    echo $nome_completo . "|" . $primeiro_nome . "|" . $total_contratadas . "|" . $total_demitidas;

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>