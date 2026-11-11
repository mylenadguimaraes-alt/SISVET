
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
        <title>Renovar contrato</title>
    </head>
    <body style='font-family:Arial,sans-serif;padding:25px;background:#F5F5F5;color:#202B3C'>
        <p>" . htmlspecialchars($mensagem, ENT_QUOTES, "UTF-8") . "</p>
        <a href='renovar_contrato_recepcionista.php?id_contrato={$id}'>
            Voltar ao formulário
        </a>
        &nbsp;|&nbsp;
        <a href='dados_recepcionista.php?status=demitida'>
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
        cr.id_contrato,
        cr.id_recepcionista,
        cr.id_clinica,
        cr.cargo,
        cr.tipo_contrato,
        cr.carga_horaria_semanal,
        cr.observacoes,
        r.nome,
        c.nome_clinica
    FROM contrato_recepcionista cr
    INNER JOIN recepcionista r
        ON r.id_recepcionista = cr.id_recepcionista
    INNER JOIN clinica c
        ON c.id_clinica = cr.id_clinica
    WHERE cr.id_contrato = ?
      AND c.id_dono = ?
      AND cr.status = 'demitida'
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro ao buscar contrato: " . $conn->error);
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
    // para a mesma recepcionista na mesma clínica
    $sql = "
        SELECT id_contrato
        FROM contrato_recepcionista
        WHERE id_recepcionista = ?
          AND id_clinica = ?
          AND status = 'contratada'
          AND id_contrato <> ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Erro ao verificar contrato ativo: " . $conn->error);
        erroRenovacao(
            "Não foi possível verificar os contratos ativos.",
            $id_contrato
        );
    }

    $stmt->bind_param(
        "iii",
        $contrato["id_recepcionista"],
        $contrato["id_clinica"],
        $id_contrato
    );

    $stmt->execute();
    $contrato_ativo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($contrato_ativo) {
        erroRenovacao(
            "Esta recepcionista já possui outro contrato ativo nesta clínica.",
            $id_contrato
        );
    }

    // Atualiza o contrato existente. Não cria outro registro.
    $sql = "
        UPDATE contrato_recepcionista cr
        INNER JOIN clinica c
            ON c.id_clinica = cr.id_clinica
        SET
            cr.data_inicio = ?,
            cr.data_fim = ?,
            cr.status = 'contratada'
        WHERE cr.id_contrato = ?
          AND cr.status = 'demitida'
          AND c.id_dono = ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Erro ao preparar renovação: " . $conn->error);
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
        error_log("Erro ao renovar contrato: " . $stmt->error);
        $stmt->close();

        erroRenovacao(
            "Não foi possível atualizar o contrato. Verifique a estrutura da tabela.",
            $id_contrato
        );
    }

    $stmt->close();
    $conn->close();

    header(
        "Location: dados_recepcionista.php?status=contratada&resultado=renovado"
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

    <title>Renovar contrato</title>

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

    <h1>Renovar contrato</h1>

    <div class="informacao">
        <strong>Recepcionista:</strong>
        <?= h($contrato["nome"]) ?>
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
        action="renovar_contrato_recepcionista.php"
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
                href="dados_recepcionista.php?status=demitida"
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
