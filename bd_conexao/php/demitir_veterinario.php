<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["id_dono"])) {
    exit("Acesso negado. Faça login novamente.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: detalhe_contrato_veterinario.php?status=contratado");
    exit;
}

$id_dono = (int) $_SESSION["id_dono"];

$id_contrato = filter_input(
    INPUT_POST,
    "id_contrato",
    FILTER_VALIDATE_INT
);

if (!$id_contrato || $id_contrato <= 0) {
    header("Location: detalhe_contrato_veterinario.php?status=contratado&resultado=erro");
    exit;
}

$sql = "
    UPDATE contrato_veterinario cv
    INNER JOIN clinica c
        ON c.id_clinica = cv.id_clinica
    SET
        cv.status = 'demitido',
        cv.data_fim = COALESCE(cv.data_fim, CURDATE())
    WHERE cv.id_contrato = ?
      AND cv.status = 'contratado'
      AND c.id_dono = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro ao preparar demissão do veterinário: " . $conn->error);
    header("Location: detalhe_contrato_veterinario.php?status=contratado&resultado=erro");
    exit;
}

$stmt->bind_param("ii", $id_contrato, $id_dono);

if (!$stmt->execute()) {
    error_log("Erro ao demitir veterinário: " . $stmt->error);
    $stmt->close();

    header("Location: detalhe_contrato_veterinario.php?status=contratado&resultado=erro");
    exit;
}

$alteradas = $stmt->affected_rows;

$stmt->close();
$conn->close();

if ($alteradas === 1) {
    header("Location: detalhe_contrato_veterinario.php?status=contratado&resultado=demitido");
    exit;
}

header("Location: detalhe_contrato_veterinario.php?status=contratado&resultado=erro");
exit;