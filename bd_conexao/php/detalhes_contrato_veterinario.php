<?php
session_start();

require_once __DIR__ . "/conexao.php";

if (!isset($_SESSION["id_dono"])) {
    header("Location: ../../login.html");
    exit;
}

$id_dono = (int) $_SESSION["id_dono"];

function escapar($valor)
{
    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function formatarData($data)
{
    if (empty($data) || $data === "0000-00-00") {
        return "Não informado";
    }

    $dataFormatada = DateTime::createFromFormat("!Y-m-d", $data);

    return $dataFormatada
        ? $dataFormatada->format("d/m/Y")
        : escapar($data);
}

function dataValida($data)
{
    if ($data === "") {
        return true;
    }

    $dataObj = DateTime::createFromFormat("!Y-m-d", $data);

    return $dataObj && $dataObj->format("Y-m-d") === $data;
}

$id_contrato = filter_input(
    INPUT_GET,
    "id_contrato",
    FILTER_VALIDATE_INT
);

if (!$id_contrato || $id_contrato <= 0) {
    http_response_code(400);
    exit("Identificador do contrato inválido.");
}

if (empty($_SESSION["csrf_contrato_vet"])) {
    $_SESSION["csrf_contrato_vet"] = bin2hex(random_bytes(32));
}

$mensagem = "";
$tipoMensagem = "";

/*
|--------------------------------------------------------------------------
| SALVAR ALTERAÇÕES DO CONTRATO DO VETERINÁRIO
|--------------------------------------------------------------------------
| Somente a tabela contrato_veterinario é alterada. 
| O status NÃO é alterado por este formulário.
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_contrato_vet"], $token)
    ) {
        http_response_code(403);
        exit("Solicitação inválida. Atualize a página e tente novamente.");
    }

    $cargo = trim($_POST["cargo"] ?? "");
    $tipo_contrato = trim($_POST["tipo_contrato"] ?? "");
    $data_inicio = trim($_POST["data_inicio"] ?? "");
    $data_fim = trim($_POST["data_fim"] ?? "");
    $carga_horaria = trim($_POST["carga_horaria_semanal"] ?? "");
    $observacoes = trim($_POST["observacoes"] ?? "");

    $erros = [];

    if ($cargo === "") {
        $erros[] = "Informe o cargo.";
    }

    if ($tipo_contrato === "") {
        $erros[] = "Informe o tipo de contrato.";
    }

    if (!dataValida($data_inicio)) {
        $erros[] = "A data de início é inválida.";
    }

    if (!dataValida($data_fim)) {
        $erros[] = "A data de término é inválida.";
    }

    if (
        $data_inicio !== "" &&
        $data_fim !== "" &&
        $data_fim < $data_inicio
    ) {
        $erros[] = "A data de término não pode ser anterior à data de início.";
    }

    if (
        $carga_horaria !== "" &&
        (
            !is_numeric($carga_horaria) ||
            (float) $carga_horaria < 0 ||
            (float) $carga_horaria > 168
        )
    ) {
        $erros[] = "A carga horária deve ser um número entre 0 e 168.";
    }

    if (!$erros) {
        $sqlUpdate = "
            UPDATE contrato_veterinario cv
            INNER JOIN clinica c
                ON c.id_clinica = cv.id_clinica
            SET
                cv.cargo = ?,
                cv.tipo_contrato = ?,
                cv.data_inicio = ?,
                cv.data_fim = ?,
                cv.carga_horaria_semanal = ?,
                cv.observacoes = ?
            WHERE cv.id_contrato = ?
              AND c.id_dono = ?
        ";

        $stmtUpdate = $conn->prepare($sqlUpdate);

        if (!$stmtUpdate) {
            error_log("Erro ao preparar atualização do contrato de veterinário: " . $conn->error);
            $mensagem = "Não foi possível preparar a atualização.";
            $tipoMensagem = "erro";
        } else {
            $dataInicioBanco = $data_inicio !== "" ? $data_inicio : null;
            $dataFimBanco = $data_fim !== "" ? $data_fim : null;
            $cargaBanco = $carga_horaria !== "" ? $carga_horaria : null;

            $stmtUpdate->bind_param(
                "ssssssii",
                $cargo,
                $tipo_contrato,
                $dataInicioBanco,
                $dataFimBanco,
                $cargaBanco,
                $observacoes,
                $id_contrato,
                $id_dono
            );

            if ($stmtUpdate->execute()) {
                if ($stmtUpdate->affected_rows >= 0) {
                    $stmtUpdate->close();

                    header(
                        "Location: detalhes_contrato_veterinario.php?id_contrato="
                        . $id_contrato
                        . "&salvo=1"
                    );
                    exit;
                }
            } else {
                error_log("Erro ao atualizar contrato do veterinário: " . $stmtUpdate->error);
                $mensagem = "Não foi possível salvar as alterações.";
                $tipoMensagem = "erro";
            }

            $stmtUpdate->close();
        }
    } else {
        $mensagem = implode(" ", $erros);
        $tipoMensagem = "erro";
    }
}

if (isset($_GET["salvo"]) && $_GET["salvo"] === "1") {
    $mensagem = "Alterações do contrato salvas com sucesso!";
    $tipoMensagem = "sucesso";
}

/*
|--------------------------------------------------------------------------
| CONSULTAR CONTRATO E DADOS DO VETERINÁRIO
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        cv.id_contrato,
        cv.id_veterinario,
        cv.id_clinica,
        cv.cargo,
        cv.tipo_contrato,
        cv.data_inicio,
        cv.data_fim,
        cv.carga_horaria_semanal,
        cv.observacoes,
        cv.status,

        v.nome AS nome_completo,
        v.cpf,
        v.email,
        v.telefone,
        v.numero_crmv,
        v.estado_crmv,
        v.caminho_documento_verificacao AS caminho_documento,

        c.nome_clinica,
        c.razao_social

    FROM contrato_veterinario cv

    INNER JOIN veterinarios v
        ON v.id = cv.id_veterinario

    INNER JOIN clinica c
        ON c.id_clinica = cv.id_clinica

    WHERE cv.id_contrato = ?
      AND c.id_dono = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro na consulta do contrato do veterinário: " . $conn->error);
    http_response_code(500);
    exit("Não foi possível consultar os dados do contrato.");
}

$stmt->bind_param("ii", $id_contrato, $id_dono);
$stmt->execute();

$resultado = $stmt->get_result();
$dados = $resultado->fetch_assoc();

$stmt->close();

if (!$dados) {
    http_response_code(404);
    exit("Contrato não encontrado ou você não tem autorização para visualizá-lo.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $tipoMensagem === "erro") {
    $dados["cargo"] = $_POST["cargo"] ?? $dados["cargo"];
    $dados["tipo_contrato"] = $_POST["tipo_contrato"] ?? $dados["tipo_contrato"];
    $dados["data_inicio"] = $_POST["data_inicio"] ?? $dados["data_inicio"];
    $dados["data_fim"] = $_POST["data_fim"] ?? $dados["data_fim"];
    $dados["carga_horaria_semanal"] =
        $_POST["carga_horaria_semanal"] ?? $dados["carga_horaria_semanal"];
    $dados["observacoes"] = $_POST["observacoes"] ?? $dados["observacoes"];
}

$status = strtolower(trim($dados["status"] ?? ""));

if ($status === "contratado") {
    $statusTexto = "Contratado";
    $classeStatus = "contratada";
} elseif ($status === "demitido") {
    $statusTexto = "Demitido";
    $classeStatus = "demitida";
} else {
    $statusTexto = $status !== "" ? ucfirst($status) : "Não informado";
    $classeStatus = "outro";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detalhes do contrato de veterinário - SISVET</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #F5F5F5;
            color: #202B3C;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        h1 {
            margin-top: 0;
            color: #0B3478;
            font-size: 27px;
        }

        .subtitulo {
            margin-bottom: 25px;
            color: #667085;
        }

        .card {
            margin-bottom: 20px;
            padding: 22px;
            background: #FFFFFF;
            border: 1px solid #D9E0EA;
            border-radius: 12px;
        }

        h2 {
            margin-top: 0;
            padding-bottom: 12px;
            color: #1351B4;
            border-bottom: 1px solid #D9E0EA;
            font-size: 19px;
        }

        .informacoes {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .campo {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .campo strong,
        label {
            display: block;
            margin-bottom: 7px;
            color: #667085;
            font-size: 13px;
            font-weight: bold;
        }

        .campo span {
            font-size: 15px;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-width: 0;
            padding: 11px;
            border: 1px solid #D9E0EA;
            border-radius: 7px;
            background: #FFFFFF;
            color: #202B3C;
            font: inherit;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid #1351B4;
            outline-offset: 1px;
        }

        .somente-leitura {
            padding: 11px;
            background: #F5F5F5;
            border-radius: 7px;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .contratada {
            color: #067647;
            background: #D1FADF;
        }

        .demitida {
            color: #B42318;
            background: #FEE4E2;
        }

        .outro {
            color: #344054;
            background: #EAECF0;
        }

        .documento,
        .botao-salvar {
            display: inline-block;
            padding: 12px 18px;
            border: none;
            border-radius: 7px;
            background: #1351B4;
            color: #FFFFFF;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        .documento:hover,
        .botao-salvar:hover {
            background: #0B3478;
        }

        .acoes {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .mensagem {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 8px;
        }

        .mensagem.sucesso {
            background: #D1FADF;
            color: #067647;
        }

        .mensagem.erro {
            background: #FEE4E2;
            color: #B42318;
        }

        .nota {
            margin-top: 0;
            color: #667085;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            .informacoes {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<main class="container">

    <h1>Detalhes do contrato do veterinário</h1>

    <p class="subtitulo">
        Edite os dados do contrato. Os dados cadastrais do veterinário,
        a clínica e o status são somente para consulta.
    </p>

    <?php if ($mensagem !== ""): ?>
        <div class="mensagem <?= escapar($tipoMensagem) ?>">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <form method="POST"
          action="detalhes_contrato_veterinario.php?id_contrato=<?= (int) $id_contrato ?>">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escapar($_SESSION["csrf_contrato_vet"]) ?>"
        >

        <!-- DADOS EDITÁVEIS DO CONTRATO -->
        <section class="card">

            <h2>Informações do contrato</h2>

            <div class="informacoes">

                <div class="campo">
                    <strong>ID do contrato</strong>
                    <div class="somente-leitura">
                        <?= (int) $dados["id_contrato"] ?>
                    </div>
                </div>

                <div class="campo">
                    <strong>Status atual</strong>
                    <div class="somente-leitura">
                        <span class="status <?= escapar($classeStatus) ?>">
                            <?= escapar($statusTexto) ?>
                        </span>
                    </div>
                </div>

                <div class="campo">
                    <label for="cargo">Cargo *</label>
                    <input
                        type="text"
                        id="cargo"
                        name="cargo"
                        maxlength="150"
                        value="<?= escapar($dados["cargo"]) ?>"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="tipo_contrato">Tipo de contrato *</label>
                    <input
                        type="text"
                        id="tipo_contrato"
                        name="tipo_contrato"
                        maxlength="100"
                        value="<?= escapar($dados["tipo_contrato"]) ?>"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="data_inicio">Data de início</label>
                    <input
                        type="date"
                        id="data_inicio"
                        name="data_inicio"
                        value="<?= escapar($dados["data_inicio"]) ?>"
                    >
                </div>

                <div class="campo">
                    <label for="data_fim">Data de término</label>
                    <input
                        type="date"
                        id="data_fim"
                        name="data_fim"
                        value="<?= escapar($dados["data_fim"]) ?>"
                    >
                </div>

                <div class="campo">
                    <label for="carga_horaria_semanal">
                        Carga horária semanal (horas)
                    </label>
                    <input
                        type="number"
                        id="carga_horaria_semanal"
                        name="carga_horaria_semanal"
                        min="0"
                        max="168"
                        step="0.01"
                        value="<?= escapar($dados["carga_horaria_semanal"]) ?>"
                    >
                </div>

            </div>

            <div style="margin-top: 20px;">
                <label for="observacoes">Observações</label>
                <textarea
                    id="observacoes"
                    name="observacoes"
                    maxlength="10000"
                ><?= escapar($dados["observacoes"]) ?></textarea>
            </div>

        </section>

        <!-- DADOS DO VETERINÁRIO: SOMENTE LEITURA COM MÁSCARAS -->
        <section class="card">

            <h2>Dados do veterinário</h2>

            <div class="informacoes">

                <div class="campo">
                    <strong>Nome completo</strong>
                    <div class="somente-leitura">
                        <?= escapar($dados["nome_completo"]) ?: "Não informado" ?>
                    </div>
                </div>

                <div class="campo">
                    <strong>CPF</strong>
                    <div class="somente-leitura campo-cpf">
                        <?= escapar($dados["cpf"]) ?: "Não informado" ?>
                    </div>
                </div>

                <div class="campo">
                    <strong>CRMV</strong>
                    <div class="somente-leitura">
                        <?= escapar($dados["numero_crmv"] . (!empty($dados["estado_crmv"]) ? "/" . $dados["estado_crmv"] : "")) ?: "Não informado" ?>
                    </div>
                </div>

                <div class="campo">
                    <strong>E-mail</strong>
                    <div class="somente-leitura">
                        <?= escapar($dados["email"]) ?: "Não informado" ?>
                    </div>
                </div>

                <div class="campo">
                    <strong>Telefone</strong>
                    <div class="somente-leitura campo-telefone">
                        <?= escapar($dados["telefone"]) ?: "Não informado" ?>
                    </div>
                </div>

            </div>

        </section>

        <!-- CLÍNICA: SOMENTE LEITURA -->
        <section class="card">

            <h2>Clínica</h2>

            <div class="informacoes">

                <div class="campo">
                    <strong>Nome da clínica</strong>
                    <div class="somente-leitura">
                        <?= escapar($dados["nome_clinica"]) ?: "Não informado" ?>
                    </div>
                </div>

                <div class="campo">
                    <strong>Razão social</strong>
                    <div class="somente-leitura">
                        <?= escapar($dados["razao_social"]) ?: "Não informado" ?>
                    </div>
                </div>

            </div>

        </section>

        <!-- DOCUMENTO -->
        <section class="card">

            <h2>Documento de verificação do veterinário</h2>

            <?php if (!empty($dados["caminho_documento"])): ?>

                <p class="nota">
                    O documento não é alterado por este formulário.
                </p>

                <a
                    class="documento"
                    href="visualizar_documentos.php?tipo=veterinario&amp;id=<?= (int) $dados["id_contrato"] ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Visualizar documento
                </a>

            <?php else: ?>

                <p>Nenhum documento de verificação foi cadastrado para este veterinário.</p>

            <?php endif; ?>

        </section>

        <div class="acoes">

            <button type="submit" class="botao-salvar">
                Salvar alterações do contrato
            </button>

        </div>

    </form>

</main>

<script>
    // Máscaras automáticas via JavaScript para CPF e Telefone
    document.addEventListener("DOMContentLoaded", function () {
        const elementoCpf = document.querySelector(".campo-cpf");
        if (elementoCpf) {
            let valorCpf = elementoCpf.textContent.trim().replace(/\D/g, "");
            if (valorCpf.length === 11) {
                elementoCpf.textContent = valorCpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            }
        }

        const elementoTel = document.querySelector(".campo-telefone");
        if (elementoTel) {
            let valorTel = elementoTel.textContent.trim().replace(/\D/g, "");
            if (valorTel.length === 11) {
                elementoTel.textContent = valorTel.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
            } else if (valorTel.length === 10) {
                elementoTel.textContent = valorTel.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
            }
        }
    });
</script>

</body>
</html>