<?php
// Desativa a exibição direta de erros em HTML para não corromper o JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . "/conexao.php";

    if (!isset($conn)) {
        throw new Exception("Erro de conexão com a base de dados.");
    }

    $id_pet = filter_input(INPUT_GET, 'id_pet', FILTER_VALIDATE_INT);

    if (!$id_pet || $id_pet <= 0) {
        throw new Exception('ID do pet inválido.');
    }

    $sql = "
        SELECT 
            d.id_documento, 
            d.nome_documento, 
            d.caminho_documento, 
            d.data_cadastro,
            p.nome AS nome_pet,
            t.nome_completo AS nome_tutor,
            c.nome_clinica,
            v.nome AS nome_veterinario
        FROM documentos d
        INNER JOIN pet p ON p.id_pet = d.id_pet
        INNER JOIN tutor t ON t.id_tutor = p.id_tutor
        INNER JOIN clinica c ON c.id_clinica = d.id_clinica
        INNER JOIN veterinarios v ON v.id = d.id_veterinario
        WHERE d.id_pet = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro na preparação da consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $id_pet);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $documentos = [];
    while ($row = $resultado->fetch_assoc()) {
        $documentos[] = [
            'id_documento' => (int) $row['id_documento'],
            'nome_documento' => $row['nome_documento'],
            'caminho_documento' => $row['caminho_documento'],
            'data_cadastro' => $row['data_cadastro'] ? date('d/m/Y H:i', strtotime($row['data_cadastro'])) : 'Não informada',
            'nome_pet' => $row['nome_pet'] ?? 'Não vinculado',
            'nome_tutor' => $row['nome_tutor'] ?? 'Não vinculado',
            'nome_clinica' => $row['nome_clinica'] ?? 'Clínica não especificada',
            'nome_veterinario' => $row['nome_veterinario'] ?? 'Veterinário não especificado'
        ];
    }
    $stmt->close();

    echo json_encode([
        'sucesso' => true,
        'documentos' => $documentos
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ]);
}
?>