<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . "/conexao.php";

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
$clinica = null;
$total_denuncias = 0;
$cnpj_busca_input = trim($_GET["cnpj"] ?? "");

// ========================================
// 1. PROCESSAR AÇÃO DE SUSPENDER (POST)
// ========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "confirmar_suspensao") {
    $id_clinica = filter_input(INPUT_POST, "id_clinica", FILTER_VALIDATE_INT);

    if ($id_clinica && $id_clinica > 0) {
        $sql_susp = "UPDATE clinica SET suspenso = 1, status_cadastro = 'suspensa' WHERE id_clinica = ?";
        $stmt_s = $conn->prepare($sql_susp);
        $stmt_s->bind_param("i", $id_clinica);

        if ($stmt_s->execute() && $stmt_s->affected_rows > 0) {
            $mensagem = "Clínica suspensa com sucesso!";
            $tipo_mensagem = "sucesso";
        } else {
            $mensagem = "Erro ao suspender a clínica ou ela já se encontra suspensa.";
            $tipo_mensagem = "erro";
        }
        $stmt_s->close();
    }
}

// ========================================
// 2. BUSCAR CLÍNICA E CONTAGEM DE DENÚNCIAS
// ========================================
if ($cnpj_busca_input !== "") {
    // Remove a máscara (pontos, barras e traços) para buscar apenas os números no banco
    $cnpj_limpo = preg_replace('/\D/', '', $cnpj_busca_input);

    $sql_cli = "
        SELECT 
            c.id_clinica,
            c.razao_social,
            c.nome_clinica,
            c.email AS email_clinica,
            c.telefone AS telefone_clinica,
            c.cep,
            c.endereco,
            c.numero,
            c.bairro,
            c.cidade,
            c.estado,
            c.cnpj,
            c.suspenso,
            c.status_cadastro,
            d.nome_completo AS nome_dono,
            d.cpf AS cpf_dono
        FROM clinica c
        INNER JOIN dono_clinica d ON c.id_dono = d.id_dono
        WHERE c.cnpj = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql_cli);
    $stmt->bind_param("s", $cnpj_limpo);
    $stmt->execute();
    $clinica = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($clinica) {
        $sql_den = "SELECT COUNT(*) AS total FROM denuncias WHERE id_clinica = ?";
        $stmt_d = $conn->prepare($sql_den);
        $stmt_d->bind_param("i", $clinica["id_clinica"]);
        $stmt_d->execute();
        $res_den = $stmt_d->get_result()->fetch_assoc();
        $total_denuncias = $res_den["total"] ?? 0;
        $stmt_d->close();
    } else {
        $mensagem = "Nenhuma clínica encontrada com o CNPJ informado.";
        $tipo_mensagem = "erro";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suspender Clínica - SISVET</title>
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
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--borda);
            box-shadow: 0 2px 6px rgba(11, 52, 120, 0.04);
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

        .mensagem {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
        }
        .mensagem-sucesso { background-color: var(--verde-bg); border: 1px solid #A6F4C5; color: var(--verde); }
        .mensagem-erro { background-color: var(--vermelho-bg); border: 1px solid #FECDCA; color: var(--vermelho); }

        .card-resultado {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(11, 52, 120, 0.06);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .card-topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--borda);
            padding-bottom: 12px;
        }
        .card-topo h2 {
            margin: 0;
            color: var(--azul-escuro);
            font-size: 20px;
        }
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-ativa { background-color: var(--verde-bg); color: var(--verde); border: 1px solid #A6F4C5; }
        .badge-suspensa { background-color: var(--vermelho-bg); color: var(--vermelho); border: 1px solid #FECDCA; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            font-size: 14px;
            color: var(--texto-secundario);
            background: #FAFAFC;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #E4E7EC;
        }
        .info-grid strong { color: var(--texto); }

        .alerta-denuncias {
            background-color: var(--amarelo-bg);
            border: 1px solid #FEC84B;
            color: var(--amarelo-txt);
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .acoes-card {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 1px dashed var(--borda);
        }
        .btn-suspender {
            background-color: var(--vermelho);
            color: #FFF;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-suspender:hover { background-color: #912018; }
    </style>
</head>
<body>
    <main class="container">
        <header class="cabecalho">
            <div>
                <h1>Suspender Cl&iacute;nica</h1>
                <p>Consulte uma clínica pelo CNPJ para analisar suas informações, histórico de denúncias e aplicar suspensão.</p>
            </div>
            <a href="painel_admin.php" class="btn-voltar">Voltar ao Painel</a>
        </header>

        <!-- Formulário de Busca por CNPJ com Máscara Visual -->
        <form method="GET" action="" class="form-busca">
            <input type="text" id="cnpj_busca" name="cnpj" placeholder="Digite o CNPJ (ex: 00.000.000/0000-00)..." value="<?= htmlspecialchars($cnpj_busca_input, ENT_QUOTES, 'UTF-8') ?>" maxlength="18" required>
            <button type="submit" class="btn-pesquisar">Pesquisar Clínica</button>
        </form>

        <?php if (!empty($mensagem)): ?>
            <div class="mensagem mensagem-<?= $tipo_mensagem ?>"><?= $mensagem ?></div>
        <?php endif; ?>

        <?php if ($clinica): ?>
            <div class="card-resultado">
                <div class="card-topo">
                    <h2><?= htmlspecialchars($clinica["nome_clinica"], ENT_QUOTES, "UTF-8") ?></h2>
                    <?php if ($clinica["suspenso"] == 1): ?>
                        <span class="badge badge-suspensa">Status: Suspensa</span>
                    <?php else: ?>
                        <span class="badge badge-ativa">Status: Ativa / Autorizada</span>
                    <?php endif; ?>
                </div>

                <div class="alerta-denuncias">
                    <span>&#9888; Total de Denúncias Registradas contra esta Clínica:</span>
                    <span style="font-size: 18px;"><?= $total_denuncias ?> denúndia(s)</span>
                </div>

                <div class="info-grid">
                    <div>Razão Social: <strong><?= htmlspecialchars($clinica["razao_social"], ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div>CNPJ: <strong><?= htmlspecialchars($clinica["cnpj"], ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div>E-mail: <strong><?= htmlspecialchars($clinica["email_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div>Telefone: <strong><?= htmlspecialchars($clinica["telefone_clinica"], ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div>Localidade: <strong><?= htmlspecialchars($clinica["cidade"], ENT_QUOTES, "UTF-8") ?> / <?= htmlspecialchars($clinica["estado"], ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div>Endereço: <strong><?= htmlspecialchars($clinica["endereco"], ENT_QUOTES, "UTF-8") ?>, Nº <?= htmlspecialchars($clinica["numero"], ENT_QUOTES, "UTF-8") ?> - <?= htmlspecialchars($clinica["bairro"], ENT_QUOTES, "UTF-8") ?> (CEP: <?= htmlspecialchars($clinica["cep"], ENT_QUOTES, "UTF-8") ?>)</strong></div>
                    <div>Proprietário(a): <strong><?= htmlspecialchars($clinica["nome_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                    <div>CPF do Dono: <strong><?= htmlspecialchars($clinica["cpf_dono"], ENT_QUOTES, "UTF-8") ?></strong></div>
                </div>

                <?php if ($clinica["suspenso"] == 0): ?>
                    <div class="acoes-card">
                        <form action="" method="POST" onsubmit="return confirm('Tem certeza de que deseja suspender esta clínica?');">
                            <input type="hidden" name="id_clinica" value="<?= $clinica["id_clinica"] ?>">
                            <button type="submit" name="acao" value="confirmar_suspensao" class="btn-suspender">
                                Confirmar Suspensão da Clínica
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="color: var(--vermelho); font-weight: bold; text-align: right; font-size: 13px; margin-top: 5px;">
                        Esta clínica já se encontra suspensa no sistema.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Script JavaScript para aplicar a máscara visualmente -->
    <script>
        document.getElementById('cnpj_busca').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não for dígito
            
            if (value.length > 14) {
                value = value.substring(0, 14);
            }

            if (value.length > 12) {
                value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            } else if (value.length > 8) {
                value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4})$/, '$1.$2.$3/$4');
            } else if (value.length > 5) {
                value = value.replace(/^(\d{2})(\d{3})(\d{0,3})$/, '$1.$2.$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d{0,3})$/, '$1.$2');
            }

            e.target.value = value;
        });
    </script>
</body>
</html>