
<?php

session_start();
require_once __DIR__ . "/conexao.php";

// Impede acesso direto sem envio do formulário
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método de envio inválido.");
}

// Exibe uma mensagem ao usuário
function mensagem($texto, $voltar = true) {
    echo "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>SISVET - Cadastro</title>
    </head>
    <body>
        <script>
            alert(" . json_encode($texto) . ");
            " . ($voltar
                ? "history.back();"
                : "window.location.href = document.referrer || '/';") . "
        </script>
    </body>
    </html>";

    exit;
}

// Recebe os dados do formulário
$nome = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$cpf = preg_replace('/\D/', '', $_POST["cpf"] ?? "");
$data_nascimento = trim($_POST["birth_date"] ?? "");
$telefone = trim($_POST["telefone"] ?? "");

$cep = trim($_POST["cep"] ?? "");
$endereco = trim($_POST["endereco"] ?? "");
$numero = trim($_POST["numero"] ?? "");
$complemento = trim($_POST["complemento"] ?? "");
$bairro = trim($_POST["bairro"] ?? "");
$cidade = trim($_POST["cidade"] ?? "");
$estado = strtoupper(trim($_POST["estado"] ?? ""));

$senha = $_POST["password"] ?? "";

// Verifica os campos obrigatórios
if (
    $nome === "" ||
    $email === "" ||
    $cpf === "" ||
    $data_nascimento === "" ||
    $telefone === "" ||
    $cep === "" ||
    $endereco === "" ||
    $numero === "" ||
    $senha === ""
) {
    mensagem("Preencha todos os campos obrigatórios.");
}

// Valida o e-mail
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mensagem("Informe um e-mail válido.");
}

// Verifica a quantidade de dígitos do CPF
if (strlen($cpf) !== 11) {
    mensagem("O CPF deve conter 11 dígitos.");
}

// Valida a data de nascimento
$data = DateTime::createFromFormat("!Y-m-d", $data_nascimento);

if (
    !$data ||
    $data->format("Y-m-d") !== $data_nascimento ||
    $data > new DateTime("today")
) {
    mensagem("Informe uma data de nascimento válida.");
}

// Valida o estado, caso tenha sido informado
if ($estado !== "" && !preg_match('/^[A-Z]{2}$/', $estado)) {
    mensagem("Informe a sigla do estado com duas letras.");
}

// Exige pelo menos 8 caracteres na senha
if (strlen($senha) < 8) {
    mensagem("A senha deve ter pelo menos 8 caracteres.");
}

// Converte os campos opcionais vazios em NULL
$complemento = $complemento !== "" ? $complemento : null;
$bairro = $bairro !== "" ? $bairro : null;
$cidade = $cidade !== "" ? $cidade : null;
$estado = $estado !== "" ? $estado : null;

// Verifica se o CPF já está cadastrado
$sql_cpf = "SELECT id_tutor FROM tutor WHERE cpf = ?";

$stmt = $conn->prepare($sql_cpf);

if (!$stmt) {
    error_log("Erro ao preparar consulta de CPF: " . $conn->error);
    mensagem("Erro ao verificar o CPF.");
}

$stmt->bind_param("s", $cpf);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    mensagem("Este CPF já está cadastrado.");
}

$stmt->close();

// Verifica se o e-mail já está cadastrado
$sql_email = "SELECT id_tutor FROM tutor WHERE email = ?";

$stmt = $conn->prepare($sql_email);

if (!$stmt) {
    error_log("Erro ao preparar consulta de e-mail: " . $conn->error);
    mensagem("Erro ao verificar o e-mail.");
}

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    mensagem("Este e-mail já está cadastrado.");
}

$stmt->close();

// Gera o hash seguro da senha
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

if ($senha_hash === false) {
    mensagem("Não foi possível processar a senha.");
}

// Insere o tutor no banco de dados
$sql = "INSERT INTO tutor (
    nome_completo,
    cpf,
    email,
    data_nascimento,
    telefone,
    cep,
    endereco,
    numero,
    complemento,
    bairro,
    cidade,
    estado,
    senha
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Erro ao preparar cadastro do tutor: " . $conn->error);
    mensagem("Não foi possível preparar o cadastro.");
}

// Todos os parâmetros são strings.
// O CPF é salvo apenas com os 11 dígitos, sem pontuação.
$stmt->bind_param(
    "sssssssssssss",
    $nome,
    $cpf,
    $email,
    $data_nascimento,
    $telefone,
    $cep,
    $endereco,
    $numero,
    $complemento,
    $bairro,
    $cidade,
    $estado,
    $senha_hash
);

// Executa o cadastro
if ($stmt->execute()) {

    $id_tutor = $conn->insert_id;

    // Salva os dados do tutor na sessão
    session_regenerate_id(true);

    $_SESSION["id_tutor"] = $id_tutor;
    $_SESSION["nome_tutor"] = $nome;
    $_SESSION["email_tutor"] = $email;

    $stmt->close();

    // Redireciona para o painel do tutor
    header("Location: ../../incial/pages/tutor/dashboard/dashboard.html");
    
    mensagem("Tutor cadastrado com sucesso!", false);
    exit;


} else {
    $codigo_erro = $stmt->errno;

    error_log("Erro ao cadastrar tutor: " . $stmt->error);

    $stmt->close();

    if ($codigo_erro === 1062) {
        mensagem("Este CPF ou e-mail já está cadastrado.");
    }

    mensagem("Erro ao cadastrar tutor. Tente novamente.");
}
?>
