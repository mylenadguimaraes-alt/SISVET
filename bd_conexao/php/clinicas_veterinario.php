<?php
session_start();

// Validação de sessão flexível para o veterinário
$id_veterinario = $_SESSION["id"] ?? $_SESSION["id_veterinario"] ?? null;

if (empty($id_veterinario)) {
    exit("Erro: Sua sessão expirou. Entre novamente no sistema.");
}

$id_veterinario = (int) $id_veterinario;
$acao = $_GET["acao"] ?? "";

require_once __DIR__ . "/conexao.php"; // Ajuste o caminho da conexão se necessário

// Endpoint de consulta via fetch (Retorna texto separado por pipe |)
if ($acao === "consultar") {
    header('Content-Type: text/plain; charset=UTF-8');
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                c.nome_clinica, 
                c.email, 
                c.telefone, 
                c.status_cadastro, 
                cv.status AS status_contrato
            FROM contrato_veterinario cv
            INNER JOIN clinica c ON c.id_clinica = cv.id_clinica
            WHERE cv.id_veterinario = ?
            ORDER BY c.nome_clinica ASC
        ");
        $stmt->bind_param("i", $id_veterinario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $linhas = [];
        while ($row = $resultado->fetch_assoc()) {
            // Monta uma string por linha com os campos separados por |
            $linhas[] = implode("~", [
                $row['nome_clinica'] ?? '',
                $row['email'] ?? '',
                $row['telefone'] ?? '',
                $row['status_cadastro'] ?? '',
                $row['status_contrato'] ?? ''
            ]);
        }
        $stmt->close();

        // Separa as clínicas por quebra de linha (\n)
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
    <title>Clínicas Contratadas - SISVET</title>
    <link rel="stylesheet" href="css/style_contrato.css">

</head>

<body>
    <main class="pagina">

        <div class="titulo-pagina">
            <div>
                <h1>Clínicas Cadastradas</h1>
                <p>Consulte as clínicas vinculadas ao seu perfil profissional e seus respectivos status.</p>
            </div>
        </div>

        <div id="mensagem" class="mensagem" role="status" aria-live="polite"></div>

        <section class="cartao">
            <!-- Barra de Pesquisa -->
            <div class="barra-pesquisa">
                <input type="text" id="filtroPesquisa" placeholder="Pesquisar por nome da clínica, e-mail ou telefone..." autocomplete="off">
            </div>

            <div class="tabela-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nome da Clínica</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Status da Clínica</th>
                            <th>Status do Contrato</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaClinicas">
                        <tr>
                            <td colspan="5" style="text-align: center;">Carregando clínicas...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const urlPHP = "clinicas_veterinario.php"; // Substitua pelo nome real deste arquivo se necessário
        const tabelaClinicas = document.getElementById("tabelaClinicas");
        const filtroPesquisa = document.getElementById("filtroPesquisa");
        const mensagem = document.getElementById("mensagem");

        let dadosClinicas = [];

        function formatarStatusClinica(status) {
            const map = {
                'autorizada': 'Autorizada',
                'em_espera': 'Em Espera',
                'suspensa': 'Suspensa'
            };
            return `<span class="badge badge-${status}">${map[status] || status}</span>`;
        }

        function formatarStatusContrato(status) {
            const map = {
                'contratado': 'Contratado',
                'demitido': 'Demitido'
            };
            return `<span class="badge badge-${status}">${map[status] || status}</span>`;
        }

        function renderizarTabela(dados) {
            tabelaClinicas.innerHTML = "";

            if (dados.length === 0) {
                tabelaClinicas.innerHTML = `<tr><td colspan="5" style="text-align: center;">Nenhuma clínica encontrada.</td></tr>`;
                return;
            }

            dados.forEach(item => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td>${item.nome}</td>
                    <td>${item.email}</td>
                    <td>${item.telefone}</td>
                    <td>${formatarStatusClinica(item.statusClinica)}</td>
                    <td>${formatarStatusContrato(item.statusContrato)}</td>
                `;
                tabelaClinicas.appendChild(tr);
            });
        }

        async function carregarClinicas() {
            try {
                const resposta = await fetch(urlPHP + "?acao=consultar", {
                    method: "GET",
                    credentials: "same-origin",
                    cache: "no-store"
                });

                const texto = await resposta.text();

                if (!resposta.ok || texto.startsWith("Erro:")) {
                    throw new Error(texto.replace("Erro: ", "") || "Não foi possível carregar as clínicas.");
                }

                if (!texto.trim()) {
                    dadosClinicas = [];
                    renderizarTabela(dadosClinicas);
                    return;
                }

                const linhas = texto.split("\n");
                dadosClinicas = linhas.map(linha => {
                    const partes = linha.split("~");
                    return {
                        nome: partes[0] || "",
                        email: partes[1] || "",
                        telefone: partes[2] || "",
                        statusClinica: partes[3] || "",
                        statusContrato: partes[4] || ""
                    };
                });

                renderizarTabela(dadosClinicas);
            } catch (erro) {
                mensagem.textContent = erro.message;
                mensagem.className = "mensagem erro";
                tabelaClinicas.innerHTML = `<tr><td colspan="5" style="text-align: center; color: red;">Erro ao carregar dados.</td></tr>`;
            }
        }

        // Filtro interativo da barra de pesquisa
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