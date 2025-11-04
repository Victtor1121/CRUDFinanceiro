<?php
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// usuário logado (ajuste conforme seu sistema)
$usuario_id = $_SESSION['usuario_id'] ?? 1;
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// --- Handlers: CREATE / UPDATE / DELETE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // normaliza campos comuns
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'add') {
        $tipo = $_POST['tipo'] === 'receita' ? 'receita' : 'despesa';
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = floatval(str_replace(',', '.', ($_POST['valor'] ?? 0)));
        $data = $_POST['data'] ?? date('Y-m-d');
        $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;

        // FORÇA TIPO A PARTIR DA CATEGORIA (validação servidor)
    if ($categoria_id) {
      $cstmt = $pdo->prepare("SELECT tipo FROM categorias WHERE id = ?");
      $cstmt->execute([$categoria_id]);
      $catTipo = $cstmt->fetchColumn();
      if ($catTipo && in_array($catTipo, ['receita','despesa'])) {
        $tipo = $catTipo;
      }
    }

        $sql = "INSERT INTO transacoes (usuario_id, categoria_id, tipo, descricao, valor, data_transacao)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $categoria_id, $tipo, $descricao, $valor, $data]);

        header('Location: dashboard.php');
        exit;
    }

    if ($acao === 'editar') {
        $id = intval($_POST['id'] ?? 0);
        $tipo = $_POST['tipo'] === 'receita' ? 'receita' : 'despesa';
        $descricao = trim($_POST['descricao'] ?? '');
        $valor = floatval(str_replace(',', '.', ($_POST['valor'] ?? 0)));
        $data = $_POST['data'] ?? date('Y-m-d');
        $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;

        $sql = "UPDATE transacoes SET categoria_id = ?, tipo = ?, descricao = ?, valor = ?, data_transacao = ?
                WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$categoria_id, $tipo, $descricao, $valor, $data, $id, $usuario_id]);

        header('Location: dashboard.php');
        exit;
    }

    if ($acao === 'deletar') {
        $id = intval($_POST['id'] ?? 0);
        $sql = "DELETE FROM transacoes WHERE id = ? AND usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $usuario_id]);

        header('Location: dashboard.php');
        exit;
    }
}

// --- Dados para exibição ---
// resumo
$resumoStmt = $pdo->prepare("
    SELECT 
       SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS total_receitas,
       SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS total_despesas
    FROM transacoes
    WHERE usuario_id = ?
");
$resumoStmt->execute([$usuario_id]);
$resumo = $resumoStmt->fetch(PDO::FETCH_ASSOC);
$total_receitas = floatval($resumo['total_receitas'] ?? 0);
$total_despesas = floatval($resumo['total_despesas'] ?? 0);
$saldo = $total_receitas - $total_despesas;

// categorias (globais e do usuário se existir coluna usuario_id -> ajuste se não tiver)
try {
    // se tabela categorias não tiver usuario_id, esta query continuará funcionando (sem WHERE)
    $catStmt = $pdo->prepare("
    SELECT id, nome, tipo 
    FROM categorias 
    WHERE usuario_id IS NULL OR usuario_id = ?
    ORDER BY nome
");
$catStmt->execute([$usuario_id]);
$categorias = $catStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // fallback: categorias mínimas
    $categorias = [];
}

// transações do usuário
$txStmt = $pdo->prepare("
    SELECT t.id, t.tipo, t.descricao, t.valor, t.data_transacao, c.nome AS categoria
    FROM transacoes t
    LEFT JOIN categorias c ON t.categoria_id = c.id
    WHERE t.usuario_id = ?
    ORDER BY t.data_transacao DESC, t.id DESC
    LIMIT 5
");
$txStmt->execute([$usuario_id]);
$transacoes = $txStmt->fetchAll(PDO::FETCH_ASSOC);

// se id para editar via GET (abrir formulário de edição)
$editMode = false;
$editarRegistro = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id'])) {
    $idEdit = intval($_GET['id']);
    $q = $pdo->prepare("SELECT * FROM transacoes WHERE id = ? AND usuario_id = ?");
    $q->execute([$idEdit, $usuario_id]);
    $editarRegistro = $q->fetch(PDO::FETCH_ASSOC);
    if ($editarRegistro) $editMode = true;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Dashboard - Financeiro</title>
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.4.0"></script>



  <style>
    .align-right { text-align: right; }
    .table { width:100%; border-collapse: collapse; }
    .table th, .table td { padding:8px;border-bottom:1px solid #eeeeee29; } 
    .table tbody tr:hover {
  border-left: 1px solid #00b4d8;
}

    .text-success { 
      color: #0a8;
      background-color:rgba(5, 107, 56, 0.27);

    } 
    .text-danger { 
      color:#d00; 
      background-color:rgba(100, 41, 18, 0.18);
    }
    .btn-mini-excluir{
    color: #d00;
    background-color: transparent;
    border: none;
    margin-left: -5px;
    cursor: pointer;
    }
    .btn-mini-editar{
    color: #00b4d8;
    background-color: transparent;
    border: none;
    }
    .btn-mini {
  padding: 4px 8px;
  border-radius: 8px;
  font-size: 0.85rem;
  cursor: pointer;
}

    .panel form label {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-weight: 500;
  font-size: 0.85rem;
  color: #dce7f3;
}

.input-panel,
.input-panel-tip,
.input-panel-cat {
  background: rgba(255,255,255,0.12)!important;
  border: 1px solid rgba(255,255,255,0.18)!important;
  border-radius: 14px!important;
  padding: 10px 14px!important;
  font-size: .9rem;
  color: #fff!important;
  transition: 0.25s!important;
  outline: none;
}
.tipo-disabled{
  cursor: not-allowed;
}

.input-panel:focus,
.input-panel-tip:focus,
.input-panel-cat:focus{
  border-color:#00b4d8!important;
  box-shadow: 0 0 10px rgba(0,180,216,0.5);
}

.grid-form{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:14px;
  width:100%;
  margin-top:10px;
}

@media(min-width:460px){
  .grid-form{
    grid-template-columns: 1fr 1fr;
  }
  .grid-form label:nth-child(n+3){
    grid-column:1 / span 2;
  }
}

.grid-form label:nth-child(n+3){
  grid-column: 1 / span 2;
}

.grid-form > div:last-child{
  grid-column: 1 / span 2; /* ocupa largura total */
  width:100%;
  display:block;
}

.btn-custom{
  width:100% !important;
}
.input-panel-cat option,
.input-panel-tip option {
  background: #0d1b2a;        /* fundo igual seu tema base */
  color: #eafdff;             /* texto claro legível */
  padding: 10px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  font-size: .9rem;
}

.input-panel-cat option:hover,
.input-panel-tip option:hover,
.input-panel-cat option:focus,
.input-panel-tip option:focus {
  background: #00b4d8;
  color: #fff;
}

  </style>
</head>
<body>
  <div class="app-layout">
    <aside class="sidebar">
      <div class="brand"><div class="logo">Financeiro</div><div class="small">Controle Pessoal</div></div>
      <?php
$current = basename($_SERVER['PHP_SELF']); // ex: 'dashboard.php'
?>
<nav class="nav">
  <a class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
    <i class="bi bi-speedometer2"></i> Dashboard
  </a>

  <a class="nav-item <?= $current === 'transacoes.php' ? 'active' : '' ?>" href="transacoes.php">
    <i class="bi bi-wallet2"></i> Transações
  </a>

  <a class="nav-item <?= $current === 'categorias.php' ? 'active' : '' ?>" href="categorias.php">
    <i class="bi bi-tags"></i> Categorias
  </a>

  <a class="nav-item <?= $current === 'relatorios.php' ? 'active' : '' ?>" href="relatorios.php">
    <i class="bi bi-bar-chart"></i> Relatórios
  </a>

  <a class="nav-item <?= $current === 'perfil.php' ? 'active' : '' ?>" href="perfil.php">
    <i class="bi bi-person-circle"></i> Meu Perfil
  </a>

  <a class="nav-item logout <?= $current === 'logout.php' ? 'active' : '' ?>" href="logout.php">
    <i class="bi bi-box-arrow-right"></i> Sair
  </a>
</nav>


    </aside>

    <main class="main">
      <header class="topbar">
        <div class="welcome">
          <h1>Olá, <span class="username"><?= htmlspecialchars($usuario_nome) ?></span></h1>
          <p class="muted">Resumo rápido da sua saúde financeira</p>
        </div>
      </header>

      <section class="hero-cards">
        <div class="card-hero card-receitas" style="color: white;">
        <div class="card-content">
            <div class="card-icon receita">
                <i class="bi bi-arrow-up-right-square"></i> <!-- Ícone de Receita -->
            </div>
            <div class="card-info">
                <div class="card-head">Receitas</div>
                <div class="card-value"><?= number_format($total_receitas, 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
        <div class="card-hero card-despesas" style="color: white;">
        <div class="card-content">
            <div class="card-icon despesas" >
                <i class="bi bi-arrow-down-left-square"></i> 
            </div>
            <div class="card-info">
                <div class="card-head">Despesas</div>
                <div class="card-value"><?= number_format($total_despesas, 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
        <div class="card-hero card-balance" style="color: white;">
        <div class="card-content">
            <div class="card-icon saldo">
                <i class="bi bi-arrow-right-square"></i> 
            </div>
            <div class="card-info">
                <div class="card-head">Saldo</div>
                <div class="card-value"><?= number_format($saldo, 2, ',', '.') ?></div>
            </div>
        </div>
        </div>
      </section>

      <div class="content-grid" style="display:flex;gap:16px;">
        <section class="center-col" style="flex:1;">
          <div class="panel">
            <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
              <h3>Transações recentes</h3>
            </div>

            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Tipo</th>
                    <th class="align-right">Valor</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($transacoes)): ?>
                    <?php foreach ($transacoes as $t): ?>
                      <tr data-id="<?= $t['id'] ?>">
                        <td><?= date('d/m/Y', strtotime($t['data_transacao'])) ?></td>
                        <td><?= htmlspecialchars($t['descricao']) ?></td>
                        <td><?= htmlspecialchars($t['categoria'] ?? '-') ?></td>
                        <td>
              <span class="tx-tipo <?= $t['tipo'] ?>">
                <?= ucfirst($t['tipo']) ?>
              </span>
            </td>
                        <td class="align-right">R$ <?= number_format($t['valor'], 2, ',', '.') ?></td>
                        <td>
              <div class="table-action">
                <a href="dashboard.php?action=edit&id=<?= $t['id'] ?>" class="btn-edit btn-mini" data-tooltip="Editar transação">
                  <i class="bi bi-pencil-square"></i>
                </a>

                <form method="post" action="dashboard.php" style="display:inline;" onsubmit="return confirm('Excluir transação?');">
                  <input type="hidden" name="acao" value="deletar">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button class="btn-delete btn-mini" data-tooltip="Excluir Transação">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="6" class="text-center">Nenhuma transação encontrada.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            
          </div>
          <div class="panel" style="margin-top:20px;">
              <h4>Evolução do Saldo</h4>
              <canvas id="chartSaldo" style="height:240px;"></canvas>
            </div>
        </section>

        


        <aside class="right-col" style="width:360px;">
          <div class="panel" style="margin-bottom:12px;">
            <h4><?= $editMode ? 'Editar transação' : 'Nova transação' ?></h4>

            <?php if ($editMode && $editarRegistro): ?>
              <form method="post">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id" value="<?= intval($editarRegistro['id']) ?>">

                <div class="grid-form">

                  <label>Data <input class="input-panel" type="date" name="data" data-tooltip="Insira a data" value="<?= htmlspecialchars($editarRegistro['data_transacao']) ?>" required></label>
                  <label>Tipo
                    <select name="tipo_display" class="input-panel-tip tipo-disabled" disabled aria-disabled="true">
                      <option value="receita">Receita</option>
                      <option value="despesa">Despesa</option>
                    </select>
  <!-- campo que realmente será enviado no POST -->
  <input type="hidden" name="tipo" value="<?= isset($editarRegistro) ? htmlspecialchars($editarRegistro['tipo']) : 'despesa' ?>">
</label>
                  <label>Categoria
                    <select name="categoria_id" class="input-panel-cat">
                      <option value="">-- Sem categoria --</option>
                      <?php foreach ($categorias as $c): ?>
  <option value="<?= $c['id'] ?>"
          data-tipo="<?= htmlspecialchars($c['tipo']) ?>"
          <?= ($editarRegistro['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
    <?= htmlspecialchars($c['nome']) ?>
  </option>
<?php endforeach; ?>

                    </select>
                  </label>
                  <label>Valor <input class="input-panel"  type="number" step="0.01" name="valor" value="<?= number_format($editarRegistro['valor'], 2, '.', '') ?>" required></label>
                  <label>Descrição <input class="input-panel" type="text" name="descricao" value="<?= htmlspecialchars($editarRegistro['descricao']) ?>"></label>
                  <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn-custom" data-tooltip="Salvar Alteração">Salvar</button>
                    <button href="dashboard.php" class="btn-custom" id="cancela" data-tooltip="Cancelar Alteração">Cancelar</button>
                  </div>
                </div>
              </form>

            <?php else: ?>
              <form method="post">
                <input type="hidden" name="acao" value="add">
                <div class="grid-form">

                  <label>Data <input class="input-panel" type="date" name="data" value="<?= date('Y-m-d') ?>" required></label>
                  <label>Tipo

                  <select name="tipo_display" class="input-panel-tip tipo-disabled" disabled aria-disabled="true">
                    <option value="receita">Receita</option>
                    <option value="despesa">Despesa</option>
                  </select>

                  <!-- campo que realmente será enviado no POST -->
                  <input type="hidden" name="tipo" value="<?= isset($editarRegistro) ? htmlspecialchars($editarRegistro['tipo']) : 'despesa' ?>">
                </label>
                  <label>Categoria
                    <select name="categoria_id" class="input-panel-cat">
                      <option value="">-- Sem categoria --</option>
                      <?php foreach ($categorias as $c): ?>
  <option value="<?= $c['id'] ?>" data-tipo="<?= htmlspecialchars($c['tipo']) ?>">
    <?= htmlspecialchars($c['nome']) ?>
  </option>
<?php endforeach; ?>

                    </select>
                  </label>
                  <label>Valor 

                    <input  class="input-panel" type="number" step="0.01" name="valor" value="" required></label>

                    <label>Descrição 
                    <input class="input-panel" type="text" name="descricao" value=""></label>
                  <div class="div-button">
                    <button type="submit" class="btn-custom" data-tooltip="Adicionar transação">Adicionar</button>
                  </div>
                </div>
              </form>
            <?php endif; ?>

          </div>

          <div class="panel">
            <h4>Resumo rápido</h4>
            <p><strong>Saldo:</strong> R$ <?= number_format($saldo, 2, ',', '.') ?></p>
            <p><strong>Receitas:</strong> R$ <?= number_format($total_receitas, 2, ',', '.') ?></p>
            <p><strong>Despesas:</strong> R$ <?= number_format($total_despesas, 2, ',', '.') ?></p>
          </div>
        </aside>
      </div>

      

    </main>
  </div>

  <?php
// pega histórico de saldo acumulado por data
$saldoHistorico = [];
$stmtHist = $pdo->prepare("
    SELECT DATE(data_transacao) AS data,
      SUM(CASE WHEN tipo='receita' THEN valor ELSE -valor END) AS impacto
    FROM transacoes
    WHERE usuario_id = ?
    GROUP BY DATE(data_transacao)
    ORDER BY DATE(data_transacao) ASC
");
$stmtHist->execute([$usuario_id]);
$rowsHist = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

// se não tiver transação, evitar erro e já iniciar com saldo atual
// monta histórico de saldo diário de forma robusta (substitua o bloco anterior)
$saldoHistorico = [];

// já temos $rowsHist com DATE(data_transacao) AS data e impacto por data
// indexa impacto por data (formato YYYY-MM-DD) e força float
$map = [];
foreach ($rowsHist as $r) {
    $key = trim($r['data']); // garante sem espaços
    $map[$key] = floatval($r['impacto']);
}

// 1) determina a data inicial: tenta usar a primeira linha retornada, se não, usa hoje
if (!empty($rowsHist) && !empty($rowsHist[0]['data'])) {
    $start = strtotime($rowsHist[0]['data']);
} else {
    $start = strtotime(date('Y-m-d'));
}

$end = strtotime('+1 days', strtotime(date('Y-m-d')));


// 3) pega saldo acumulado antes do primeiro dia do intervalo (pode ser 0)
$primeiraData = date('Y-m-d', $start);
$saldoInicialStmt = $pdo->prepare("
    SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE -valor END), 0) AS ini
    FROM transacoes
    WHERE usuario_id = ? AND DATE(data_transacao) < ?
");
$saldoInicialStmt->execute([$usuario_id, $primeiraData]);
$saldoInicial = floatval($saldoInicialStmt->fetchColumn());

// 4) acumula dia a dia, garantindo que o dia atual seja incluído
$totalAcu = $saldoInicial;
for ($d = $start; $d <= $end; $d += 86400) {
    $dataAtualYMD = date('Y-m-d', $d);
    if (isset($map[$dataAtualYMD])) {
        $totalAcu += $map[$dataAtualYMD];
    }
    $saldoHistorico[] = [
        'data'  => date('d/m', $d),
        'saldo' => $totalAcu
    ];
}

// se por alguma razão o array ficou vazio (proteção), inserir ponto com saldo atual
if (empty($saldoHistorico)) {
    $saldoHistorico[] = [
        'data'  => date('d/m'),
        'saldo' => floatval($saldo)
    ];
}

?>


<script >
  
const ctx = document.getElementById('chartSaldo').getContext('2d');

const saldoFinal = <?= json_encode($saldo) ?>;

new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($saldoHistorico,'data')) ?>,
    datasets: [{
      label: 'Evolução do Saldo',
      data: <?= json_encode(array_map('floatval', array_column($saldoHistorico,'saldo'))) ?>,
      tension: 0.35,
      borderWidth: 2
    }]
  },
  options: {
  plugins: { 
    legend: { labels: { color: 'white' } },
    annotation: {
      annotations: {
        saldoLine: {
          type: 'line',
          yMin: saldoFinal,
          yMax: saldoFinal,
          borderWidth: 1.5,
          borderColor: 'white', // usa cor padrao do tema (pode ajustar)
          borderDash: [4,4],
          label: {
            display: true,
            content: 'Saldo Final',
            position: 'start',
            backgroundColor: 'rgba(255,255,255,.15)',
            color: 'white'
          }
        }
      }
    }
  },
  scales: {
    x: { ticks: { color: 'white' } },
    y: { ticks: { color: 'white' } }
  }
}

});

</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // para cada form que tenha categoria + tipo_hidden
  document.querySelectorAll('form').forEach(function(form) {
    const selCategoria = form.querySelector('select[name="categoria_id"]');
    const selTipoHidden = form.querySelector('input[name="tipo"]');
    const selTipoDisplay = form.querySelector('select[name="tipo_display"]');

    if (!selCategoria || !selTipoHidden || !selTipoDisplay) return;

    function aplicarTipoDaCategoria() {
      const opt = selCategoria.options[selCategoria.selectedIndex];
      if (!opt) return;
      const tipo = opt.dataset.tipo; // exige data-tipo nas options
      if (tipo === 'receita' || tipo === 'despesa') {
        selTipoHidden.value = tipo;
        // atualizar select visível para mostrar o label certo
        selTipoDisplay.value = tipo;
      } else {
        // sem categoria selecionada -> mantém valor padrão (não limpa o hidden)
      }
    }

    selCategoria.addEventListener('change', aplicarTipoDaCategoria);
    aplicarTipoDaCategoria(); // aplica no carregamento (modo edição / preload)
  });
});


</script>
<script>
document.addEventListener("mousemove", e => {
  const t = e.target.closest("[data-tooltip]");
  if (!t) return;

  const tooltip = t.getAttribute("data-tooltip");
  const el = t;

  const rect = el.getBoundingClientRect();
  const tipWidth = tooltip.length * 6.8;

  let x = rect.left + rect.width / 2 - tipWidth / 2;
  if (x < 5) x = 5;
  if (x + tipWidth > window.innerWidth) x = window.innerWidth - tipWidth - 5;

  el.style.setProperty("--tooltip-x", x + "px");
});
document.querySelectorAll("select[data-tooltip]").forEach(sel => {
  sel.addEventListener("mouseover", e => {
    const tip = document.createElement("div");
    tip.className = "tooltip-js";
    tip.innerText = sel.dataset.tooltip;
    document.body.appendChild(tip);

    const rect = sel.getBoundingClientRect();
    tip.style.top = rect.top - 32 + "px";
    tip.style.left = rect.left + "px";

    sel.addEventListener("mouseout", () => tip.remove(), { once: true });
  });
});

</script>

</body>
</html>
