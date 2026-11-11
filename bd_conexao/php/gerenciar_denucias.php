<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . "/conexao.php";

// Garante a codificação UTF-8
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
}

// ========================================
// VERIFICAÇÃO DE SESSÃO DO PAINEL ADMIN
// ========================================
if (!isset($_SESSION["id_administrador"]) && !isset($_SESSION["id_admin"])) {
    header("Location: login_admin.php");
    exit;
}

$mensagem = "";
$tipo_mensagem = "";

// ========================================
// PROCESSAMENTO DE MUDANÇA DE STATUS (POST)
// ========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "mudar_status") {
    $id_denuncia = filter_input(INPUT_POST, "id_denuncia", FILTER_VALIDATE_INT);
    $novo_status = trim($_POST["novo_status"] ?? "");

    // Regras de transição permitidas:
    // pendente -> em analise
    // em analise -> procedente ou arquivada
    $status_validos = ["em analise", "procedente", "arquivada"];

    if ($id_denuncia && in_array($novo_status, $status_validos)) {
        // Busca o status atual para validar se a transição é permitida
        $sql_checa = "SELECT status FROM denuncias WHERE id_denuncia = ? LIMIT 1";
        $stmt_c = $conn->prepare($sql_checa);
        $stmt_c->bind_param("i", $id_denuncia);
        $stmt_c->execute();
        $atual = $stmt_c->get_result()->fetch_assoc();
        $stmt_c->close();

        if ($atual) {
            $status_atual = $atual["status"];
            $transicao_valida = false;

            if ($status_atual === 'pendente' && $novo_status === 'em analise') {
                $transicao_valida = true;
            } elseif ($status_atual === 'em analise' && ($novo_status === 'procedente' || $novo_status === 'arquivada')) {
                $transicao_valida = true;
            }

            if ($transicao_valida) {
                $sql_up = "UPDATE denuncias SET status = ? WHERE id_denuncia = ?";
                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("si", $novo_status, $id_denuncia);
                if ($stmt_up->execute()) {
                    $mensagem = "Status da denúncia atualizado com sucesso para: " . ucfirst($novo_status);
                    $tipo_mensagem = "sucesso";
                } else {
                    $mensagem = "Erro ao atualizar o status no banco de dados.";
                    $tipo_mensagem = "erro";
                }
                $stmt_up->close();
            } else {
                $mensagem = "Transição de status inválida para o estado atual da denúncia.";
                $tipo_mensagem = "erro";
            }
        }
    } else {
        $mensagem = "Parâmetros inválidos para alteração de status.";
        $tipo_mensagem = "erro";
    }
}

// ========================================
// CONSULTA DE DENÚNCIAS COM PESQUISA
// ========================================
$termo_busca = trim($_GET["busca"] ?? "");

$sql_denuncias = "
    SELECT 
        d.id_denuncia,
        d.motivo,
        d.descricao_detalhada,
        d.evidencia,
        d.status,
        d.data_denuncia,
        c.nome_clinica,
        c.cnpj,
        c.cidade AS cidade_clinica,
        c.estado AS estado_clinica,
        t.nome_completo AS nome_tutor,
        t.email AS email_tutor,
        t.telefone AS telefone_tutor,
        t.cpf AS cpf_tutor
    FROM denuncias d
    INNER JOIN clinica c ON d.id_clinica = c.id_clinica
    INNER JOIN tutor t ON d.id_tutor = t.id_tutor
    WHERE 1=1
";

$params = [];
$types = "";

if ($termo_busca !== "") {
    $sql_denuncias .= " AND (
        d.motivo LIKE ? OR 
        d.descricao_detalhada LIKE ? OR 
        c.nome_clinica LIKE ? OR 
        c.cnpj LIKE ? OR 
        t.nome_completo LIKE ? OR 
        t.email LIKE ? OR 
        t.telefone LIKE ? OR
        t.cpf LIKE ?
    )";
    $like = "%" . $termo_busca . "%";
    for ($i = 0; $i < 8; $i++) {
        $params[] = $like;
        $types .= "s";
    }
}

$sql_denuncias .= " ORDER BY d.data_denuncia DESC";

$stmt = $conn->prepare($sql_denuncias);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Separar em seções estruturadas
$secoes = [
    'pendente' => [],
    'em analise' => [],
    'procedente' => [],
    'arquivada' => []
];

foreach ($resultado as $den) {
    $st = strtolower($den['status']);
    if (isset($secoes[$st])) {
        $secoes[$st][] = $den;
    } else {
        $secoes['pendente'][] = $den;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Denúncias - SISVET</title>
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
            --vermelho-bg: #FEF3F2;
            --amarelo-bg: #FFFAEB;
            --amarelo-txt: #B54708;
            --cinza-bg: #F2F4F7;
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
        .mensagem-erro { background-color: var(--vermelho-bg); border: 1px solid #FECDCA; color: var(--vermelho); }

        .secao-denuncias { margin-bottom: 35px; }
        .secao-titulo {
            font-size: 18px;
            color: var(--azul-escuro);
            border-bottom: 2px solid var(--borda);
            padding-bottom: 8px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-denuncia {
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
        .badge-pendente { background-color: var(--amarelo-bg); color: var(--amarelo-txt); border: 1px solid #FEC84B; }
        .badge-analise { background-color: #EFF8FF; color: #175CD3; border: 1px solid #B2DDFF; }
        .badge-procedente { background-color: var(--verde-bg); color: var(--verde); border: 1px solid #A6F4C5; }
        .badge-arquivada { background-color: var(--cinza-bg); color: #344054; border: 1px solid #D0D5DD; }

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

        .descricao-caixa {
            grid-column: 1 / -1;
            background: #FFF;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid var(--borda);
            color: var(--texto);
            margin-top: 4px;
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
        .btn-analise { background-color: #175CD3; color: #FFF; }
        .btn-analise:hover { background-color: #0E3B8B; }
        .btn-procedente { background-color: var(--verde); color: #FFF; }
        .btn-procedente:hover { background-color: #054F31; }
        .btn-arquivar { background-color: #475467; color: #FFF; }
        .btn-arquivar:hover { background-color: #1D2939; }

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
                <h1>Gerenciamento de Den&uacute;ncias</h1>
                <p>Analise denúncias registradas contra clínicas, verifique evidências e atualize os status.</p>
            </div>
  
        </header>

        <!-- Barra de Pesquisa Abrangente -->
        <form method="GET" action="" class="form-busca">
            <input type="text" name="busca" placeholder="Pesquisar por motivo, descrição, nome da clínica, CNPJ ou dados do tutor (nome, e-mail, telefone, CPF)..." value="<?= htmlspecialchars($termo_busca, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-pesquisar">Pesquisar</button>
            <?php if (!empty($termo_busca)): ?>
                <a href="gerenciar_denucias.php" class="btn-limpar">Limpar</a>
            <?php endif; ?>
        </form>

        <?php if (!empty($mensagem)): ?>
            <div class="mensagem mensagem-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>

        <!-- ================= SEÇÃO 1: PENDENTES ================= -->
        <section class="secao-denuncias">
            <h2 class="secao-titulo">Den&uacute;ncias Pendentes</h2>
            <?php if (!empty($secoes['pendente'])): ?>
                <?php foreach ($secoes['pendente'] as $den): ?>
                    <div class="card-denuncia">
                        <div class="card-topo">
                            <h3>Motivo: <?= htmlspecialchars($den["motivo"], ENT_QUOTES, "UTF-8") ?></h3>
                            <span class="badge badge-pendente">Pendente</span>
                        </div>
                        <div class="info-grid">
                            <div class="bloco-subtitulo">Dados da Clínica Denunciada</div>
                            <div>Clínica: <strong><?= htmlspecialchars($den["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CNPJ da Clínica: <strong><?= htmlspecialchars($den["cnpj"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Localidade: <strong><?= htmlspecialchars($den["cidade_clinica"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($den["estado_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>

                            <div class="bloco-subtitulo">Contato do Tutor Denunciante</div>
                            <div>Nome do Tutor: <strong><?= htmlspecialchars($den["nome_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CPF do Tutor: <strong><?= htmlspecialchars($den["cpf_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>E-mail do Tutor: <strong><?= htmlspecialchars($den["email_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Telefone do Tutor: <strong><?= htmlspecialchars($den["telefone_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>

                            <div class="bloco-subtitulo">Detalhes da Denúncia (Data: <?= date('d/m/Y H:i', strtotime($den["data_denuncia"])) ?>)</div>
                            <div class="descricao-caixa">
                                <?= nl2br(htmlspecialchars($den["descricao_detalhada"], ENT_QUOTES, "UTF-8")) ?>
                            </div>
                        </div>
                        <div class="acoes-card">
                            <?php if (!empty($den["evidencia"])): ?>
                                <!-- Redireciona para o arquivo de visualização de documentos passando o tipo=evidencia e o id_denuncia -->
                                <a href="visualizar_documentos.php?tipo=evidencia&id_denuncia=<?= $den["id_denuncia"] ?>" target="_blank" class="btn-acao btn-visualizar">
                                    Visualizar Evid&ecirc;ncia
                                </a>
                            <?php endif; ?>
                            <form action="" method="POST" style="margin:0;">
                                <input type="hidden" name="id_denuncia" value="<?= $den["id_denuncia"] ?>">
                                <input type="hidden" name="acao" value="mudar_status">
                                <input type="hidden" name="novo_status" value="em analise">
                                <button type="submit" class="btn-acao btn-analise">Mover para Em Análise</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma denúncia pendente encontrada.</div>
            <?php endif; ?>
        </section>

        <!-- ================= SEÇÃO 2: EM ANÁLISE ================= -->
        <section class="secao-denuncias">
            <h2 class="secao-titulo">Den&uacute;ncias em An&aacute;lise</h2>
            <?php if (!empty($secoes['em analise'])): ?>
                <?php foreach ($secoes['em analise'] as $den): ?>
                    <div class="card-denuncia">
                        <div class="card-topo">
                            <h3>Motivo: <?= htmlspecialchars($den["motivo"], ENT_QUOTES, "UTF-8") ?></h3>
                            <span class="badge badge-analise">Em Análise</span>
                        </div>
                        <div class="info-grid">
                            <div class="bloco-subtitulo">Dados da Clínica Denunciada</div>
                            <div>Clínica: <strong><?= htmlspecialchars($den["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CNPJ da Clínica: <strong><?= htmlspecialchars($den["cnpj"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Localidade: <strong><?= htmlspecialchars($den["cidade_clinica"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($den["estado_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>

                            <div class="bloco-subtitulo">Contato do Tutor Denunciante</div>
                            <div>Nome do Tutor: <strong><?= htmlspecialchars($den["nome_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CPF do Tutor: <strong><?= htmlspecialchars($den["cpf_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>E-mail do Tutor: <strong><?= htmlspecialchars($den["email_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Telefone do Tutor: <strong><?= htmlspecialchars($den["telefone_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>

                            <div class="bloco-subtitulo">Detalhes da Denúncia (Data: <?= date('d/m/Y H:i', strtotime($den["data_denuncia"])) ?>)</div>
                            <div class="descricao-caixa">
                                <?= nl2br(htmlspecialchars($den["descricao_detalhada"], ENT_QUOTES, "UTF-8")) ?>
                            </div>
                        </div>
                        <div class="acoes-card">
                            <?php if (!empty($den["evidencia"])): ?>
                                <a href="visualizar_documentos.php?tipo=evidencia&id_denuncia=<?= $den["id_denuncia"] ?>" target="_blank" class="btn-acao btn-visualizar">
                                    Visualizar Evid&ecirc;ncia
                                </a>
                            <?php endif; ?>
                            <form action="" method="POST" style="margin:0; display:inline-flex; gap:8px;">
                                <input type="hidden" name="id_denuncia" value="<?= $den["id_denuncia"] ?>">
                                <input type="hidden" name="acao" value="mudar_status">
                                <button type="submit" name="novo_status" value="procedente" class="btn-acao btn-procedente">Marcar Procedente</button>
                                <button type="submit" name="novo_status" value="arquivada" class="btn-acao btn-arquivar">Arquivar Denúncia</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma denúncia em análise encontrada.</div>
            <?php endif; ?>
        </section>

        <!-- ================= SEÇÃO 3: PROCEDENTES ================= -->
        <section class="secao-denuncias">
            <h2 class="secao-titulo">Den&uacute;ncias Procedentes</h2>
            <?php if (!empty($secoes['procedente'])): ?>
                <?php foreach ($secoes['procedente'] as $den): ?>
                    <div class="card-denuncia">
                        <div class="card-topo">
                            <h3>Motivo: <?= htmlspecialchars($den["motivo"], ENT_QUOTES, "UTF-8") ?></h3>
                            <span class="badge badge-procedente">Procedente</span>
                        </div>
                        <div class="info-grid">
                            <div class="bloco-subtitulo">Dados da Clínica Denunciada</div>
                            <div>Clínica: <strong><?= htmlspecialchars($den["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CNPJ da Clínica: <strong><?= htmlspecialchars($den["cnpj"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Localidade: <strong><?= htmlspecialchars($den["cidade_clinica"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($den["estado_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>

                            <div class="bloco-subtitulo">Contato do Tutor Denunciante</div>
                            <div>Nome do Tutor: <strong><?= htmlspecialchars($den["nome_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CPF do Tutor: <strong><?= htmlspecialchars($den["cpf_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>E-mail do Tutor: <strong><?= htmlspecialchars($den["email_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Telefone do Tutor: <strong><?= htmlspecialchars($den["telefone_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>

                            <div class="bloco-subtitulo">Detalhes da Denúncia (Data: <?= date('d/m/Y H:i', strtotime($den["data_denuncia"])) ?>)</div>
                            <div class="descricao-caixa">
                                <?= nl2br(htmlspecialchars($den["descricao_detalhada"], ENT_QUOTES, "UTF-8")) ?>
                            </div>
                        </div>
                        <div class="acoes-card">
                            <?php if (!empty($den["evidencia"])): ?>
                                <a href="visualizar_documentos.php?tipo=evidencia&id_denuncia=<?= $den["id_denuncia"] ?>" target="_blank" class="btn-acao btn-visualizar">
                                    Visualizar Evid&ecirc;ncia
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma denúncia procedente encontrada.</div>
            <?php endif; ?>
        </section>

        <!-- ================= SEÇÃO 4: ARQUIVADAS ================= -->
        <section class="secao-denuncias">
            <h2 class="secao-titulo">; Den&uacute;ncias Arquivadas</h2>
            <?php if (!empty($secoes['arquivada'])): ?>
                <?php foreach ($secoes['arquivada'] as $den): ?>
                    <div class="card-denuncia">
                        <div class="card-topo">
                            <h3>Motivo: <?= htmlspecialchars($den["motivo"], ENT_QUOTES, "UTF-8") ?></h3>
                            <span class="badge badge-arquivada">Arquivada</span>
                        </div>
                        <div class="info-grid">
                            <div class="bloco-subtitulo">Dados da Clínica Denunciada</div>
                            <div>Clínica: <strong><?= htmlspecialchars($den["nome_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CNPJ da Clínica: <strong><?= htmlspecialchars($den["cnpj"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Localidade: <strong><?= htmlspecialchars($den["cidade_clinica"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($den["estado_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>

                            <div class="bloco-subtitulo">Contato do Tutor Denunciante</div>
                            <div>Nome do Tutor: <strong><?= htmlspecialchars($den["nome_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>CPF do Tutor: <strong><?= htmlspecialchars($den["cpf_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>E-mail do Tutor: <strong><?= htmlspecialchars($den["email_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>
                            <div>Telefone do Tutor: <strong><?= htmlspecialchars($den["telefone_tutor"], ENT_QUOTES, "UTF-8") ?></strong></div>

                            <div class="bloco-subtitulo">Detalhes da Denúncia (Data: <?= date('d/m/Y H:i', strtotime($den["data_denuncia"])) ?>)</div>
                            <div class="descricao-caixa">
                                <?= nl2br(htmlspecialchars($den["descricao_detalhada"], ENT_QUOTES, "UTF-8")) ?>
                            </div>
                        </div>
                        <div class="acoes-card">
                            <?php if (!empty($den["evidencia"])): ?>
                                <a href="visualizar_documentos.php?tipo=evidencia&id_denuncia=<?= $den["id_denuncia"] ?>" target="_blank" class="btn-acao btn-visualizar">
                                    Visualizar Evid&ecirc;ncia
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vazio-secao">Nenhuma denúncia arquivada encontrada.</div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>