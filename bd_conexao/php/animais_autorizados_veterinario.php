
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


// VALIDAR SESSÃO

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


// VERIFICAR SE O ANIMAL ESTÁ AUTORIZADO

function verificarAutorizacao(
    $conn,
    $id_tutor,
    $id_pet,
    $id_clinica
) {

    $sql = "

        SELECT a.status

        FROM autorizacoes a

        WHERE a.id_tutor = ?
          AND a.id_pet = ?
          AND a.id_clinica = ?

        ORDER BY
            a.data_atualizacao DESC,
            a.id_autorizacao DESC

        LIMIT 1

    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Erro ao verificar autorização.");
    }

    $stmt->bind_param(
        "iii",
        $id_tutor,
        $id_pet,
        $id_clinica
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $autorizado = false;

    if ($row = $resultado->fetch_assoc()) {

        $autorizado = ($row["status"] === "confirmado");

    }

    $stmt->close();

    return $autorizado;

}


// 1. BUSCAR TUTOR PELO CPF

if ($acao === "buscar_tutor") {

    $cpf = preg_replace(
        "/\D/",
        "",
        $_POST["cpf"] ?? ""
    );

    if (strlen($cpf) !== 11) {

        responder([
            "sucesso" => false,
            "mensagem" => "Informe um CPF válido."
        ], 400);

    }

    try {

        // Localizar tutor

        $stmt = $conn->prepare("

            SELECT
                id_tutor,
                nome_completo,
                email,
                telefone

            FROM tutor

            WHERE REPLACE(
                REPLACE(
                    REPLACE(cpf, '.', ''),
                    '-', ''
                ),
                ' ',
                ''
            ) = ?

            LIMIT 1

        ");

        if (!$stmt) {
            throw new Exception("Erro ao preparar a busca do tutor.");
        }

        $stmt->bind_param("s", $cpf);

        $stmt->execute();

        $resultado = $stmt->get_result();

        $tutor = $resultado->fetch_assoc();

        $stmt->close();

        if (!$tutor) {

            responder([
                "sucesso" => false,
                "mensagem" => "Nenhum tutor foi encontrado com esse CPF."
            ], 404);

        }

        $id_tutor = (int) $tutor["id_tutor"];


        // BUSCAR ANIMAIS AUTORIZADOS

        $sql = "

            SELECT
                p.id_pet,
                p.nome,
                p.especie

            FROM pet p

            INNER JOIN autorizacoes a
                ON a.id_pet = p.id_pet
                AND a.id_tutor = p.id_tutor
                AND a.id_clinica = ?

            WHERE p.id_tutor = ?

              AND a.id_autorizacao = (

                  SELECT a2.id_autorizacao

                  FROM autorizacoes a2

                  WHERE a2.id_pet = p.id_pet
                    AND a2.id_tutor = p.id_tutor
                    AND a2.id_clinica = ?

                  ORDER BY
                      a2.data_atualizacao DESC,
                      a2.id_autorizacao DESC

                  LIMIT 1

              )

              AND a.status = 'confirmado'

            ORDER BY p.nome ASC

        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Erro ao consultar os animais autorizados.");
        }

        $stmt->bind_param(
            "iii",
            $id_clinica,
            $id_tutor,
            $id_clinica
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $pets = [];

        while ($row = $resultado->fetch_assoc()) {

            $pets[] = [

                "id_pet" => (int) $row["id_pet"],

                "nome" => $row["nome"],

                "especie" => $row["especie"]

            ];

        }

        $stmt->close();


        responder([

            "sucesso" => true,

            "tutor" => [

                "id_tutor" => $id_tutor,

                "nome_completo" => $tutor["nome_completo"],

                "email" => $tutor["email"],

                "telefone" => $tutor["telefone"]

            ],

            "pets" => $pets

        ]);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        responder([
            "sucesso" => false,
            "mensagem" => "Erro ao buscar os dados do tutor e seus animais."
        ], 500);

    }

}


// 2. BUSCAR INFORMAÇÕES DO ANIMAL

if ($acao === "buscar_pet") {

    $id_tutor = filter_input(
        INPUT_POST,
        "id_tutor",
        FILTER_VALIDATE_INT
    );

    $id_pet = filter_input(
        INPUT_POST,
        "id_pet",
        FILTER_VALIDATE_INT
    );

    if (!$id_tutor || !$id_pet) {

        responder([
            "sucesso" => false,
            "mensagem" => "Dados do animal inválidos."
        ], 400);

    }

    try {

        if (!verificarAutorizacao(
            $conn,
            $id_tutor,
            $id_pet,
            $id_clinica
        )) {

            responder([
                "sucesso" => false,
                "mensagem" => "Este animal não possui autorização ativa para esta clínica."
            ], 403);

        }


        $stmt = $conn->prepare("

            SELECT

                id_pet,
                id_tutor,
                nome,
                especie,
                raca,
                sexo,
                data_nascimento,
                peso,
                cor,
                porte,
                microchip,
                castrado,
                observacoes,
                foto

            FROM pet

            WHERE id_pet = ?
              AND id_tutor = ?

            LIMIT 1

        ");

        if (!$stmt) {
            throw new Exception("Erro ao consultar os dados do animal.");
        }

        $stmt->bind_param(
            "ii",
            $id_pet,
            $id_tutor
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $animal = $resultado->fetch_assoc();

        $stmt->close();

        if (!$animal) {

            responder([
                "sucesso" => false,
                "mensagem" => "Animal não encontrado."
            ], 404);

        }

        responder([

            "sucesso" => true,

            "pet" => $animal

        ]);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        responder([
            "sucesso" => false,
            "mensagem" => "Erro ao carregar as informações do animal."
        ], 500);

    }

}


// 3. ATUALIZAR INFORMAÇÕES DO ANIMAL

if ($acao === "atualizar_pet") {

    $id_tutor = filter_input(
        INPUT_POST,
        "id_tutor",
        FILTER_VALIDATE_INT
    );

    $id_pet = filter_input(
        INPUT_POST,
        "id_pet",
        FILTER_VALIDATE_INT
    );

    $nome = trim($_POST["nome"] ?? "");

    $especie = trim($_POST["especie"] ?? "");

    $raca = trim($_POST["raca"] ?? "");

    $sexo = $_POST["sexo"] ?? "Não informado";

    $data_nascimento = $_POST["data_nascimento"] ?? "";

    $peso = $_POST["peso"] ?? "";

    $cor = trim($_POST["cor"] ?? "");

    $porte = trim($_POST["porte"] ?? "");

    $microchip = trim($_POST["microchip"] ?? "");

    $castrado = $_POST["castrado"] ?? "0";

    $observacoes = trim($_POST["observacoes"] ?? "");


    // VALIDAR DADOS

    if (!$id_tutor || !$id_pet) {

        responder([
            "sucesso" => false,
            "mensagem" => "Identificação do animal inválida."
        ], 400);

    }

    if ($nome === "" || $especie === "") {

        responder([
            "sucesso" => false,
            "mensagem" => "Nome e espécie são obrigatórios."
        ], 400);

    }

    if (!in_array(
        $sexo,
        ["Macho", "Fêmea", "Não informado"],
        true
    )) {

        responder([
            "sucesso" => false,
            "mensagem" => "Sexo inválido."
        ], 400);

    }

    if (!in_array($castrado, ["0", "1"], true)) {

        responder([
            "sucesso" => false,
            "mensagem" => "Valor de castração inválido."
        ], 400);

    }

    if ($peso !== "" && (
        !is_numeric($peso) ||
        (float) $peso < 0 ||
        (float) $peso > 9999.99
    )) {

        responder([
            "sucesso" => false,
            "mensagem" => "Informe um peso válido."
        ], 400);

    }

    if (
        $data_nascimento !== "" &&
        (
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $data_nascimento
            ) ||
            !checkdate(
                (int) substr($data_nascimento, 5, 2),
                (int) substr($data_nascimento, 8, 2),
                (int) substr($data_nascimento, 0, 4)
            ) ||
            $data_nascimento > date("Y-m-d")
        )
    ) {

        responder([
            "sucesso" => false,
            "mensagem" => "Data de nascimento inválida."
        ], 400);

    }


    try {

        // Verificar autorização novamente antes de salvar

        if (!verificarAutorizacao(
            $conn,
            $id_tutor,
            $id_pet,
            $id_clinica
        )) {

            responder([
                "sucesso" => false,
                "mensagem" => "Não é possível alterar este animal. A autorização não está confirmada."
            ], 403);

        }


        // Atualizar animal

        $stmt = $conn->prepare("

            UPDATE pet

            SET
                nome = ?,
                especie = ?,
                raca = ?,
                sexo = ?,
                data_nascimento = NULLIF(?, ''),
                peso = NULLIF(?, ''),
                cor = ?,
                porte = ?,
                microchip = NULLIF(?, ''),
                castrado = ?,
                observacoes = ?

            WHERE id_pet = ?
              AND id_tutor = ?

        ");

        if (!$stmt) {
            throw new Exception("Erro ao preparar a atualização.");
        }

        $stmt->bind_param(
            "sssssssssisii",
            $nome,
            $especie,
            $raca,
            $sexo,
            $data_nascimento,
            $peso,
            $cor,
            $porte,
            $microchip,
            $castrado,
            $observacoes,
            $id_pet,
            $id_tutor
        );

        $stmt->execute();

        $stmt->close();


        responder([

            "sucesso" => true,

            "mensagem" => "Informações do animal atualizadas com sucesso!"

        ]);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        if ($conn->errno === 1062) {

            responder([
                "sucesso" => false,
                "mensagem" => "Este número de microchip já está cadastrado em outro animal."
            ], 409);

        }

        responder([
            "sucesso" => false,
            "mensagem" => "Não foi possível atualizar as informações do animal."
        ], 500);

    }

}


// AÇÃO INVÁLIDA

responder([
    "sucesso" => false,
    "mensagem" => "Ação não encontrada."
], 400);

?>
