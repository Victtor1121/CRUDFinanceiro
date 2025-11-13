<?php
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

/* ==========================
   FILTROS
========================== */

$periodo = $_GET['periodo'] ?? 'mes';
$categoria_id = $_GET['categoria_id'] ?? '';

$conditions = "WHERE t.usuario_id = ?";
$params = [$usuario_id];

if ($periodo === 'mes') {
    $conditions .= " AND MONTH(data_transacao) = MONTH(NOW()) AND YEAR(data_transacao) = YEAR(NOW())";
}
if ($periodo === 'mes_anterior') {
    $conditions .= " AND MONTH(data_transacao) = MONTH(NOW() - INTERVAL 1 MONTH)
                    AND YEAR(data_transacao) = YEAR(NOW() - INTERVAL 1 MONTH)";
}

if ($periodo === 'ano') {
    $conditions .= " AND YEAR(data_transacao) = YEAR(NOW())";
}
if ($categoria_id !== '') {
    $conditions .= " AND categoria_id = ?";
    $params[] = $categoria_id;
}
$tipo = $_GET['tipo'] ?? '';

if ($tipo !== '') {
    $conditions .= " AND t.tipo = ?";
    $params[] = $tipo;
}

/* ==========================
   CONSULTA PRINCIPAL
========================== */

$sql = "
SELECT t.*, c.nome AS categoria_nome 
FROM transacoes t 
LEFT JOIN categorias c ON t.categoria_id = c.id
$conditions
ORDER BY t.data_transacao DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================
   RESUMO
========================== */

$total_r = 0; 
$total_d = 0;
$categorias_totais = [];

foreach ($rows as $r) {
    if ($r['tipo'] === 'receita') $total_r += $r['valor'];
    else $total_d += $r['valor'];

    if (!isset($categorias_totais[$r['categoria_nome']])) {
        $categorias_totais[$r['categoria_nome']] = 0;
    }
    $categorias_totais[$r['categoria_nome']] += $r['valor'];
}

$saldo = $total_r - $total_d;

/* ==========================
   INSIGHTS
========================== */

function compara_mes($pdo, $usuario_id, $tipo) {
    $sql = "
    SELECT SUM(valor) FROM transacoes 
    WHERE usuario_id = ? AND tipo = ? 
    AND MONTH(data_transacao)=MONTH(NOW())-1 AND YEAR(data_transacao)=YEAR(NOW())";
    $st = $pdo->prepare($sql);
    $st->execute([$usuario_id, $tipo]);
    return $st->fetchColumn() ?: 0;
}

$mes_passado_despesas = compara_mes($pdo, $usuario_id, 'despesa');
$diff = $mes_passado_despesas ? (($total_d-$mes_passado_despesas)/$mes_passado_despesas) * 100 : 0;

arsort($categorias_totais);
$top_cat = array_key_first($categorias_totais) ?? '—';
$top_val = $categorias_totais[$top_cat] ?? 0;

$previsao = $saldo * 12;

/* ==========================
   EXPORT
========================== */

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=relatorio.csv");
    
    $output = fopen("php://output", "w");
    fputcsv($output, ["Data", "Tipo", "Categoria", "Descrição", "Valor"]);
    foreach ($rows as $r) {
        fputcsv($output, [$r['data_transacao'], $r['tipo'], $r['categoria_nome'], $r['descricao'], $r['valor']]);
    }
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    echo "<script>alert('Aqui entra o PDF via dompdf — vou te passar o instalador depois 😎');window.location='relatorios.php';</script>";
}

if (isset($_GET['export']) && $_GET['export'] === 'full') {
    echo "<script>alert('Relatório completo será um PDF + CSV + insights');window.location='relatorios.php';</script>";
}

/* ==========================
   CARREGA CATEGORIAS
========================== */
$cStmt = $pdo->prepare("SELECT id, nome FROM categorias WHERE usuario_id IS NULL OR usuario_id = ? ORDER BY nome");
$cStmt->execute([$usuario_id]);
$categorias = $cStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Relatórios Financeiros</title>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<style>
    .align-right { text-align: right; }
    .table { width:100%; border-collapse: collapse; }
    .table th, .table td { padding:8px;border-bottom:1px solid #eeeeee29; } 
    .table tbody tr:hover {
  border-left: 1px solid #00b4d8;
}
select {
  background: rgba(255,255,255,0.12)!important;
  border: 1px solid rgba(255,255,255,0.18)!important;
  border-radius: 14px!important;
  padding: 10px 14px!important;
  font-size: .9rem;
  color: #fff!important;
  transition: 0.25s!important;
  outline: none;
  cursor: pointer;
}
select:focus{
  border-color:#00b4d8!important;
  box-shadow: 0 0 10px rgba(0,180,216,0.5);
}
select option{
    background: #0d1b2a;        /* fundo igual seu tema base */
  color: #eafdff;             /* texto claro legível */
  padding: 10px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  font-size: .9rem;

}
select option:hover{

    background: #00b4d8;
    color: #fff;
}
.filters{display:flex;gap:10px;margin-bottom:10px;}
.filters select,.filters input{padding:8px;border-radius:8px;border:1px solid #444;background:#1b2b3a;color:#fff;}
.card-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-top:15px;}
.card{background:#203448;padding:14px;border-radius:12px;color:#fff;}
.insights{background:#203448;padding:14px;border-radius:12px;}
button.export{background:#00b4d8;padding:8px 12px;border-radius:8px;color:#fff;border:none;cursor:pointer;}
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

  <!-- === SEÇÃO DE SIMULADORES === -->
 <div class="simuladores-section">
  <span class="simuladores-title"><i class="bi bi-controller"></i> Simuladores</span>

  <div class="simulador-dual">
    <a href="simulador-crash.php" class="half crash">
      <i class="bi bi-rocket-takeoff"></i> Crash
    </a>
    <a href="simulador-mines.php" class="half mines">
      <i class="bi bi-gem"></i> Mines
    </a>
  </div>

  <a class="nav-item logout <?= $current === 'logout.php' ? 'active' : '' ?>" href="logout.php">
    <i class="bi bi-box-arrow-right"></i> Sair
  </a>
</nav>


    </aside>

<main class="main">
<header class="topbar">
  <h1>Relatórios</h1>
  <p class="muted">Análise avançada de suas finanças</p>
</header>

<div class="panel">
<form method="get" class="filters">

<div class="filter-item">
<label >
  Período</label>
  <div data-tooltip="Escolha o período">
  <select name="periodo">
    <option value="mes">Mês Atual</option>
    <option value="mes_anterior">Mês Anterior</option>
    <option value="ano">Ano Atual</option>
  </select>
  </div>


</div>

<div class="filter-item">
<label >
    Categoria</label>
    <div data-tooltip="Escolha a categoria">
<select name="categoria_id" data-tooltip="Selecionar categoria">
  <option value="">Todas</option>
  <?php foreach($categorias as $c): ?>
  <option value="<?= $c['id'] ?>" <?= $categoria_id==$c['id']?'selected':'' ?>>
    <?= $c['nome'] ?>
  </option>
  <?php endforeach; ?>
</select>
</div>

</div>

<div class="filter-item">
<label>Tipo</label>
<div data-tooltip="Escolha o tipo">
<select name="tipo" data-tooltip="Selecionar tipo">
  <option value="">Todos</option>
  <option value="receita" <?= $tipo=='receita'?'selected':'' ?>>Receitas</option>
  <option value="despesa" <?= $tipo=='despesa'?'selected':'' ?>>Despesas</option>
</select>
</div>
</div>

<button class="btn-filter" data-tooltip="Aplicar filtros"><i class="bi bi-funnel"></i></button>
</form>


<div class="export-container">
  <div class="dropdown export-dropdown">
    <button id="exportBtn" class="btn-export-main" data-tooltip="Exportar relatórios">
      <i class="bi bi-box-arrow-up-right"></i> Exportar
      <i class="bi bi-chevron-down dropdown-arrow"></i>
    </button>

    <div class="dropdown-menu">
      <a href="?export=csv" class="export-option" data-tooltip="Baixar planilha CSV">
        <i class="bi bi-file-earmark-spreadsheet"></i> CSV
      </a>

      <a onclick="exportPDF()" class="export-option" data-tooltip="Gerar arquivo PDF">
        <i class="bi bi-file-earmark-pdf"></i> PDF
      </a>

      <a onclick="exportExcel()" class="export-option" data-tooltip="Baixar Excel (em breve)">
        <i class="bi bi-file-earmark-excel"></i> Excel
      </a>

      <a onclick="exportFull()" class="export-option" data-tooltip="Relatório completo premium">
        <i class="bi bi-archive"></i> Completo
      </a>
    </div>
  </div>
</div>


<div class="card-grid">
<div class="card"><b>Receitas</b><br>R$ <?= number_format($total_r,2,',','.') ?></div>
<div class="card"><b>Despesas</b><br>R$ <?= number_format($total_d,2,',','.') ?></div>
<div class="card"><b>Saldo</b><br>R$ <?= number_format($saldo,2,',','.') ?></div>
</div>

<br>

<div class="insights">
<b>Insights financeiros 📊</b><br><br>
<?php if($mes_passado_despesas>0): ?>
• Você gastou <b><?= number_format($diff,1) ?>%</b> <?= $diff>0?'a mais':'a menos' ?> do que no mês passado.<br>
<?php endif; ?>
• Maior categoria: <b><?= $top_cat ?></b> (R$ <?= number_format($top_val,2,',','.') ?>)<br>
• Se continuar assim, economizará <b>R$ <?= number_format($previsao,2,',','.') ?></b> até o fim do ano.
</div>

<br>

<table class="table" style="width:100%;background:#1e2e3d;color:#fff;border-radius:8px;">
<thead><tr>
<th>Data</th><th>Tipo</th><th>Categoria</th><th>Descrição</th><th>Valor</th>
</tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr>
<td><?= date('d/m/Y',strtotime($r['data_transacao'])) ?></td>
<td><?= ucfirst($r['tipo']) ?></td>
<td><?= $r['categoria_nome'] ?></td>
<td><?= $r['descricao'] ?></td>
<td>R$ <?= number_format($r['valor'],2,',','.') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
<div id="progressBar" class="progress-bar"></div>
</main>
</div>
<script>
const exportBtn = document.getElementById("exportBtn");
const menu = document.querySelector(".dropdown-menu");
const options = document.querySelectorAll(".export-option");

exportBtn.addEventListener("click", () => {
  menu.classList.toggle("show");
});

// Close dropdown if click outside
document.addEventListener("click", e => {
  if (!e.target.closest(".export-dropdown")) {
    menu.classList.remove("show");
  }
});

// Loading spinner on click
options.forEach(opt => {
  opt.addEventListener("click", () => {
    const originalHTML = exportBtn.innerHTML;

    // inicia animação
    exportBtn.classList.add("loading");
    exportBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processando...';

    // tempo limite para voltar ao normal se a página não mudar
    setTimeout(() => {
      exportBtn.classList.remove("loading");
      exportBtn.innerHTML = originalHTML;
    }, 3000); // ⏳ 3.5 segundos - ajuste se quiser

  });
});

</script>
<script>
const progressBar = document.getElementById('progressBar');

// Função para iniciar a progress bar
function startProgress() {
  let progress = 0;
  progressBar.style.opacity = "1";

  const interval = setInterval(() => {
    progress += Math.random() * 20; 
    if (progress >= 90) progress = 90; 

    progressBar.style.width = progress + "%";
  }, 300);

  // Retorna função para finalizar
  return function finishProgress() {
    clearInterval(interval);
    progressBar.style.width = "100%";
    setTimeout(() => {
      progressBar.style.opacity = "0";
      setTimeout(() => progressBar.style.width = "0%", 400);
    }, 300);
  }
}

// Aplica progresso nos botões do dropdown
options.forEach(opt => {
  opt.addEventListener("click", () => {
    exportBtn.classList.add("loading");
    exportBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processando...';

    const end = startProgress(); // começa barra
    
    // finaliza barra após navegação começar
    setTimeout(() => end(), 2500);
  });
});
</script>

<script>
    async function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.text("Relatório Financeiro", 14, 10);

    const table = document.querySelector("table");
    const rows = [...table.querySelectorAll("tr")].map(tr =>
        [...tr.children].map(td => td.innerText)
    );

    doc.autoTable({
        head: [rows[0]],
        body: rows.slice(1),
        theme: 'grid'
    });

    doc.save("relatorio.pdf");
}
function exportExcel() {
    const table = document.querySelector("table");
    const wb = XLSX.utils.table_to_book(table, { sheet: "Relatorio" });
    XLSX.writeFile(wb, "relatorio.xlsx");
}
async function exportFull() {
    const zip = new JSZip();

    // CSV que você já gera
    const table = document.querySelector("table");
    let csv = "";
    table.querySelectorAll("tr").forEach(row => {
        const cols = [...row.children].map(td => td.innerText);
        csv += cols.join(",") + "\n";
    });
    zip.file("relatorio.csv", csv);

    // PDF
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.text("Relatório Completo", 14, 10);
    doc.autoTable({ html: "table" });
    zip.file("relatorio.pdf", doc.output("blob"));

    // Excel
    const wb = XLSX.utils.table_to_book(table);
    const excelBuffer = XLSX.write(wb, { bookType: "xlsx", type: "array" });
    zip.file("relatorio.xlsx", excelBuffer);

    const content = await zip.generateAsync({ type: "blob" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(content);
    link.download = "relatorio_completo.zip";
    link.click();
}

</script>
<!-- PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<!-- Excel -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
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
