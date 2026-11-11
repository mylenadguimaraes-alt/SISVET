
<?php
session_start();

require_once __DIR__ . "/conexao.php";

// Identifica a recepcionista logada
$id_recepcionista = $_SESSION["id_recepcionista"]
    ?? $_SESSION["id_rec"]
    ?? $_SESSION["id"]
    ?? null;

$acao = $_GET["acao"] ?? "";

// Verifica se existe uma recepcionista na sessão
if (empty($id_recepcionista)) {

    if ($acao === "consultar" || $acao === "entrar") {
        header("Content-Type: application/json; charset=UTF-8");

        http_response_code(401);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Sua sessão expirou. Entre novamente no sistema."
        ]);

        exit;
    }

    exit("Erro: Sua sessão expirou. Entre novamente no sistema.");
}

$id_recepcionista = (int) $id_recepcionista;


// ========================================
// FUNÇÃO DE RESPOSTA JSON
// ========================================

function responderJSON($dados, $codigo = 200) {

    http_response_code($codigo);

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode($dados, JSON_UNESCAPED_UNICODE);

    exit;
}


// ========================================
// FUNÇÃO PARA VALIDAR O CONTRATO
// ========================================

function verificarContratoAtivo($conn, $id_recepcionista, $id_clinica) {

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
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    $stmt->bind_param(
        "ii",
        $id_recepcionista,
        $id_clinica
    );

    if (!$stmt->execute()) {

        $erro = $stmt->error;

        $stmt->close();

        throw new Exception("Erro ao verificar o contrato: " . $erro);
    }

    $resultado = $stmt->get_result();

    $permitido = $resultado->num_rows > 0;

    $stmt->close();

    return $permitido;
}


// ========================================
// AÇÃO: CONSULTAR CLÍNICAS
// ========================================

if ($acao === "consultar") {

    try {

        $sql = "
            SELECT
                c.id_clinica,
                c.nome_clinica,
                c.email,
                c.telefone

            FROM contrato_recepcionista cr

            INNER JOIN clinica c
                ON c.id_clinica = cr.id_clinica

            WHERE cr.id_recepcionista = ?
              AND cr.status = 'contratada'
              AND c.status_cadastro = 'autorizada'
              AND (
                    cr.data_fim IS NULL
                    OR cr.data_fim >= CURDATE()
              )

            ORDER BY c.nome_clinica ASC
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception(
                "Erro ao preparar a consulta: " . $conn->error
            );
        }

        $stmt->bind_param("i", $id_recepcionista);

        if (!$stmt->execute()) {

            $erro = $stmt->error;

            $stmt->close();

            throw new Exception(
                "Erro ao consultar as clínicas: " . $erro
            );
        }

        $resultado = $stmt->get_result();

        $clinicas = [];

        while ($row = $resultado->fetch_assoc()) {

            $clinicas[] = [
                "id" => (int) $row["id_clinica"],
                "nome" => $row["nome_clinica"],
                "email" => $row["email"],
                "telefone" => $row["telefone"]
            ];
        }

        $stmt->close();

        responderJSON([
            "sucesso" => true,
            "clinicas" => $clinicas
        ]);

    } catch (Exception $e) {

        responderJSON([
            "sucesso" => false,
            "mensagem" => $e->getMessage()
        ], 500);
    }
}


// ========================================
// AÇÃO: ENTRAR NA CLÍNICA
// ========================================

if ($acao === "entrar") {

    $id_clinica = filter_input(
        INPUT_POST,
        "id_clinica",
        FILTER_VALIDATE_INT
    );

    if (!$id_clinica || $id_clinica <= 0) {

        responderJSON([
            "sucesso" => false,
            "mensagem" => "ID da clínica inválido."
        ], 400);
    }

    try {

        // Confere novamente se a recepcionista tem autorização
        $permitido = verificarContratoAtivo(
            $conn,
            $id_recepcionista,
            $id_clinica
        );

        if (!$permitido) {

            unset($_SESSION["id_clinica_ativa"]);

            responderJSON([
                "sucesso" => false,
                "mensagem" => "Você não possui contrato ativo para acessar esta clínica, ou ela não está autorizada."
            ], 403);
        }

        // Salva a clínica selecionada na sessão
        $_SESSION["id_clinica_ativa"] = (int) $id_clinica;

        responderJSON([
            "sucesso" => true,
            "mensagem" => "Acesso autorizado."
        ]);

    } catch (Exception $e) {

        responderJSON([
            "sucesso" => false,
            "mensagem" => $e->getMessage()
        ], 500);
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Entrar na Clínica - SISVET</title>

    <link rel="stylesheet" href="css/style_contrato.css">

    <link rel="icon" href="../../img/logo.png">

    <style>

        .botao-entrar {
            padding: 8px 14px;
            background: var(--azul);
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }

        .botao-entrar:hover {
            background: var(--azul-escuro);
        }

        .botao-entrar:disabled {
            opacity: 0.6;
            cursor: wait;
        }

    </style>

</head>

<body>

    <main class="pagina">

        <header class="titulo-pagina">

            <h1>Entrar na Clínica</h1>

            <p>
                Selecione uma clínica na qual você possui contrato ativo
                para acessar o painel de atendimento da recepção.
            </p>

        </header>


        <div
            id="mensagem"
            class="mensagem"
            role="status"
            aria-live="polite">
        </div>


        <section class="cartao">

            <div class="barra-pesquisa" style="margin-bottom: 20px;">

                <input
                    type="text"
                    id="filtroPesquisa"
                    placeholder="Pesquisar por nome da clínica, e-mail ou telefone..."
                    autocomplete="off"
                >

            </div>


            <div class="tabela-container">

                <table>

                    <thead>

                        <tr>
                            <th>Nome da Clínica</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th style="text-align: right;">Ação</th>
                        </tr>

                    </thead>


                    <tbody id="tabelaClinicas">

                        <tr>
                            <td colspan="4">
                                Carregando clínicas disponíveis...
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>

        </section>

    </main>


    <script>

        const urlPHP = "entrar_clinica_recepcionista.php";

        const tabelaClinicas = document.getElementById("tabelaClinicas");

        const filtroPesquisa = document.getElementById("filtroPesquisa");

        const mensagem = document.getElementById("mensagem");

        let dadosClinicas = [];


        // Exibe mensagens
        function mostrarMensagem(texto, tipo = "erro") {

            mensagem.textContent = texto;

            mensagem.className = "mensagem " + tipo;
        }


        // Renderiza a tabela
        function renderizarTabela(dados) {

            tabelaClinicas.innerHTML = "";

            if (dados.length === 0) {

                const linha = document.createElement("tr");

                const celula = document.createElement("td");

                celula.colSpan = 4;

                celula.textContent =
                    "Nenhuma clínica disponível no momento.";

                linha.appendChild(celula);

                tabelaClinicas.appendChild(linha);

                return;
            }


            dados.forEach(item => {

                const tr = document.createElement("tr");


                const tdNome = document.createElement("td");

                tdNome.textContent = item.nome || "Não informado";


                const tdEmail = document.createElement("td");

                tdEmail.textContent = item.email || "Não informado";


                const tdTelefone = document.createElement("td");

                tdTelefone.textContent = item.telefone || "Não informado";


                const tdAcao = document.createElement("td");

                tdAcao.style.textAlign = "right";


                const botao = document.createElement("button");

                botao.className = "botao-entrar";

                botao.textContent = "Entrar";

                botao.addEventListener("click", function () {

                    entrarClinica(item.id, botao);

                });


                tdAcao.appendChild(botao);


                tr.appendChild(tdNome);

                tr.appendChild(tdEmail);

                tr.appendChild(tdTelefone);

                tr.appendChild(tdAcao);


                tabelaClinicas.appendChild(tr);

            });

        }


        // Consulta as clínicas disponíveis
        async function carregarClinicas() {

            try {

                const resposta = await fetch(
                    urlPHP + "?acao=consultar",
                    {
                        credentials: "same-origin"
                    }
                );

                const dados = await resposta.json();

                if (!resposta.ok || !dados.sucesso) {

                    throw new Error(
                        dados.mensagem || "Erro ao carregar as clínicas."
                    );
                }

                dadosClinicas = dados.clinicas || [];

                renderizarTabela(dadosClinicas);

            } catch (erro) {

                console.error("Erro ao carregar clínicas:", erro);

                mostrarMensagem(erro.message);

                tabelaClinicas.innerHTML = "";

            }

        }


        // Entra na clínica selecionada
        async function entrarClinica(idClinica, botao) {

            const formData = new FormData();

            formData.append("id_clinica", idClinica);

            botao.disabled = true;

            botao.textContent = "Entrando...";


            try {

                const resposta = await fetch(
                    urlPHP + "?acao=entrar",
                    {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin"
                    }
                );

                const dados = await resposta.json();

                if (!resposta.ok || !dados.sucesso) {

                    throw new Error(
                        dados.mensagem ||
                        "Não foi possível acessar a clínica."
                    );
                }

                window.location.href =
                    "../../incial/pages/clinica/painel_recepcionista/pages/painel_clinica/index.html";


            } catch (erro) {

                mostrarMensagem(erro.message);

                botao.disabled = false;

                botao.textContent = "Entrar";

            }

        }


        // Pesquisa por nome, e-mail ou telefone
        filtroPesquisa.addEventListener("input", function () {

            const termo = this.value.toLowerCase().trim();

            const filtrados = dadosClinicas.filter(item => {

                return (

                    String(item.nome || "")
                        .toLowerCase()
                        .includes(termo)

                    ||

                    String(item.email || "")
                        .toLowerCase()
                        .includes(termo)

                    ||

                    String(item.telefone || "")
                        .toLowerCase()
                        .includes(termo)

                );

            });

            renderizarTabela(filtrados);

        });


        // Inicializa a página
        carregarClinicas();

    </script>

</body>

</html>
