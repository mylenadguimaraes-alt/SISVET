
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

date_default_timezone_set("America/Sao_Paulo");

require_once __DIR__ . "/conexao.php";


// ========================================
// RESPOSTA JSON
// ========================================

function responder($dados, $codigo = 200) {

    http_response_code($codigo);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ========================================
// IDENTIFICAÇÃO DA SESSÃO
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
        "mensagem" => "Sessão inválida. Entre novamente na clínica."
    ], 401);

}

$id_veterinario = (int) $id_veterinario;
$id_clinica = (int) $id_clinica;


// ========================================
// VERIFICAÇÃO DO ACESSO À CLÍNICA
// ========================================

function verificarAcesso(
    $conn,
    $id_veterinario,
    $id_clinica
) {

    $sql = "

        SELECT c.id_clinica

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

        throw new Exception(
            "Erro ao verificar o contrato do veterinário."
        );

    }

    $stmt->bind_param(
        "ii",
        $id_veterinario,
        $id_clinica
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $autorizado = $resultado->num_rows > 0;

    $stmt->close();

    return $autorizado;

}


// ========================================
// CONSULTAR ATENDIMENTOS DO DIA
// ========================================

try {

    if (!verificarAcesso(
        $conn,
        $id_veterinario,
        $id_clinica
    )) {

        unset($_SESSION["id_clinica_ativa"]);

        responder([
            "sucesso" => false,
            "mensagem" => "Seu contrato não está ativo ou a clínica não está autorizada. Entre novamente."
        ], 403);

    }


    // Verifica a ação

    $acao = $_GET["acao"] ?? "";

    if ($acao !== "consultar") {

        responder([
            "sucesso" => false,
            "mensagem" => "Ação não encontrada."
        ], 400);

    }


    // Data atual

    $hoje = date("Y-m-d");


    // ========================================
    // BUSCAR CONSULTAS
    // ========================================

    $sql = "

        SELECT

            co.id_consulta,

            co.data_hora,

            co.motivo,

            co.observacoes,

            co.status,

            t.nome_completo AS nome_tutor,

            t.email,

            p.id_pet,

            p.nome AS nome_pet,

            p.especie

        FROM consultas co

        INNER JOIN tutor t
            ON t.id_tutor = co.id_tutor

        INNER JOIN pet p
            ON p.id_pet = co.id_pet
            AND p.id_tutor = co.id_tutor

        WHERE co.id_clinica = ?

          AND co.data_hora >= ?

          AND co.data_hora < DATE_ADD(
              ?,
              INTERVAL 1 DAY
          )

        ORDER BY co.data_hora ASC

    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Erro ao preparar a consulta dos atendimentos."
        );

    }

    $stmt->bind_param(
        "iss",
        $id_clinica,
        $hoje,
        $hoje
    );

    $stmt->execute();

    $resultado = $stmt->get_result();


    // ========================================
    // ORGANIZAR RESULTADOS
    // ========================================

    $consultas = [];

    while ($row = $resultado->fetch_assoc()) {

        $consultas[] = [

            "id_consulta" => (int) $row["id_consulta"],

            "data_hora" => $row["data_hora"],

            "nome_tutor" => $row["nome_tutor"],

            "email" => $row["email"],

            "id_pet" => (int) $row["id_pet"],

            "nome_pet" => $row["nome_pet"],

            "especie" => $row["especie"],

            "motivo" => $row["motivo"],

            "observacoes" => $row["observacoes"],

            "status" => $row["status"]

        ];

    }

    $stmt->close();


    // ========================================
    // RETORNAR RESULTADO
    // ========================================

    responder([

        "sucesso" => true,

        "data" => $hoje,

        "total" => count($consultas),

        "consultas" => $consultas

    ]);


} catch (Throwable $e) {

    error_log(
        "Erro ao consultar atendimentos do veterinário: "
        . $e->getMessage()
    );

    responder([
        "sucesso" => false,
        "mensagem" => "Ocorreu um erro ao consultar os atendimentos."
    ], 500);

}

?>
