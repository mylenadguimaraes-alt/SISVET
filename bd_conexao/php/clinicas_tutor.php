<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once "conexao.php";

// Garante a codificação UTF-8 na comunicação com o banco de dados
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// Capturar parâmetros de busca
$tipo_busca = trim($_GET["tipo_busca"] ?? "nome");
$termo = trim($_GET["termo"] ?? "");

// Base da query: apenas clínicas autorizadas, ordenadas alfabeticamente
$sql = "
    SELECT 
        id_clinica, 
        nome_clinica, 
        razao_social, 
        email, 
        telefone, 
        cep, 
        endereco, 
        numero, 
        complemento, 
        bairro, 
        cidade, 
        estado 
    FROM clinica 
    WHERE autorizado = 1 AND status_cadastro = 'autorizada'
";

$params = [];
$types = "";

// Aplicar filtros de acordo com a escolha de pesquisa
if ($termo !== "") {
    switch ($tipo_busca) {
        case 'nome':
            $sql .= " AND nome_clinica LIKE ?";
            $termo_like = "%" . $termo . "%";
            $params[] = $termo_like;
            $types .= "s";
            break;
            
        case 'estado':
            $sql .= " AND estado = ?";
            $params[] = strtoupper($termo);
            $types .= "s";
            break;
            
        case 'cidade':
            $sql .= " AND cidade LIKE ?";
            $termo_like = "%" . $termo . "%";
            $params[] = $termo_like;
            $types .= "s";
            break;
            
        case 'endereco':
            $sql .= " AND (endereco LIKE ? OR bairro LIKE ? OR cep LIKE ? OR numero LIKE ?)";
            $termo_like = "%" . $termo . "%";
            array_push($params, $termo_like, $termo_like, $termo_like, $termo_like);
            $types .= "ssss";
            break;
            
        case 'contato':
            $sql .= " AND (email LIKE ? OR telefone LIKE ?)";
            $termo_like = "%" . $termo . "%";
            array_push($params, $termo_like, $termo_like);
            $types .= "ss";
            break;
    }
}

// Ordenação alfabética obrigatória por nome da clínica
$sql .= " ORDER BY nome_clinica ASC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt) {
    $stmt->execute();
    $resultado = $stmt->get_result();
    $clinicas = $resultado->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $clinicas = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínicas Autorizadas - SISVET</title>
    
    <style>
        :root {
            --azul: #1351B4;
            --azul-escuro: #0B3478;
            --azul-claro: #EAF2FF;
            --fundo: #F5F5F5;
            --branco: #FFFFFF;
            --texto: #202B3C;
            --texto-secundario: #667085;
            --borda: #D9E0EA;
            --vermelho: #D92D20;
            --vermelho-hover: #B42318;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background-color: var(--fundo);
            color: var(--texto);
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .cabecalho {
            margin-bottom: 25px;
        }

        .cabecalho h1 {
            margin: 0 0 10px;
            color: var(--azul-escuro);
            font-size: 28px;
        }

        .cabecalho p {
            margin: 0;
            color: var(--texto-secundario);
            font-size: 15px;
            line-height: 1.6;
        }

        .form-busca {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            background: var(--branco);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--borda);
            box-shadow: 0 2px 6px rgba(11, 52, 120, 0.04);
        }

        .form-busca select, 
        .form-busca input {
            padding: 12px 14px;
            border: 1px solid var(--borda);
            border-radius: 8px;
            font-size: 14px;
            background-color: var(--branco);
            color: var(--texto);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-busca select:focus, 
        .form-busca input:focus {
            border-color: var(--azul);
            box-shadow: 0 0 0 3px rgba(19, 81, 180, 0.12);
        }

        .form-busca input {
            flex: 1;
            min-width: 240px;
        }

        .btn-pesquisar {
            background-color: var(--azul);
            color: var(--branco);
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .btn-pesquisar:hover {
            background-color: var(--azul-escuro);
        }

        .btn-limpar {
            padding: 12px 16px;
            text-decoration: none;
            color: var(--texto-secundario);
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            font-weight: 600;
        }

        .btn-limpar:hover {
            color: var(--texto);
        }

        .card-clinica {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 16px;
            box-shadow: 0 3px 10px rgba(11, 52, 120, 0.04);
        }

        .card-clinica h3 {
            margin: 0 0 14px;
            color: var(--azul-escuro);
            font-size: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
            font-size: 14px;
            color: var(--texto);
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .info-grid strong {
            color: var(--texto-secundario);
        }

        .btn-denunciar {
            background-color: var(--vermelho);
            color: var(--branco);
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }

        .btn-denunciar:hover {
            background-color: var(--vermelho-hover);
        }

        .sem-resultados {
            text-align: center;
            color: var(--texto-secundario);
            background: var(--branco);
            border: 1px solid var(--borda);
            padding: 40px;
            border-radius: 12px;
            font-style: italic;
        }

        @media (max-width: 650px) {
            body { padding: 16px; }
            .form-busca { flex-direction: column; }
            .form-busca select, .form-busca input, .btn-pesquisar { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="cabecalho">
            <h1>Cl&iacute;nicas Veterin&aacute;rias Autorizadas</h1>
            <p>Consulte abaixo as cl&iacute;nicas cadastradas e autorizadas no sistema.</p>
        </header>

        <!-- Formulário de Pesquisa -->
        <form method="GET" action="" class="form-busca">
            <select name="tipo_busca">
                <option value="nome" <?= $tipo_busca === 'nome' ? 'selected' : '' ?>>Pesquisar por Nome</option>
                <option value="estado" <?= $tipo_busca === 'estado' ? 'selected' : '' ?>>Pesquisar por Estado (UF)</option>
                <option value="cidade" <?= $tipo_busca === 'cidade' ? 'selected' : '' ?>>Pesquisar por Cidade</option>
                <option value="endereco" <?= $tipo_busca === 'endereco' ? 'selected' : '' ?>>Pesquisar por Endere&ccedil;o</option>
                <option value="contato" <?= $tipo_busca === 'contato' ? 'selected' : '' ?>>Pesquisar por Email ou Telefone</option>
            </select>

            <input type="text" name="termo" placeholder="Digite o termo para pesquisar..." value="<?= htmlspecialchars($termo, ENT_QUOTES, 'UTF-8') ?>">
            
            <button type="submit" class="btn-pesquisar">Pesquisar</button>
            <?php if ($termo !== ""): ?>
                <a href="clinicas_tutor.php" class="btn-limpar">Limpar filtro</a>
            <?php endif; ?>
        </form>

        <!-- Listagem de Clínicas -->
        <?php if (!empty($clinicas)): ?>
            <?php foreach ($clinicas as $clinica): ?>
                <div class="card-clinica">
                    <h3><?= htmlspecialchars($clinica["nome_clinica"], ENT_QUOTES, "UTF-8") ?></h3>
                    
                    <div class="info-grid">
                        <div><strong>Cidade / Estado:</strong> <?= htmlspecialchars($clinica["cidade"] ?? "N&atilde;o informada", ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($clinica["estado"] ?? "--", ENT_QUOTES, "UTF-8") ?></div>
                        <div><strong>Endere&ccedil;o:</strong> <?= htmlspecialchars($clinica["endereco"], ENT_QUOTES, "UTF-8") ?>, N&ordm; <?= htmlspecialchars($clinica["numero"], ENT_QUOTES, "UTF-8") ?><?= !empty($clinica["complemento"]) ? " - " . htmlspecialchars($clinica["complemento"], ENT_QUOTES, "UTF-8") : "" ?></div>
                        <div><strong>Bairro:</strong> <?= htmlspecialchars($clinica["bairro"] ?? "N&atilde;o informado", ENT_QUOTES, "UTF-8") ?> | <strong>CEP:</strong> <?= htmlspecialchars($clinica["cep"], ENT_QUOTES, "UTF-8") ?></div>
                        <div><strong>Telefone:</strong> <?= htmlspecialchars($clinica["telefone"], ENT_QUOTES, "UTF-8") ?></div>
                        <div><strong>E-mail:</strong> <?= htmlspecialchars($clinica["email"], ENT_QUOTES, "UTF-8") ?></div>
                    </div>

                    <!-- Botão Denunciar Clínica -->
                    <a href="cadastrar_denuncia.php?id_clinica=<?= $clinica["id_clinica"] ?>" class="btn-denunciar">
                        Denunciar cl&iacute;nica
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sem-resultados">Nenhuma cl&iacute;nica autorizada encontrada com os crit&eacute;rios informados.</div>
        <?php endif; ?>
    </div>
</body>
</html>