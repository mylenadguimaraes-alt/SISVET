
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";


// Identifica a recepcionista
$id_recepcionista = $_SESSION["id_recepcionista"]
    ?? $_SESSION["id_rec"]
    ?? $_SESSION["id"]
    ?? null;


// Identifica a clínica selecionada
$id_clinica = $_SESSION["id_clinica_ativa"] ?? null;


// Identifica a ação
$acao = $_GET["acao"] ?? "";


// Função de resposta JSON
function responder($dados, $codigo = 200) {

    http_response_code($codigo);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// Verifica a sessão da recepcionista
if (empty($id_recepcionista)) {

    responder([
        "sucesso" => false,
        "mensagem" => "Sua sessão expirou. Entre novamente no sistema."
    ], 401);
}


// ========================================
// AÇÃO: SAIR DA CLÍNICA
// ========================================

if ($acao === "sair") {

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        responder([
            "sucesso" => false,
            "mensagem" => "Método não permitido."
        ], 405);
    }

    // Remove somente a clínica ativa
    unset($_SESSION["id_clinica_ativa"]);

    responder([
        "sucesso" => true,
        "mensagem" => "Você saiu da clínica."
    ]);
}


// ========================================
// VERIFICA SE EXISTE UMA CLÍNICA ATIVA
// ========================================

if (empty($id_clinica)) {

    responder([
        "sucesso" => false,
        "mensagem" => "Nenhuma clínica foi selecionada."
    ], 403);
}


// ========================================
// AÇÃO: CONSULTAR NOME DA CLÍNICA
// ========================================

if ($acao === "consultar") {

    try {

        $sql = "
            SELECT
                c.id_clinica,
                c.nome_clinica

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

            throw new Exception(
                "Erro ao preparar a consulta: " . $conn->error
            );
        }

        $stmt->bind_param(
            "ii",
            $id_recepcionista,
            $id_clinica
        );

        if (!$stmt->execute()) {

            throw new Exception(
                "Erro ao consultar a clínica: " . $stmt->error
            );
        }

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {

            $stmt->close();

            unset($_SESSION["id_clinica_ativa"]);

            responder([
                "sucesso" => false,
                "mensagem" => "Seu contrato não está mais ativo ou a clínica não está autorizada. Selecione outra clínica."
            ], 403);
        }

        $clinica = $resultado->fetch_assoc();

        $stmt->close();

        responder([
            "sucesso" => true,
            "id_clinica" => (int) $clinica["id_clinica"],
            "nome_clinica" => $clinica["nome_clinica"]
        ]);

    } catch (Exception $e) {

        responder([
            "sucesso" => false,
            "mensagem" => $e->getMessage()
        ], 500);
    }
}


// ========================================
// AÇÃO INVÁLIDA
// ========================================

responder([
    "sucesso" => false,
    "mensagem" => "Ação não encontrada."
], 400);

?>
