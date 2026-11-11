<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once "conexao.php";

// Garante a codificação UTF-8
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// Verificar se o tutor está logado
if (!isset($_SESSION["id_tutor"])) {
    header("Location: login_tutor.php");
    exit;
}

$id_tutor = (int) $_SESSION["id_tutor"];
$termo_busca = trim($_GET["busca"] ?? "");

// ========================================
// BUSCAR DOCUMENTOS RELACIONADOS AOS PETS DO TUTOR
// Juntando com documentos, clínica, veterinário e pet
// ========================================
$sql_docs = "
    SELECT 
        d.id_documento,
        d.nome_documento,
        d.caminho_documento,
        d.data_cadastro,
        p.id_pet,
        p.nome AS nome_pet,
        cl.nome_clinica,
        v.nome AS nome_veterinario
    FROM documentos d
    INNER JOIN pet p ON d.id_pet = p.id_pet
    INNER JOIN clinica cl ON d.id_clinica = cl.id_clinica
    INNER JOIN veterinarios v ON d.id_veterinario = v.id
    WHERE p.id_tutor = ?
";

$params = [$id_tutor];
$types = "i";

// Filtro de pesquisa por nome do documento ou da clínica
if ($termo_busca !== "") {
    $sql_docs .= " AND (d.nome_documento LIKE ? OR cl.nome_clinica LIKE ?)";
    $like = "%" . $termo_busca . "%";
    array_push($params, $like, $like);
    $types .= "ss";
}

$sql_docs .= " ORDER BY p.nome ASC, d.data_cadastro DESC";

$stmt_d = $conn->prepare($sql_docs);
if ($stmt_d && !empty($params)) {
    $stmt_d->bind_param($types, ...$params);
}
$stmt_d->execute();
$resultado_docs = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_d->close();

// Agrupar os documentos por pet para exibir em seções separadas
$pets_documentos = [];
foreach ($resultado_docs as $doc) {
    $id_pet = $doc['id_pet'];
    if (!isset($pets_documentos[$id_pet])) {
        $pets_documentos[$id_pet] = [
            'nome_pet' => $doc['nome_pet'],
            'documentos' => []
        ];
    }
    $pets_documentos[$id_pet]['documentos'][] = $doc;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos dos Pets - SISVET</title>
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
            --verde: #067647;
            --verde-bg: #ECFDF3;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px;
            background-color: var(--fundo);
            color: var(--texto);
            font-family: Arial, Helvetica, sans-serif;
        }
        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }
        .cabecalho {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cabecalho h1 {
            margin: 0 0 6px;
            color: var(--azul-escuro);
            font-size: 26px;
        }
        .cabecalho p {
            margin: 0;
            color: var(--texto-secundario);
            font-size: 14px;
        }
        .btn-voltar {
            padding: 10px 16px;
            background-color: #667085;
            color: #FFF;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-voltar:hover { background-color: #475467; }

        .form-busca {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: var(--branco);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid var(--borda);
        }
        .form-busca input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid var(--borda);
            border-radius: 6px;
            font-size: 14px;
            outline: none;
        }
        .form-busca input:focus { border-color: var(--azul); }
        .btn-pesquisar {
            background-color: var(--azul);
            color: var(--branco);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-pesquisar:hover { background-color: var(--azul-escuro); }
        .btn-limpar {
            padding: 10px;
            text-decoration: none;
            color: var(--texto-secundario);
            font-size: 14px;
            display: flex;
            align-items: center;
            font-weight: 600;
        }

        /* Seções por Pet */
        .secao-pet {
            margin-bottom: 30px;
        }
        .secao-titulo {
            font-size: 18px;
            color: var(--azul-escuro);
            border-bottom: 2px solid var(--borda);
            padding-bottom: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-documento {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 12px;
            box-shadow: 0 2px 6px rgba(11, 52, 120, 0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .doc-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }
        .doc-titulo {
            margin: 0;
            font-size: 16px;
            color: var(--texto);
            font-weight: bold;
        }
        .doc-detalhes {
            font-size: 13px;
            color: var(--texto-secundario);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .doc-detalhes strong { color: var(--texto); }

        .btn-visualizar {
            background-color: var(--azul);
            color: #FFF;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background-color 0.2s;
            white-space: nowrap;
        }
        .btn-visualizar:hover { background-color: var(--azul-escuro); }

        .sem-resultados {
            text-align: center;
            background: var(--branco);
            padding: 40px;
            border-radius: 12px;
            border: 1px solid var(--borda);
            color: var(--texto-secundario);
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="cabecalho">
            <div>
                <h1>Documentos M&eacute;dicos</h1>
                <p>Consulte exames, receitas e laudos emitidos para os seus pets.</p>
            </div>
            
        </header>

        <!-- Barra de Pesquisa (por nome do documento ou clínica) -->
        <form method="GET" action="" class="form-busca">
            <input type="text" name="busca" placeholder="Pesquisar por nome do documento ou cl&iacute;nica..." value="<?= htmlspecialchars($termo_busca, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-pesquisar">Pesquisar</button>
            <?php if ($termo_busca !== ""): ?>
                <a href="documentos_tutor.php" class="btn-limpar">Limpar</a>
            <?php endif; ?>
        </form>

        <!-- Listagem Separada por Seção de Pet -->
        <?php if (!empty($pets_documentos)): ?>
            <?php foreach ($pets_documentos as $id_pet => $dados_pet): ?>
                <div class="secao-pet">
                    <h2 class="secao-titulo">Pet: <?= htmlspecialchars($dados_pet['nome_pet'], ENT_QUOTES, "UTF-8") ?></h2>
                    
                    <?php foreach ($dados_pet['documentos'] as $doc): ?>
                        <div class="card-documento">
                            <div class="doc-info">
                                <h3 class="doc-titulo"><?= htmlspecialchars($doc["nome_documento"], ENT_QUOTES, "UTF-8") ?></h3>
                                <div class="doc-detalhes">
                                    <div>Cl&iacute;nica: <strong><?= htmlspecialchars($doc["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                                    <div>Veterin&aacute;rio(a): <strong><?= htmlspecialchars($doc["nome_veterinario"], ENT_QUOTES, "UTF-8") ?></strong></div>
                                    <div>Data: <?= date('d/m/Y H:i', strtotime($doc["data_cadastro"])) ?></div>
                                </div>
                            </div>

                            <!-- Botão para visualizar o documento armazenado no bucket/caminho -->
                            <a href="visualizar_documentos.php?tipo=documento_individual&id_documento=<?= $doc["id_documento"] ?>" target="_blank" class="btn-visualizar">
                                Visualizar Documento
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="sem-resultados">Nenhum documento encontrado com os crit&eacute;rios informados.</div>
        <?php endif; ?>
    </div>
</body>
</html>