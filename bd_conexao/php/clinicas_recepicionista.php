
<?php
session_start();

require_once __DIR__ . "/conexao.php";

// Identifica a recepcionista pela sessão
$id_recepcionista = $_SESSION["id_recepcionista"]
    ?? $_SESSION["id_rec"]
    ?? $_SESSION["id"]
    ?? null;

// Retorna as clínicas em formato JSON
if (isset($_GET["acao"]) && $_GET["acao"] === "consultar") {

    header("Content-Type: application/json; charset=UTF-8");

    if (!$id_recepcionista) {
        http_response_code(401);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Não foi possível identificar a recepcionista."
        ]);

        exit;
    }

    $sql = "
        SELECT
            c.id_clinica,
            c.nome_clinica,
            c.email,
            c.telefone,
            c.status_cadastro,
            cr.status AS status_contrato
        FROM contrato_recepcionista cr
        INNER JOIN clinica c
            ON c.id_clinica = cr.id_clinica
        WHERE cr.id_recepcionista = ?
        ORDER BY c.nome_clinica ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Erro ao preparar a consulta."
        ]);

        exit;
    }

    $stmt->bind_param("i", $id_recepcionista);

    if (!$stmt->execute()) {
        http_response_code(500);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Erro ao consultar as clínicas."
        ]);

        $stmt->close();
        exit;
    }

    $resultado = $stmt->get_result();

    $clinicas = [];

    while ($linha = $resultado->fetch_assoc()) {
        $clinicas[] = $linha;
    }

    echo json_encode([
        "sucesso" => true,
        "clinicas" => $clinicas
    ], JSON_UNESCAPED_UNICODE);

    $stmt->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Minhas Clínicas</title>

    <link rel="stylesheet" href="css/style_contrato.css">
</head>

<body>

    <main class="pagina">

        <header class="titulo-pagina">
            <h1>Minhas Clínicas</h1>

            <p>
                Consulte as clínicas em que você possui ou já possuiu contrato.
            </p>
        </header>

        <section class="cartao">

            <h2>Clínicas vinculadas</h2>

            <p class="descricao">
                Aqui você pode visualizar os dados e a situação dos seus contratos.
            </p>

            <div id="mensagem" class="mensagem"></div>

            <div class="barra-pesquisa">
                <input
                    type="text"
                    id="pesquisaClinica"
                    placeholder="Pesquisar clínica pelo nome..."
                >
            </div>

            <div class="tabela-container">

                <table>

                    <thead>
                        <tr>
                            <th>Clínica</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Status da clínica</th>
                            <th>Status do contrato</th>
                        </tr>
                    </thead>

                    <tbody id="listaClinicas">
                        <tr>
                            <td colspan="5">Carregando clínicas...</td>
                        </tr>
                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <script>
        const listaClinicas = document.getElementById("listaClinicas");
        const mensagem = document.getElementById("mensagem");
        const pesquisa = document.getElementById("pesquisaClinica");

        let clinicasCarregadas = [];

        // Converte os status do banco para as classes do CSS
        function obterClasseStatus(status, tipo) {

            status = String(status || "").toLowerCase();

            if (tipo === "clinica") {

                const classes = {
                    autorizada: "badge-autorizada",
                    em_espera: "badge-em_espera",
                    suspensa: "badge-suspensa"
                };

                return classes[status] || "";
            }

            if (tipo === "contrato") {

                const classes = {
                    contratada: "badge-contratado",
                    demitida: "badge-demitido"
                };

                return classes[status] || "";
            }

            return "";
        }

        // Converte os status para nomes que serão exibidos
        function formatarStatus(status) {

            const nomes = {
                autorizada: "Autorizada",
                em_espera: "Em espera",
                suspensa: "Suspensa",
                contratada: "Contratada",
                demitida: "Demitida"
            };

            return nomes[status] || status || "Não informado";
        }

        // Cria o elemento visual do status
        function criarBadge(status, tipo) {

            const badge = document.createElement("span");

            badge.className = "badge " + obterClasseStatus(status, tipo);
            badge.textContent = formatarStatus(status);

            return badge;
        }

        // Cria uma célula de texto
        function criarCelula(valor) {

            const td = document.createElement("td");

            td.textContent = valor || "Não informado";

            return td;
        }

        // Exibe as clínicas na tabela
        function exibirClinicas(lista) {

            listaClinicas.innerHTML = "";

            if (lista.length === 0) {

                const linha = document.createElement("tr");
                const celula = document.createElement("td");

                celula.colSpan = 5;
                celula.textContent = "Nenhuma clínica encontrada.";

                linha.appendChild(celula);
                listaClinicas.appendChild(linha);

                return;
            }

            lista.forEach(clinica => {

                const linha = document.createElement("tr");

                linha.appendChild(
                    criarCelula(clinica.nome_clinica)
                );

                linha.appendChild(
                    criarCelula(clinica.email)
                );

                linha.appendChild(
                    criarCelula(clinica.telefone)
                );

                const statusClinica = document.createElement("td");

                statusClinica.appendChild(
                    criarBadge(clinica.status_cadastro, "clinica")
                );

                linha.appendChild(statusClinica);

                const statusContrato = document.createElement("td");

                statusContrato.appendChild(
                    criarBadge(clinica.status_contrato, "contrato")
                );

                linha.appendChild(statusContrato);

                listaClinicas.appendChild(linha);
            });
        }

        // Consulta as clínicas no PHP
        async function carregarClinicas() {

            try {

                const resposta = await fetch(
                    "clinicas_recepicionista.php?acao=consultar"
                );

                const dados = await resposta.json();

                if (!resposta.ok || !dados.sucesso) {

                    mensagem.textContent =
                        dados.mensagem || "Não foi possível carregar as clínicas.";

                    mensagem.className = "mensagem erro";

                    listaClinicas.innerHTML = "";
                    return;
                }

                clinicasCarregadas = dados.clinicas;

                exibirClinicas(clinicasCarregadas);

            } catch (erro) {

                console.error("Erro:", erro);

                mensagem.textContent =
                    "Ocorreu um erro ao carregar as clínicas.";

                mensagem.className = "mensagem erro";
            }
        }

        // Pesquisa pelo nome da clínica
        pesquisa.addEventListener("input", function () {

            const termo = pesquisa.value.toLowerCase().trim();

            const resultado = clinicasCarregadas.filter(clinica => {

                return String(clinica.nome_clinica || "")
                    .toLowerCase()
                    .includes(termo);

            });

            exibirClinicas(resultado);
        });

        carregarClinicas();
    </script>

</body>
</html>
