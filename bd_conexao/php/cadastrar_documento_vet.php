
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/supabase_documento.php";

function responder($sucesso, $mensagem, $codigo = 200) {
    http_response_code($codigo);

    echo json_encode([
        "sucesso" => $sucesso,
        "mensagem" => $mensagem
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// VALIDAR MÉTODO

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(false, "Método não permitido.", 405);
}

// VALIDAR SESSÃO

$idVeterinario = $_SESSION["id_veterinario"]
    ?? $_SESSION["id"]
    ?? $_SESSION["id_vet"]
    ?? null;

$idClinica = $_SESSION["id_clinica_ativa"] ?? null;

if (empty($idVeterinario) || empty($idClinica)) {
    responder(false, "Sessão expirada. Entre novamente na clínica.", 401);
}

$idVeterinario = (int) $idVeterinario;
$idClinica = (int) $idClinica;

// RECEBER DADOS

$idPet = filter_input(INPUT_POST, "id_pet", FILTER_VALIDATE_INT);

$nomeDocumento = trim($_POST["nome_documento"] ?? "");

if (!$idPet || $idPet <= 0) {
    responder(false, "ID do animal inválido ou não informado.");
}

if ($nomeDocumento === "" || mb_strlen($nomeDocumento) > 150) {
    responder(false, "Informe um nome válido para o documento.");
}

// VALIDAR ARQUIVO

if (!isset($_FILES["arquivo"])) {
    responder(false, "Nenhum arquivo foi enviado.");
}

$arquivo = $_FILES["arquivo"];

if ($arquivo["error"] !== UPLOAD_ERR_OK) {
    responder(false, "Erro durante o envio do arquivo.");
}

if ($arquivo["size"] <= 0 || $arquivo["size"] > 10 * 1024 * 1024) {
    responder(false, "O arquivo deve possuir no máximo 10 MB.");
}

if (!is_uploaded_file($arquivo["tmp_name"])) {
    responder(false, "Arquivo enviado inválido.");
}

// ========================================
// VALIDAR FORMATO
// ========================================

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($arquivo["tmp_name"]);

$formatosPermitidos = [
    "application/pdf" => "pdf",
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp"
];

if (!isset($formatosPermitidos[$mime])) {
    responder(false, "Formato não permitido. Envie PDF, JPG, PNG ou WEBP.");
}

$extensao = $formatosPermitidos[$mime];

// VALIDAR AUTORIZAÇÃO DA CLÍNICA

try {

    $sql = "
        SELECT p.id_pet

        FROM pet p

        INNER JOIN autorizacoes a
            ON a.id_pet = p.id_pet
            AND a.id_clinica = ?

        WHERE p.id_pet = ?

          AND a.id_autorizacao = (
              SELECT MAX(a2.id_autorizacao)
              FROM autorizacoes a2
              WHERE a2.id_pet = p.id_pet
                AND a2.id_clinica = ?
          )

          AND a.status = 'confirmado'

        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Erro ao preparar validação da autorização.");
    }

    $stmt->bind_param(
        "iii",
        $idClinica,
        $idPet,
        $idClinica
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $animal = $resultado->fetch_assoc();

    $stmt->close();

    if (!$animal) {
        responder(
            false,
            "Esta clínica não possui autorização confirmada para acessar os documentos deste animal.",
            403
        );
    }

    // VALIDAR SUPABASE

    if (
        !defined("SUPABASE_URL") ||
        !defined("SUPABASE_KEY") ||
        !defined("SUPABASE_BUCKET")
    ) {
        throw new Exception("Configuração do Supabase incompleta.");
    }

    if (!function_exists("curl_init")) {
        throw new Exception("A extensão cURL não está habilitada.");
    }

    // PREPARAR ARQUIVO

    $nomeSeguro = bin2hex(random_bytes(16)) . "." . $extensao;

    $caminho = "pets/" . $idPet . "/documentos/" . $nomeSeguro;

    $bucket = SUPABASE_BUCKET;

    $url = rtrim(SUPABASE_URL, "/")
        . "/storage/v1/object/"
        . rawurlencode($bucket)
        . "/"
        . implode("/", array_map(
            "rawurlencode",
            explode("/", $caminho)
        ));

    $conteudo = file_get_contents($arquivo["tmp_name"]);

    if ($conteudo === false) {
        throw new Exception("Não foi possível ler o arquivo.");
    }

    // ========================================
    // ENVIAR PARA O SUPABASE
    // ========================================

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $conteudo,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_KEY,
            "Authorization: Bearer " . SUPABASE_KEY,
            "Content-Type: " . $mime,
            "x-upsert: false"
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15
    ]);

    $respostaSupabase = curl_exec($ch);

    $erroCurl = curl_error($ch);

    $codigoSupabase = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($respostaSupabase === false) {
        throw new Exception("Erro ao enviar arquivo: " . $erroCurl);
    }

    if ($codigoSupabase < 200 || $codigoSupabase >= 300) {
        error_log("Erro Supabase: " . $respostaSupabase);

        throw new Exception("Não foi possível armazenar o documento no Supabase.");
    }

    // ========================================
    // CADASTRAR NO MYSQL
    // ========================================

    $sql = "
        INSERT INTO documentos (
            id_pet,
            id_clinica,
            id_veterinario,
            nome_documento,
            caminho_documento
        ) VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Erro ao preparar cadastro do documento.");
    }

    $stmt->bind_param(
        "iiiss",
        $idPet,
        $idClinica,
        $idVeterinario,
        $nomeDocumento,
        $caminho
    );

    if (!$stmt->execute()) {
        throw new Exception("Não foi possível cadastrar o documento no banco.");
    }

    $stmt->close();

    responder(
        true,
        "Documento cadastrado com sucesso!"
    );

} catch (Throwable $e) {

    error_log(
        "Erro cadastrar_documento_vet.php: " . $e->getMessage()
    );

    responder(
        false,
        "Ocorreu um erro ao cadastrar o documento. Verifique o banco de dados e o Supabase.",
        500
    );
}
?>
