<?php
session_start();
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . "/conexao.php"; 

// Validação de sessão flexível para 'id' ou 'id_recepcionista'
$id_recepcionista = $_SESSION["id"] ?? $_SESSION["id_recepcionista"] ?? null;

if (empty($id_recepcionista)) {
    exit("Erro: Sua sessão expirou. Entre novamente no sistema.");
}

$id_recepcionista = (int) $id_recepcionista;
$acao = $_GET["acao"] ?? $_POST["acao"] ?? "";

try {
    // 1. CONSULTAR DADOS
    if ($acao === "consultar") {
        $stmt = $conn->prepare("
            SELECT nome, cpf, email, telefone, data_nascimento, cep, rua, numero, complemento, bairro, cidade, estado, caminho_documento 
            FROM recepcionista 
            WHERE id_recepcionista = ? 
            LIMIT 1
        ");
        $stmt->bind_param("i", $id_recepcionista);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$resultado) {
            exit("Erro: Cadastro da recepcionista não encontrado.");
        }

        $tem_documento = !empty($resultado['caminho_documento']) ? "1" : "0";
        
        // Retorna todos os campos na ordem exata separados por pipe (|)
        echo implode("|", [
            $resultado['nome'] ?? '',
            $resultado['cpf'] ?? '',
            $resultado['email'] ?? '',
            $resultado['telefone'] ?? '',
            $resultado['data_nascimento'] ?? '',
            $resultado['cep'] ?? '',
            $resultado['rua'] ?? '',
            $resultado['numero'] ?? '',
            $resultado['complemento'] ?? '',
            $resultado['bairro'] ?? '',
            $resultado['cidade'] ?? '',
            $resultado['estado'] ?? '',
            $tem_documento
        ]);
        exit;
    }

    // 2. SALVAR DADOS E SENHA (SEM PEDIR SENHA ATUAL)
    if ($acao === "salvar") {
        $limparMascara = function($valor) {
            return preg_replace('/\D/', '', $valor);
        };

        $nome = trim($_POST["nome"] ?? "");
        $cpf = $limparMascara(trim($_POST["cpf"] ?? ""));
        $email = trim($_POST["email"] ?? "");
        $telefone = $limparMascara(trim($_POST["telefone"] ?? ""));
        $data_nascimento = trim($_POST["data_nascimento"] ?? "");
        $cep = $limparMascara(trim($_POST["cep"] ?? ""));
        $rua = trim($_POST["rua"] ?? "");
        $numero = trim($_POST["numero"] ?? "");
        $complemento = trim($_POST["complemento"] ?? "");
        $bairro = trim($_POST["bairro"] ?? "");
        $cidade = trim($_POST["cidade"] ?? "");
        $estado = trim($_POST["estado"] ?? "");
        $novaSenha = $_POST["nova_senha"] ?? "";

        if (empty($nome) || empty($cpf) || empty($email) || empty($telefone) || empty($data_nascimento) || empty($cep) || empty($rua) || empty($numero) || empty($bairro) || empty($cidade) || empty($estado)) {
            exit("Erro: Preencha todos os campos obrigatórios.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            exit("Erro: Informe um e-mail válido.");
        }

        // Condicional para atualizar a senha apenas se o campo foi preenchido
        $sqlSenhaUpdate = "";
        $tiposBind = "ssssssssssss";
        $paramsBind = [
            &$nome,
            &$cpf,
            &$email,
            &$telefone,
            &$data_nascimento,
            &$cep,
            &$rua,
            &$numero,
            &$complemento,
            &$bairro,
            &$cidade,
            &$estado
        ];

        if (!empty($novaSenha)) {
            $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $sqlSenhaUpdate = ", senha = ?";
            $tiposBind .= "s";
            $paramsBind[] = &$senhaHash;
        }

        $tiposBind .= "i";
        $paramsBind[] = &$id_recepcionista;

        $sql = "
            UPDATE recepcionista 
            SET nome = ?, cpf = ?, email = ?, telefone = ?, data_nascimento = ?, cep = ?, rua = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ? 
            {$sqlSenhaUpdate}
            WHERE id_recepcionista = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($tiposBind, ...$paramsBind);
        
        if ($stmt->execute()) {
            $stmt->close();
            echo "Dados pessoais e senha atualizados com sucesso.";
        } else {
            $stmt->close();
            exit("Erro: Não foi possível salvar os dados.");
        }
        exit;
    }

    exit("Erro: Ação inválida.");

} catch (Exception $e) {
    exit("Erro: " . $e->getMessage());
}
?>