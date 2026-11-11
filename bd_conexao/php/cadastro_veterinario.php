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

$nome = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$data_nascimento = $_POST["birth_date"] ?? "";
$telefone = preg_replace('/\D/', '', $_POST["telefone"] ?? "");
$cpf = preg_replace('/\D/', '', $_POST["cpf"] ?? "");

$cep = preg_replace('/\D/', '', $_POST["cep"] ?? "");
$rua = trim($_POST["street"] ?? "");
$numero = trim($_POST["number"] ?? "");
$complemento = trim($_POST["complement"] ?? "");
$bairro = trim($_POST["neighborhood"] ?? "");
$cidade = trim($_POST["city"] ?? "");
$estado = strtoupper(trim($_POST["estado"] ?? ""));

$numero_crmv = trim($_POST["crmv_number"] ?? "");
$estado_crmv = strtoupper(trim($_POST["crmv_state"] ?? ""));
$senha = $_POST["password"] ?? "";

// ========================================
// VALIDAR CAMPOS OBRIGATÓRIOS
// ========================================

if (
    $nome === "" ||
    $email === "" ||
    $data_nascimento === "" ||
    $telefone === "" ||
    $cpf === "" ||
    $cep === "" ||
    $rua === "" ||
    $numero === "" ||
    $numero_crmv === "" ||
    $estado_crmv === "" ||
    $senha === ""
) {
    voltarComAlerta("Preencha todos os campos obrigatórios.");
}

// ========================================
// VALIDAR CPF E CRMV
// ========================================

if (strlen($cpf) !== 11) {
    voltarComAlerta("Informe um CPF com 11 números.");
}

if (strlen($estado_crmv) !== 2) {
    voltarComAlerta("Informe um estado do CRMV válido (UF).");
}

// ========================================
// VERIFICAR DUPLICIDADE (CPF, E-MAIL E CRMV)
// ========================================

$stmt = $conn->prepare("SELECT id FROM veterinarios WHERE cpf = ? OR email = ? OR numero_crmv = ?");

if (!$stmt) {
    error_log("Erro ao preparar verificação de duplicidade: " . $conn->error);
    voltarComAlerta("Não foi possível verificar os dados cadastrais.");
}

$stmt->bind_param("sss", $cpf, $email, $numero_crmv);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $stmt->close();
    voltarComAlerta("Este CPF, e-mail ou número de CRMV já está cadastrado.");
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

if (strlen($cep) !== 8) {
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
    voltarComAlerta("O veterinário deve ter pelo menos 18 anos.");
}

// ========================================
// VALIDAR SENHA
// ========================================

if (strlen($senha) < 6) {
    voltarComAlerta("A senha deve ter pelo menos 6 caracteres.");
}

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// ========================================
// VALIDAR DOCUMENTO DE VERIFICAÇÃO (OPCIONAL OU OBRIGATÓRIO)
// ========================================

$caminhoArquivo = null;

if (
    isset($_FILES["verification_document"]) &&
    $_FILES["verification_document"]["error"] !== UPLOAD_ERR_NO_FILE
) {
    if ($_FILES["verification_document"]["error"] !== UPLOAD_ERR_OK) {
        voltarComAlerta("Não foi possível receber o documento. Selecione o arquivo novamente.");
    }

    $arquivo = $_FILES["verification_document"];
    $limite = 5 * 1024 * 1024; // 5 MB

    if ($arquivo["size"] <= 0 || $arquivo["size"] > $limite) {
        voltarComAlerta("O documento deve ter no máximo 5 MB.");
    }

    $extensao = strtolower(pathinfo($arquivo["name"], PATHINFO_EXTENSION));
    $extensoesPermitidas = ["pdf", "jpg", "jpeg", "png"];

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        voltarComAlerta("Envie um documento PDF, JPG ou PNG.");
    }

    if (!function_exists("curl_init")) {
        error_log("A extensão cURL do PHP não está habilitada.");
        voltarComAlerta("Não foi possível enviar o documento. Verifique a configuração do PHP.");
    }

    $conteudo = file_get_contents($arquivo["tmp_name"]);

    if ($conteudo === false) {
        voltarComAlerta("Não foi possível ler o documento enviado.");
    }

    // Gerar nome único e caminho seguro no Supabase Bucket
    $nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensao;
    $caminhoArquivo = "documentos-veterinarios/" . bin2hex(random_bytes(16)) . "/" . $nomeArquivo;

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
        CURLOPT_TIMEOUT => 120
    ]);

    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($resposta === false || $erroCurl !== "") {
        error_log("Erro de conexão com Supabase: " . $erroCurl);
        voltarComAlerta("Não foi possível enviar o documento para o armazenamento. Verifique sua conexão.");
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Erro Supabase Storage. HTTP: " . $httpCode . " Resposta: " . $resposta);
        voltarComAlerta("Não foi possível armazenar o documento. Código HTTP: " . $httpCode . ". Verifique o bucket e suas permissões.");
    }
}

// ========================================
// CADASTRAR NO BANCO DE DADOS
// ========================================

try {
    $sql = "INSERT INTO veterinarios (
        nome,
        email,
        data_nascimento,
        telefone,
        cpf,
        cep,
        endereco,
        numero,
        complemento,
        bairro,
        cidade,
        estado,
        numero_crmv,
        estado_crmv,
        caminho_documento_verificacao,
        senha
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException("Não foi possível preparar o cadastro.");
    }

    $stmt->bind_param(
        "ssssssssssssssss",
        $nome,
        $email,
        $data_nascimento,
        $telefone,
        $cpf,
        $cep,
        $rua,
        $numero,
        $complemento,
        $bairro,
        $cidade,
        $estado,
        $numero_crmv,
        $estado_crmv,
        $caminhoArquivo,
        $senha_hash
    );

    $stmt->execute();

    $id_veterinario = $conn->insert_id;
    $_SESSION["id"] = $id_veterinario; // Ajustado conforme a chave primária 'id' da tabela veterinarios

    $stmt->close();
    $conn->close();

    echo "<script>
        alert('Cadastro realizado com sucesso!');
        window.location.href = '../../incial/pages/clinica/painel_veterinario/index.html';
    </script>";
    exit;

} catch (Throwable $e) {
    error_log("Erro no cadastro do veterinario: " . $e->getMessage());

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    $conn->close();

    voltarComAlerta("Não foi possível concluir o cadastro no banco de dados. Verifique os dados e tente novamente.");
}

?>