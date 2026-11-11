
<?php

session_start();
require_once "conexao.php";
require_once __DIR__ . "/supabase_config.php";

// ========================================
// CONFIGURAÇÃO DO SUPABASE
// ========================================

if (
    !defined("SUPABASE_URL") ||
    !defined("SUPABASE_KEY") ||
    !defined("SUPABASE_BUCKET")
) {
    error_log("Configurações do Supabase não encontradas.");

    echo "<script>
        alert('Erro na configuração do armazenamento de documentos.');
        window.history.back();
    </script>";
    exit;
}

// ========================================
// FUNÇÃO PARA EXIBIR ALERTAS
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
// RECEBER OS DADOS DO FORMULÁRIO
// ========================================

$nome_completo = trim($_POST["nome_completo"] ?? "");
$cpf = preg_replace('/\D/', '', $_POST["cpf"] ?? "");
$email = trim($_POST["email"] ?? "");
$telefone = preg_replace('/\D/', '', $_POST["telefone"] ?? "");
$data_nascimento = $_POST["data_nascimento"] ?? "";

$cep = preg_replace('/\D/', '', $_POST["cep"] ?? "");
$rua = trim($_POST["rua"] ?? "");
$numero = trim($_POST["numero"] ?? "");
$complemento = trim($_POST["complemento"] ?? "");
$bairro = trim($_POST["bairro"] ?? "");
$cidade = trim($_POST["cidade"] ?? "");
$estado = trim($_POST["estado"] ?? "");

$senha = $_POST["senha"] ?? "";

// ========================================
// VALIDAR CAMPOS OBRIGATÓRIOS
// ========================================

if (
    $nome_completo === "" ||
    $cpf === "" ||
    $email === "" ||
    $telefone === "" ||
    $data_nascimento === "" ||
    $senha === ""
) {
    voltarComAlerta("Preencha todos os campos obrigatórios.");
}

// ========================================
// VALIDAR CPF
// ========================================

if (strlen($cpf) !== 11) {
    voltarComAlerta("Informe um CPF com 11 números.");
}

// ========================================
// VERIFICAR CPF DUPLICADO
// ========================================

$stmt = $conn->prepare(
    "SELECT cpf FROM dono_clinica WHERE cpf = ?"
);

if (!$stmt) {
    error_log("Erro ao preparar verificação de CPF: " . $conn->error);
    voltarComAlerta("Não foi possível verificar o CPF.");
}

$stmt->bind_param("s", $cpf);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $stmt->close();
    voltarComAlerta("Este CPF já está cadastrado.");
}

$stmt->close();

// ========================================
// VERIFICAR E-MAIL DUPLICADO
// ========================================

$stmt = $conn->prepare(
    "SELECT email FROM dono_clinica WHERE email = ?"
);

if (!$stmt) {
    error_log("Erro ao preparar verificação de e-mail: " . $conn->error);
    voltarComAlerta("Não foi possível verificar o e-mail.");
}

$stmt->bind_param("s", $email);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $stmt->close();
    voltarComAlerta("Este e-mail já está cadastrado.");
}

$stmt->close();

// ========================================
// VALIDAR E-MAIL, TELEFONE E CEP
// ========================================

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    voltarComAlerta("Informe um e-mail válido.");
}

if (strlen($telefone) !== 10 && strlen($telefone) !== 11) {
    voltarComAlerta("Informe um telefone válido.");
}

if ($cep !== "" && strlen($cep) !== 8) {
    voltarComAlerta("Informe um CEP com 8 números.");
}

// ========================================
// VALIDAR DATA DE NASCIMENTO E IDADE
// ========================================

$data = DateTime::createFromFormat("Y-m-d", $data_nascimento);

if (
    !$data ||
    $data->format("Y-m-d") !== $data_nascimento ||
    $data > new DateTime()
) {
    voltarComAlerta("Informe uma data de nascimento válida.");
}

$hoje = new DateTime();
$idade = $data->diff($hoje)->y;

if ($idade < 18) {
    voltarComAlerta(
        "O responsável pela clínica deve ter pelo menos 18 anos."
    );
}

// ========================================
// VALIDAR SENHA
// ========================================

if (strlen($senha) < 8) {
    voltarComAlerta("A senha deve ter pelo menos 8 caracteres.");
}

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// ========================================
// VALIDAR DOCUMENTO
// ========================================

if (
    !isset($_FILES["documento_identificacao"]) ||
    $_FILES["documento_identificacao"]["error"] !== UPLOAD_ERR_OK
) {
    voltarComAlerta(
        "Não foi possível receber o documento. Selecione o arquivo novamente."
    );
}

$arquivo = $_FILES["documento_identificacao"];

$limite = 5 * 1024 * 1024;

if ($arquivo["size"] <= 0 || $arquivo["size"] > $limite) {
    voltarComAlerta("O documento deve ter no máximo 5 MB.");
}

$extensao = strtolower(
    pathinfo($arquivo["name"], PATHINFO_EXTENSION)
);

$extensoesPermitidas = ["pdf", "jpg", "jpeg", "png"];

if (!in_array($extensao, $extensoesPermitidas, true)) {
    voltarComAlerta("Envie um documento PDF, JPG ou PNG.");
}

// ========================================
// VERIFICAR EXTENSÃO CURL
// ========================================

if (!function_exists("curl_init")) {
    error_log("A extensão cURL do PHP não está habilitada.");

    voltarComAlerta(
        "Não foi possível enviar o documento. Verifique a configuração do PHP."
    );
}

// ========================================
// LER DOCUMENTO
// ========================================

$conteudo = file_get_contents($arquivo["tmp_name"]);

if ($conteudo === false) {
    voltarComAlerta("Não foi possível ler o documento enviado.");
}

// ========================================
// ENVIAR DOCUMENTO PARA O SUPABASE
// ========================================

// Gerar nome único para o arquivo.
$nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensao;

// Guardar em uma pasta privada.
$caminhoArquivo = "documentos-donos/" .
    bin2hex(random_bytes(16)) . "/" .
    $nomeArquivo;

$urlUpload = rtrim(SUPABASE_URL, "/") .
    "/storage/v1/object/" .
    rawurlencode(SUPABASE_BUCKET) . "/" .
    str_replace("%2F", "/", rawurlencode($caminhoArquivo));

$ch = curl_init($urlUpload);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $conteudo,
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

if ($resposta === false || $erroCurl !== "") {
    error_log("Erro de conexão com Supabase: " . $erroCurl);

    voltarComAlerta(
        "Não foi possível cadastrar o documento no armazenamento. Verifique sua conexão e tente novamente."
    );
}

if ($httpCode < 200 || $httpCode >= 300) {
    error_log(
        "Erro Supabase Storage. HTTP: " .
        $httpCode . " Resposta: " . $resposta
    );

    voltarComAlerta(
        "Não foi possível cadastrar o documento no armazenamento. HTTP: " .
        $httpCode . ". Verifique a configuração do Supabase."
    );
}

// ========================================
// CADASTRAR NO BANCO DE DADOS
// ========================================

try {
    $sql = "INSERT INTO dono_clinica (
        nome_completo,
        cpf,
        email,
        telefone,
        data_nascimento,
        documento_identificacao,
        cep,
        rua,
        numero,
        complemento,
        bairro,
        cidade,
        estado,
        senha
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            "Não foi possível preparar o cadastro."
        );
    }

    $stmt->bind_param(
        "ssssssssssssss",
        $nome_completo,
        $cpf,
        $email,
        $telefone,
        $data_nascimento,
        $caminhoArquivo,
        $cep,
        $rua,
        $numero,
        $complemento,
        $bairro,
        $cidade,
        $estado,
        $senha_hash
    );

    $stmt->execute();

    $id_dono = $conn->insert_id;
    $_SESSION["id_dono"] = $id_dono;

    $stmt->close();
    $conn->close();

    echo "<script>
        alert('Cadastro realizado com sucesso!');
        window.location.href = '../../incial/pages/clinica/painel_dono/index.html';
    </script>";
    exit;

} catch (Throwable $e) {
    error_log("Erro no cadastro do dono: " . $e->getMessage());

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    // O upload já ocorreu; o arquivo pode permanecer no Storage.

    $conn->close();

    voltarComAlerta(
        "Não foi possível concluir o cadastro no banco de dados. Verifique os dados e tente novamente."
    );
}

?>
