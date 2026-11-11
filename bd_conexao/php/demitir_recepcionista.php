
<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["id_dono"])) {
    exit("Acesso negado. Faça login novamente.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dados_recepcionista.php?status=contratada");
    exit;
}

$id_dono = (int) $_SESSION["id_dono"];

$id_contrato = filter_input(
    INPUT_POST,
    "id_contrato",
    FILTER_VALIDATE_INT
);

if (!$id_contrato || $id_contrato <= 0) {
    header("Location: dados_recepcionista.php?status=contratada&resultado=erro");
    exit;
}

$sql = "
    UPDATE contrato_recepcionista cr
    INNER JOIN clinica c
        ON c.id_clinica = cr.id_clinica
    SET
        cr.status = 'demitida',
        cr.data_fim = COALESCE(cr.data_fim, CURDATE())
    WHERE cr.id_contrato = ?
      AND cr.status = 'contratada'
      AND c.id_dono = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro ao preparar demissão: " . $conn->error);
    header("Location: dados_recepcionistas.php?status=contratada&resultado=erro");
    exit;
}

$stmt->bind_param("ii", $id_contrato, $id_dono);

if (!$stmt->execute()) {
    error_log("Erro ao demitir recepcionista: " . $stmt->error);
    $stmt->close();

    header("Location: dados_recepcionista.php?status=contratado&resultado=erro");
    exit;
}

$alteradas = $stmt->affected_rows;

$stmt->close();
$conn->close();

if ($alteradas === 1) {
    header("Location: dados_recepcionista.php?status=contratado&resultado=demitido");
    exit;
}

header("Location: dados_recepcionista.php?status=contratado&resultado=erro");
exit;
?>
