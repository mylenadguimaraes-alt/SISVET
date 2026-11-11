
<?php

session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

// ========================================
// SESSÃO
// ========================================

$id_recepcionista = $_SESSION["id_recepcionista"]
    ?? $_SESSION["id_rec"]
    ?? $_SESSION["id"]
    ?? null;

$id_clinica = $_SESSION["id_clinica_ativa"] ?? null;


// ========================================
// FUNÇÃO DE RESPOSTA
// ========================================

function responder($sucesso, $mensagem, $codigo = 200, $dados = [])
{
    http_response_code($codigo);

    echo json_encode(
        array_merge([
            "sucesso" => $sucesso,
            "mensagem" => $mensagem
        ], $dados),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ========================================
// VALIDAR SESSÃO
// ========================================

if (empty($id_recepcionista) || empty($id_clinica)) {
    responder(
        false,
        "Sua sessão expirou. Entre novamente na clínica.",
        401
    );
}

$id_recepcionista = (int) $id_recepcionista;
$id_clinica = (int) $id_clinica;

$acao = $_GET["acao"] ?? "";


// ========================================
// VERIFICAR ACESSO À CLÍNICA
// ========================================

function verificarAcesso($conn, $id_recepcionista, $id_clinica)
{
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
// VALIDAR ACESSO
// ========================================

try {

    if (!verificarAcesso(
        $conn,
        $id_recepcionista,
        $id_clinica
    )) {

        unset($_SESSION["id_clinica_ativa"]);

        responder(
            false,
            "Seu contrato não está ativo ou a clínica não está autorizada.",
            403
        );
    }


    // ========================================
    // BUSCAR TUTOR PELO CPF
    // ========================================

    if ($acao === "buscar_tutor") {

        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            responder(false, "Método não permitido.", 405);
        }

        $cpf = preg_replace(
            "/\D/",
            "",
            $_POST["cpf"] ?? ""
        );

        if (strlen($cpf) !== 11) {
            responder(false, "Informe um CPF válido.", 400);
        }

        $sql = "
            SELECT
                id_tutor,
                nome_completo,
                email,
                telefone

            FROM tutor

            WHERE REPLACE(
                REPLACE(
                    REPLACE(cpf, '.', ''),
                    '-',
                    ''
                ),
                ' ',
                ''
            ) = ?

            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Erro ao preparar a busca do tutor.");
        }

        $stmt->bind_param("s", $cpf);

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {

            $stmt->close();

            responder(
                false,
                "Nenhum tutor encontrado com esse CPF.",
                404
            );
        }

        $tutor = $resultado->fetch_assoc();

        $stmt->close();


        // ========================================
        // BUSCAR PETS DO TUTOR
        // ========================================

        $stmt = $conn->prepare("
            SELECT
                id_pet,
                nome,
                especie,
                raca

            FROM pet

            WHERE id_tutor = ?

            ORDER BY nome ASC
        ");

        if (!$stmt) {
            throw new Exception("Erro ao buscar os animais.");
        }

        $stmt->bind_param("i", $tutor["id_tutor"]);

        $stmt->execute();

        $resultadoPets = $stmt->get_result();

        $pets = [];

        while ($animal = $resultadoPets->fetch_assoc()) {

            $pets[] = [
                "id_pet" => (int) $animal["id_pet"],
                "nome" => $animal["nome"],
                "especie" => $animal["especie"],
                "raca" => $animal["raca"]
            ];
        }

        $stmt->close();

        responder(
            true,
            "Tutor encontrado.",
            200,
            [
                "tutor" => [
                    "id_tutor" => (int) $tutor["id_tutor"],
                    "nome_completo" => $tutor["nome_completo"],
                    "email" => $tutor["email"],
                    "telefone" => $tutor["telefone"]
                ],
                "pets" => $pets
            ]
        );
    }


    // ========================================
    // VALIDAR SE O PET PERTENCE AO TUTOR
    // ========================================

    function validarTutorPet($conn, $id_tutor, $id_pet)
    {
        $stmt = $conn->prepare("
            SELECT id_pet

            FROM pet

            WHERE id_pet = ?
              AND id_tutor = ?

            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Erro ao verificar o animal.");
        }

        $stmt->bind_param(
            "ii",
            $id_pet,
            $id_tutor
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $existe = $resultado->num_rows > 0;

        $stmt->close();

        return $existe;
    }


    // ========================================
    // CONSULTAR AUTORIZAÇÃO EXISTENTE
    // ========================================

    if ($acao === "consultar_autorizacao") {

        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            responder(false, "Método não permitido.", 405);
        }

        $id_tutor = filter_var(
            $_POST["id_tutor"] ?? null,
            FILTER_VALIDATE_INT
        );

        $id_pet = filter_var(
            $_POST["id_pet"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id_tutor || !$id_pet) {
            responder(false, "Dados inválidos.", 400);
        }

        if (!validarTutorPet($conn, $id_tutor, $id_pet)) {
            responder(
                false,
                "O animal não pertence a esse tutor.",
                403
            );
        }

        $stmt = $conn->prepare("
            SELECT
                id_autorizacao,
                status

            FROM autorizacoes

            WHERE id_tutor = ?
              AND id_pet = ?
              AND id_clinica = ?

            ORDER BY id_autorizacao DESC

            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Erro ao consultar autorização.");
        }

        $stmt->bind_param(
            "iii",
            $id_tutor,
            $id_pet,
            $id_clinica
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {

            $stmt->close();

            responder(
                true,
                "Nenhuma autorização encontrada.",
                200,
                [
                    "existe" => false,
                    "status" => null
                ]
            );
        }

        $autorizacao = $resultado->fetch_assoc();

        $stmt->close();

        responder(
            true,
            "Autorização localizada.",
            200,
            [
                "existe" => true,
                "id_autorizacao" => (int) $autorizacao["id_autorizacao"],
                "status" => $autorizacao["status"]
            ]
        );
    }


    // ========================================
    // SOLICITAR AUTORIZAÇÃO
    // ========================================

    if ($acao === "solicitar") {

        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            responder(false, "Método não permitido.", 405);
        }

        $id_tutor = filter_var(
            $_POST["id_tutor"] ?? null,
            FILTER_VALIDATE_INT
        );

        $id_pet = filter_var(
            $_POST["id_pet"] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id_tutor || !$id_pet) {
            responder(false, "Dados inválidos.", 400);
        }

        if (!validarTutorPet($conn, $id_tutor, $id_pet)) {
            responder(
                false,
                "O animal não pertence a esse tutor.",
                403
            );
        }


        // ========================================
        // INICIAR TRANSAÇÃO
        // ========================================

        $conn->begin_transaction();

        try {

            // Bloquear a clínica durante a operação.

            $stmt = $conn->prepare("
                SELECT id_clinica

                FROM clinica

                WHERE id_clinica = ?

                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception("Erro ao verificar a clínica.");
            }

            $stmt->bind_param("i", $id_clinica);

            $stmt->execute();

            $resultadoClinica = $stmt->get_result();

            if ($resultadoClinica->num_rows === 0) {

                $stmt->close();

                throw new Exception("Clínica não encontrada.");
            }

            $stmt->close();


            // Verificar novamente o contrato.

            if (!verificarAcesso(
                $conn,
                $id_recepcionista,
                $id_clinica
            )) {
                throw new Exception("Acesso à clínica não autorizado.");
            }


            // ========================================
            // BUSCAR PEDIDO EXISTENTE
            // ========================================

            $stmt = $conn->prepare("
                SELECT
                    id_autorizacao,
                    status

                FROM autorizacoes

                WHERE id_tutor = ?
                  AND id_pet = ?
                  AND id_clinica = ?

                ORDER BY id_autorizacao DESC

                LIMIT 1

                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception("Erro ao verificar pedido existente.");
            }

            $stmt->bind_param(
                "iii",
                $id_tutor,
                $id_pet,
                $id_clinica
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $pedido = $resultado->fetch_assoc();

            $stmt->close();


            // ========================================
            // JÁ EXISTE UM PEDIDO
            // ========================================

            if ($pedido) {

                $id_autorizacao = (int) $pedido["id_autorizacao"];

                $status = $pedido["status"];


                // PEDIDO EM ESPERA

                if ($status === "espera") {

                    throw new Exception(
                        "O pedido de autorização já foi enviado e está aguardando análise da clínica."
                    );
                }


                // PEDIDO CONFIRMADO

                if ($status === "confirmado") {

                    throw new Exception(
                        "A autorização deste animal já está confirmada."
                    );
                }


                // PEDIDO REVOGADO

                if ($status === "revogado") {

                    throw new Exception(
                        "Esta autorização foi revogada. Não é possível solicitar novamente."
                    );
                }


                // PEDIDO NEGADO

                if ($status === "negado") {

                    $stmt = $conn->prepare("
                        UPDATE autorizacoes

                        SET
                            status = 'espera',
                            confirmado = 0,
                            negado = 0,
                            revogado = 0

                        WHERE id_autorizacao = ?
                    ");

                    if (!$stmt) {
                        throw new Exception("Erro ao atualizar pedido negado.");
                    }

                    $stmt->bind_param(
                        "i",
                        $id_autorizacao
                    );

                    if (!$stmt->execute()) {

                        $stmt->close();

                        throw new Exception("Não foi possível reenviar o pedido.");
                    }

                    $stmt->close();

                    $conn->commit();

                    responder(
                        true,
                        "O pedido anterior estava negado. Uma nova solicitação foi enviada e está aguardando análise da clínica.",
                        200,
                        [
                            "id_autorizacao" => $id_autorizacao,
                            "status" => "espera"
                        ]
                    );
                }


                throw new Exception("Status de autorização desconhecido.");
            }


            // ========================================
            // CRIAR NOVA AUTORIZAÇÃO
            // ========================================

            $stmt = $conn->prepare("
                INSERT INTO autorizacoes (
                    id_tutor,
                    id_pet,
                    id_clinica,
                    confirmado,
                    negado,
                    revogado,
                    status
                )

                VALUES (?, ?, ?, 0, 0, 0, 'espera')
            ");

            if (!$stmt) {
                throw new Exception("Erro ao preparar solicitação.");
            }

            $stmt->bind_param(
                "iii",
                $id_tutor,
                $id_pet,
                $id_clinica
            );

            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception("Não foi possível cadastrar a solicitação.");
            }

            $id_autorizacao = $stmt->insert_id;

            $stmt->close();

            $conn->commit();

            responder(
                true,
                "Solicitação de autorização enviada com sucesso! Aguarde a análise da clínica.",
                200,
                [
                    "id_autorizacao" => $id_autorizacao,
                    "status" => "espera"
                ]
            );

        } catch (Throwable $e) {

            $conn->rollback();

            throw $e;
        }
    }


    // ========================================
    // AÇÃO NÃO ENCONTRADA
    // ========================================

    responder(
        false,
        "Ação não encontrada.",
        400
    );

} catch (Throwable $e) {

    $codigo = 500;

    if (
        strpos($e->getMessage(), "já foi enviado") !== false ||
        strpos($e->getMessage(), "já está confirmada") !== false ||
        strpos($e->getMessage(), "foi revogada") !== false
    ) {
        $codigo = 409;
    }

    responder(
        false,
        $e->getMessage(),
        $codigo
    );
}

?>
