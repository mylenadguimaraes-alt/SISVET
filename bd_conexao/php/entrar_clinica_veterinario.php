<?php
session_start();

$id_veterinario = $_SESSION["id"] ?? $_SESSION["id_veterinario"] ?? null;

if (empty($id_veterinario)) {
    exit("Erro: Sua sessão expirou. Entre novamente no sistema.");
}

$id_veterinario = (int) $id_veterinario;
$acao = $_GET["acao"] ?? "";

require_once __DIR__ . "/conexao.php";

if ($acao === "entrar") {
    header('Content-Type: text/plain; charset=UTF-8');
    $id_clinica = filter_input(INPUT_POST, "id_clinica", FILTER_VALIDATE_INT);

    if (!$id_clinica) {
        exit("Erro: ID da clínica inválido.");
    }

    // Valida se o contrato está ativo, a clínica autorizada e o contrato NÃO está expirado
    $stmt = $conn->prepare("
        SELECT c.id_clinica 
        FROM contrato_veterinario cv
        INNER JOIN clinica c ON c.id_clinica = cv.id_clinica
        WHERE cv.id_veterinario = ? 
          AND cv.id_clinica = ? 
          AND cv.status = 'contratado' 
          AND c.status_cadastro = 'autorizada'
          AND (cv.data_fim IS NULL OR cv.data_fim >= CURDATE())
        LIMIT 1
    ");
    $stmt->bind_param("ii", $id_veterinario, $id_clinica);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $_SESSION["id_clinica_ativa"] = $id_clinica;
        echo "Sucesso";
    } else {
        http_response_code(403);
        exit("Erro: Acesso não autorizado ou contrato expirado para esta clínica.");
    }
    $stmt->close();
    exit;
}

if ($acao === "consultar") {
    header('Content-Type: text/plain; charset=UTF-8');
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                c.id_clinica,
                c.nome_clinica, 
                c.email, 
                c.telefone 
            FROM contrato_veterinario cv
            INNER JOIN clinica c ON c.id_clinica = cv.id_clinica
            WHERE cv.id_veterinario = ?
              AND cv.status = 'contratado'
              AND c.status_cadastro = 'autorizada'
              AND (cv.data_fim IS NULL OR cv.data_fim >= CURDATE())
            ORDER BY c.nome_clinica ASC
        ");
        $stmt->bind_param("i", $id_veterinario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $linhas = [];
        while ($row = $resultado->fetch_assoc()) {
            $linhas[] = implode("~", [
                $row['id_clinica'] ?? '',
                $row['nome_clinica'] ?? '',
                $row['email'] ?? '',
                $row['telefone'] ?? ''
            ]);
        }
        $stmt->close();

        echo implode("\n", $linhas);
    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    }
    exit;
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
    </style>
</head>
<body>
    <main class="pagina">
        <div class="titulo-pagina">
            <h1>Entrar na Clínica</h1>
            <p>Selecione uma clínica autorizada para acessar o painel de atendimento veterinário.</p>
        </div>

        <div id="mensagem" class="mensagem" role="status" aria-live="polite"></div>

        <section class="cartao">
            <div class="barra-pesquisa" style="margin-bottom: 20px;">
                <input type="text" id="filtroPesquisa" placeholder="Pesquisar por nome da clínica, e-mail ou telefone..." style="width: 100%; min-height: 45px; padding: 11px 14px; border: 1px solid var(--borda); border-radius: 8px; outline: none;" autocomplete="off">
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
                            <td colspan="4" style="text-align: center; color: var(--texto-suave);">Carregando clínicas autorizadas...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const urlPHP = "entrar_clinica_veterinario.php";
        const tabelaClinicas = document.getElementById("tabelaClinicas");
        const filtroPesquisa = document.getElementById("filtroPesquisa");
        const mensagem = document.getElementById("mensagem");

        let dadosClinicas = [];

        function renderizarTabela(dados) {
            tabelaClinicas.innerHTML = "";

            if (dados.length === 0) {
                tabelaClinicas.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--texto-suave);">Nenhuma clínica disponível no momento.</td></tr>`;
                return;
            }

            dados.forEach(item => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td>${item.nome}</td>
                    <td>${item.email}</td>
                    <td>${item.telefone}</td>
                    <td style="text-align: right;">
                        <button class="botao-entrar" onclick="entrarClinica(${item.id})">Entrar</button>
                    </td>
                `;
                tabelaClinicas.appendChild(tr);
            });
        }

        async function carregarClinicas() {
            try {
                const resposta = await fetch(urlPHP + "?acao=consultar", { credentials: "same-origin" });
                const texto = await resposta.text();

                if (!resposta.ok || texto.startsWith("Erro:")) {
                    throw new Error(texto.replace("Erro: ", "") || "Erro ao carregar clínicas.");
                }

                if (!texto.trim()) {
                    dadosClinicas = [];
                    renderizarTabela(dadosClinicas);
                    return;
                }

                dadosClinicas = texto.split("\n").map(linha => {
                    const partes = linha.split("~");
                    return { id: partes[0], nome: partes[1], email: partes[2], telefone: partes[3] };
                });

                renderizarTabela(dadosClinicas);
            } catch (erro) {
                mensagem.textContent = erro.message;
                mensagem.className = "mensagem erro";
            }
        }

        async function entrarClinica(idClinica) {
            const formData = new FormData();
            formData.append("id_clinica", idClinica);

            try {
                const resposta = await fetch(urlPHP + "?acao=entrar", {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                });
                const resultado = await resposta.text();

                if (!resposta.ok || resultado.startsWith("Erro:")) {
                    throw new Error(resultado.replace("Erro: ", "") || "Não foi possível acessar a clínica.");
                }

                window.location.href = "../../incial/pages/clinica/painel_veterinario/pages/painel_clinica/index.html"; 
            } catch (erro) {
                mensagem.textContent = erro.message;
                mensagem.className = "mensagem erro";
            }
        }

        filtroPesquisa.addEventListener("input", (e) => {
            const termo = e.target.value.toLowerCase().trim();
            const filtrados = dadosClinicas.filter(item => 
                item.nome.toLowerCase().includes(termo) ||
                item.email.toLowerCase().includes(termo) ||
                item.telefone.toLowerCase().includes(termo)
            );
            renderizarTabela(filtrados);
        });

        carregarClinicas();
    </script>
</body>
</html>