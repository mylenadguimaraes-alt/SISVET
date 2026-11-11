
<?php

session_start();

date_default_timezone_set("America/Sao_Paulo");

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";


// CONFIGURAÇÕES

$HORARIO_ABERTURA = "08:00";
$HORARIO_FECHAMENTO = "22:00";
$INTERVALO_MINUTOS = 60;


// SESSÃO

$id_recepcionista = $_SESSION["id_recepcionista"]
    ?? $_SESSION["id_rec"]
    ?? $_SESSION["id"]
    ?? null;

$id_clinica = $_SESSION["id_clinica_ativa"] ?? null;


// RESPOSTA JSON

function responder($sucesso, $mensagem, $codigo = 200, $dados = []) {

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


// VALIDAR SESSÃO

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


// VERIFICAR ACESSO À CLÍNICA

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
        throw new Exception("Erro ao verificar o acesso à clínica.");
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


// VALIDAR ACESSO

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


    // BUSCAR TUTOR

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

            responder(false, "Informe um CPF com 11 números.", 400);
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

            responder(false, "Nenhum tutor encontrado com esse CPF.", 404);
        }

        $tutor = $resultado->fetch_assoc();

        $stmt->close();


        // BUSCAR PETS DO TUTOR

        $sqlPets = "
            SELECT
                id_pet,
                nome,
                especie,
                raca

            FROM pet

            WHERE id_tutor = ?

            ORDER BY nome ASC
        ";

        $stmt = $conn->prepare($sqlPets);

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


    // CADASTRAR ATENDIMENTO

    if ($acao === "cadastrar") {

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

        $data = trim($_POST["data"] ?? "");
        $hora = trim($_POST["hora"] ?? "");
        $motivo = trim($_POST["motivo"] ?? "");
        $observacoes = trim($_POST["observacoes"] ?? "");


        // VALIDAR CAMPOS

        if (
            !$id_tutor ||
            !$id_pet ||
            !$data ||
            !$hora ||
            !$motivo
        ) {

            responder(false, "Preencha todos os campos obrigatórios.", 400);
        }

        if (mb_strlen($motivo) > 1000) {

            responder(false, "O motivo deve ter no máximo 1000 caracteres.", 400);
        }


        // VALIDAR DATA

        $dataObj = DateTime::createFromFormat("!Y-m-d", $data);

        if (
            !$dataObj ||
            $dataObj->format("Y-m-d") !== $data
        ) {

            responder(false, "Data inválida.", 400);
        }

        $hoje = date("Y-m-d");

        if ($data < $hoje) {

            responder(false, "Não é permitido marcar atendimentos em datas passadas.", 400);
        }


        // VALIDAR HORA

        $horaObj = DateTime::createFromFormat("!H:i", $hora);

        if (
            !$horaObj ||
            $horaObj->format("H:i") !== $hora
        ) {

            responder(false, "Horário inválido.", 400);
        }


        // HORÁRIO DE FUNCIONAMENTO

        if (
            $hora < $HORARIO_ABERTURA ||
            $hora >= $HORARIO_FECHAMENTO
        ) {

            responder(
                false,
                "A clínica funciona das 08:00 às 22:00. Escolha um horário dentro desse período.",
                400
            );
        }


        $data_hora = $data . " " . $hora . ":00";


        // NÃO PERMITIR HORÁRIO PASSADO

        $agendamento = DateTime::createFromFormat(
            "Y-m-d H:i:s",
            $data_hora
        );

        $agora = new DateTime();

        if ($agendamento <= $agora) {

            responder(
                false,
                "Não é possível cadastrar um atendimento em um horário que já passou.",
                400
            );
        }


        // INICIAR TRANSAÇÃO

        $conn->begin_transaction();

        try {

            // Bloqueia o registro da clínica.
            // Os outros cadastros desta clínica aguardam
            // até a conclusão desta transação.

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


            // Verifica novamente o contrato
            if (!verificarAcesso(
                $conn,
                $id_recepcionista,
                $id_clinica
            )) {

                throw new Exception("O acesso à clínica não está mais autorizado.");
            }


            // VERIFICAR TUTOR
            $stmt = $conn->prepare("
                SELECT id_tutor

                FROM tutor

                WHERE id_tutor = ?

                LIMIT 1
            ");

            $stmt->bind_param("i", $id_tutor);

            $stmt->execute();

            $resultadoTutor = $stmt->get_result();

            if ($resultadoTutor->num_rows === 0) {

                $stmt->close();

                throw new Exception("Tutor não encontrado.");
            }

            $stmt->close();


            // VERIFICAR PET

            $stmt = $conn->prepare("
                SELECT id_pet

                FROM pet

                WHERE id_pet = ?
                  AND id_tutor = ?

                LIMIT 1
            ");

            $stmt->bind_param("ii", $id_pet, $id_tutor);

            $stmt->execute();

            $resultadoPet = $stmt->get_result();

            if ($resultadoPet->num_rows === 0) {

                $stmt->close();

                throw new Exception("O animal selecionado não pertence a esse tutor.");
            }

            $stmt->close();


            // VERIFICAR INTERVALO DE 1 HORA

            $sqlConflito = "

                SELECT
                    id_consulta,
                    data_hora

                FROM consultas

                WHERE id_clinica = ?

                  AND status <> 'cancelada'

                  AND data_hora > DATE_SUB(
                      ?,
                      INTERVAL 60 MINUTE
                  )

                  AND data_hora < DATE_ADD(
                      ?,
                      INTERVAL 60 MINUTE
                  )

                LIMIT 1
            ";

            $stmt = $conn->prepare($sqlConflito);

            if (!$stmt) {
                throw new Exception("Erro ao verificar a disponibilidade.");
            }

            $stmt->bind_param(
                "iss",
                $id_clinica,
                $data_hora,
                $data_hora
            );

            $stmt->execute();

            $resultadoConflito = $stmt->get_result();

            if ($resultadoConflito->num_rows > 0) {

                $conflito = $resultadoConflito->fetch_assoc();

                $stmt->close();

                $horarioExistente = date(
                    "d/m/Y H:i",
                    strtotime($conflito["data_hora"])
                );

                throw new Exception(
                    "Horário indisponível! Já existe um atendimento marcado para " .
                    $horarioExistente .
                    ". É necessário manter pelo menos uma hora de intervalo entre os atendimentos."
                );
            }

            $stmt->close();


            // CADASTRAR

            $sqlInsert = "

                INSERT INTO consultas (

                    id_tutor,
                    id_clinica,
                    id_pet,
                    data_hora,
                    motivo,
                    status,
                    observacoes

                )

                VALUES (?, ?, ?, ?, ?, 'pendente', ?)
            ";

            $stmt = $conn->prepare($sqlInsert);

            if (!$stmt) {
                throw new Exception("Erro ao preparar o cadastro do atendimento.");
            }

            $stmt->bind_param(
                "iiisss",
                $id_tutor,
                $id_clinica,
                $id_pet,
                $data_hora,
                $motivo,
                $observacoes
            );

            if (!$stmt->execute()) {

                throw new Exception("Erro ao cadastrar o atendimento.");
            }

            $id_consulta = $stmt->insert_id;

            $stmt->close();


            // FINALIZAR TRANSAÇÃO

            $conn->commit();

        } catch (Exception $e) {

            $conn->rollback();

            throw $e;
        }


        responder(
            true,
            "Atendimento cadastrado com sucesso!",
            200,
            [
                "id_consulta" => $id_consulta
            ]
        );
    }


    // AÇÃO NÃO ENCONTRADA

    responder(false, "Ação não encontrada.", 400);


} catch (Throwable $e) {

    responder(
        false,
        "Erro ao processar a solicitação: " . $e->getMessage(),
        500
    );
}

?>
