<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["id_dono"])) {
    exit("Acesso negado. Faça login novamente.");
}

$id_dono = (int) $_SESSION["id_dono"];

function h($valor) {
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

function erroRenovacao($mensagem, $id_contrato) {
    $id = (int) $id_contrato;

    echo "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Renovar contrato do veterinário</title>
    </head>
    <body style='font-family:Arial,sans-serif;padding:25px;background:#F5F5F5;color:#202B3C'>
        <p>" . htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8") . "</p>
        <a href='renovar_contrato_veterinario.php?id_contrato={$id}'>
            Voltar ao formulário
        </a>
        &nbsp;|&nbsp;
        <a href='detalhe_contrato_veterinario.php?status=demitido'>
            Voltar à lista
        </a>
    </body>
    </html>";

    exit;
}

$id_contrato = filter_input(
    INPUT_POST,
    "id_contrato",
    FILTER_VALIDATE_INT
);

if (!$id_contrato) {
    $id_contrato = filter_input(
        INPUT_GET,
        "id_contrato",
        FILTER_VALIDATE_INT
    );
}

if (!$id_contrato || $id_contrato <= 0) {
    exit("Contrato inválido.");
}

// Busca o contrato demitido e verifica se pertence ao dono conectado
$sql = "
    SELECT
        cv.id_contrato,
        cv.id_veterinario,
        cv.id_clinica,
        cv.cargo,
        cv.tipo_contrato,
        cv.carga_horaria_semanal,
        cv.observacoes,
        v.nome AS nome_veterinario,
        v.numero_crmv,
        v.estado_crmv,
        c.nome_clinica
    FROM contrato_veterinario cv
    INNER JOIN veterinarios v
        ON v.id = cv.id_veterinario
    INNER JOIN clinica c
        ON c.id_clinica = cv.id_clinica
    WHERE cv.id_contrato = ?
      AND c.id_dono = ?
      AND cv.status = 'demitido'
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro ao buscar contrato do veterinário: " . $conn->error);
    exit("Não foi possível carregar o contrato.");
}

$stmt->bind_param("ii", $id_contrato, $id_dono);
$stmt->execute();

$contrato = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$contrato) {
    exit("Contrato não encontrado ou você não tem autorização para renová-lo.");
}

// Processa o formulário
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $data_inicio = trim($_POST["data_inicio"] ?? "");
    $data_fim_digitada = trim($_POST["data_fim"] ?? "");

    // A data de início é obrigatória
    $inicio = DateTime::createFromFormat("!Y-m-d", $data_inicio);

    if (
        !$inicio ||
        $inicio->format("Y-m-d") !== $data_inicio
    ) {
        erroRenovacao(
            "Informe uma data de início válida.",
            $id_contrato
        );
    }

    // A data de término é opcional
    $data_fim = null;

    if ($data_fim_digitada !== "") {

        $fim = DateTime::createFromFormat(
            "!Y-m-d",
            $data_fim_digitada
        );

        if (
            !$fim ||
            $fim->format("Y-m-d") !== $data_fim_digitada
        ) {
            erroRenovacao(
                "Informe uma data de término válida ou deixe o campo vazio.",
                $id_contrato
            );
        }

        if ($data_fim_digitada < $data_inicio) {
            erroRenovacao(
                "A data de término não pode ser anterior à data de início.",
                $id_contrato
            );
        }

        $data_fim = $data_fim_digitada;
    }

    // Impede reativar se já existir outro contrato ativo
    // para o mesmo veterinário na mesma clínica
    $sql = "
        SELECT id_contrato
        FROM contrato_veterinario
        WHERE id_veterinario = ?
          AND id_clinica = ?
          AND status = 'contratado'
          AND id_contrato <> ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Erro ao verificar contrato ativo de veterinário: " . $conn->error);
        erroRenovacao(
            "Não foi possível verificar os contratos ativos.",
            $id_contrato
        );
    }

    $stmt->bind_param(
        "iii",
        $contrato["id_veterinario"],
        $contrato["id_clinica"],
        $id_contrato
    );

    $stmt->execute();
    $contrato_ativo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($contrato_ativo) {
        erroRenovacao(
            "Este veterinário já possui outro contrato ativo nesta clínica.",
            $id_contrato
        );
    }

    // Atualiza o contrato existente. Não cria outro registro.
    $sql = "
        UPDATE contrato_veterinario cv
        INNER JOIN clinica c
            ON c.id_clinica = cv.id_clinica
        SET
            cv.data_inicio = ?,
            cv.data_fim = ?,
            cv.status = 'contratado'
        WHERE cv.id_contrato = ?
          AND cv.status = 'demitido'
          AND c.id_dono = ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Erro ao preparar renovação do veterinário: " . $conn->error);
        erroRenovacao(
            "Não foi possível preparar a renovação.",
            $id_contrato
        );
    }

    $stmt->bind_param(
        "ssii",
        $data_inicio,
        $data_fim,
        $id_contrato,
        $id_dono
    );

    if (!$stmt->execute()) {
        error_log("Erro ao renovar contrato do veterinário: " . $stmt->error);
        $stmt->close();

        erroRenovacao(
            "Não foi possível atualizar o contrato. Verifique a estrutura da tabela.",
            $id_contrato
        );
    }

    $stmt->close();
    $conn->close();

    header(
        "Location: detalhe_contrato_veterinario.php?status=contratado&resultado=renovado"
    );
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Renovar contrato do veterinário</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #F5F5F5;
            color: #202B3C;
        }

        .container {
            max-width: 650px;
            margin: 0 auto;
            padding: 25px;
            background: #FFFFFF;
            border: 1px solid #D9E0EA;
            border-radius: 10px;
        }

        h1 {
            margin-top: 0;
            color: #0B3478;
            font-size: 25px;
        }

        .informacao {
            padding: 14px;
            margin-bottom: 22px;
            background: #EAF2FF;
            border-radius: 6px;
            line-height: 1.7;
        }

        .campo {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #D9E0EA;
            border-radius: 6px;
            font-family: Arial, sans-serif;
            font-size: 15px;
        }

        .ajuda {
            margin-top: 6px;
            color: #667085;
            font-size: 13px;
        }

        .botoes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
        }

        button,
        .voltar {
            display: inline-block;
            padding: 12px 16px;
            border: none;
            border-radius: 6px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #1351B4;
            color: #FFFFFF;
        }

        button:hover {
            background: #0B3478;
        }

        .voltar {
            background: #EAF2FF;
            color: #1351B4;
        }

        @media (max-width: 500px) {
            body {
                padding: 12px;
            }

            .container {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<main class="container">

    <h1>Renovar contrato do veterinário</h1>

    <div class="informacao">
        <strong>Veterinário:</strong>
        <?= h($contrato["nome_veterinario"]) ?>
        <br>

        <strong>CRMV:</strong>
        <?= h($contrato["numero_crmv"] . "/" . $contrato["estado_crmv"]) ?>
        <br>

        <strong>Clínica:</strong>
        <?= h($contrato["nome_clinica"]) ?>
        <br>

        <strong>Cargo:</strong>
        <?= h($contrato["cargo"]) ?>
        <br>

        <strong>Tipo de contrato:</strong>
        <?= h($contrato["tipo_contrato"]) ?>
    </div>

    <p>
        Informe as novas datas. Ao confirmar, o contrato atual será reativado.
        A data de término é opcional.
    </p>

    <form
        method="POST"
        action="renovar_contrato_veterinario.php"
    >
        <input
            type="hidden"
            name="id_contrato"
            value="<?= (int) $contrato["id_contrato"] ?>"
        >

        <div class="campo">
            <label for="data_inicio">Nova data de início *</label>

            <input
                type="date"
                id="data_inicio"
                name="data_inicio"
                required
            >
        </div>

        <div class="campo">
            <label for="data_fim">Nova data de término (opcional)</label>

            <input
                type="date"
                id="data_fim"
                name="data_fim"
            >

            <p class="ajuda">
                Se não houver uma data de término definida, deixe o campo vazio.
            </p>
        </div>

        <div class="botoes">
            <button type="submit">
                Confirmar renovação
            </button>

            <a
                class="voltar"
                href="detalhe_contrato_veterinario.php?status=demitido"
            >
                Cancelar
            </a>
        </div>
    </form>

</main>

<script>
    const inicio = document.getElementById("data_inicio");
    const fim = document.getElementById("data_fim");

    inicio.addEventListener("change", function () {
        fim.min = inicio.value;

        if (fim.value && fim.value < inicio.value) {
            fim.value = "";
        }
    });
</script>

</body>
</html>