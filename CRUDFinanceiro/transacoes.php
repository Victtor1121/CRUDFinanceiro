<?php
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

/* Buscar todas as transações */
$stmt = $pdo->prepare("
    SELECT t.id, t.tipo, t.descricao, t.valor, t.data_transacao, c.nome AS categoria
    FROM transacoes t
    LEFT JOIN categorias c ON t.categoria_id = c.id
    WHERE t.usuario_id = ?
    ORDER BY t.data_transacao DESC, t.id DESC
");
$stmt->execute([$usuario_id]);
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Gráfico por Tipo */
$pieTipoStmt = $pdo->prepare("
    SELECT tipo, COUNT(*) AS total 
    FROM transacoes
    WHERE usuario_id = ?
    GROUP BY tipo
");
$pieTipoStmt->execute([$usuario_id]);
$transTipo = $pieTipoStmt->fetchAll(PDO::FETCH_ASSOC);

/* Gráfico por Categoria */
$pieCatStmt = $pdo->prepare("
  SELECT IFNULL(c.nome, 'Sem categoria') AS categoria,
        COUNT(*) AS total,
        c.cor AS cor_hex
  FROM transacoes t
  LEFT JOIN categorias c ON t.categoria_id = c.id
  WHERE t.usuario_id = ?
  GROUP BY c.nome, c.cor
  ORDER BY total DESC
");
$pieCatStmt->execute([$usuario_id]);
$transCat = $pieCatStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Transações</title>

<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.table-scroll {
  max-height: 420px;
  overflow-y: auto;
  margin-top: 10px;
  padding-right: 5px;
}
@media (max-width: 900px) {
  .panel.charts-grid {
    grid-template-columns: 1fr !important;
  }
}
.chart-legend {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.chart-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  font-weight: 500;
  color: #eaf9ff;
  opacity: 0.85;
}

.chart-legend-color {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: inline-block;
}

.table-scroll::-webkit-scrollbar {
  width: 8px;
}
.table-scroll::-webkit-scrollbar-thumb {
  background: #00b4d8;
  border-radius: 8px;
}
.transactions-table {
  width: 100% !important;
  min-width: 0 !important;
}

.transactions-table td,
.transactions-table th {
  padding: 10px 6px; /* diminuir espaçamento */
  white-space: nowrap; /* não deixa quebrar texto */
}
.btn-mini {
  padding: 4px 8px;
  border-radius: 8px;
  font-size: 0.85rem;
  cursor: pointer;
}
</style>
</head>

<body>
<div class="app-layout">

<aside class="sidebar">
  <div class="brand">
    <div class="logo">Financeiro</div>
    <div class="small">Controle Pessoal</div>
  </div>
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
    <h1>Transações</h1>
    <p class="muted">Histórico completo das suas movimentações</p>
  </div>
</header>

<div class="content-grid" style="grid-template-columns: 1fr;">
<div class="panel">
    <h4>Lista de Transações</h4>

    <div class="table-scroll">
      <table class="transactions-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Descrição</th>
            <th>Categoria</th>
            <th>Tipo</th>
            <th class="align-right">Valor</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($transacoes): foreach($transacoes as $t): ?>
          <tr>
            <td><?= date('d/m/Y', strtotime($t['data_transacao'])) ?></td>
            <td><?= htmlspecialchars($t['descricao']) ?></td>
            <td><?= htmlspecialchars($t['categoria'] ?? '-') ?></td>
            <td>
              <span class="tx-tipo <?= $t['tipo'] ?>">
                <?= ucfirst($t['tipo']) ?>
              </span>
            </td>
            <td class="align-right">
              R$ <?= number_format($t['valor'], 2, ',', '.') ?>
            </td>
            <td>
              <div class="table-action">
                <a href="dashboard.php?action=edit&id=<?= $t['id'] ?>" class="btn-edit btn-mini" data-tooltip="Editar transação">
                  <i class="bi bi-pencil-square"></i>
                </a>

                <form method="post" action="dashboard.php" style="display:inline;" onsubmit="return confirm('Excluir transação?');">
                  <input type="hidden" name="acao" value="deletar">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>" >
                  <button class="btn-delete btn-mini" data-tooltip="Excluir transação">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5">Nenhuma transação encontrada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
    <!-- PAINEL GRÁFICO TIPO -->
    <div class="panel" style="display:flex;flex-direction:column;align-items:center;">
    <h4>Transações por Tipo</h4>
    <div style="width:260px;">

      <canvas id="pieTipo"></canvas>
    </div>
    </div>

<!-- PAINEL GRÁFICO CATEGORIA -->
  <div class="panel" style="display:flex;flex-direction:column;align-items:center;">
    <h4>Transações por Categoria</h4>
    <div style="width:260px;">
    

      <canvas id="pieCat"></canvas>
    </div>
  </div>
    </div>
</div>

</main>
</div>

<script>
(function(){
    
  // Dados vindos do PHP
  const tipoLabels = <?= json_encode(array_column($transTipo, 'tipo')) ?> || [];
  const tipoData   = <?= json_encode(array_column($transTipo, 'total')) ?> || [];

  const catLabels = <?= json_encode(array_column($transCat, 'categoria')) ?> || [];
  const catData   = <?= json_encode(array_column($transCat, 'total')) ?> || [];

  // cores fixas para tipos
  const tipoColorMap = {
    'receita': '#00b4d8', // azul
    'despesa':  '#ff4d6d'  // vermelho
  };

  // mapa de cores para categorias conhecidas - estenda conforme precisar
  const categoriaColorMap = {
    'Sem categoria': '#9AA8B2',
  'Alimentação': '#2FB86C',
  'Salário': '#FFB703',
  'Saúde': '#FF7B7B'
    // adicione outras categorias fixas aqui: 'Transporte':'#hex'
  };

  // função utilitária: gera cor HSL determinística a partir do nome
  function colorFromString(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      hash = str.charCodeAt(i) + ((hash << 5) - hash);
      hash = hash & hash;
    }
    const h = Math.abs(hash) % 360;         // matiz
    const s = 65;                           // saturação
    const l = 55;                           // luminosidade
    return `hsl(${h} ${s}% ${l}%)`;
  }

  // monta array de cores para o gráfico de tipos (um slice por label)
  const tipoColors = tipoLabels.map(lbl => {
    const key = String(lbl || '').toLowerCase();
    return tipoColorMap[key] || '#888888';
  });

  // monta array de cores para categorias (usa mapa fixo ou gera por hash)
  const catColors = catLabels.map(lbl => {
    if (!lbl) return '#9aa8b2';
    if (categoriaColorMap.hasOwnProperty(lbl)) return categoriaColorMap[lbl];
    return colorFromString(lbl);
  });

  // cria gráfico tipo (pie)
  const pieTipoCtx = document.getElementById('pieTipo');
  if (pieTipoCtx) {
    new Chart(pieTipoCtx, {
      type: 'pie',
      data: {
        labels: tipoLabels,
        datasets: [{
          data: tipoData,
          backgroundColor: tipoColors,
          borderColor: '#fff',
          borderWidth: 1
        }]
      },
      options: {
  plugins: {
    legend: {
      display: true,
      labels: {
        color: '#eaf9ff',        // cor do texto da legenda
        boxWidth: 14,            // largura do marcador
        boxHeight: 14,           // altura do marcador (Chart.js v4)
        usePointStyle: true,     // usa ponto estilizado (retângulo/círculo)
        pointStyle: 'rect'       // estilo do marcador
      }
    },
    tooltip: { enabled: true }
  }
}

    });
  }

  // cria gráfico categoria (pie)
  const pieCatCtx = document.getElementById('pieCat');
  if (pieCatCtx) {
    new Chart(pieCatCtx, {
      type: 'pie',
      data: {
        labels: catLabels,
        datasets: [{
          data: catData,
          backgroundColor: catColors,
          borderColor: '#fff',
          borderWidth: 1
        }]
      },
      options: {
  plugins: {
    legend: {
      display: true,
      labels: {
        color: '#eaf9ff',        // cor do texto da legenda
        boxWidth: 14,            // largura do marcador
        boxHeight: 14,           // altura do marcador (Chart.js v4)
        usePointStyle: true,     // usa ponto estilizado (retângulo/círculo)
        pointStyle: 'rect'       // estilo do marcador
      }
    },
    tooltip: { enabled: true }
  }
}

    });
  }

})();
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
</script>

</body>
</html>
