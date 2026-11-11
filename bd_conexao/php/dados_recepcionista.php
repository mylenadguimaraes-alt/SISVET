<?php
session_start();

require_once "conexao.php";

// ========================================
// VERIFICAR LOGIN DO DONO
// ========================================

if (!isset($_SESSION["id_dono"])) {
    header("Location: login.html");
    exit;
}

$id_dono = (int) $_SESSION["id_dono"];

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

function h($valor)
{
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

function dataBrasileira($data)
{
    if (empty($data) || $data === "0000-00-00") {
        return "Não informada";
    }

    $timestamp = strtotime($data);

    if ($timestamp === false) {
        return "Não informada";
    }

    return date("d/m/Y", $timestamp);
}

// ========================================
// DEFINIR O STATUS DA LISTAGEM
// ========================================

$statusPermitidos = ["contratada", "demitida"];

$status = $_GET["status"] ?? "contratada";

if (!in_array($status, $statusPermitidos, true)) {
    $status = "contratada";
}

// ========================================
// MENSAGENS DE RESULTADO
// ========================================

$mensagem = "";
$tipoMensagem = "";

if (isset($_GET["resultado"])) {
    switch ($_GET["resultado"]) {
        case "demitida":
            $mensagem = "Contrato encerrado com sucesso.";
            $tipoMensagem = "sucesso";
            break;

        case "renovado":
            $mensagem = "Contrato renovado com sucesso.";
            $tipoMensagem = "sucesso";
            break;

        case "atualizado":
            $mensagem = "Dados do contrato atualizados com sucesso.";
            $tipoMensagem = "sucesso";
            break;
    }
}

// ========================================
// BUSCAR CONTRATOS
// ========================================

$sql = "
    SELECT
        cr.id_contrato,
        cr.id_recepcionista,
        cr.id_clinica,
        cr.cargo,
        cr.tipo_contrato,
        cr.data_inicio,
        cr.data_fim,
        cr.carga_horaria_semanal,
        cr.observacoes,
        cr.status,

        r.nome AS nome_recepcionista,
        r.cpf,
        r.email,
        r.telefone,

        c.nome_clinica

    FROM contrato_recepcionista cr

    INNER JOIN recepcionista r
        ON r.id_recepcionista = cr.id_recepcionista

    INNER JOIN clinica c
        ON c.id_clinica = cr.id_clinica

    WHERE cr.status = ?
      AND c.id_dono = ?

    ORDER BY
        cr.data_inicio DESC,
        r.nome ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Erro ao preparar a consulta: " . h($conn->error));
}

$stmt->bind_param("si", $status, $id_dono);

if (!$stmt->execute()) {
    die("Erro ao buscar os contratos: " . h($stmt->error));
}

$resultado = $stmt->get_result();

$contratos = [];

while ($linha = $resultado->fetch_assoc()) {
    $contratos[] = $linha;
}

$stmt->close();

$totalContratos = count($contratos);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $status === "contratada"
            ? "Recepcionistas Contratadas"
            : "Recepcionistas Demitidas" ?>
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #F5F5F5;
            color: #202B3C;
            font-family: Arial, sans-serif;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .cabecalho {
            margin-bottom: 25px;
        }

        .cabecalho h1 {
            margin: 0 0 10px;
            color: #0B3478;
            font-size: 26px;
        }

        .cabecalho p {
            margin: 0;
            color: #667085;
            font-size: 14px;
            line-height: 1.5;
        }

        .resumo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 18px;
            background: #FFFFFF;
            border: 1px solid #D9E0EA;
            border-radius: 10px;
        }

        .resumo strong {
            color: #0B3478;
            font-size: 16px;
        }

        .pesquisa {
            width: 100%;
            margin-bottom: 20px;
        }

        .pesquisa input {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #D9E0EA;
            border-radius: 8px;
            background: #FFFFFF;
            font-size: 14px;
            outline: none;
        }

        .pesquisa input:focus {
            border-color: #1351B4;
            box-shadow: 0 0 0 3px #EAF2FF;
        }

        .lista-contratos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 20px;
        }

        .bloco-clinica {
            padding: 22px;
            background: #FFFFFF;
            border: 1px solid #D9E0EA;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
            min-width: 0;
        }

        .topo-card {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 18px;
        }

        .topo-card h2 {
            margin: 0 0 7px;
            color: #0B3478;
            font-size: 19px;
            overflow-wrap: anywhere;
        }

        .nome-clinica {
            margin: 0;
            color: #667085;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            flex-shrink: 0;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-contratada {
            background: #EAF2FF;
            color: #1351B4;
        }

        .status-demitida {
            background: #FEE4E2;
            color: #D92D20;
        }

        .informacoes {
            display: grid;
            gap: 12px;
            margin-bottom: 18px;
        }

        .informacao {
            overflow-wrap: anywhere;
        }

        .informacao strong {
            display: block;
            margin-bottom: 4px;
            color: #667085;
            font-size: 12px;
            font-weight: normal;
        }

        .informacao span {
            color: #202B3C;
            font-size: 14px;
            line-height: 1.5;
        }

        .aviso-vencido {
            margin: 0 0 18px;
            padding: 12px;
            border: 1px solid #FDA29B;
            border-radius: 8px;
            background: #FEF3F2;
            color: #B42318;
            font-size: 13px;
            line-height: 1.5;
        }

        .acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid #D9E0EA;
        }

        .acoes form {
            flex: 1;
            min-width: 120px;
            margin: 0;
        }

        .acoes button {
            width: 100%;
            min-height: 40px;
            padding: 10px 12px;
            border: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .detalhes {
            background: #1351B4;
            color: #FFFFFF;
        }

        .detalhes:hover {
            background: #0B3478;
        }

        .renovar {
            background: #EAF2FF;
            color: #1351B4;
        }

        .renovar:hover {
            background: #D5E5FF;
        }

        .demitir {
            background: #FEE4E2;
            color: #B42318;
        }

        .demitir:hover {
            background: #FECACA;
        }

        .mensagem {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .mensagem.sucesso {
            border: 1px solid #A6F4C5;
            background: #ECFDF3;
            color: #027A48;
        }

        .sem-resultados {
            grid-column: 1 / -1;
            padding: 35px 20px;
            text-align: center;
            background: #FFFFFF;
            border: 1px dashed #D9E0EA;
            border-radius: 10px;
        }

        .sem-resultados h2 {
            margin: 0 0 10px;
            color: #0B3478;
            font-size: 19px;
        }

        .sem-resultados p {
            margin: 0;
            color: #667085;
            font-size: 14px;
            line-height: 1.5;
        }

        .oculto {
            display: none !important;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            .cabecalho h1 {
                font-size: 22px;
            }

            .bloco-clinica {
                padding: 17px;
            }

            .topo-card {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <header class="cabecalho">
        <h1>
            <?= $status === "contratada"
                ? "Recepcionistas contratadas"
                : "Recepcionistas demitidas" ?>
        </h1>

        <p>
            <?= $status === "contratada"
                ? "Consulte os contratos ativos das recepcionistas vinculadas às suas clínicas."
                : "Consulte o histórico dos contratos encerrados das recepcionistas vinculadas às suas clínicas." ?>
        </p>
    </header>

    <?php if ($mensagem !== ""): ?>
        <div class="mensagem <?= h($tipoMensagem) ?>">
            <?= h($mensagem) ?>
        </div>
    <?php endif; ?>

    <section class="resumo">
        <strong>
            Total de contratos:
            <span id="totalContratos"><?= $totalContratos ?></span>
        </strong>
    </section>

    <div class="pesquisa">
        <input
            type="search"
            id="pesquisaClinica"
            placeholder="Pesquisar por recepcionista, CPF, e-mail ou clínica..."
            autocomplete="off"
            aria-label="Pesquisar contratos"
        >
    </div>

    <section class="lista-contratos" id="listaContratos">

        <?php if ($totalContratos === 0): ?>

            <div class="sem-resultados" id="semResultados">
                <h2>Nenhum contrato encontrado</h2>

                <p>
                    <?= $status === "contratada"
                        ? "Não existem contratos de recepcionistas contratadas para exibir."
                        : "Não existem contratos de recepcionistas demitidas para exibir." ?>
                </p>
            </div>

        <?php else: ?>

            <?php foreach ($contratos as $linha): ?>

                <?php
                $contratoVencido = (
                    $linha["status"] === "contratada"
                    && !empty($linha["data_fim"])
                    && $linha["data_fim"] < date("Y-m-d")
                );
                ?>

                <article class="bloco-clinica">

                    <div class="topo-card">

                        <div>
                            <h2 class="nome-recepcionista">
                                <?= h($linha["nome_recepcionista"]) ?>
                            </h2>

                            <p class="nome-clinica">
                                Clínica: <?= h($linha["nome_clinica"]) ?>
                            </p>
                        </div>

                        <span class="status <?= $linha["status"] === "contratada"
                            ? "status-contratada"
                            : "status-demitida" ?>">
                            <?= $linha["status"] === "contratada"
                                ? "Contratada"
                                : "Demitida" ?>
                        </span>

                    </div>

                    <div class="informacoes">

                        <div class="informacao">
                            <strong>CPF</strong>
                            <span><?= h($linha["cpf"]) ?></span>
                        </div>

                        <div class="informacao">
                            <strong>E-mail</strong>
                            <span><?= h($linha["email"]) ?></span>
                        </div>

                        <div class="informacao">
                            <strong>Telefone</strong>
                            <span><?= h($linha["telefone"]) ?></span>
                        </div>

                        <div class="informacao">
                            <strong>Cargo</strong>
                            <span><?= h($linha["cargo"]) ?></span>
                        </div>

                        <div class="informacao">
                            <strong>Tipo de contrato</strong>
                            <span><?= h($linha["tipo_contrato"]) ?></span>
                        </div>

                        <div class="informacao">
                            <strong>Data de início</strong>
                            <span><?= h(dataBrasileira($linha["data_inicio"])) ?></span>
                        </div>

                        <div class="informacao">
                            <strong>Data de término</strong>
                            <span>
                                <?= !empty($linha["data_fim"])
                                    ? h(dataBrasileira($linha["data_fim"]))
                                    : "Sem data de término" ?>
                            </span>
                        </div>

                        <div class="informacao">
                            <strong>Carga horária semanal</strong>
                            <span>
                                <?= h($linha["carga_horaria_semanal"]) ?> horas
                            </span>
                        </div>

                    </div>

                    <?php if ($contratoVencido): ?>
                        <div class="aviso-vencido">
                            <strong>Contrato vencido!</strong>
                            A data de término já passou. Verifique a situação
                            e renove o contrato, se necessário.
                        </div>
                    <?php endif; ?>

                    <div class="acoes">

                        <form
                            action="detalhes_contrato_recepcionista.php"
                            method="GET"
                        >
                            <input
                                type="hidden"
                                name="id_contrato"
                                value="<?= (int) $linha["id_contrato"] ?>"
                            >

                            <button type="submit" class="detalhes">
                                Ver detalhes
                            </button>
                        </form>

                        <?php if ($linha["status"] === "contratada"): ?>

                            <form
                                action="demitir_recepcionista.php"
                                method="POST"
                                onsubmit="return confirm('Tem certeza de que deseja encerrar este contrato?');"
                            >
                                <input
                                    type="hidden"
                                    name="id_contrato"
                                    value="<?= (int) $linha["id_contrato"] ?>"
                                >

                                <button type="submit" class="demitir">
                                    Demitir
                                </button>
                            </form>

                        <?php else: ?>

                            <form
                                action="renovar_contrato_recepcionista.php"
                                method="GET"
                            >
                                <input
                                    type="hidden"
                                    name="id_contrato"
                                    value="<?= (int) $linha["id_contrato"] ?>"
                                >

                                <button type="submit" class="renovar">
                                    Renovar contrato
                                </button>
                            </form>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

            <div class="sem-resultados oculto" id="semResultados">
                <h2>Nenhum resultado encontrado</h2>
                <p>Não encontramos contratos com os termos pesquisados.</p>
            </div>

        <?php endif; ?>

    </section>

</div>

<script>
    const campoPesquisa = document.getElementById("pesquisaClinica");
    const cartoes = document.querySelectorAll(".bloco-clinica");
    const contador = document.getElementById("totalContratos");
    const mensagemVazia = document.getElementById("semResultados");

    campoPesquisa.addEventListener("input", function () {
        const termo = this.value
            .trim()
            .toLocaleLowerCase("pt-BR");

        let encontrados = 0;

        cartoes.forEach(function (cartao) {
            const conteudo = cartao.textContent
                .toLocaleLowerCase("pt-BR");

            const corresponde = conteudo.includes(termo);

            cartao.classList.toggle("oculto", !corresponde);

            if (corresponde) {
                encontrados++;
            }
        });

        contador.textContent = encontrados;

        if (mensagemVazia) {
            mensagemVazia.classList.toggle(
                "oculto",
                encontrados > 0 || termo === "" && cartoes.length > 0
            );
        }
    });
</script>

</body>
</html>
