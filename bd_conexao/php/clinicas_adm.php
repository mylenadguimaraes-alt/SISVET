<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once "conexao.php";

// Garante a codificação UTF-8
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// Verificar se o administrador está logado
if (!isset($_SESSION["id_administrador"]) && !isset($_SESSION["id_admin"])) {
    header("Location: login_admin.php");
    exit;
}

$mensagem = "";
$tipo_mensagem = "";

// ========================================
// PROCESSAR AÇÃO DE AUTORIZAR CLÍNICA (POST)
// ========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "autorizar") {
    $id_clinica = filter_input(INPUT_POST, "id_clinica", FILTER_VALIDATE_INT);

    if ($id_clinica && $id_clinica > 0) {
        $sql_aut = "UPDATE clinica SET autorizado = 1, status_cadastro = 'autorizada' WHERE id_clinica = ? AND status_cadastro = 'em_espera'";
        $stmt_aut = $conn->prepare($sql_aut);
        $stmt_aut->bind_param("i", $id_clinica);

        if ($stmt_aut->execute() && $stmt_aut->affected_rows > 0) {
            $mensagem = "Clínica autorizada com sucesso!";
            $tipo_mensagem = "sucesso";
        } else {
            $mensagem = "Erro ao autorizar a clínica ou ela já não está em espera.";
            $tipo_mensagem = "erro";
        }
        $stmt_aut->close();
    }
}

// Capturar termo de busca
$termo_busca = trim($_GET["busca"] ?? "");

// ========================================
// BUSCAR CLÍNICAS E DADOS DO DONO
// ========================================
$sql_clinicas = "
    SELECT 
        c.id_clinica,
        c.id_dono,
        c.razao_social,
        c.nome_clinica,
        c.email AS email_clinica,
        c.telefone AS telefone_clinica,
        c.cep AS cep_clinica,
        c.endereco AS endereco_clinica,
        c.numero AS numero_clinica,
        c.complemento AS complemento_clinica,
        c.bairro AS bairro_clinica,
        c.cidade AS cidade_clinica,
        c.estado AS estado_clinica,
        c.cnpj,
        c.documento_licenca,
        c.autorizado,
        c.suspenso,
        c.status_cadastro,
        c.data_cadastro,
        d.nome_completo AS nome_dono,
        d.cpf AS cpf_dono,
        d.data_nascimento AS nascimento_dono,
        d.estado AS estado_dono,
        d.cidade AS cidade_dono,
        d.telefone AS telefone_dono,
        d.email AS email_dono
    FROM clinica c
    INNER JOIN dono_clinica d ON c.id_dono = d.id_dono
    WHERE 1=1
";

$params = [];
$types = "";

if ($termo_busca !== "") {
    $sql_clinicas .= " AND (
        c.nome_clinica LIKE ? OR 
        c.razao_social LIKE ? OR 
        c.estado LIKE ? OR 
        c.cidade LIKE ? OR 
        c.endereco LIKE ? OR 
        c.bairro LIKE ? OR 
        c.cnpj LIKE ? OR 
        c.telefone LIKE ? OR 
        c.email LIKE ? OR
        d.nome_completo LIKE ? OR
        d.cpf LIKE ? OR
        d.cidade LIKE ? OR
        d.estado LIKE ?
    )";
    $like = "%" . $termo_busca . "%";
    for ($i = 0; $i < 13; $i++) {
        $params[] = $like;
        $types .= "s";
    }
}

$sql_clinicas .= " ORDER BY c.data_cadastro DESC";

$stmt = $conn->prepare($sql_clinicas);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Separar as clínicas nas seções
$secoes = [
    'em_espera' => [],
    'autorizada' => [],
    'suspensa' => []
];

foreach ($resultado as $cli) {
    $st = strtolower($cli['status_cadastro']);
    if ($st === 'espera') $st = 'em_espera';
    if ($st === 'autorizadas') $st = 'autorizada';
    if ($st === 'suspensas') $st = 'suspensa';

    if (isset($secoes[$st])) {
        $secoes[$st][] = $cli;
    } else {
        $secoes['em_espera'][] = $cli;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Clínicas - SISVET</title>
    <style>
        :root {
            --azul: #1351B4;
            --azul-escuro: #0B3478;
            --fundo: #F5F5F5;
            --branco: #FFFFFF;
            --texto: #202B3C;
            --texto-secundario: #667085;
            --borda: #D9E0EA;
            --verde: #067647;
            --verde-bg: #ECFDF3;
            --vermelho: #D92D20;
            --amarelo-bg: #FFFAEB;
            --amarelo-txt: #B54708;
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
            max-width: 1100px;
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

        .mensagem {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        .mensagem-sucesso { background-color: var(--verde-bg); border: 1px solid #A6F4C5; color: var(--verde); }
        .mensagem-erro { background-color: #FEF3F2; border: 1px solid #FECDCA; color: var(--vermelho); }

        .secao-clinicas { margin-bottom: 30px; }
        .secao-titulo {
            font-size: 18px;
            color: var(--azul-escuro);
            border-bottom: 2px solid var(--borda);
            padding-bottom: 8px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-clinica {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(11, 52, 120, 0.04);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .card-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-topo h3 {
            margin: 0;
            color: var(--texto);
            font-size: 18px;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-espera { background-color: var(--amarelo-bg); color: var(--amarelo-txt); border: 1px solid #FEC84B; }
        .badge-autorizada { background-color: var(--verde-bg); color: var(--verde); border: 1px solid #A6F4C5; }
        .badge-suspensa { background-color: #FEF3F2; color: var(--vermelho); border: 1px solid #FECDCA; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            font-size: 13px;
            color: var(--texto-secundario);
            background: #FAFAFC;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #E4E7EC;
        }
        .info-grid strong { color: var(--texto); }

        .bloco-subtitulo {
            grid-column: 1 / -1;
            font-weight: bold;
            color: var(--azul-escuro);
            font-size: 13px;
            border-bottom: 1px solid var(--borda);
            padding-bottom: 4px;
            margin-top: 6px;
        }

        .acoes-card {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            align-items: center;
            margin-top: 5px;
            padding-top: 10px;
            border-top: 1px dashed var(--borda);
        }
        .btn-acao {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-visualizar { background-color: var(--azul); color: #FFF; }
        .btn-visualizar:hover { background-color: var(--azul-escuro); }
        .btn-autorizar { background-color: var(--verde); color: #FFF; }
        .btn-autorizar:hover { background-color: #054F31; }

        .vazio-secao {
            font-size: 13px;
            color: var(--texto-secundario);
            font-style: italic;
            margin-bottom: 15px;
            background: var(--branco);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--borda);
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="cabecalho">
            <div>
                <h1>Gerenciamento de Cl&iacute;nicas</h1>
                <p>Consulte cadastros, analise documenta&ccedil;&otilde;es e gerencie o status das unidades.</p>
            </div>
 
        </header>

        <!-- Pesquisa -->
        <form method="GET" action="" class="form-busca">
            <input type="text" name="busca" placeholder="Pesquisar por nome, CNPJ, cidade, estado, endereço, telefone, e-mail ou dados do dono..." value="<?= htmlspecialchars($termo_busca, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-pesquisar">Pesquisar</button>
            <?php if (!empty($termo_busca)): ?>
                <a href="clinicas_adm.php" class="btn-limpar">Limpar</a>
            <?php endif; ?>
        </form>

        <?php if (!empty($mensagem)): ?>
            <div class="mensagem mensagem-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>

        <!-- SEÇÃO 1: CLÍNICAS EM ESPERA -->
        <section class="secao-clinicas">
            <h2 class="secao-titulo">Cl&iacute;nicas em Espera</h2>
            <?php if (!empty($secoes['em_espera'])): ?>
                <?php foreach ($secoes['em_espera'] as $cli): ?>
                    <div class="card-clinica">
                        <div class="card-topo">
                            <h3><?= htmlspecialchars($cli["nome_clinica"], ENT_QUOTES, "UTF-8") ?></h3>
                            <span class="badge badge-espera">Em Espera</span>
                        </div>
                        <div class="info-grid">
                            <div class="bloco-subtitulo">Dados da Clínica</div>
                            <div>Raz&atilde;o Social: <strong><?= htmlspecialchars($cli["razao_social"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CNPJ: <strong><?= htmlspecialchars($cli["cnpj"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>E-mail da Clínica: <strong><?= htmlspecialchars($cli["email_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Telefone da Clínica: <strong><?= htmlspecialchars($cli["telefone_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Localidade: <strong><?= htmlspecialchars($cli["cidade_clinica"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["estado_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Endere&ccedil;o: <strong><?= htmlspecialchars($cli["endereco_clinica"], ENT_QUOTES, "UTF-8") ?>, Nº <?= htmlspecialchars($cli["numero_clinica"], ENT_QUOTES, "UTF-8") ?> - <?= htmlspecialchars($cli["bairro_clinica"], ENT_QUOTES, "UTF-8") ?> (CEP: <?= htmlspecialchars($cli["cep_clinica"], ENT_QUOTES, "UTF-8") ?>)</strong></div>

                            <div class="bloco-subtitulo">Dados do Dono / Proprietário</div>
                            <div>Nome do Dono: <strong><?= htmlspecialchars($cli["nome_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CPF do Dono: <strong><?= htmlspecialchars($cli["cpf_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Data de Nascimento: <strong><?= date('d/m/Y', strtotime($cli["nascimento_dono"])) ?></strong></div>
                            <div>Localidade do Dono: <strong><?= htmlspecialchars($cli["cidade_dono"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["estado_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Contato do Dono: <strong><?= htmlspecialchars($cli["telefone_dono"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["email_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                        </div>
                        <div class="acoes-card">
                            <a href="visualizar_documentos.php?tipo=adm_clinica&id_clinica=<?= $cli["id_clinica"] ?>" target="_blank" class="btn-acao btn-visualizar">
                                Visualizar Licen&ccedil;a
                            </a>
                            <form action="" method="POST" style="margin:0;">
                                <input type="hidden" name="id_clinica" value="<?= $cli["id_clinica"] ?>">
                                <button type="submit" name="acao" value="autorizar" class="btn-acao btn-autorizar">Autorizar Cl&iacute;nica</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma cl&iacute;nica em espera encontrada.</div>
            <?php endif; ?>
        </section>

        <!-- SEÇÃO 2: CLÍNICAS AUTORIZADAS -->
        <section class="secao-clinicas">
            <h2 class="secao-titulo">Cl&iacute;nicas Autorizadas</h2>
            <?php if (!empty($secoes['autorizada'])): ?>
                <?php foreach ($secoes['autorizada'] as $cli): ?>
                    <div class="card-clinica">
                        <div class="card-topo">
                            <h3><?= htmlspecialchars($cli["nome_clinica"], ENT_QUOTES, "UTF-8") ?></h3>
                            <span class="badge badge-autorizada">Autorizada</span>
                        </div>
                        <div class="info-grid">
                            <div class="bloco-subtitulo">Dados da Clínica</div>
                            <div>Raz&atilde;o Social: <strong><?= htmlspecialchars($cli["razao_social"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CNPJ: <strong><?= htmlspecialchars($cli["cnpj"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>E-mail da Clínica: <strong><?= htmlspecialchars($cli["email_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Telefone da Clínica: <strong><?= htmlspecialchars($cli["telefone_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Localidade: <strong><?= htmlspecialchars($cli["cidade_clinica"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["estado_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Endere&ccedil;o: <strong><?= htmlspecialchars($cli["endereco_clinica"], ENT_QUOTES, "UTF-8") ?>, Nº <?= htmlspecialchars($cli["numero_clinica"], ENT_QUOTES, "UTF-8") ?> - <?= htmlspecialchars($cli["bairro_clinica"], ENT_QUOTES, "UTF-8") ?> (CEP: <?= htmlspecialchars($cli["cep_clinica"], ENT_QUOTES, "UTF-8") ?>)</strong></div>

                            <div class="bloco-subtitulo">Dados do Dono / Proprietário</div>
                            <div>Nome do Dono: <strong><?= htmlspecialchars($cli["nome_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CPF do Dono: <strong><?= htmlspecialchars($cli["cpf_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Data de Nascimento: <strong><?= date('d/m/Y', strtotime($cli["nascimento_dono"])) ?></strong></div>
                            <div>Localidade do Dono: <strong><?= htmlspecialchars($cli["cidade_dono"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["estado_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Contato do Dono: <strong><?= htmlspecialchars($cli["telefone_dono"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["email_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                        </div>
                        <div class="acoes-card">
                            <a href="visualizar_documentos.php?tipo=adm_clinica&id_clinica=<?= $cli["id_clinica"] ?>" target="_blank" class="btn-acao btn-visualizar">
                                Visualizar Licen&ccedil;a
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma cl&iacute;nica autorizada encontrada.</div>
            <?php endif; ?>
        </section>

        <!-- SEÇÃO 3: CLÍNICAS SUSPENSAS -->
        <section class="secao-clinicas">
            <h2 class="secao-titulo">Cl&iacute;nicas Suspensas</h2>
            <?php if (!empty($secoes['suspensa'])): ?>
                <?php foreach ($secoes['suspensa'] as $cli): ?>
                    <div class="card-clinica">
                        <div class="card-topo">
                            <h3><?= htmlspecialchars($cli["nome_clinica"], ENT_QUOTES, "UTF-8") ?></h3>
                            <span class="badge badge-suspensa">Suspensa</span>
                        </div>
                        <div class="info-grid">
                            <div class="bloco-subtitulo">Dados da Clínica</div>
                            <div>Raz&atilde;o Social: <strong><?= htmlspecialchars($cli["razao_social"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CNPJ: <strong><?= htmlspecialchars($cli["cnpj"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>E-mail da Clínica: <strong><?= htmlspecialchars($cli["email_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Telefone da Clínica: <strong><?= htmlspecialchars($cli["telefone_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Localidade: <strong><?= htmlspecialchars($cli["cidade_clinica"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["estado_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Endere&ccedil;o: <strong><?= htmlspecialchars($cli["endereco_clinica"], ENT_QUOTES, "UTF-8") ?>, Nº <?= htmlspecialchars($cli["numero_clinica"], ENT_QUOTES, "UTF-8") ?> - <?= htmlspecialchars($cli["bairro_clinica"], ENT_QUOTES, "UTF-8") ?> (CEP: <?= htmlspecialchars($cli["cep_clinica"], ENT_QUOTES, "UTF-8") ?>)</strong></div>

                            <div class="bloco-subtitulo">Dados do Dono / Proprietário</div>
                            <div>Nome do Dono: <strong><?= htmlspecialchars($cli["nome_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CPF do Dono: <strong><?= htmlspecialchars($cli["cpf_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Data de Nascimento: <strong><?= date('d/m/Y', strtotime($cli["nascimento_dono"])) ?></strong></div>
                            <div>Localidade do Dono: <strong><?= htmlspecialchars($cli["cidade_dono"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["estado_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Contato do Dono: <strong><?= htmlspecialchars($cli["telefone_dono"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($cli["email_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                        </div>
                        <div class="acoes-card">
                            <a href="visualizar_documentos.php?tipo=adm_clinica&id_clinica=<?= $cli["id_clinica"] ?>" target="_blank" class="btn-acao btn-visualizar">
                                Visualizar Licen&ccedil;a
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma cl&iacute;nica suspensa encontrada.</div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>