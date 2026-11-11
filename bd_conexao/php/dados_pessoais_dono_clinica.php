<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

function responder(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if (empty($_SESSION["id_dono"])) {
    responder([
        "sucesso" => false,
        "mensagem" => "Sua sessão expirou. Entre novamente no sistema."
    ], 401);
}

require_once __DIR__ . "/conexao.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("A conexão mysqli \$conn não foi encontrada.");
    responder([
        "sucesso" => false,
        "mensagem" => "Não foi possível conectar ao banco de dados."
    ], 500);
}

$conn->set_charset("utf8mb4");

$idDono = (int) $_SESSION["id_dono"];
$acao = $_GET["acao"] ?? $_POST["acao"] ?? "";

if ($acao === "consultar") {

    $sql = "
        SELECT
            nome_completo,
            cpf,
            email,
            telefone,
            data_nascimento,
            cep,
            rua,
            numero,
            complemento,
            bairro,
            cidade,
            estado,
            documento_identificacao
        FROM dono_clinica
        WHERE id_dono = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Erro na consulta de dados do dono: " . $conn->error);
        responder([
            "sucesso" => false,
            "mensagem" => "Não foi possível consultar seus dados."
        ], 500);
    }

    $stmt->bind_param("i", $idDono);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $dono = $resultado->fetch_assoc();

    $stmt->close();

    if (!$dono) {
        responder([
            "sucesso" => false,
            "mensagem" => "Cadastro do dono não encontrado."
        ], 404);
    }

    $dono["tem_documento"] =
        !empty($dono["documento_identificacao"]);

    unset($dono["documento_identificacao"]);

    responder([
        "sucesso" => true,
        "dados" => $dono
    ]);
}

if ($acao === "salvar") {

    $camposObrigatorios = [
        "nome_completo",
        "cpf",
        "email",
        "telefone",
        "data_nascimento",
        "cep",
        "rua",
        "numero",
        "bairro",
        "cidade",
        "estado"
    ];

    foreach ($camposObrigatorios as $campo) {
        if (!isset($_POST[$campo]) || trim($_POST[$campo]) === "") {
            responder([
                "sucesso" => false,
                "mensagem" => "Preencha todos os campos obrigatórios."
            ], 422);
        }
    }

    // Função auxiliar para remover tudo o que não for número (para CPF, Telefone e CEP)
    $limparMascara = function($valor) {
        return preg_replace('/\D/', '', $valor);
    };

    $nome = trim($_POST["nome_completo"]);
    $cpf = $limparMascara(trim($_POST["cpf"]));
    $email = trim($_POST["email"]);
    $telefone = $limparMascara(trim($_POST["telefone"]));
    $dataNascimento = trim($_POST["data_nascimento"]);
    $cep = $limparMascara(trim($_POST["cep"]));
    $rua = trim($_POST["rua"]);
    $numero = trim($_POST["numero"]);
    $complemento = trim($_POST["complemento"] ?? "");
    $bairro = trim($_POST["bairro"]);
    $cidade = trim($_POST["cidade"]);
    $estado = trim($_POST["estado"]);

    $senhaAtual = $_POST["senha_atual"] ?? "";
    $novaSenha = $_POST["nova_senha"] ?? "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responder([
            "sucesso" => false,
            "mensagem" => "Informe um e-mail válido."
        ], 422);
    }

    $data = DateTime::createFromFormat("!Y-m-d", $dataNascimento);

    if (!$data || $data->format("Y-m-d") !== $dataNascimento) {
        responder([
            "sucesso" => false,
            "mensagem" => "Informe uma data de nascimento válida."
        ], 422);
    }

    // Estrutura dinâmica para atualizar a senha caso o usuário tenha preenchido
    $atualizarSenhaSql = "";
    $tiposBind = "ssssssssssss";
    $paramsBind = [
        &$nome,
        &$cpf,
        &$email,
        &$telefone,
        &$dataNascimento,
        &$cep,
        &$rua,
        &$numero,
        &$complemento,
        &$bairro,
        &$cidade,
        &$estado
    ];

    if (!empty($senhaAtual) || !empty($novaSenha)) {
        if (empty($senhaAtual) || empty($novaSenha)) {
            responder([
                "sucesso" => false,
                "mensagem" => "Para alterar a senha, informe a senha atual e a nova senha."
            ], 422);
        }

        // Verifica a senha atual no banco
        $sqlSenha = "SELECT senha FROM dono_clinica WHERE id_dono = ? LIMIT 1";
        $stmtSenha = $conn->prepare($sqlSenha);
        $stmtSenha->bind_param("i", $idDono);
        $stmtSenha->execute();
        $resSenha = $stmtSenha->get_result()->fetch_assoc();
        $stmtSenha->close();

        if (!$resSenha || !password_verify($senhaAtual, $resSenha["senha"])) {
            responder([
                "sucesso" => false,
                "mensagem" => "A senha atual está incorreta."
            ], 422);
        }

        $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $atualizarSenhaSql = ", senha = ?";
        $tiposBind .= "s";
        $paramsBind[] = &$senhaHash;
    }

    $tiposBind .= "i";
    $paramsBind[] = &$idDono;

    $sql = "
        UPDATE dono_clinica
        SET
            nome_completo = ?,
            cpf = ?,
            email = ?,
            telefone = ?,
            data_nascimento = ?,
            cep = ?,
            rua = ?,
            numero = ?,
            complemento = ?,
            bairro = ?,
            cidade = ?,
            estado = ?
            {$atualizarSenhaSql}
        WHERE id_dono = ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Erro ao preparar atualização: " . $conn->error);
        responder([
            "sucesso" => false,
            "mensagem" => "Não foi possível preparar a atualização."
        ], 500);
    }

    $stmt->bind_param($tiposBind, ...$paramsBind);

    if (!$stmt->execute()) {
        error_log("Erro ao atualizar dados: " . $stmt->error);
        $stmt->close();

        responder([
            "sucesso" => false,
            "mensagem" => "Não foi possível salvar os dados."
        ], 500);
    }

    $stmt->close();

    responder([
        "sucesso" => true,
        "mensagem" => "Dados pessoais e senha atualizados com sucesso."
    ]);
}

responder([
    "sucesso" => false,
    "mensagem" => "Ação inválida."
], 400);