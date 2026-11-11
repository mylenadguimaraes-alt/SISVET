<?php
session_start();

// Caminho de conexão com base na sua árvore de pastas[cite: 2]
require_once __DIR__ . "/conexao.php"; 

if (empty($_SESSION["id_tutor"])) {
    exit("Não autorizado");
}

$id_tutor = (int) $_SESSION["id_tutor"];

try {
    // 1. Nome do tutor
    $stmtTutor = $conn->prepare("SELECT nome_completo FROM tutor WHERE id_tutor = ? LIMIT 1");
    $stmtTutor->bind_param("i", $id_tutor);
    $stmtTutor->execute();
    $tutor = $stmtTutor->get_result()->fetch_assoc();
    $stmtTutor->close();

    $nome_completo = $tutor ? $tutor['nome_completo'] : 'Tutor';
    $primeiro_nome = explode(' ', trim($nome_completo))[0];

    // 2. Contar Pets
    $stmtPets = $conn->prepare("SELECT COUNT(*) as total FROM pet WHERE id_tutor = ?");
    $stmtPets->bind_param("i", $id_tutor);
    $stmtPets->execute();
    $total_pets = $stmtPets->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtPets->close();

    // 3. Contar Consultas
    $stmtConsultas = $conn->prepare("SELECT COUNT(*) as total FROM consultas WHERE id_tutor = ? AND status IN ('pendente', 'confirmada')");
    $stmtConsultas->bind_param("i", $id_tutor);
    $stmtConsultas->execute();
    $total_consultas = $stmtConsultas->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtConsultas->close();

    // 4. Contar Autorizações
    $stmtAutorizacoes = $conn->prepare("SELECT COUNT(*) as total FROM autorizacoes WHERE id_tutor = ? AND status IN ('espera', 'confirmado')");
    $stmtAutorizacoes->bind_param("i", $id_tutor);
    $stmtAutorizacoes->execute();
    $total_autorizacoes = $stmtAutorizacoes->get_result()->fetch_assoc()['total'] ?? 0;
    $stmtAutorizacoes->close();

    // Retorna os dados em formato de texto simples separado por hífen ou pipe (|)
    // Ordem: NomeCompleto | PrimeiroNome | TotalPets | TotalConsultas | TotalAutorizacoes
    echo $nome_completo . "|" . $primeiro_nome . "|" . $total_pets . "|" . $total_consultas . "|" . $total_autorizacoes;

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>