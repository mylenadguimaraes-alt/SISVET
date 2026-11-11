
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";


// ========================================
// RESPOSTA JSON
// ========================================

function responder($dados, $codigo = 200)
{
    http_response_code($codigo);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ========================================
// VALIDAR SESSÃO
// ========================================

$id_recepcionista = $_SESSION["id_recepcionista"]
    ?? $_SESSION["id_rec"]
    ?? $_SESSION["id"]
    ?? null;

$id_clinica = $_SESSION["id_clinica_ativa"] ?? null;


if (empty($id_recepcionista) || empty($id_clinica)) {

    responder([
        "sucesso" => false,
        "mensagem" => "Sua sessão expirou. Entre novamente na clínica."
    ], 401);

}

$id_recepcionista = (int) $id_recepcionista;
$id_clinica = (int) $id_clinica;


// ========================================
// VERIFICAR ACESSO À CLÍNICA
// ========================================

try {

    $stmt = $conn->prepare("
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
    ");

    if (!$stmt) {
        throw new Exception("Erro ao verificar o acesso à clínica.");
    }

    $stmt->bind_param(
        "ii",
        $id_recepcionista,
        $id_clinica
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $acesso = $resultado->num_rows > 0;

    $stmt->close();


    if (!$acesso) {

        unset($_SESSION["id_clinica_ativa"]);

        responder([
            "sucesso" => false,
            "mensagem" => "Seu contrato não está ativo ou a clínica não está autorizada."
        ], 403);

    }


    // ========================================
    // CONSULTAR AUTORIZAÇÕES
    // ========================================

    $sql = "
        SELECT

            a.id_autorizacao,
            a.status,
            a.data_solicitacao,
            a.data_atualizacao,

            t.nome_completo AS nome_tutor,
            t.cpf,

            p.nome AS nome_pet,
            p.especie,
            p.raca

        FROM autorizacoes a

        INNER JOIN tutor t
            ON t.id_tutor = a.id_tutor

        INNER JOIN pet p
            ON p.id_pet = a.id_pet
            AND p.id_tutor = a.id_tutor

        WHERE a.id_clinica = ?

        ORDER BY
            a.data_solicitacao DESC,
            a.id_autorizacao DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta de autorizações.");
    }

    $stmt->bind_param("i", $id_clinica);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $autorizacoes = [];


    // ========================================
    // ORGANIZAR AUTORIZAÇÕES
    // ========================================

    $statusPermitidos = [
        "espera" => "Em espera",
        "confirmado" => "Confirmada",
        "negado" => "Negada",
        "revogado" => "Revogada"
    ];

    while ($row = $resultado->fetch_assoc()) {

        $status = $row["status"];

        if (!isset($statusPermitidos[$status])) {
            continue;
        }

        $autorizacoes[] = [

            "id_autorizacao" => (int) $row["id_autorizacao"],

            "status" => $status,

            "status_formatado" => $statusPermitidos[$status],

            "nome_tutor" => $row["nome_tutor"],

            "cpf" => $row["cpf"],

            "nome_pet" => $row["nome_pet"],

            "especie" => $row["especie"],

            "raca" => $row["raca"],

            "data_solicitacao" => $row["data_solicitacao"],

            "data_atualizacao" => $row["data_atualizacao"]

        ];

    }

    $stmt->close();


    // ========================================
    // RETORNAR DADOS
    // ========================================

    responder([
        "sucesso" => true,
        "mensagem" => "Autorizações carregadas com sucesso.",
        "autorizacoes" => $autorizacoes
    ]);


} catch (Throwable $e) {

    error_log(
        "Erro ao visualizar autorizações: " . $e->getMessage()
    );

    responder([
        "sucesso" => false,
        "mensagem" => "Ocorreu um erro ao consultar as autorizações."
    ], 500);

}

?>
