
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

function responder($dados, $codigo = 200) {

    http_response_code($codigo);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ========================================
// SESSÃO
// ========================================

$id_veterinario =
    $_SESSION["id_veterinario"]
    ?? $_SESSION["id_vet"]
    ?? $_SESSION["id"]
    ?? null;

$id_clinica = $_SESSION["id_clinica_ativa"] ?? null;

if (empty($id_veterinario) || empty($id_clinica)) {

    responder([
        "sucesso" => false,
        "mensagem" => "Sessão expirada. Entre novamente na clínica."
    ], 401);

}

$id_veterinario = (int) $id_veterinario;
$id_clinica = (int) $id_clinica;

$acao = $_GET["acao"] ?? "";


// ========================================
// VERIFICAR CONTRATO
// ========================================

function verificarContrato($conn, $id_veterinario, $id_clinica) {

    $sql = "

        SELECT c.id_clinica, c.nome_clinica

        FROM contrato_veterinario cv

        INNER JOIN clinica c
            ON c.id_clinica = cv.id_clinica

        WHERE cv.id_veterinario = ?
          AND cv.id_clinica = ?
          AND cv.status = 'contratado'
          AND c.status_cadastro = 'autorizada'
          AND (
              cv.data_fim IS NULL
              OR cv.data_fim >= CURDATE()
          )

        LIMIT 1

    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Erro ao verificar contrato.");
    }

    $stmt->bind_param(
        "ii",
        $id_veterinario,
        $id_clinica
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $clinica = $resultado->fetch_assoc();

    $stmt->close();

    return $clinica;

}


// ========================================
// CONSULTAR CLÍNICA
// ========================================

try {

    if ($acao === "consultar") {

        $clinica = verificarContrato(
            $conn,
            $id_veterinario,
            $id_clinica
        );

        if (!$clinica) {

            unset($_SESSION["id_clinica_ativa"]);

            responder([
                "sucesso" => false,
                "mensagem" => "Seu contrato não está ativo ou a clínica não está autorizada."
            ], 403);

        }

        responder([
            "sucesso" => true,
            "nome_clinica" => $clinica["nome_clinica"]
        ]);

    }


// ========================================
// SAIR DA CLÍNICA
// ========================================

    if ($acao === "sair") {

        if ($_SERVER["REQUEST_METHOD"] !== "POST") {

            responder([
                "sucesso" => false,
                "mensagem" => "Método não permitido."
            ], 405);

        }

        unset($_SESSION["id_clinica_ativa"]);

        responder([
            "sucesso" => true,
            "mensagem" => "Você saiu da clínica."
        ]);

    }


// ========================================
// AÇÃO INVÁLIDA
// ========================================

    responder([
        "sucesso" => false,
        "mensagem" => "Ação não encontrada."
    ], 400);


} catch (Throwable $e) {

    error_log(
        "Erro no painel do veterinário: " . $e->getMessage()
    );

    responder([
        "sucesso" => false,
        "mensagem" => "Ocorreu um erro ao processar sua solicitação."
    ], 500);

}

?>
