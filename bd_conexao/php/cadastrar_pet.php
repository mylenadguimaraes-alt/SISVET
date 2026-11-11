<?php

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

require_once "conexao.php";
require_once __DIR__ . "/supabase_config.php"; // Inclui as configurações do Supabase (SUPABASE_URL, SUPABASE_KEY, SUPABASE_BUCKET)

// ========================================
// FUNÇÃO PARA REDIRECIONAR COM ERRO
// ========================================

function mostrarMensagem($titulo, $mensagem)
{
    $destino = "/sisvet/bd_conexao/php/pets_tutor.php";

    $parametros = http_build_query([
        "erro" => $mensagem,
        "titulo" => $titulo
    ]);

    header("Location: " . $destino . "?" . $parametros);
    exit;
}

// ========================================
// VERIFICAR LOGIN
// ========================================

if (!isset($_SESSION["id_tutor"])) {
    mostrarMensagem(
        "Acesso não autorizado",
        "Faça login novamente para cadastrar um pet."
    );
}

$id_tutor = (int) $_SESSION["id_tutor"];

// ========================================
// VERIFICAR MÉTODO DO FORMULÁRIO
// ========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    mostrarMensagem(
        "Solicitação inválida",
        "Utilize o formulário para cadastrar um pet."
    );
}

// ========================================
// RECEBER OS DADOS
// ========================================

$nome = trim($_POST["nome"] ?? "");
$especie = trim($_POST["especie"] ?? "");
$raca = trim($_POST["raca"] ?? "");
$sexo = trim($_POST["sexo"] ?? "Não informado");
$data_nascimento = trim($_POST["data_nascimento"] ?? "");
$peso = trim($_POST["peso"] ?? "");
$cor = trim($_POST["cor"] ?? "");
$porte = trim($_POST["porte"] ?? "");
$microchip = trim($_POST["microchip"] ?? "");
$castrado = isset($_POST["castrado"]) ? 1 : 0;
$observacoes = trim($_POST["observacoes"] ?? "");

// ========================================
// VALIDAR CAMPOS OBRIGATÓRIOS
// ========================================

if ($nome === "" || $especie === "") {
    mostrarMensagem(
        "Campos obrigatórios",
        "Preencha o nome e a espécie do pet."
    );
}

// ========================================
// VALIDAR TAMANHO DOS CAMPOS
// ========================================

if (
    mb_strlen($nome) > 100 ||
    mb_strlen($especie) > 50 ||
    mb_strlen($raca) > 100 ||
    mb_strlen($cor) > 50 ||
    mb_strlen($porte) > 30 ||
    mb_strlen($microchip) > 50
) {
    mostrarMensagem(
        "Dados inválidos",
        "Um ou mais campos ultrapassaram o limite de caracteres permitido."
    );
}

// ========================================
// VALIDAR ESPÉCIE
// ========================================

$especiesPermitidas = [
    "Cachorro",
    "Gato",
    "Ave",
    "Coelho",
    "Roedor",
    "Réptil",
    "Outro"
];

if (!in_array($especie, $especiesPermitidas, true)) {
    mostrarMensagem(
        "Espécie inválida",
        "Selecione uma espécie válida."
    );
}

// ========================================
// VALIDAR SEXO
// ========================================

$sexosPermitidos = [
    "Macho",
    "Fêmea",
    "Não informado"
];

if (!in_array($sexo, $sexosPermitidos, true)) {
    mostrarMensagem(
        "Sexo inválido",
        "Selecione uma opção válida para o sexo do pet."
    );
}

// ========================================
// TRATAR CAMPOS OPCIONAIS
// ========================================

$raca = $raca !== "" ? $raca : null;
$data_nascimento = $data_nascimento !== "" ? $data_nascimento : null;
$peso = $peso !== "" ? $peso : null;
$cor = $cor !== "" ? $cor : null;
$porte = $porte !== "" ? $porte : null;
$microchip = $microchip !== "" ? $microchip : null;
$observacoes = $observacoes !== "" ? $observacoes : null;

// ========================================
// VALIDAR DATA DE NASCIMENTO
// ========================================

if ($data_nascimento !== null) {
    $data = DateTime::createFromFormat("!Y-m-d", $data_nascimento);
    $errosData = DateTime::getLastErrors();

    if (
        !$data ||
        ($errosData !== false &&
            (
                $errosData["warning_count"] > 0 ||
                $errosData["error_count"] > 0
            )
        ) ||
        $data->format("Y-m-d") !== $data_nascimento
    ) {
        mostrarMensagem(
            "Data inválida",
            "Informe uma data de nascimento válida."
        );
    }

    if ($data > new DateTime("today")) {
        mostrarMensagem(
            "Data inválida",
            "A data de nascimento não pode ser futura."
        );
    }
}

// ========================================
// VALIDAR PESO
// ========================================

if ($peso !== null) {
    $peso = str_replace(",", ".", $peso);

    if (
        !is_numeric($peso) ||
        (float) $peso <= 0 ||
        (float) $peso > 9999.99
    ) {
        mostrarMensagem(
            "Peso inválido",
            "Informe um peso válido, maior que zero."
        );
    }

    $peso = (float) $peso;
}

// ========================================
// VERIFICAR MICROCHIP DUPLICADO
// ========================================

if ($microchip !== null) {
    $sqlMicrochip = "
        SELECT id_pet
        FROM pet
        WHERE microchip = ?
        LIMIT 1
    ";

    $stmtMicrochip = $conn->prepare($sqlMicrochip);

    if (!$stmtMicrochip) {
        error_log("Erro ao preparar consulta do microchip: " . $conn->error);

        mostrarMensagem(
            "Erro no sistema",
            "Não foi possível verificar o microchip. Tente novamente."
        );
    }

    $stmtMicrochip->bind_param("s", $microchip);

    if (!$stmtMicrochip->execute()) {
        error_log("Erro ao verificar microchip: " . $stmtMicrochip->error);
        $stmtMicrochip->close();

        mostrarMensagem(
            "Erro no sistema",
            "Não foi possível verificar o microchip. Tente novamente."
        );
    }

    $stmtMicrochip->store_result();
    $microchipExiste = $stmtMicrochip->num_rows > 0;
    $stmtMicrochip->close();

    if ($microchipExiste) {
        mostrarMensagem(
            "Microchip já cadastrado",
            "Já existe um pet cadastrado com esse número de microchip."
        );
    }
}

// ========================================
// PROCESSAR E ENVIAR FOTO PARA O SUPABASE
// ========================================

$caminhoFotoBanco = null;

if (
    isset($_FILES["foto"]) &&
    $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE
) {
    if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
        mostrarMensagem(
            "Erro no envio da foto",
            "Não foi possível enviar a foto. Tente novamente."
        );
    }

    $tamanhoMaximo = 5 * 1024 * 1024;

    if ($_FILES["foto"]["size"] > $tamanhoMaximo) {
        mostrarMensagem(
            "Foto muito grande",
            "A foto deve ter no máximo 5 MB."
        );
    }

    $arquivoTemporario = $_FILES["foto"]["tmp_name"];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivoTemporario);

    $tiposPermitidos = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (
        !isset($tiposPermitidos[$mime]) ||
        getimagesize($arquivoTemporario) === false
    ) {
        mostrarMensagem(
            "Formato inválido",
            "Envie uma imagem JPG, PNG ou WEBP."
        );
    }

    if (!function_exists("curl_init")) {
        error_log("A extensão cURL do PHP não está habilitada.");
        mostrarMensagem(
            "Erro no sistema",
            "Não foi possível enviar a foto. Verifique a configuração do PHP."
        );
    }

    $conteudoFoto = file_get_contents($arquivoTemporario);

    if ($conteudoFoto === false) {
        mostrarMensagem(
            "Erro ao ler foto",
            "Não foi possível ler o arquivo de foto enviado."
        );
    }

    // Configurações do Supabase
    if (
        !defined("SUPABASE_URL") ||
        !defined("SUPABASE_KEY") ||
        !defined("SUPABASE_BUCKET")
    ) {
        mostrarMensagem(
            "Erro de configuração",
            "Configurações do Supabase não encontradas."
        );
    }

    $extensao = $tiposPermitidos[$mime];
    $nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensao;
    
    // Caminho estruturado no Bucket (ex: fotos-pets/id_tutor/nome_unico.jpg)
    $caminhoArquivoBucket = "fotos-pets/" . $id_tutor . "/" . $nomeArquivo;

    $urlUpload = rtrim(SUPABASE_URL, "/") .
        "/storage/v1/object/" .
        rawurlencode(SUPABASE_BUCKET) . "/" .
        str_replace("%2F", "/", rawurlencode($caminhoArquivoBucket));

    $ch = curl_init($urlUpload);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $conteudoFoto,
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
        error_log("Erro de conexão com Supabase (Foto Pet): " . $erroCurl);
        mostrarMensagem(
            "Erro de conexão",
            "Não foi possível enviar a foto para o armazenamento. Tente novamente."
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Erro Supabase Storage (Foto Pet). HTTP: " . $httpCode . " Resposta: " . $resposta);
        mostrarMensagem(
            "Erro no armazenamento",
            "Não foi possível salvar a foto no servidor externo. HTTP: " . $httpCode
        );
    }

    // Atribui o caminho gerado no bucket para ser gravado no banco de dados
    $caminhoFotoBanco = $caminhoArquivoBucket;
}

// ========================================
// PREPARAR INSERT
// ========================================

$sql = "
    INSERT INTO pet (
        id_tutor,
        nome,
        especie,
        raca,
        sexo,
        data_nascimento,
        peso,
        cor,
        porte,
        microchip,
        castrado,
        observacoes,
        foto
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro ao preparar cadastro do pet: " . $conn->error);

    mostrarMensagem(
        "Erro no cadastro",
        "Não foi possível preparar o cadastro do pet."
    );
}

// ========================================
// VINCULAR OS DADOS
// ========================================

$stmt->bind_param(
    "isssssdsssiss",
    $id_tutor,
    $nome,
    $especie,
    $raca,
    $sexo,
    $data_nascimento,
    $peso,
    $cor,
    $porte,
    $microchip,
    $castrado,
    $observacoes,
    $caminhoFotoBanco
);

// ========================================
// EXECUTAR CADASTRO
// ========================================

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();

    header("Location: /sisvet/bd_conexao/php/pets_tutor.php?sucesso=1");
    exit;
}

// ========================================
// TRATAR ERRO NO CADASTRO
// ========================================

$codigoErro = $stmt->errno;
error_log("Erro ao cadastrar pet: " . $stmt->error);

$stmt->close();
$conn->close();

if ($codigoErro === 1062) {
    mostrarMensagem(
        "Dado duplicado",
        "O microchip informado já está cadastrado."
    );
}

mostrarMensagem(
    "Erro no cadastro",
    "Não foi possível cadastrar o pet. Verifique os dados e tente novamente."
);