
<?php

require_once __DIR__ . "/supabase_config.php";

/**
 * Registra informações de depuração sem expor a chave secreta.
 */
function registrarErroSupabase(string $mensagem): void
{
    $arquivo = __DIR__ . "/erro_supabase.log";

    file_put_contents(
        $arquivo,
        date("Y-m-d H:i:s") . " | " . $mensagem . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Prepara e valida o caminho do arquivo no bucket.
 */
function prepararCaminhoDocumento(string $caminho): string
{
    $caminho = trim($caminho);

    if ($caminho === "") {
        throw new RuntimeException("O caminho do documento está vazio.");
    }

    // Se vier uma URL do Supabase, extrai o caminho do objeto.
    if (filter_var($caminho, FILTER_VALIDATE_URL)) {
        $partesURL = parse_url($caminho);
        $caminhoURL = $partesURL["path"] ?? "";

        if (
            preg_match(
                '#/storage/v1/object/(?:sign|public|authenticated)/(.+)$#',
                $caminhoURL,
                $correspondencia
            )
        ) {
            $caminho = $correspondencia[1];
        } else {
            throw new RuntimeException(
                "A URL informada não corresponde a um objeto do Supabase Storage."
            );
        }

        // Remove query string e fragmento: o caminho do objeto não os inclui.
        $caminho = rawurldecode($caminho);
    }

    $caminho = ltrim(str_replace("\\", "/", $caminho), "/");

    $bucket = trim(SUPABASE_BUCKET, "/");

    // Remove o nome do bucket se ele já estiver no início do caminho.
    if (strpos($caminho, $bucket . "/") === 0) {
        $caminho = substr($caminho, strlen($bucket) + 1);
    }

    $segmentos = explode("/", $caminho);

    foreach ($segmentos as $segmento) {
        if (
            $segmento === "" ||
            $segmento === "." ||
            $segmento === ".."
        ) {
            throw new RuntimeException(
                "O caminho do documento contém segmentos inválidos."
            );
        }
    }

    return implode("/", $segmentos);
}

/**
 * Gera uma URL assinada para abrir um documento privado.
 */
function gerarLinkDocumentoSupabase(string $caminho): string
{
    if (
        !defined("SUPABASE_URL") ||
        !defined("SUPABASE_KEY") ||
        !defined("SUPABASE_BUCKET")
    ) {
        registrarErroSupabase(
            "Configuração incompleta: verifique supabase_config.php."
        );

        throw new RuntimeException(
            "A configuração do Supabase está incompleta."
        );
    }

    if (!function_exists("curl_init")) {
        registrarErroSupabase("A extensão cURL do PHP não está habilitada.");

        throw new RuntimeException(
            "A extensão cURL do PHP não está habilitada."
        );
    }

    $baseURL = rtrim(SUPABASE_URL, "/");
    $bucket = trim(SUPABASE_BUCKET, "/");

    if ($bucket === "") {
        throw new RuntimeException("O nome do bucket está vazio.");
    }

    $caminho = prepararCaminhoDocumento($caminho);

    // Codifica cada segmento separadamente, preservando as barras.
    $segmentosCodificados = array_map(
        "rawurlencode",
        explode("/", $caminho)
    );

    $caminhoCodificado = implode("/", $segmentosCodificados);

    $url = $baseURL
        . "/storage/v1/object/sign/"
        . rawurlencode($bucket)
        . "/"
        . $caminhoCodificado;

    $payload = json_encode([
        "expiresIn" => 300
    ]);

    if ($payload === false) {
        throw new RuntimeException(
            "Não foi possível preparar a solicitação ao Supabase."
        );
    }

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "apikey: " . SUPABASE_KEY,
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ]);

    $resposta = curl_exec($curl);
    $erroCurl = curl_error($curl);
    $codigoHTTP = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($resposta === false) {
        registrarErroSupabase(
            "Falha cURL: " . $erroCurl
            . " | Bucket: " . $bucket
            . " | Caminho: " . $caminho
        );

        throw new RuntimeException(
            "Não foi possível conectar ao Supabase Storage."
        );
    }

    $dados = json_decode($resposta, true);

    // Registra a resposta recebida, sem registrar a chave secreta.
    registrarErroSupabase(
        "HTTP: " . $codigoHTTP
        . " | Bucket: " . $bucket
        . " | Caminho: " . $caminho
        . " | Resposta: " . substr($resposta, 0, 1500)
    );

    if (
        $codigoHTTP < 200 ||
        $codigoHTTP >= 300 ||
        !is_array($dados) ||
        empty($dados["signedURL"])
    ) {
        throw new RuntimeException(
            "O Supabase não conseguiu gerar a URL assinada. "
            . "Consulte erro_supabase.log."
        );
    }

    $link = $dados["signedURL"];

    // Algumas respostas retornam uma URL absoluta.
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }

    // Outras retornam um caminho relativo.
    if (strpos($link, "/storage/v1/") === 0) {
        return $baseURL . $link;
    }

    if (strpos($link, "/object/") === 0) {
        return $baseURL . "/storage/v1" . $link;
    }

    return $baseURL . "/storage/v1/" . ltrim($link, "/");
}
