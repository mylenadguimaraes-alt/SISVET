
<?php

session_start();

require_once "conexao.php";
require_once __DIR__ . "/supabase_config.php";

// ========================================
// 1. VERIFICAR A CONFIGURAÇÃO DO SUPABASE
// ========================================

if (
    !defined("SUPABASE_URL") ||
    !defined("SUPABASE_KEY") ||
    !defined("SUPABASE_BUCKET")
) {
    error_log("Configurações do Supabase não encontradas.");

    exit("<script>
        alert('Erro na configuração do armazenamento de documentos.');
        window.history.back();
    </script>");
}

// ========================================
// 2. FUNÇÃO PARA EXIBIR ALERTAS
// ========================================

function voltarComAlerta($mensagem)
{
    echo "<script>
        alert(" . json_encode($mensagem) . ");
        window.history.back();
    </script>";
    exit;
}

// ========================================
// 3. VERIFICAR SE O DONO ESTÁ LOGADO
// ========================================

if (empty($_SESSION["id_dono"])) {
    voltarComAlerta(
        "Faça login como dono para cadastrar uma clínica."
    );
}

$id_dono = (int) $_SESSION["id_dono"];

// ========================================
// 4. VERIFICAR O MÉTODO DO FORMULÁRIO
// ========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cadastro_clinica.html");
    exit;
}

// ========================================
// 5. RECEBER OS DADOS
// ========================================

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

$cnpj = preg_replace('/\D/', '', $_POST["cnpj"] ?? "");
$telefoneNumeros = preg_replace('/\D/', '', $telefone);
$cepNumeros = preg_replace('/\D/', '', $cep);

// ========================================
// 6. VALIDAR CAMPOS OBRIGATÓRIOS
// ========================================

if (
    $razao_social === "" ||
    $nome_clinica === "" ||
    $email === "" ||
    $telefone === "" ||
    $cep === "" ||
    $endereco === "" ||
    $numero === "" ||
    $estado === "" ||
    $cnpj === ""
) {
    voltarComAlerta(
        "Preencha todos os campos obrigatórios."
    );
}

// ========================================
// 7. VALIDAR E-MAIL
// ========================================

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    voltarComAlerta("Informe um e-mail válido.");
}

// ========================================
// 8. VALIDAR CNPJ
// ========================================

if (strlen($cnpj) !== 14) {
    voltarComAlerta("O CNPJ deve conter 14 números.");
}

// ========================================
// 9. VALIDAR TELEFONE
// ========================================

if (
    strlen($telefoneNumeros) !== 10 &&
    strlen($telefoneNumeros) !== 11
) {
    voltarComAlerta(
        "Informe um telefone completo com DDD."
    );
}

// ========================================
// 10. VALIDAR CEP
// ========================================

if (strlen($cepNumeros) !== 8) {
    voltarComAlerta(
        "O CEP deve conter 8 números."
    );
}

// ========================================
// 11. VERIFICAR CNPJ DUPLICADO
// ========================================

$stmt = $conn->prepare(
    "SELECT id_clinica
     FROM clinica
     WHERE cnpj = ?
     LIMIT 1"
);

if (!$stmt) {
    error_log("Erro ao preparar consulta de CNPJ: " . $conn->error);
    voltarComAlerta("Não foi possível verificar o CNPJ.");
}

$stmt->bind_param("s", $cnpj);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $stmt->close();

    voltarComAlerta(
        "Este CNPJ já está cadastrado no SISVET."
    );
}

$stmt->close();

// ========================================
// 12. VERIFICAR E-MAIL DUPLICADO
// ========================================

$stmt = $conn->prepare(
    "SELECT id_clinica
     FROM clinica
     WHERE email = ?
     LIMIT 1"
);

if (!$stmt) {
    error_log("Erro ao preparar consulta de e-mail: " . $conn->error);
    voltarComAlerta("Não foi possível verificar o e-mail.");
}

$stmt->bind_param("s", $email);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $stmt->close();

    voltarComAlerta(
        "Este e-mail já está cadastrado para uma clínica."
    );
}

$stmt->close();

// ========================================
// 13. VERIFICAR O DOCUMENTO DE LICENÇA
// ========================================

if (
    !isset($_FILES["documento_licenca"]) ||
    $_FILES["documento_licenca"]["error"] !== UPLOAD_ERR_OK
) {
    voltarComAlerta(
        "Envie o documento de licença da clínica."
    );
}

$arquivo = $_FILES["documento_licenca"];

$limite = 5 * 1024 * 1024;

if ($arquivo["size"] <= 0 || $arquivo["size"] > $limite) {
    voltarComAlerta(
        "O documento deve ter no máximo 5 MB."
    );
}

$extensao = strtolower(
    pathinfo($arquivo["name"], PATHINFO_EXTENSION)
);

$extensoesPermitidas = ["pdf", "jpg", "jpeg", "png"];

if (!in_array($extensao, $extensoesPermitidas, true)) {
    voltarComAlerta(
        "Envie um arquivo PDF, JPG ou PNG."
    );
}

// ========================================
// 14. VERIFICAR A EXTENSÃO CURL
// ========================================

if (!function_exists("curl_init")) {
    error_log("A extensão cURL do PHP não está habilitada.");

    voltarComAlerta(
        "Não foi possível enviar o documento. Verifique a configuração do PHP."
    );
}

// ========================================
// 15. LER O DOCUMENTO
// ========================================

$documento = file_get_contents($arquivo["tmp_name"]);

if ($documento === false) {
    voltarComAlerta(
        "Não foi possível ler o documento enviado."
    );
}

// ========================================
// 16. ENVIAR DOCUMENTO AO SUPABASE
// ========================================

// Gerar um nome aleatório para evitar colisões.
$nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensao;

// Organizar os documentos em uma pasta.
$caminhoArquivo = "documentos-clinicas/" .
    bin2hex(random_bytes(16)) . "/" .
    $nomeArquivo;

// Montar a URL do Supabase Storage.
$urlUpload = rtrim(SUPABASE_URL, "/") .
    "/storage/v1/object/" .
    rawurlencode(SUPABASE_BUCKET) . "/" .
    str_replace(
        "%2F",
        "/",
        rawurlencode($caminhoArquivo)
    );

$ch = curl_init($urlUpload);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $documento,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_KEY,
        "Authorization: Bearer " . SUPABASE_KEY,
        "Content-Type: application/octet-stream",
        "x-upsert: false"
    ],
    CURLOPT_TIMEOUT => 60
]);

$resposta = curl_exec($ch);
$erroCurl = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Verificar erro de conexão.
if ($resposta === false || $erroCurl !== "") {
    error_log(
        "Erro de conexão com Supabase: " . $erroCurl
    );

    voltarComAlerta(
        "Não foi possível enviar o documento ao armazenamento. Verifique sua conexão e tente novamente."
    );
}

// Verificar resposta HTTP do Supabase.
if ($httpCode < 200 || $httpCode >= 300) {
    error_log(
        "Erro Supabase Storage. HTTP: " .
        $httpCode . " Resposta: " . $resposta
    );

    voltarComAlerta(
        "Não foi possível armazenar o documento. HTTP: " .
        $httpCode . ". Verifique o bucket e as políticas de acesso do Supabase."
    );
}

// ========================================
// 17. DEFINIR O ESTADO INICIAL DA CLÍNICA
// ========================================

$autorizado = 0;
$suspenso = 0;
$status_cadastro = "em_espera";

// ========================================
// 18. CADASTRAR A CLÍNICA NO MYSQL
// ========================================

try {

    $sql = "INSERT INTO clinica (
        id_dono,
        razao_social,
        nome_clinica,
        email,
        telefone,
        cep,
        endereco,
        numero,
        complemento,
        bairro,
        cidade,
        estado,
        cnpj,
        documento_licenca,
        autorizado,
        suspenso,
        status_cadastro
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            "Erro ao preparar cadastro: " . $conn->error
        );
    }

    // documento_licenca recebe apenas o caminho no Supabase.
    $stmt->bind_param(
        "isssssssssssssiis",
        $id_dono,
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
        $cnpj,
        $caminhoArquivo,
        $autorizado,
        $suspenso,
        $status_cadastro
    );
    $stmt->execute();

    $stmt->close();
    $conn->close();

    echo "<script>
        alert('Clínica cadastrada com sucesso! Ela ainda precisa ser autorizada.');
        window.history.back();
    </script>";

    exit;

} catch (Throwable $e) {

    error_log(
        "Erro no cadastro de clínica: " . $e->getMessage()
    );

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    $conn->close();

    voltarComAlerta(
        "Não foi possível cadastrar a clínica. Verifique os dados e tente novamente."
    );
}

?>
