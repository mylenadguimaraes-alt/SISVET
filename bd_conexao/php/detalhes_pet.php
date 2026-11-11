<?php
session_start();

mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . "/conexao.php";

// ========================================
// VERIFICAR LOGIN DO TUTOR
// ========================================
if (!isset($_SESSION["id_tutor"])) {
    http_response_code(401);
    exit("Faça login para visualizar os detalhes do pet.");
}

$id_tutor = (int) $_SESSION["id_tutor"];

// Captura o id_pet priorizando o GET (ao abrir a página) ou o POST (após submeter)
$id_pet = filter_input(INPUT_GET, "id_pet", FILTER_VALIDATE_INT);
if (!$id_pet || $id_pet <= 0) {
    $id_pet = filter_input(INPUT_POST, "id_pet", FILTER_VALIDATE_INT);
}

if (!$id_pet || $id_pet <= 0) {
    http_response_code(400);
    exit("ID do pet inválido.");
}

$sucesso = false;
$erro = "";

// ========================================
// PROCESSAR O FORMULÁRIO QUANDO ENVIADO (POST)
// ========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = trim($_POST["nome"] ?? "");
    $especie = trim($_POST["especie"] ?? "");
    
    $raca = trim($_POST["raca"] ?? "");
    $raca = ($raca === "") ? null : $raca;

    $sexo = $_POST["sexo"] ?? "Não informado";
    
    $data_nascimento = !empty($_POST["data_nascimento"]) ? $_POST["data_nascimento"] : null;
    
    $peso = !empty($_POST["peso"]) ? str_replace(',', '.', $_POST["peso"]) : null;
    $peso = ($peso !== null) ? (float) $peso : null;

    $cor = trim($_POST["cor"] ?? "");
    $cor = ($cor === "") ? null : $cor;

    $porte = trim($_POST["porte"] ?? "");
    $porte = ($porte === "") ? null : $porte;

    $microchip = trim($_POST["microchip"] ?? "");
    $microchip = ($microchip === "") ? null : $microchip;

    $castrado = isset($_POST["castrado"]) ? 1 : 0;
    
    // Tratamento rigoroso para observações
    $observacoes = trim($_POST["observacoes"] ?? "");
    $observacoes = ($observacoes === "") ? null : $observacoes;

    if ($nome === "" || $especie === "") {
        $erro = "Nome e espécie são obrigatórios.";
    } else {
        // Usamos prepared statement com tratamento para garantir que nulos funcionem perfeitamente
        $sql_update = "
            UPDATE pet 
            SET nome = ?, especie = ?, raca = ?, sexo = ?, data_nascimento = ?, peso = ?, cor = ?, porte = ?, microchip = ?, castrado = ?, observacoes = ?
            WHERE id_pet = ? AND id_tutor = ?
        ";

        $stmt_up = $conn->prepare($sql_update);
        if ($stmt_up) {
            $stmt_up->bind_param(
                "sssssdsssisii",
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
                $id_pet,
                $id_tutor
            );

            if ($stmt_up->execute()) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?id_pet=" . $id_pet . "&sucesso=1");
                exit;
            } else {
                $erro = "Erro ao atualizar dados: " . $stmt_up->error;
            }
            $stmt_up->close();
        } else {
            $erro = "Erro ao preparar a atualização no banco de dados.";
        }
    }
}

// Verifica se veio parâmetro de sucesso pela URL
$sucesso = isset($_GET["sucesso"]) && $_GET["sucesso"] === "1";

// ========================================
// BUSCAR DADOS ATUAIS DO PET (PARA EXIBIR NO FORMULÁRIO)
// ========================================
$sql = "
    SELECT id_pet, nome, especie, raca, sexo, data_nascimento, peso, cor, porte, microchip, castrado, observacoes, foto
    FROM pet
    WHERE id_pet = ? AND id_tutor = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    exit("Erro ao preparar consulta.");
}

$stmt->bind_param("ii", $id_pet, $id_tutor);
$stmt->execute();
$resultado = $stmt->get_result();
$pet = $resultado->fetch_assoc();
$stmt->close();

if (!$pet) {
    http_response_code(404);
    exit("Pet não encontrado ou acesso não autorizado.");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Pet - <?= htmlspecialchars($pet["nome"] ?? "", ENT_QUOTES, "UTF-8") ?></title>
    
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px;
            background-color: #F5F5F5;
            color: #202B3C;
            font-family: Arial, Helvetica, sans-serif;
        }
        .pagina {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #D9E0EA;
            box-shadow: 0 4px 14px rgba(11, 52, 120, 0.05);
        }
        .topo-acoes {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .botao-voltar, .botao-foto {
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }
        .botao-voltar {
            background-color: #667085;
            color: #FFFFFF;
        }
        .botao-voltar:hover { background-color: #475467; }
        
        .botao-foto {
            background-color: #0B3478;
            color: #FFFFFF;
        }
        .botao-foto:hover { background-color: #1351B4; }

        .cabecalho h1 {
            margin: 0 0 8px;
            font-size: 26px;
            color: #0B3478;
        }
        .cabecalho p {
            margin: 0 0 24px;
            color: #667085;
            font-size: 14px;
        }

        .mensagem {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        .mensagem-sucesso { background-color: #ECFDF3; border: 1px solid #A6F4C5; color: #067647; }
        .mensagem-erro { background-color: #FEF3F2; border: 1px solid #FECDCA; color: #B42318; }

        .secao {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #E4E7EC;
        }
        .secao h2 {
            font-size: 18px;
            color: #0B3478;
            margin: 0 0 6px;
        }
        .descricao-secao {
            font-size: 13px;
            color: #667085;
            margin: 0 0 16px;
        }

        .grade {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .campo-completo {
            grid-column: span 2;
        }
        label {
            font-size: 13px;
            font-weight: 600;
            color: #202B3C;
        }
        .opcional {
            font-weight: normal;
            color: #98A2B3;
        }
        input[type="text"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            padding: 10px 12px;
            border: 1px solid #D9E0EA;
            border-radius: 6px;
            font-size: 14px;
            color: #202B3C;
            background-color: #FCFCFD;
            width: 100%;
        }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid #1351B4;
            border-color: transparent;
        }
        textarea { resize: vertical; min-height: 80px; }

        .opcao-castrado {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }
        .opcao-castrado input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #1351B4;
        }
        .opcao-castrado label {
            font-weight: normal;
            cursor: pointer;
        }

        .acoes {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }
        .botao-salvar {
            padding: 12px 24px;
            background-color: #1351B4;
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .botao-salvar:hover { background-color: #0B3478; }

        @media (max-width: 600px) {
            .grade { grid-template-columns: 1fr; }
            .campo-completo { grid-column: span 1; }
            .topo-acoes { flex-direction: column; align-items: stretch; gap: 10px; }
        }
    </style>
</head>

<body>
    <main class="pagina">

        <div class="topo-acoes">
            <a href="pets_tutor.php" class="botao-voltar">
                ← Voltar para Meus Pets
            </a>
            
            <?php if (!empty($pet["foto"])): ?>
                <!-- Visualizar foto do pet via Supabase -->
                <a href="visualizar_documentos.php?tipo=foto_pet&id_pet=<?= $id_pet ?>" target="_blank" class="botao-foto">
                    Visualizar Foto do Pet
                </a>
            <?php endif; ?>
        </div>

        <header class="cabecalho">
            <h1>Editar Informações do Pet</h1>
            <p>Atualize os dados de <?= htmlspecialchars($pet["nome"] ?? "", ENT_QUOTES, "UTF-8") ?> abaixo.</p>
        </header>

        <!-- MENSAGENS DE SUCESSO OU ERRO -->
        <?php if ($sucesso): ?>
            <div class="mensagem mensagem-sucesso">Informações atualizadas com sucesso!</div>
        <?php elseif ($erro !== ""): ?>
            <div class="mensagem mensagem-erro"><?= htmlspecialchars($erro, ENT_QUOTES, "UTF-8") ?></div>
        <?php endif; ?>

        <!-- Formulário apontando para a própria página (action="") -->
        <form action="" method="POST">
            <input type="hidden" name="id_pet" value="<?= $id_pet ?>">

            <section class="secao">
                <h2>Informações básicas</h2>
                <p class="descricao-secao">Atualize os principais dados do animal.</p>

                <div class="grade">
                    <div class="campo">
                        <label for="nome">Nome do pet *</label>
                        <input type="text" id="nome" name="nome" maxlength="100" value="<?= htmlspecialchars($pet["nome"] ?? "", ENT_QUOTES, "UTF-8") ?>" required>
                    </div>

                    <div class="campo">
                        <label for="especie">Espécie *</label>
                        <select id="especie" name="especie" required>
                            <?php $esp = $pet["especie"] ?? ""; ?>
                            <option value="">Selecione a espécie</option>
                            <option value="Cachorro" <?= $esp === 'Cachorro' ? 'selected' : '' ?>>Cachorro</option>
                            <option value="Gato" <?= $esp === 'Gato' ? 'selected' : '' ?>>Gato</option>
                            <option value="Ave" <?= $esp === 'Ave' ? 'selected' : '' ?>>Ave</option>
                            <option value="Coelho" <?= $esp === 'Coelho' ? 'selected' : '' ?>>Coelho</option>
                            <option value="Roedor" <?= $esp === 'Roedor' ? 'selected' : '' ?>>Roedor</option>
                            <option value="Réptil" <?= $esp === 'Réptil' ? 'selected' : '' ?>>Réptil</option>
                            <option value="Outro" <?= $esp === 'Outro' ? 'selected' : '' ?>>Outro</option>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="raca">Raça <span class="opcional">(opcional)</span></label>
                        <input type="text" id="raca" name="raca" maxlength="100" value="<?= htmlspecialchars($pet["raca"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                    </div>

                    <div class="campo">
                        <label for="sexo">Sexo</label>
                        <select id="sexo" name="sexo">
                            <?php $sex = $pet["sexo"] ?? "Não informado"; ?>
                            <option value="Não informado" <?= $sex === 'Não informado' ? 'selected' : '' ?>>Não informado</option>
                            <option value="Macho" <?= $sex === 'Macho' ? 'selected' : '' ?>>Macho</option>
                            <option value="Fêmea" <?= $sex === 'Fêmea' ? 'selected' : '' ?>>Fêmea</option>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="data_nascimento">Data de nascimento <span class="opcional">(opcional)</span></label>
                        <input type="date" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($pet["data_nascimento"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                    </div>

                    <div class="campo">
                        <label for="peso">Peso em kg <span class="opcional">(opcional)</span></label>
                        <input type="number" id="peso" name="peso" min="0.01" max="9999.99" step="0.01" value="<?= htmlspecialchars($pet["peso"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                    </div>

                    <div class="campo">
                        <label for="cor">Cor <span class="opcional">(opcional)</span></label>
                        <input type="text" id="cor" name="cor" maxlength="50" value="<?= htmlspecialchars($pet["cor"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                    </div>

                    <div class="campo">
                        <label for="porte">Porte <span class="opcional">(opcional)</span></label>
                        <input type="text" id="porte" name="porte" maxlength="30" placeholder="Ex.: Pequeno, Médio, Grande..." value="<?= htmlspecialchars($pet["porte"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                    </div>
                </div>
            </section>

            <section class="secao">
                <h2>Identificação e cuidados</h2>
                <p class="descricao-secao">Gerencie informações de microchip e observações médicas.</p>

                <div class="grade">
                    <div class="campo campo-completo">
                        <label for="microchip">Número do microchip <span class="opcional">(opcional)</span></label>
                        <input type="text" id="microchip" name="microchip" maxlength="50" value="<?= htmlspecialchars($pet["microchip"] ?? "", ENT_QUOTES, "UTF-8") ?>">
                    </div>

                    <div class="campo campo-completo">
                        <label>Castração</label>
                        <div class="opcao-castrado">
                            <input type="checkbox" id="castrado" name="castrado" value="1" <?= (isset($pet["castrado"]) && $pet["castrado"] == 1) ? 'checked' : '' ?>>
                            <label for="castrado">Meu pet é castrado</label>
                        </div>
                    </div>

                    <div class="campo campo-completo">
                        <label for="observacoes">Observações <span class="opcional">(opcional)</span></label>
                        <textarea id="observacoes" name="observacoes" placeholder="Informe alergias, características ou outras informações importantes..."><?= htmlspecialchars($pet["observacoes"] ?? "", ENT_QUOTES, "UTF-8") ?></textarea>
                    </div>
                </div>
            </section>

            <div class="acoes">
                <button type="submit" class="botao-salvar">
                    Salvar alterações
                </button>
            </div>
        </form>
    </main>
</body>
</html>