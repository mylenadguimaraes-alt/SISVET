<?php

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . "/conexao.php";

// ========================================
// VERIFICAR LOGIN
// ========================================

if (!isset($_SESSION["id_tutor"])) {
    http_response_code(401);
    exit("Faça login para visualizar seus pets.");
}

$id_tutor = (int) $_SESSION["id_tutor"];

// ========================================
// RECEBER MENSAGENS
// ========================================

$sucesso = isset($_GET["sucesso"]) && $_GET["sucesso"] === "1";

$erro = $_GET["erro"] ?? "";

$tituloErro = $_GET["titulo"] ??
    "Não foi possível realizar a operação.";

// ========================================
// BUSCAR PETS DO TUTOR
// ========================================

$sql = "
    SELECT id_pet, nome, especie
    FROM pet
    WHERE id_tutor = ?
    ORDER BY nome ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro ao preparar consulta dos pets: " . $conn->error);
    http_response_code(500);
    exit("Não foi possível carregar os pets.");
}

$stmt->bind_param("i", $id_tutor);

if (!$stmt->execute()) {
    error_log("Erro ao consultar pets: " . $stmt->error);
    http_response_code(500);
    exit("Não foi possível carregar os pets.");
}

$resultado = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meus Pets - PetCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background-color: #F5F5F5;
            color: #202B3C;
            font-family: Arial, Helvetica, sans-serif;
        }

        .pagina {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* TÍTULO DA PÁGINA */

        .titulo-pagina {
            margin-bottom: 24px;
        }

        .titulo-pagina h1 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #0B3478;
        }

        .titulo-pagina p {
            margin: 0;
            color: #667085;
            font-size: 15px;
            line-height: 1.5;
        }

        /* MENSAGENS DE SUCESSO E ERRO */

        .mensagem {
            margin-bottom: 24px;
            padding: 16px 20px;
            border: 1px solid;
            border-radius: 10px;
            line-height: 1.5;
        }

        .mensagem p {
            margin: 6px 0 0;
            font-size: 14px;
        }

        .mensagem-sucesso {
            background-color: #ECFDF3;
            border-color: #A6F4C5;
            color: #067647;
        }

        .mensagem-erro {
            background-color: #FEF3F2;
            border-color: #FECDCA;
            color: #B42318;
        }

        /* GRADE DOS PETS */

        .lista-pets {
            display: grid;
            grid-template-columns: repeat(
                auto-fill,
                minmax(220px, 1fr)
            );
            gap: 20px;
            align-items: stretch;
        }

        /* CARD DO PET */

        .card-pet {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-height: 190px;
            padding: 24px;
            border: 1px solid #D9E0EA;
            border-radius: 12px;
            background-color: #FFFFFF;
            color: #202B3C;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .card-pet:hover {
            border-color: #1351B4;
            box-shadow: 0 4px 14px rgba(11, 52, 120, 0.10);
        }

        .nome-pet {
            margin: 0 0 10px;
            color: #0B3478;
            font-size: 21px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .especie-pet {
            margin: 0 0 24px;
            color: #202B3C;
            font-size: 15px;
            font-weight: 600;
        }

        /* LINK PARA VER DETALHES */

        .link-detalhes {
            display: inline-block;
            margin-top: auto;
            padding: 10px 14px;
            border-radius: 6px;
            background-color: #1351B4;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .link-detalhes:hover {
            background-color: #0B3478;
        }

        .link-detalhes:focus-visible {
            outline: 3px solid #8BB8FF;
            outline-offset: 3px;
        }

        /* CARD DE CADASTRO */

        .card-cadastro {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            min-height: 190px;
            padding: 24px;
            border: 2px dashed #1351B4;
            border-radius: 12px;
            background-color: #EAF2FF;
            color: #0B3478;
            text-decoration: none;
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        .card-cadastro:hover {
            background-color: #FFFFFF;
            box-shadow: 0 4px 14px rgba(11, 52, 120, 0.10);
        }

        .card-cadastro h2 {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .card-cadastro p {
            margin: 0;
            color: #667085;
            font-size: 14px;
            line-height: 1.5;
        }

        .sem-pets {
            align-self: center;
            color: #667085;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            body {
                padding: 20px;
            }

            .titulo-pagina h1 {
                font-size: 24px;
            }

            .lista-pets {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <main class="pagina">

        <header class="titulo-pagina">
            <h1>Meus Pets</h1>
            <p>Consulte seus animais ou cadastre um novo pet.</p>
        </header>

        <!-- MENSAGEM DE SUCESSO OU ERRO -->

        <?php if ($sucesso): ?>

            <div class="mensagem mensagem-sucesso" role="status">
                <strong>Pet cadastrado com sucesso!</strong>
                <p>O novo pet foi adicionado à sua lista.</p>
            </div>

        <?php elseif ($erro !== ""): ?>

            <div class="mensagem mensagem-erro" role="alert">
                <strong>
                    <?= htmlspecialchars(
                        $tituloErro,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </strong>

                <p>
                    <?= htmlspecialchars(
                        $erro,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </p>
            </div>

        <?php endif; ?>

        <section class="lista-pets">

            <!-- CARD PARA CADASTRAR PET -->

            <a
                href="/sisvet/incial/pages/tutor/dashboard/pages/cadastrar_pet/index.html"
                class="card-cadastro"
            >
                <h2>Cadastrar pet</h2>
                <p>Adicione um novo animal à sua carteira.</p>
            </a>

            <!-- PETS CADASTRADOS -->

            <?php if ($resultado->num_rows > 0): ?>

                <?php while ($pet = $resultado->fetch_assoc()): ?>

                    <article class="card-pet">

                        <h2 class="nome-pet">
                            <?= htmlspecialchars(
                                $pet["nome"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </h2>

                        <p class="especie-pet">
                            <?= htmlspecialchars(
                                $pet["especie"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </p>

                        <a
                            class="link-detalhes"
                            href="detalhes_pet.php?id_pet=<?= (int) $pet["id_pet"] ?>"
                        >
                            Ver detalhes
                        </a>

                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <p class="sem-pets">
                    Você ainda não cadastrou nenhum pet.
                    Clique em “Cadastrar pet” para começar.
                </p>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>

<?php

$stmt->close();
$conn->close();

?>
