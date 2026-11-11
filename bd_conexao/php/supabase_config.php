<?php

$arquivoEnv = dirname(__DIR__, 2) . "/.env";

if (!file_exists($arquivoEnv)) {
    die("Arquivo .env não encontrado.");
}

$variaveis = parse_ini_file($arquivoEnv, false, INI_SCANNER_RAW);

if ($variaveis === false) {
    die("Erro ao carregar o arquivo .env.");
}

define("SUPABASE_URL", $variaveis["SUPABASE_URL"]);
define("SUPABASE_KEY", $variaveis["SUPABASE_KEY"]);
define("SUPABASE_SECRET_KEY", $variaveis["SUPABASE_SECRET_KEY"]);
define("SUPABASE_BUCKET", $variaveis["SUPABASE_BUCKET"]);

?>