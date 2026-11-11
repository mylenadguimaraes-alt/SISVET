<?php

session_start();

require_once "conexao.php";
require_once __DIR__ . "/supabase_config.php";

// ========================================
// FUNÇÃO PARA EXIBIR ALERTAS
// ========================================

function voltarComAlerta($mensagem)
{
    echo "<script>
        alert(" . json_encode(
            $mensagem,
            JSON_UNESCAPED_UNICODE
        ) . ");
        window.history.back();
    </script>";
    exit;
}

// ========================================
// VERIFICAR MÉTODO DA REQUISIÇÃO
// ========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    voltarComAlerta("Acesse o cadastro pelo formulário.");
}

// ========================================
// VERIFICAR CONFIGURAÇÃO DO SUPABASE
// ========================================

if (
    !defined("SUPABASE_URL") ||
    !defined("SUPABASE_KEY") ||
    !defined("SUPABASE_BUCKET")
) {
    error_log("Configurações do Supabase não encontradas.");

    voltarComAlerta(
        "Erro na configuração do armazenamento de documentos."
    );
}

// ========================================
// RECEBER OS DADOS DO FORMULÁRIO
// ========================================

$nome = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");

$cpf = preg_replace(
    '/\D/',
    '',
    $_POST["cpf"] ?? ""
);

$data_nascimento = $_POST["data_nascimento"] ?? "";

$telefone = preg_replace(
    '/\D/',
    '',
    $_POST["telefone"] ?? ""
);

$cep = preg_replace(
    '/\D/',
    '',
    $_POST["cep"] ?? ""
);

$rua = trim($_POST["rua"] ?? "");
$numero = trim($_POST["numero"] ?? "");
$complemento = trim($_POST["complemento"] ?? "");
$bairro = trim($_POST["bairro"] ?? "");
$cidade = trim($_POST["cidade"] ?? "");
$estado = strtoupper(trim($_POST["estado"] ?? ""));

$senha = $_POST["senha"] ?? "";

// ========================================
// VALIDAR CAMPOS OBRIGATÓRIOS
// ========================================

if (
    $nome === "" ||
    $email === "" ||
    $cpf === "" ||
    $data_nascimento === "" ||
    $telefone === "" ||
    $cep === "" ||
    $rua === "" ||
    $numero === "" ||
    $senha === ""
) {
    voltarComAlerta(
        "Preencha todos os campos obrigatórios."
    );
}

// ========================================
// VALIDAR CPF
// ========================================

if (strlen($cpf) !== 11) {
    voltarComAlerta(
        "Informe um CPF com 11 números."
    );
}

// ========================================
// VALIDAR E-MAIL
// ========================================

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    voltarComAlerta(
        "Informe um endereço de e-mail válido."
    );
}

// ========================================
// VALIDAR TELEFONE E CEP
// ========================================

if (
    strlen($telefone) !== 10 &&
    strlen($telefone) !== 11
) {
    voltarComAlerta(
        "Informe um telefone válido."
    );
}

if (strlen($cep) !== 8) {
    voltarComAlerta(
        "Informe um CEP com 8 números."
    );
}

// ========================================
// VALIDAR ESTADO
// ========================================

if ($estado !== "" && !preg_match('/^[A-Z]{2}$/', $estado)) {
    voltarComAlerta(
        "Informe a sigla do estado com duas letras."
    );
}

// ========================================
// VALIDAR DATA DE NASCIMENTO
// ========================================

$data = DateTime::createFromFormat(
    "!Y-m-d",
    $data_nascimento
);

if (
    !$data ||
    $data->format("Y-m-d") !== $data_nascimento ||
    $data > new DateTime("today")
) {
    voltarComAlerta(
        "Informe uma data de nascimento válida."
    );
}

// ========================================
// VALIDAR IDADE MÍNIMA
// ========================================

$hoje = new DateTime("today");
$idade = $data->diff($hoje)->y;

if ($idade < 18) {
    voltarComAlerta(
        "A recepcionista deve ter pelo menos 18 anos."
    );
}

// ========================================
// VALIDAR SENHA
// ========================================

if (strlen($senha) < 8) {
    voltarComAlerta(
        "A senha deve ter pelo menos 8 caracteres."
    );
}

$senha_hash = password_hash(
    $senha,
    PASSWORD_DEFAULT
);

if ($senha_hash === false) {
    error_log("Não foi possível gerar o hash da senha.");

    voltarComAlerta(
        "Não foi possível processar a senha."
    );
}

// ========================================
// VERIFICAR CPF E E-MAIL DUPLICADOS
// ========================================

$stmt = $conn->prepare(
    "SELECT cpf, email
     FROM recepcionista
     WHERE cpf = ? OR email = ?
     LIMIT 1"
);

if (!$stmt) {
    error_log(
        "Erro ao preparar consulta: " . $conn->error
    );

    voltarComAlerta(
        "Não foi possível verificar os dados cadastrados."
    );
}

$stmt->bind_param("ss", $cpf, $email);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $registro = $resultado->fetch_assoc();
    $stmt->close();

    if ($registro["cpf"] === $cpf) {
        voltarComAlerta(
            "Este CPF já está cadastrado."
        );
    }

    voltarComAlerta(
        "Este e-mail já está cadastrado."
    );
}

$stmt->close();

// ========================================
// VALIDAR DOCUMENTO DE VERIFICAÇÃO
// ========================================

if (
    !isset($_FILES["documento_verificacao"]) ||
    $_FILES["documento_verificacao"]["error"] !== UPLOAD_ERR_OK
) {
    voltarComAlerta(
        "Selecione o documento de verificação novamente."
    );
}

$arquivo = $_FILES["documento_verificacao"];

// Limite de 5 MB.
$limite = 5 * 1024 * 1024;

if (
    $arquivo["size"] <= 0 ||
    $arquivo["size"] > $limite
) {
    voltarComAlerta(
        "O documento deve ter no máximo 5 MB."
    );
}

// ========================================
// VALIDAR EXTENSÃO DO ARQUIVO
// ========================================

$extensao = strtolower(
    pathinfo(
        $arquivo["name"],
        PATHINFO_EXTENSION
    )
);

$extensoesPermitidas = [
    "pdf",
    "jpg",
    "jpeg",
    "png"
];

if (!in_array($extensao, $extensoesPermitidas, true)) {
    voltarComAlerta(
        "Envie um documento PDF, JPG ou PNG."
    );
}

// ========================================
// VERIFICAR EXTENSÃO CURL
// ========================================

if (!function_exists("curl_init")) {
    error_log(
        "A extensão cURL do PHP não está habilitada."
    );

    voltarComAlerta(
        "O envio do documento não está disponível. Verifique a configuração do PHP."
    );
}

// ========================================
// LER O DOCUMENTO
// ========================================

$conteudo = file_get_contents(
    $arquivo["tmp_name"]
);

if ($conteudo === false) {
    voltarComAlerta(
        "Não foi possível ler o documento enviado."
    );
}

// ========================================
// GERAR CAMINHO DO DOCUMENTO NO SUPABASE
// ========================================

// Nome aleatório para evitar colisões e expor dados pessoais.
$nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensao;

$caminhoArquivo = "recepcionistas/" .
    bin2hex(random_bytes(16)) . "/" .
    $nomeArquivo;

// Montar URL do Supabase Storage.
$urlUpload = rtrim(SUPABASE_URL, "/") .
    "/storage/v1/object/" .
    rawurlencode(SUPABASE_BUCKET) . "/" .
    str_replace(
        "%2F",
        "/",
        rawurlencode($caminhoArquivo)
    );

// ========================================
// ENVIAR DOCUMENTO PARA O SUPABASE
// ========================================

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
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 60
]);

$resposta = curl_exec($ch);
$erroCurl = curl_error($ch);
$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);

// ========================================
// VERIFICAR RESPOSTA DO SUPABASE
// ========================================

if ($resposta === false || $erroCurl !== "") {
    error_log(
        "Erro de conexão com Supabase: " . $erroCurl
    );

    voltarComAlerta(
        "Não foi possível enviar o documento ao armazenamento. Tente novamente."
    );
}

if ($httpCode < 200 || $httpCode >= 300) {
    error_log(
        "Erro Supabase Storage. HTTP: " .
        $httpCode .
        " Resposta: " .
        $resposta
    );

    voltarComAlerta(
        "Não foi possível armazenar o documento. Código HTTP: " .
        $httpCode .
        ". Verifique o bucket e suas permissões."
    );
}

// ========================================
// CADASTRAR RECEPCIONISTA NO MYSQL
// ========================================

$stmt = null;

try {

    $sql = "INSERT INTO recepcionista (
        nome,
        email,
        cpf,
        data_nascimento,
        telefone,
        cep,
        rua,
        numero,
        complemento,
        bairro,
        cidade,
        estado,
        caminho_documento,
        senha
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            "Erro ao preparar cadastro: " . $conn->error
        );
    }

    $stmt->bind_param(
        "ssssssssssssss",
        $nome,
        $email,
        $cpf,
        $data_nascimento,
        $telefone,
        $cep,
        $rua,
        $numero,
        $complemento,
        $bairro,
        $cidade,
        $estado,
        $caminhoArquivo,
        $senha_hash
    );

    $stmt->execute();

    // Recuperar o ID criado.
    $id_recepcionista = $conn->insert_id;

    // Criar sessão da recepcionista.
    $_SESSION["id_recepcionista"] = $id_recepcionista;

    $stmt->close();
    $conn->close();

    // ========================================
    // REDIRECIONAR APÓS CADASTRO BEM-SUCEDIDO
    // ========================================

    echo "<script>
        alert('Cadastro da recepcionista realizado com sucesso!');
        window.location.href =
            '../../incial/pages/clinica/painel_recepcionista/index.html';
    </script>";

    exit;

} catch (Throwable $e) {

    error_log(
        "Erro no cadastro da recepcionista: " .
        $e->getMessage()
    );

    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    $conn->close();

    // O arquivo já pode ter sido enviado ao Storage.
    // Em caso de erro no MySQL, ele poderá ficar sem referência no banco.

    voltarComAlerta(
        "O documento foi enviado, mas não foi possível concluir o cadastro no banco de dados. Verifique os dados e tente novamente."
    );
}

?>
