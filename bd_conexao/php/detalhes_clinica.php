
<?php
session_start();

require_once __DIR__ . "/conexao.php";

if (empty($_SESSION["id_dono"])) {
    http_response_code(401);
    exit("Você precisa estar logado para acessar esta página.");
}

$id_dono = (int) $_SESSION["id_dono"];
$id_clinica = filter_input(INPUT_GET, "id_clinica", FILTER_VALIDATE_INT);

if (!$id_clinica || $id_clinica <= 0) {
    http_response_code(400);
    exit("ID da clínica inválido.");
}

function escapar($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$mensagem = "";
$tipoMensagem = "";

// Busca os dados da clínica.
$sql = "SELECT
            id_clinica,
            razao_social,
            nome_clinica,
            email,
            telefone,
            cnpj,
            cep,
            endereco,
            numero,
            complemento,
            bairro,
            cidade,
            estado,
            autorizado,
            suspenso,
            status_cadastro,
            documento_licenca
        FROM clinica
        WHERE id_clinica = ?
          AND id_dono = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro ao consultar clínica: " . $conn->error);
    http_response_code(500);
    exit("Não foi possível consultar os dados da clínica.");
}

$stmt->bind_param("ii", $id_clinica, $id_dono);
$stmt->execute();

$resultado = $stmt->get_result();
$clinica = $resultado->fetch_assoc();

$stmt->close();

if (!$clinica) {
    http_response_code(404);
    exit("Clínica não encontrada ou acesso não autorizado.");
}

// Verifica se a clínica possui um documento cadastrado.
$temDocumentoClinica = !empty(
    trim((string) ($clinica["documento_licenca"] ?? ""))
);

// Atualiza os dados da clínica.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $razao_social = trim($_POST["razao_social"] ?? "");
    $nome_clinica = trim($_POST["nome_clinica"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $cep = trim($_POST["cep"] ?? "");
    $endereco = trim($_POST["endereco"] ?? "");
    $numero = trim($_POST["numero"] ?? "");
    $complemento = trim($_POST["complemento"] ?? "");
    $bairro = trim($_POST["bairro"] ?? "");
    $cidade = trim($_POST["cidade"] ?? "");
    $estado = strtoupper(trim($_POST["estado"] ?? ""));

    if ((int) $clinica["suspenso"] === 1) {
        $mensagem = "Não é possível editar uma clínica suspensa.";
        $tipoMensagem = "erro";

    } elseif (
        $razao_social === "" ||
        $nome_clinica === "" ||
        $email === ""
    ) {
        $mensagem = "Preencha a razão social, o nome da clínica e o e-mail.";
        $tipoMensagem = "erro";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Informe um e-mail válido.";
        $tipoMensagem = "erro";

    } else {
        $sqlAtualizar = "UPDATE clinica
            SET razao_social = ?,
                nome_clinica = ?,
                email = ?,
                telefone = ?,
                cep = ?,
                endereco = ?,
                numero = ?,
                complemento = ?,
                bairro = ?,
                cidade = ?,
                estado = ?
            WHERE id_clinica = ?
              AND id_dono = ?
              AND suspenso = 0";

        $stmtAtualizar = $conn->prepare($sqlAtualizar);

        if (!$stmtAtualizar) {
            error_log(
                "Erro ao preparar atualização da clínica: " . $conn->error
            );

            $mensagem = "Não foi possível preparar a atualização.";
            $tipoMensagem = "erro";

        } else {
            $stmtAtualizar->bind_param(
                "sssssssssssii",
                $razao_social,
                $nome_clinica,
                $email,
                $telefone,
                $cep,
                $endereco,
                $numero,
                $complemento,
                $bairro,
                $cidade,
                $estado,
                $id_clinica,
                $id_dono
            );

            if ($stmtAtualizar->execute()) {
                $mensagem = "Dados da clínica atualizados com sucesso!";
                $tipoMensagem = "sucesso";

                $clinica["razao_social"] = $razao_social;
                $clinica["nome_clinica"] = $nome_clinica;
                $clinica["email"] = $email;
                $clinica["telefone"] = $telefone;
                $clinica["cep"] = $cep;
                $clinica["endereco"] = $endereco;
                $clinica["numero"] = $numero;
                $clinica["complemento"] = $complemento;
                $clinica["bairro"] = $bairro;
                $clinica["cidade"] = $cidade;
                $clinica["estado"] = $estado;

            } else {
                error_log(
                    "Erro ao atualizar clínica: " . $stmtAtualizar->error
                );

                $mensagem = "Erro ao atualizar os dados da clínica.";
                $tipoMensagem = "erro";
            }

            $stmtAtualizar->close();
        }
    }
}

if ((int) $clinica["suspenso"] === 1) {
    $situacao = "Suspensa";
} elseif ((int) $clinica["autorizado"] === 1) {
    $situacao = "Autorizada";
} else {
    $situacao = "Em espera";
}

$somenteLeitura = ((int) $clinica["suspenso"] === 1);

$urlAtual = htmlspecialchars(
    $_SERVER["PHP_SELF"] . "?id_clinica=" . $id_clinica,
    ENT_QUOTES,
    "UTF-8"
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detalhes da Clínica | PetCare</title>

    <style>
        :root {
            --azul: #1351B4;
            --azul-escuro: #0B3478;
            --azul-claro: #EAF2FF;
            --fundo: #F5F5F5;
            --branco: #FFFFFF;
            --texto: #202B3C;
            --texto-secundario: #667085;
            --borda: #D9E0EA;
            --vermelho: #D92D20;
            --verde: #16803C;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: var(--fundo);
            color: var(--texto);
        }

        .container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .topo {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .topo h1 {
            margin: 0;
            font-size: 25px;
        }

        .subtitulo {
            color: var(--texto-secundario);
            margin: 8px 0 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 8px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-voltar {
            background: #E5EAF2;
            color: var(--texto);
            flex-shrink: 0;
        }

        .btn-voltar:hover {
            background: #D5DDE8;
        }

        .btn-salvar {
            background: var(--azul);
            color: var(--branco);
        }

        .btn-salvar:hover {
            background: var(--azul-escuro);
        }

        .btn-documento {
            background: var(--branco);
            color: var(--azul);
            border: 1px solid var(--borda);
            font-weight: 600;
        }

        .btn-documento:hover {
            background: var(--azul-claro);
            border-color: var(--azul);
        }

        .btn:focus-visible {
            outline: 2px solid var(--azul);
            outline-offset: 3px;
        }

        .cartao {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .cabecalho-cartao {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        .cabecalho-cartao h2 {
            margin: 0;
            font-size: 19px;
        }

        .situacao {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            background: var(--azul-claro);
            color: var(--azul-escuro);
            font-size: 13px;
            font-weight: bold;
        }

        .grade {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 0;
        }

        .campo-largo {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            min-width: 0;
            padding: 12px;
            border: 1px solid var(--borda);
            border-radius: 7px;
            color: var(--texto);
            background: var(--branco);
            font: inherit;
        }

        input:focus {
            outline: 2px solid #B8D1FF;
            border-color: var(--azul);
        }

        input:disabled {
            background: #F0F2F5;
            color: var(--texto-secundario);
            cursor: not-allowed;
        }

        .ajuda {
            margin-top: 12px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--texto-secundario);
        }

        .acoes {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .mensagem {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .mensagem.sucesso {
            color: var(--verde);
            background: #E9F8EF;
            border: 1px solid #B7E5C6;
        }

        .mensagem.erro {
            color: var(--vermelho);
            background: #FFF0EF;
            border: 1px solid #F4C7C3;
        }

        .documento-info {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-bottom: 18px;
        }

        .documento-icone {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            background: var(--azul-claro);
            color: var(--azul);
            border-radius: 9px;
            font-size: 22px;
        }

        .documento-texto strong {
            display: block;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .documento-texto p {
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
            color: var(--texto-secundario);
        }

        @media (max-width: 650px) {
            body {
                padding: 14px;
            }

            .topo {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .topo h1 {
                font-size: 21px;
            }

            .cartao {
                padding: 17px;
            }

            .grade {
                grid-template-columns: 1fr;
            }

            .campo-largo {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>
<main class="container">

    <header class="topo">
        <button type="button" class="btn btn-voltar" id="btnVoltar">
            &#8592; Voltar
        </button>

        <div>
            <h1>Detalhes da clínica</h1>
            <p class="subtitulo">
                Consulte e atualize os dados cadastrais.
            </p>
        </div>
    </header>

    <?php if ($mensagem !== ""): ?>
        <div class="mensagem <?= escapar($tipoMensagem) ?>" role="status">
            <?= escapar($mensagem) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $urlAtual ?>" id="formClinica">
        <section class="cartao">
            <div class="cabecalho-cartao">
                <h2>Informações da clínica</h2>

                <span class="situacao">
                    <?= escapar($situacao) ?>
                </span>
            </div>

            <div class="grade">
                <div class="campo">
                    <label for="nome_clinica">Nome da clínica</label>
                    <input
                        type="text"
                        id="nome_clinica"
                        name="nome_clinica"
                        value="<?= escapar($clinica["nome_clinica"]) ?>"
                        required
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="razao_social">Razão social</label>
                    <input
                        type="text"
                        id="razao_social"
                        name="razao_social"
                        value="<?= escapar($clinica["razao_social"]) ?>"
                        required
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="email">E-mail</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= escapar($clinica["email"]) ?>"
                        required
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="telefone">Telefone</label>
                    <input
                        type="text"
                        id="telefone"
                        name="telefone"
                        value="<?= escapar($clinica["telefone"]) ?>"
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="cnpj">CNPJ</label>
                    <input
                        type="text"
                        id="cnpj"
                        value="<?= escapar($clinica["cnpj"]) ?>"
                        disabled
                    >
                </div>

                <div class="campo">
                    <label for="cep">CEP</label>
                    <input
                        type="text"
                        id="cep"
                        name="cep"
                        value="<?= escapar($clinica["cep"]) ?>"
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo campo-largo">
                    <label for="endereco">Endereço</label>
                    <input
                        type="text"
                        id="endereco"
                        name="endereco"
                        value="<?= escapar($clinica["endereco"]) ?>"
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="numero">Número</label>
                    <input
                        type="text"
                        id="numero"
                        name="numero"
                        value="<?= escapar($clinica["numero"]) ?>"
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="complemento">Complemento</label>
                    <input
                        type="text"
                        id="complemento"
                        name="complemento"
                        value="<?= escapar($clinica["complemento"]) ?>"
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="bairro">Bairro</label>
                    <input
                        type="text"
                        id="bairro"
                        name="bairro"
                        value="<?= escapar($clinica["bairro"]) ?>"
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="cidade">Cidade</label>
                    <input
                        type="text"
                        id="cidade"
                        name="cidade"
                        value="<?= escapar($clinica["cidade"]) ?>"
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>

                <div class="campo">
                    <label for="estado">Estado</label>
                    <input
                        type="text"
                        id="estado"
                        name="estado"
                        maxlength="2"
                        value="<?= escapar($clinica["estado"]) ?>"
                        <?= $somenteLeitura ? "disabled" : "" ?>
                    >
                </div>
            </div>

            <?php if ($somenteLeitura): ?>
                <p class="ajuda">
                    Esta clínica está suspensa. A edição está desabilitada.
                </p>
            <?php else: ?>
                <div class="acoes">
                    <button type="submit" class="btn btn-salvar">
                        Salvar alterações
                    </button>
                </div>
            <?php endif; ?>
        </section>
    </form>

    <section class="cartao">
        <div class="cabecalho-cartao">
            <h2>Documento da clínica</h2>
        </div>

        <div class="documento-info">
            <div class="documento-icone" aria-hidden="true">
                &#128196;
            </div>

            <div class="documento-texto">
                <strong>Documento de licença</strong>

                <?php if ($temDocumentoClinica): ?>
                    <p>
                        Existe um documento cadastrado para esta clínica.
                    </p>
                <?php else: ?>
                    <p>
                        Nenhum documento foi cadastrado para esta clínica.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($temDocumentoClinica): ?>
            <a
                class="btn btn-documento"
                href="/sisvet/bd_conexao/php/visualizar_documentos.php?tipo=clinica&amp;id_clinica=<?= (int) $clinica["id_clinica"] ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                &#128065; Visualizar documento
            </a>
        <?php endif; ?>
    </section>

</main>

<script>
    const btnVoltar = document.getElementById("btnVoltar");

    btnVoltar.addEventListener("click", function () {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href =
                "/sisvet/incial/pages/clinica/painel_dono/pages/clinicas/";
        }
    });
</script>

</body>
</html>
