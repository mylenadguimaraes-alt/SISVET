
<?php

session_start();

require_once "conexao.php";

$caminhoDetalhes = '/sisvet/bd_conexao/php/detalhes_clinica.php';


// ========================================
// 1. IDENTIFICAR O DONO LOGADO
// ========================================

$id_dono = (int) ($_SESSION["id_dono"] ?? 0);

if ($id_dono <= 0) {
    http_response_code(401);
    exit("Não foi possível identificar o dono da conta. Verifique se você está logado.");
}

// ========================================
// 2. RECEBER A SITUAÇÃO SOLICITADA
// ========================================

$situacao = $_GET["situacao"] ?? "autorizadas";

// ========================================
// 3. BUSCAR AS CLÍNICAS DO DONO
// ========================================

$sql = "SELECT *
        FROM clinica
        WHERE id_dono = ?
        ORDER BY nome_clinica ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("Erro ao preparar a consulta das clínicas.");
}

$stmt->bind_param("i", $id_dono);
$stmt->execute();

$resultado = $stmt->get_result();

// ========================================
// 4. SEPARAR AS CLÍNICAS POR SITUAÇÃO
// ========================================

$clinicas_autorizadas = [];
$clinicas_em_espera = [];
$clinicas_suspensas = [];

while ($clinica = $resultado->fetch_assoc()) {

    if ((int) $clinica["suspenso"] === 1) {

        // Clínicas suspensas
        $clinicas_suspensas[] = $clinica;

    } elseif ((int) $clinica["autorizado"] === 1) {

        // Clínicas autorizadas
        $clinicas_autorizadas[] = $clinica;

    } else {

        // Clínicas aguardando autorização
        $clinicas_em_espera[] = $clinica;
    }
}

$stmt->close();

// ========================================
// 5. ESCOLHER A LISTA SOLICITADA
// ========================================

switch ($situacao) {

    case "autorizadas":
        $lista = $clinicas_autorizadas;
        $texto_status = "Autorizada";
        $mensagem_vazia = "Nenhuma clínica autorizada encontrada.";
        break;

    case "espera":
        $lista = $clinicas_em_espera;
        $texto_status = "Aguardando autorização";
        $mensagem_vazia = "Nenhuma clínica aguardando autorização.";
        break;

    case "suspensas":
        $lista = $clinicas_suspensas;
        $texto_status = "Suspensa";
        $mensagem_vazia = "Nenhuma clínica suspensa encontrada.";
        break;

    default:
        http_response_code(400);
        $conn->close();
        exit("Situação inválida.");
}

// ========================================
// 6. EXIBIR O RESULTADO EM HTML
// ========================================

if (empty($lista)) {

    echo '<div class="mensagem-vazia">';
    echo '<h2>' . htmlspecialchars(
        $mensagem_vazia,
        ENT_QUOTES,
        "UTF-8"
    ) . '</h2>';
    echo '</div>';

} else {

    foreach ($lista as $clinica) {

        $id_clinica = (int) $clinica["id_clinica"];

        $nome = htmlspecialchars(
            $clinica["nome_clinica"] ?? "",
            ENT_QUOTES,
            "UTF-8"
        );

        $email = htmlspecialchars(
            $clinica["email"] ?? "",
            ENT_QUOTES,
            "UTF-8"
        );

        $telefone = htmlspecialchars(
            $clinica["telefone"] ?? "",
            ENT_QUOTES,
            "UTF-8"
        );

        $status = htmlspecialchars(
            $texto_status,
            ENT_QUOTES,
            "UTF-8"
        );

        echo '<div class="bloco-clinica">';

            echo '<div class="clinica-topo">';

                echo '<div class="icone-clinica">';
                    echo '<span>🏥</span>';
                echo '</div>';

                echo '<div class="nome-area">';

                    echo '<h2 class="nome-clinica">';
                        echo $nome;
                    echo '</h2>';

                    echo '<span class="status">';
                        echo $status;
                    echo '</span>';

                echo '</div>';

            echo '</div>';

            echo '<div class="dados-clinica">';

                echo '<div class="dado">';
                    echo '<span class="rotulo">E-mail</span>';
                    echo '<span>' . $email . '</span>';
                echo '</div>';

                echo '<div class="dado">';
                    echo '<span class="rotulo">Telefone</span>';
                    echo '<span>' . $telefone . '</span>';
                echo '</div>';

            echo '</div>';

            echo '<div class="acoes-clinica">';

                echo '<a class="btn-detalhes" href="' . $caminhoDetalhes
                . '?id_clinica=' . urlencode($id_clinica)
                . '&situacao=' . urlencode($situacao)
                . '">';

                    echo 'Ver detalhes';

                echo '</a>';

            echo '</div>';

        echo '</div>';
    }
}

$conn->close();

?>
