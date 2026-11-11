<?php

$arquivoEnv = dirname(__DIR__, 2) . "/.env";

if (!file_exists($arquivoEnv)) {
    die("Arquivo .env não encontrado.");
}

$variaveis = parse_ini_file($arquivoEnv, false, INI_SCANNER_RAW);

if ($variaveis === false) {
    die("Erro ao carregar as configurações.");
}

// Dados de conexão
define('HOST', $variaveis['DB_HOST']);
define('DBNAME', $variaveis['DB_NAME']);
define('USER', $variaveis['DB_USER']);
define('PASSWORD', $variaveis['DB_PASS']);

// Conexão com MySQL
$conn = mysqli_connect(HOST, USER, PASSWORD, DBNAME);

if (!$conn) {
    error_log("Erro de conexão MySQL: " . mysqli_connect_error());
    die("Não foi possível conectar ao banco de dados.");
}

mysqli_set_charset($conn, "utf8mb4");

?>