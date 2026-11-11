<?php
session_start();
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . "/conexao.php"; 

// Validação de sessão flexível para 'id' ou 'id_veterinario'
$id_veterinario = $_SESSION["id"] ?? $_SESSION["id_veterinario"] ?? null;

if (empty($id_veterinario)) {
    exit("Erro: Sua sessão expirou. Entre novamente no sistema.");
}

$id_veterinario = (int) $id_veterinario;
$acao = $_GET["acao"] ?? $_POST["acao"] ?? "";

try {
    // 1. CONSULTAR DADOS
    if ($acao === "consultar") {
        $stmt = $conn->prepare("
            SELECT nome, cpf, email, telefone, data_nascimento, numero_crmv, estado_crmv, cep, endereco, numero, complemento, bairro, cidade, estado, caminho_documento_verificacao 
            FROM veterinarios 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->bind_param("i", $id_veterinario);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$resultado) {
            exit("Erro: Cadastro do veterinário não encontrado.");
        }

        $tem_documento = !empty($resultado['caminho_documento_verificacao']) ? "1" : "0";
        
        // Retorna todos os campos na ordem exata separados por pipe (|)
        echo implode("|", [
            $resultado['nome'] ?? '',
            $resultado['cpf'] ?? '',
            $resultado['email'] ?? '',
            $resultado['telefone'] ?? '',
            $resultado['data_nascimento'] ?? '',
            $resultado['numero_crmv'] ?? '',
            $resultado['estado_crmv'] ?? '',
            $resultado['cep'] ?? '',
            $resultado['endereco'] ?? '',
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
        $numero_crmv = trim($_POST["numero_crmv"] ?? "");
        $estado_crmv = trim($_POST["estado_crmv"] ?? "");
        $cep = $limparMascara(trim($_POST["cep"] ?? ""));
        $endereco = trim($_POST["endereco"] ?? "");
        $numero = trim($_POST["numero"] ?? "");
        $complemento = trim($_POST["complemento"] ?? "");
        $bairro = trim($_POST["bairro"] ?? "");
        $cidade = trim($_POST["cidade"] ?? "");
        $estado = trim($_POST["estado"] ?? "");
        $novaSenha = $_POST["nova_senha"] ?? "";

        if (empty($nome) || empty($cpf) || empty($email) || empty($telefone) || empty($data_nascimento) || empty($numero_crmv) || empty($estado_crmv) || empty($cep) || empty($endereco) || empty($numero) || empty($bairro) || empty($cidade) || empty($estado)) {
            exit("Erro: Preencha todos os campos obrigatórios.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            exit("Erro: Informe um e-mail válido.");
        }

        // Condicional para atualizar a senha apenas se o campo foi preenchido
        $sqlSenhaUpdate = "";
        $tiposBind = "ssssssssssssss";
        $paramsBind = [
            &$nome,
            &$cpf,
            &$email,
            &$telefone,
            &$data_nascimento,
            &$numero_crmv,
            &$estado_crmv,
            &$cep,
            &$endereco,
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
        $paramsBind[] = &$id_veterinario;

        $sql = "
            UPDATE veterinarios 
            SET nome = ?, cpf = ?, email = ?, telefone = ?, data_nascimento = ?, numero_crmv = ?, estado_crmv = ?, cep = ?, endereco = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ? 
            {$sqlSenhaUpdate}
            WHERE id = ?
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