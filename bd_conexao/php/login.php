<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . "/conexao.php";

// Garante a codificação UTF-8
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

$base = "/sisvet";

// Função para exibir o alerta em JavaScript, desbloquear o botão e voltar para a página anterior
function retornarAlerta($mensagem) {
    echo "<script>
            alert('" . addslashes($mensagem) . "');
            window.history.back();
            // Garante que caso a página venha do cache do navegador, os botões sejam reativados
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
          </script>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    retornarAlerta("Requisição inválida.");
}

$email = trim($_POST["email"] ?? "");
$senha = $_POST["senha"] ?? "";
$tipo  = $_POST["tipo"] ?? "";

if ($email === "" || $senha === "") {
    retornarAlerta("Por favor, preencha todos os campos.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    retornarAlerta("E-mail com formato inválido.");
}

$usuarios = [
    "dono_clinica" => [
        "tabela" => "dono_clinica",
        "id" => "id_dono",
        "nome" => "nome_completo",
        "sessao" => "id_dono",
        "painel" => "/incial/pages/clinica/painel_dono/index.html"
    ],
    "tutor" => [
        "tabela" => "tutor",
        "id" => "id_tutor",
        "nome" => "nome_completo",
        "sessao" => "id_tutor",
        "painel" => "/incial/pages/tutor/dashboard/dashboard.html"
    ],
    "veterinario" => [
        "tabela" => "veterinarios",
        "id" => "id",
        "nome" => "nome",
        "sessao" => "id_veterinario",
        "painel" => "/incial/pages/clinica/painel_veterinario/index.html"
    ],
    "recepcionista" => [
        "tabela" => "recepcionista",
        "id" => "id_recepcionista",
        "nome" => "nome",
        "sessao" => "id_recepcionista",
        "painel" => "/incial/pages/clinica/painel_recepcionista/index.html"
    ],
    "administrador" => [
        "tabela" => "administrador",
        "id" => "id_administrador",
        "nome" => "nome_completo",
        "sessao" => "id_administrador",
        "painel" => "/incial/pages/sistema/painel_adm/index.html"
    ]
];

if (!isset($usuarios[$tipo])) {
    retornarAlerta("Selecione um tipo de usuário válido.");
}

$config = $usuarios[$tipo];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException("Conexão MySQL indisponível.");
    }

    $sql = "SELECT
                {$config['id']},
                {$config['nome']},
                email,
                senha
            FROM {$config['tabela']}
            WHERE email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException("Falha ao preparar consulta.");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmt->close();

    if (!$usuario || !password_verify($senha, $usuario["senha"])) {
        retornarAlerta("E-mail ou senha incorretos.");
    }

    session_regenerate_id(true);

    // Limpa sessões anteriores
    unset(
        $_SESSION["id_dono"],
        $_SESSION["id_tutor"],
        $_SESSION["id_veterinario"],
        $_SESSION["id_recepcionista"],
        $_SESSION["id_rec"],
        $_SESSION["id"],
        $_SESSION["id_administrador"],
        $_SESSION["nome_usuario"],
        $_SESSION["tipo_usuario"]
    );

    $_SESSION[$config["sessao"]] = $usuario[$config["id"]];
    $_SESSION["nome_usuario"] = $usuario[$config["nome"]];
    $_SESSION["tipo_usuario"] = $tipo;

    if ($tipo === "recepcionista") {
        $_SESSION["id_rec"] = $usuario["id_recepcionista"];
        $_SESSION["id"] = $usuario["id_recepcionista"];
    }

    header("Location: " . $base . $config["painel"]);
    exit;

} catch (Throwable $e) {
    error_log("LOGIN SISVET [" . $tipo . "]: " . $e->getMessage());
    retornarAlerta("Erro interno no servidor. Tente novamente mais tarde.");
}
?>