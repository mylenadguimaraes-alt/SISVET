<?php

session_start();

require_once "conexao.php";

// ========================================
// FUNÇÃO PARA MOSTRAR MENSAGENS
// ========================================

function mensagem(string $texto): void
{
    echo "<!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <title>Cadastro de contrato</title>
    </head>
    <body>
        <script>
            alert(" . json_encode(
                $texto,
                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS |
                JSON_HEX_QUOT | JSON_HEX_AMP
            ) . ");
            history.back();
        </script>
    </body>
    </html>";

    exit;
}

// ========================================
// VERIFICAR SE O DONO ESTÁ LOGADO
// ========================================

if (
    !isset($_SESSION["id_dono"]) ||
    !is_numeric($_SESSION["id_dono"])
) {
    mensagem("Sua sessão expirou. Entre novamente no sistema.");
}

$id_dono = (int) $_SESSION["id_dono"];

// ========================================
// ACEITAR SOMENTE POST
// ========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    mensagem("Acesso inválido. Envie o formulário de cadastro.");
}

// ========================================
// RECEBER OS DADOS DO FORMULÁRIO
// ========================================

$cpf_veterinario = trim($_POST["cpf_vet"] ?? "");
$cnpj_clinica = trim($_POST["cnpj_clinica"] ?? "");

$cargo = trim($_POST["cargo"] ?? "");
$tipo_contrato = trim($_POST["tipo_contrato"] ?? "");

$data_inicio = trim($_POST["data_inicio"] ?? "");
$data_fim = trim($_POST["data_fim"] ?? "");

$carga_horaria = trim(
    $_POST["carga_horaria_semanal"] ?? ""
);

$observacoes = trim($_POST["observacoes"] ?? "");

// ========================================
// NORMALIZAR CPF E CNPJ
// Remover pontos, traços, barras e espaços
// ========================================

$cpf = preg_replace('/\D/', '', $cpf_veterinario);
$cnpj = preg_replace('/\D/', '', $cnpj_clinica);

// ========================================
// VALIDAR CPF E CNPJ
// ========================================

if (strlen($cpf) !== 11) {
    mensagem("Informe um CPF válido, com 11 números.");
}

if (strlen($cnpj) !== 14) {
    mensagem("Informe um CNPJ válido, com 14 números.");
}

// ========================================
// VALIDAR CAMPOS OBRIGATÓRIOS
// ========================================

if (
    $cargo === "" ||
    $tipo_contrato === "" ||
    $data_inicio === ""
) {
    mensagem("Preencha todos os campos obrigatórios.");
}

// ========================================
// VALIDAR TIPO DE CONTRATO
// Os valores precisam corresponder ao ENUM
// da tabela contrato_veterinario
// ========================================

$tiposPermitidos = [
    "CLT",
    "PJ",
    "Temporario",
    "Estagio",
    "Outro"
];

if (!in_array($tipo_contrato, $tiposPermitidos, true)) {
    mensagem("Selecione um tipo de contrato válido.");
}

// ========================================
// VALIDAR DATAS
// ========================================

$dataInicioObj = DateTime::createFromFormat(
    '!Y-m-d',
    $data_inicio
);

if (
    !$dataInicioObj ||
    $dataInicioObj->format("Y-m-d") !== $data_inicio
) {
    mensagem("A data de início é inválida.");
}

if ($data_fim !== "") {
    $dataFimObj = DateTime::createFromFormat(
        '!Y-m-d',
        $data_fim
    );

    if (
        !$dataFimObj ||
        $dataFimObj->format("Y-m-d") !== $data_fim
    ) {
        mensagem("A data de término é inválida.");
    }

    if ($dataFimObj < $dataInicioObj) {
        mensagem(
            "A data de término não pode ser anterior à data de início."
        );
    }
} else {
    $data_fim = null;
}

// ========================================
// VALIDAR CARGA HORÁRIA
// ========================================

if ($carga_horaria !== "") {
    if (
        !ctype_digit($carga_horaria) ||
        (int) $carga_horaria < 1 ||
        (int) $carga_horaria > 168
    ) {
        mensagem("Informe uma carga horária entre 1 e 168 horas.");
    }

    $carga_horaria = (int) $carga_horaria;
} else {
    $carga_horaria = null;
}

// ========================================
// LOCALIZAR O VETERINÁRIO PELO CPF
// (Tabela veterinarios, chave primária 'id')[cite: 1]
// ========================================

$sqlVeterinario = "
    SELECT id
    FROM veterinarios
    WHERE REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(cpf, '.', ''),
                '-', ''
            ),
            '/', ''
        ),
        ' ', ''
    ) = ?
    LIMIT 1
";

$stmt = $conn->prepare($sqlVeterinario);

if (!$stmt) {
    error_log("Erro ao preparar busca de veterinário: " . $conn->error);
    mensagem("Não foi possível consultar o veterinário.");
}

$stmt->bind_param("s", $cpf);
$stmt->execute();

$resultado = $stmt->get_result();
$veterinario = $resultado->fetch_assoc();

$stmt->close();

if (!$veterinario) {
    mensagem(
        "Não existe um veterinário cadastrado com esse CPF."
    );
}

$id_veterinario = (int) $veterinario["id"];

// ========================================
// LOCALIZAR A CLÍNICA PELO CNPJ
// E CONFERIR SE PERTENCE AO DONO LOGADO
// ========================================

$sqlClinica = "
    SELECT id_clinica
    FROM clinica
    WHERE REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(cnpj, '.', ''),
                '-', ''
            ),
            '/', ''
        ),
        ' ', ''
    ) = ?
    AND id_dono = ?
    LIMIT 1
";

$stmt = $conn->prepare($sqlClinica);

if (!$stmt) {
    error_log("Erro ao preparar busca da clínica: " . $conn->error);
    mensagem("Não foi possível consultar a clínica.");
}

$stmt->bind_param("si", $cnpj, $id_dono);
$stmt->execute();

$resultado = $stmt->get_result();
$clinica = $resultado->fetch_assoc();

$stmt->close();

if (!$clinica) {
    mensagem(
        "A clínica não foi encontrada ou não pertence ao dono logado."
    );
}

$id_clinica = (int) $clinica["id_clinica"];

// ========================================
// EVITAR VÍNCULO CONTRATADO DUPLICADO
// (Tabela contrato_veterinario com status 'contratado')[cite: 1]
// ========================================

$sqlDuplicado = "
    SELECT id_contrato
    FROM contrato_veterinario
    WHERE id_veterinario = ?
      AND id_clinica = ?
      AND status = 'contratado'
    LIMIT 1
";

$stmt = $conn->prepare($sqlDuplicado);

if (!$stmt) {
    error_log("Erro ao verificar contrato: " . $conn->error);
    mensagem("Não foi possível verificar os contratos existentes.");
}

$stmt->bind_param(
    "ii",
    $id_veterinario,
    $id_clinica
);

$stmt->execute();

$resultado = $stmt->get_result();
$contratoExistente = $resultado->fetch_assoc();

$stmt->close();

if ($contratoExistente) {
    mensagem(
        "Este veterinário já possui um contrato ativo com essa clínica."
    );
}

// ========================================
// CADASTRAR O CONTRATO
// O STATUS COMEÇA COMO 'contratado'
// ========================================

$sqlInsert = "
    INSERT INTO contrato_veterinario (
        id_veterinario,
        id_clinica,
        cargo,
        tipo_contrato,
        data_inicio,
        data_fim,
        carga_horaria_semanal,
        observacoes,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'contratado')
";

$stmt = $conn->prepare($sqlInsert);

if (!$stmt) {
    error_log("Erro ao preparar cadastro de contrato: " . $conn->error);
    mensagem("Não foi possível preparar o cadastro do contrato.");
}

$stmt->bind_param(
    "iissssis",
    $id_veterinario,
    $id_clinica,
    $cargo,
    $tipo_contrato,
    $data_inicio,
    $data_fim,
    $carga_horaria,
    $observacoes
);

if (!$stmt->execute()) {
    error_log("Erro ao cadastrar contrato: " . $stmt->error);

    $stmt->close();

    mensagem("Ocorreu um erro ao cadastrar o contrato.");
}

$stmt->close();

// ========================================
// SUCESSO
// ========================================

$conn->close();

mensagem("Contrato do veterinário cadastrado com sucesso!");

?>