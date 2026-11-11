
<?php

session_start();

date_default_timezone_set("America/Sao_Paulo");

header("Content-Type: application/json; charset=UTF-8");

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
// IDENTIFICAR SESSÃO
// ========================================

$id_recepcionista =
    $_SESSION["id_recepcionista"]
    ?? $_SESSION["id_rec"]
    ?? $_SESSION["id"]
    ?? null;

$id_clinica = $_SESSION["id_clinica_ativa"] ?? null;

if (empty($id_recepcionista) || empty($id_clinica)) {

    responder([
        "sucesso" => false,
        "mensagem" => "Sessão expirada. Entre novamente na clínica."
    ], 401);

}

$id_recepcionista = (int) $id_recepcionista;
$id_clinica = (int) $id_clinica;

$acao = $_GET["acao"] ?? "";


// ========================================
// VERIFICAR ACESSO À CLÍNICA
// ========================================

function verificarAcesso($conn, $id_recepcionista, $id_clinica) {

    $sql = "

        SELECT c.id_clinica

        FROM contrato_recepcionista cr

        INNER JOIN clinica c
            ON c.id_clinica = cr.id_clinica

        WHERE cr.id_recepcionista = ?
          AND cr.id_clinica = ?
          AND cr.status = 'contratada'
          AND c.status_cadastro = 'autorizada'
          AND (
              cr.data_fim IS NULL
              OR cr.data_fim >= CURDATE()
          )

        LIMIT 1

    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Erro ao verificar acesso à clínica.");
    }

    $stmt->bind_param(
        "ii",
        $id_recepcionista,
        $id_clinica
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $autorizado = $resultado->num_rows > 0;

    $stmt->close();

    return $autorizado;
}


// ========================================
// PROCESSAMENTO
// ========================================

try {

    // Verifica se a recepcionista ainda pode acessar a clínica

    if (!verificarAcesso(
        $conn,
        $id_recepcionista,
        $id_clinica
    )) {

        unset($_SESSION["id_clinica_ativa"]);

        responder([
            "sucesso" => false,
            "mensagem" => "Seu contrato não está ativo ou a clínica não está autorizada."
        ], 403);

    }


    // ========================================
    // CONSULTAR ATENDIMENTOS
    // ========================================

    if ($acao === "consultar") {

        if ($_SERVER["REQUEST_METHOD"] !== "GET") {

            responder([
                "sucesso" => false,
                "mensagem" => "Método não permitido."
            ], 405);

        }


        $sql = "

            SELECT

                c.id_consulta,
                c.id_tutor,
                c.id_pet,
                c.data_hora,
                c.motivo,
                c.status,
                c.observacoes,

                t.nome_completo AS nome_tutor,
                t.cpf,

                p.nome AS nome_pet,
                p.especie,
                p.raca

            FROM consultas c

            INNER JOIN tutor t
                ON t.id_tutor = c.id_tutor

            INNER JOIN pet p
                ON p.id_pet = c.id_pet
                AND p.id_tutor = c.id_tutor

            WHERE c.id_clinica = ?

            ORDER BY c.data_hora ASC

        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Erro ao consultar os atendimentos.");
        }

        $stmt->bind_param("i", $id_clinica);

        $stmt->execute();

        $resultado = $stmt->get_result();

        $atendimentos = [];

        while ($row = $resultado->fetch_assoc()) {

            $atendimentos[] = [

                "id_consulta" => (int) $row["id_consulta"],

                "id_tutor" => (int) $row["id_tutor"],

                "id_pet" => (int) $row["id_pet"],

                "nome_tutor" => $row["nome_tutor"],

                "cpf" => $row["cpf"],

                "nome_pet" => $row["nome_pet"],

                "especie" => $row["especie"],

                "raca" => $row["raca"],

                "data_hora" => $row["data_hora"],

                "motivo" => $row["motivo"],

                "status" => $row["status"],

                "observacoes" => $row["observacoes"]

            ];

        }

        $stmt->close();

        responder([

            "sucesso" => true,

            "data_hora_servidor" => date("c"),

            "atendimentos" => $atendimentos

        ]);

    }


    // ========================================
    // ALTERAR STATUS DO ATENDIMENTO
    // ========================================

    if ($acao === "alterar_status") {

        if ($_SERVER["REQUEST_METHOD"] !== "POST") {

            responder([
                "sucesso" => false,
                "mensagem" => "Método não permitido."
            ], 405);

        }


        $id_consulta = filter_var(
            $_POST["id_consulta"] ?? null,
            FILTER_VALIDATE_INT
        );

        $novo_status = trim($_POST["status"] ?? "");


        // Validação do ID

        if (!$id_consulta || $id_consulta <= 0) {

            responder([
                "sucesso" => false,
                "mensagem" => "Atendimento inválido."
            ], 400);

        }


        // Somente estes dois status podem ser definidos pela recepção

        if (!in_array(
            $novo_status,
            ["realizada", "cancelada"],
            true
        )) {

            responder([
                "sucesso" => false,
                "mensagem" => "Status inválido."
            ], 400);

        }


        // ========================================
        // VERIFICAR ATENDIMENTO
        // ========================================

        $stmt = $conn->prepare("

            SELECT id_consulta, status

            FROM consultas

            WHERE id_consulta = ?
              AND id_clinica = ?

            LIMIT 1

        ");

        if (!$stmt) {
            throw new Exception("Erro ao verificar atendimento.");
        }

        $stmt->bind_param(
            "ii",
            $id_consulta,
            $id_clinica
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $consulta = $resultado->fetch_assoc();

        $stmt->close();


        if (!$consulta) {

            responder([
                "sucesso" => false,
                "mensagem" => "Atendimento não encontrado nesta clínica."
            ], 404);

        }


        // ========================================
        // IMPEDIR ALTERAÇÃO DE STATUS FINAL
        // ========================================

        if (
            $consulta["status"] === "realizada" ||
            $consulta["status"] === "cancelada"
        ) {

            responder([
                "sucesso" => false,
                "mensagem" => "Este atendimento já foi finalizado e não pode ser alterado."
            ], 400);

        }


        if (
            $consulta["status"] !== "pendente" &&
            $consulta["status"] !== "confirmada"
        ) {

            responder([
                "sucesso" => false,
                "mensagem" => "Não é possível alterar o status deste atendimento."
            ], 400);

        }


        // ========================================
        // ATUALIZAR NO BANCO
        // ========================================

        $stmt = $conn->prepare("

            UPDATE consultas

            SET status = ?

            WHERE id_consulta = ?
              AND id_clinica = ?
              AND status IN ('pendente', 'confirmada')

        ");

        if (!$stmt) {
            throw new Exception("Erro ao preparar a atualização.");
        }

        $stmt->bind_param(
            "sii",
            $novo_status,
            $id_consulta,
            $id_clinica
        );

        $stmt->execute();

        $alterado = $stmt->affected_rows === 1;

        $stmt->close();


        if (!$alterado) {

            responder([
                "sucesso" => false,
                "mensagem" => "O atendimento não foi atualizado. Ele pode já ter sido alterado."
            ], 409);

        }


        // ========================================
        // RESPOSTA
        // ========================================

        if ($novo_status === "realizada") {

            $mensagem = "Atendimento marcado como realizado com sucesso.";

        } else {

            $mensagem = "Atendimento cancelado com sucesso.";

        }


        responder([

            "sucesso" => true,

            "mensagem" => $mensagem

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
        "Erro em visualizar_atendimentos.php: " .
        $e->getMessage()
    );

    responder([
        "sucesso" => false,
        "mensagem" => "Ocorreu um erro ao processar os atendimentos."
    ], 500);

}

?>
