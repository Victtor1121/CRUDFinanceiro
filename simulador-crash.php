<?php
require_once 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// ======== saldo calculado pelo sistema =========
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
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Simulador Crash</title>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<style>
/* ====== CRASH STYLE ====== */
.crash-wrap {
  display: flex;
  gap: 20px;
  align-items: flex-start;
  padding: 20px;
}
.crash-panel {
  width: 320px;
  background: rgba(255,255,255,0.03);
  border-radius: 12px;
  padding: 18px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}
.crash-panel h3 { color: #dcefff; margin-bottom: 14px; }

.crash-control {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.crash-control label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #bcd6ea;
  margin-bottom: 6px;
}
.crash-control input {
  width: 100%;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.06);
  color: #eaf6ff;
  font-size: 0.95rem;
  box-sizing: border-box;
}
.btn-start {
  position: relative;
  display: inline-block;
  width: 100%;
  padding: 12px 18px;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 1rem;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: #fff;
  background: linear-gradient(90deg, #00c853, #00e676);
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0, 255, 140, 0.25);
  transition: all 0.25s ease;
  overflow: hidden;
}
/* Brilho animado cruzando o botão */
.btn-start::before {
  content: "";
  position: absolute;
  top: 0;
  left: -75%;
  width: 50%;
  height: 100%;
  background: linear-gradient(
    120deg,
    transparent,
    rgba(255, 255, 255, 0.25),
    transparent
  );
  transform: skewX(-25deg);
  transition: all 0.6s ease;
}

.btn-start:hover::before {
  left: 130%;
}

.btn-start:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 6px 22px rgba(0, 255, 160, 0.35);
}

.btn-start:active {
  transform: translateY(0);
  box-shadow: 0 2px 10px rgba(0, 255, 160, 0.25);
}
/* Estado "Parar o jogo" — mantém contraste */
.btn-stop {
  background: linear-gradient(90deg, #ff5f6d, #ffc371) !important;
  box-shadow: 0 4px 16px rgba(255, 100, 100, 0.35);
}

.btn-stop:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 6px 22px rgba(255, 150, 100, 0.45);
}

/* ====== GRÁFICO ====== */
.crash-board {
  position: relative;
  width: 100%;
  height: 420px;
  background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
  border-radius: 12px;
  overflow: hidden;
  box-shadow: inset 0 6px 18px rgba(0,0,0,0.4);
}

.crash-board canvas {
  width: 100%;
  height: 100%;
  display: block;
}
.line {
  position: absolute;
  bottom: 20px;
  left: 40px;
  height: 2px;
  width: 0;
  background: #ff3b3b;
  transform-origin: left bottom;
  transition: width 0.05s linear;
}
.status {
  position: absolute;
  bottom: 10px;
  right: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #9fffa3;
}
.status::before {
  content: '';
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #00ff6a;
}

/* ====== HISTÓRICO ====== */
.crash-history {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 6px;
  flex-wrap: nowrap;
  justify-content: center;
  background: rgba(0, 0, 0, 0.25);
  padding: 6px 10px;
  border-radius: 10px;
  backdrop-filter: blur(4px);
  box-shadow: 0 4px 14px rgba(0,0,0,0.3);
  opacity: .65;                    /* ← semitransparente padrão   */
  transition: transform .25s ease, opacity .25s ease;
}
.crash-history .box {
  background: rgba(255,255,255,0.06);
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: 600;
  color: #fff;
  font-size: 0.9rem;
  white-space: nowrap;
}

.crash-history .high 
{ background: linear-gradient(90deg,#00e676,#00c853); color:#001b12; }
.saldo-valor {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 1.3rem;
  font-weight: 700;
  color:rgb(122, 252, 161);
  display: flex;
  align-items: center;
  gap: 6px;
  box-shadow: inset 0 0 12px rgba(0, 255, 150, 0.08);
  transition: all 0.3s ease;
  text-shadow: 0 0 4px rgba(0, 255, 180, 0.4);
}

/* Gradiente animado no número */
.saldo-valor span {
  background: linear-gradient(90deg,rgb(0, 230, 31), #00c853,rgb(0, 255, 149));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  font-weight: 800;
  animation: saldoShine 3s linear infinite;
}

@keyframes saldoShine {
  0% { background-position: 0% 50%; }
  100% { background-position: 200% 50%; }
}

/* Hover sutil */
.saldo-valor:hover {
  transform: scale(1.02);
  box-shadow: 0 0 14px rgba(0, 255, 180, 0.15);
}
/* ====== FEEDBACK DE RESULTADO ====== */
.result-display {
  position: absolute;
  top: 55%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 2.2rem;
  font-weight: 800;
  text-shadow: 0 0 18px rgba(0,0,0,0.6);
  opacity: 0;
  pointer-events: none;
  transition: all 0.4s ease;
  z-index: 10;
}

.result-display.show {
  opacity: 1;
  transform: translate(-50%, -50%) scale(1.1);
}

/* ====== ESTILIZAÇÃO DO MULTIPLICADOR ====== */
.mult-display {
  position: absolute;
  top: 45%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: #fff;
  font-size: 3rem;
  font-weight: 800;
  padding: 10px 26px;
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.25);
  transition: all 0.4s ease;
}

/* quando o jogo crasha */
.mult-display.crashed {
  background: rgba(255, 0, 0, 0.25)!important;
  color:rgb(255, 151, 151)!important;
  text-shadow: 0 0 16px rgba(71, 71, 71, 0.8)!important;
}

/* quando o jogador ganha */
.mult-display.won {
  background: rgba(0, 255, 150, 0.25)!important;
  color:rgb(105, 253, 209)!important;
  text-shadow: 0 0 16px rgba(0, 255, 150, 0.7)!important;
}
.mines-btn {
    display:flex;
    gap:10px;
  }
  .input-panel{
  background: rgba(255,255,255,0.12)!important;
  border: 1px solid rgba(255,255,255,0.18)!important;
  border-radius: 14px!important;
  padding: 10px 14px!important;
  font-size: .9rem;
  color: #fff!important;
  transition: 0.25s!important;
  outline: none;
}
.input-panel:focus{
  border-color:#00b4d8!important;
  box-shadow: 0 0 10px rgba(0,180,216,0.5);
}
.crash-history:hover {
  transform: translate(-50%, -20px);
  opacity: .90;
}
#autoRetirar {
  width: 100%;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px;
  padding: 10px 12px;
  color: #eaf6ff;
  font-size: 0.95rem;
  box-sizing: border-box;
  transition: all 0.25s ease;
}
#autoRetirar:focus {
  outline: none;
  border-color: rgba(0, 180, 216, 0.6);
  box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.15);
}
.auto-aposta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
  font-size: 0.9rem;
  color: #bcd6ea;
  font-weight: 600;
}

/* Estilo do switch (radio personalizado) */
.switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(255,255,255,0.15);
  border-radius: 24px;
  transition: 0.3s;
  box-shadow: inset 0 0 4px rgba(0,0,0,0.3);
}

.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 4px;
  bottom: 4px;
  background-color: #fff;
  border-radius: 50%;
  transition: 0.3s;
}

/* Quando ativado */
.switch input:checked + .slider {
  background: linear-gradient(90deg, #00c853, #00e676);
  box-shadow: 0 0 6px rgba(0,255,150,0.4);
}

.switch input:checked + .slider:before {
  transform: translateX(20px);
}
/* marcador na ponta da linha (ícone) */
.line-marker {
  position: absolute;
  transform: translate(-15%, -85%);
  pointer-events: none;
  display: none; /* aparece só durante o jogo */
  z-index: 12;
  transition: transform .12s ease, opacity .12s ease;
}
.small-muted { font-size:0.85rem; color:#a7c3d9; }
.mines-status { margin-top:10px; color:#eaf6ff; font-weight:600; }
.info-row { display:flex; gap:8px; align-items:center; margin-top:6px; color:#cfe9ff; }
  .muted { color:#93b8d0; font-size:0.9rem; }
.line-marker .bi {
  font-size: 25px; 
  color:rgb(255, 0, 0); 
  text-shadow: 0 4px 12px rgba(0,0,0,0.45);
  filter: drop-shadow(0 2px 6px rgba(248, 71, 71, 0.53));
  animation: diceSpin 2.5s linear infinite;  

}
@keyframes diceSpin {
  0%   { transform: rotate(0deg); }
  100% { transform: rotate(300deg); }
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
</div>


  <a class="nav-item logout <?= $current === 'logout.php' ? 'active' : '' ?>" href="logout.php">
    <i class="bi bi-box-arrow-right"></i> Sair
  </a>

  
</nav>


    </aside>
  <main class="main">
    <header class="topbar">
    <h2 style="color:#eafdff; margin-bottom:12px;">Simulador - Crash</h2>
      <p class="muted">Tente prever até onde o multiplicador vai antes do crash</p>
    </header>

    <div class="crash-wrap">
      <div class="crash-panel">
        <div class="crash-control">
        <div class="saldo-box">
            <label>Saldo atual</label>
            <div class="saldo-valor">R$ <span id="saldoDisplay"><?= number_format($saldo,2,',','.') ?></span></div>
          </div>
          <div>
            <label>Valor da aposta</label>
            <input type="number" class="input-panel" id="valorAposta" min="1" step="0.01" value="1.00">
          </div>
          <div>
            <label>Auto Retirar em</label>
            <input type="number" class="input-panel" id="autoRetirar" min="1" step="0.1" placeholder="Ex: 2.50">
          </div>
          <div class="auto-aposta">
            <label class="switch">
            <input type="checkbox" id="autoAposta">
            <span class="slider"></span>
            </label>
            <span class="label-auto">Auto Aposta</span>
          </div>


          <div class="mines-btn">
          <button class="btn-start" id="btnCrash">Começar Jogo</button>
          </div>
          <div class="mines-status" id="statusText">Pronto para iniciar.</div>
          <div class="small-muted">Espere o multiplicador subir e retire antes do crash!</div>
        </div>
      </div>

      <div class="crash-board">
  <canvas id="crashCanvas"></canvas>
  <div class="mult-display" id="multDisplay">1.00x</div>
  <div class="crash-history" id="crashHistory"></div>
</div>

    </div>

   
  </main>
</div>

<script>
const btn = document.getElementById('btnCrash');
const multDisplay = document.getElementById('multDisplay');
const saldoEl = document.getElementById('saldoDisplay');
const hist = document.getElementById('crashHistory');
const canvas = document.getElementById('crashCanvas');
const ctx = canvas.getContext('2d');
// cria o marcador HTML com o ícone bootstrap
const boardEl = canvas.parentElement; // .crash-board
const markerEl = document.createElement('div');
markerEl.className = 'line-marker';
markerEl.innerHTML = '<i class="bi bi-dice-5"></i>'; // aqui o ícone
boardEl.appendChild(markerEl);


let saldo = parseFloat("<?= $saldo ?>");
let jogando = false;
let mult = 1.00;
let historico = [];
let crashPoint = 0;
let startTime = null;
let animationId;
let elapsed = 0;

canvas.width = canvas.offsetWidth;
canvas.height = canvas.offsetHeight;

function formatBR(v){ return v.toFixed(2).replace('.',','); }

function drawBackground(maxTime, maxMult) {
  const w = canvas.width, h = canvas.height;
  ctx.clearRect(0, 0, w, h);
  ctx.strokeStyle = 'rgba(255,255,255,0.1)';
  ctx.lineWidth = 1;
  ctx.beginPath();

  // Linhas horizontais (multiplicadores)
  const multStep = 0.5;
  for (let m = 1.0; m <= maxMult + 0.5; m += multStep) {
    const y = h - ((m - 1) / (maxMult - 1)) * (h - 60) - 40;
    ctx.moveTo(0, y);
    ctx.lineTo(w, y);
    ctx.fillStyle = 'rgba(255,255,255,0.35)';
    ctx.font = '11px monospace';
    ctx.fillText(m.toFixed(2) + "x", 6, y - 4);
  }

  // Linhas verticais (tempo)
  const timeStep = 2;
  for (let t = 0; t <= maxTime; t += timeStep) {
    const x = 60 + (t / maxTime) * (w - 100);
    ctx.moveTo(x, h - 40);
    ctx.lineTo(x, 40);
    ctx.fillStyle = 'rgba(255,255,255,0.35)';
    ctx.font = '11px monospace';
    ctx.fillText(t + "s", x - 10, h - 22);
  }

  ctx.stroke();
}

function drawLine(time, maxTime, multiplier, maxMult) {
  const w = canvas.width, h = canvas.height;
  ctx.strokeStyle = '#ff4040';
  ctx.lineWidth = 5;
  ctx.beginPath();
  ctx.moveTo(60, h - 40);

  // desenha a linha até o ponto atual
  const steps = Math.floor(time * 20);
  for (let i = 0; i <= steps; i++) {
    const t = i / 20;
    const m = 1 + t * 0.25;
    const x = 60 + (t / maxTime) * (w - 100);
    const y = h - 40 - ((m - 1) / (maxMult - 1)) * (h - 100);
    if (i === 0) ctx.moveTo(x, y);
    else ctx.lineTo(x, y);
  }

  // ponto atual
  const xNow = 60 + (time / maxTime) * (w - 100);
  const yNow = h - 40 - ((multiplier - 1) / (maxMult - 1)) * (h - 100);
  ctx.lineTo(xNow, yNow);
  ctx.stroke();

  // posiciona o marcador HTML sobre o canvas (convertendo para coordenadas do container)
  // pega rects para calcular posição relativa
  const canvasRect = canvas.getBoundingClientRect();
  const boardRect = boardEl.getBoundingClientRect();

  // xNow, yNow são em pixels relativos ao canvas (coordenadas do canvas)
  // calculamos a posição dentro do board (parent)
  const relLeft = (canvasRect.left - boardRect.left) + xNow;
  const relTop  = (canvasRect.top  - boardRect.top)  + yNow;

  markerEl.style.left = relLeft + 'px';
  markerEl.style.top  = relTop + 'px';

  // exibe o marcador
  markerEl.style.display = 'block';
  markerEl.style.opacity = '1';
}


function update(timestamp) {
  if (!jogando) return;
  if (!jogando) {
  markerEl.style.display = 'none';
  return;
}
  if (!startTime) startTime = timestamp;
  elapsed = (timestamp - startTime) / 1000;
  mult = 1 + elapsed * 0.25; // progressão suave

  const autoRetirar = parseFloat(document.getElementById('autoRetirar').value || 0);

  // AUTO CASHOUT
  if (autoRetirar && mult >= autoRetirar) {
    const aposta = parseFloat(document.getElementById('valorAposta').value || 0);
    const ganho = aposta * mult;
    saldo += ganho;
    saldoEl.textContent = formatBR(saldo);
    registrarCrash('ganho', ganho, mult.toFixed(2));
    finalizarJogo(true);

    return;
  }

  // CRASH
  if (mult >= crashPoint) {
    finalizarJogo(false);
    return;
  }

  // determina o range exibido (tempo e multiplicador)
  const maxTime = Math.max(elapsed + 1, 10);
  const maxMult = Math.max(mult + 0.5, 2.5);

  // redesenha
  drawBackground(maxTime, maxMult);
  drawLine(elapsed, maxTime, mult, maxMult);

  // atualizar multiplicador visual
  multDisplay.textContent = mult.toFixed(2) + "x";
  markerEl.style.display = 'block';
  markerEl.style.opacity = '1';
  animationId = requestAnimationFrame(update);
}


btn.addEventListener('click', () => {
  if (jogando) {
    // Cashout manual
    const aposta = parseFloat(document.getElementById('valorAposta').value || 0);
    const ganho = aposta * mult;
    saldo += ganho;
    saldoEl.textContent = formatBR(saldo);
    registrarCrash('ganho', ganho, mult.toFixed(2));
    finalizarJogo(true);
    return;
  }

  // Iniciar nova rodada
  const aposta = parseFloat(document.getElementById('valorAposta').value || 0);
  if (!aposta || aposta <= 0) return alert('Digite um valor válido.');
  if (aposta > saldo) return alert('Saldo insuficiente.');

  saldo -= aposta;
  saldoEl.textContent = formatBR(saldo);
  registrarCrash('perda', aposta);

  crashPoint = (Math.random() * 4 + 1).toFixed(2);
  mult = 1.00;
  elapsed = 0;
  startTime = null;
  jogando = true;

  multDisplay.classList.remove('crashed', 'won');
  multDisplay.textContent = "1.00x";
  multDisplay.style.background = 'rgba(0,0,0,0.25)';
  multDisplay.style.color = '#fff';

  btn.textContent = "Parar Jogo";
  btn.classList.add('btn-stop');

  ctx.clearRect(0, 0, canvas.width, canvas.height);
  drawBackground(10, 2.5);
  markerEl.style.display = 'block';
markerEl.style.opacity = '1';
  requestAnimationFrame(update);
});

function finalizarJogo(ganhou) {
  jogando = false;
  cancelAnimationFrame(animationId);

  btn.textContent = "Começar Jogo";
  btn.classList.remove('btn-stop');

  historico.unshift(mult.toFixed(2));
  if (historico.length > 10) historico.pop();
  renderHistorico();

  multDisplay.classList.remove('crashed', 'won');
  multDisplay.classList.add(ganhou ? 'won' : 'crashed');
  multDisplay.textContent = `${mult.toFixed(2)}x`;

  setTimeout(() => {
    if (!jogando) {
      multDisplay.textContent = "1.00x";
      multDisplay.classList.remove('crashed', 'won');
    }

    // === AUTO APOSTA ===
    const autoAposta = document.getElementById('autoAposta').checked;
    if (autoAposta) {
      setTimeout(() => {
        if (!jogando) iniciarNovaRodada();
      }, 1000); // aguarda 1s antes da próxima rodada
    }
  }, 2000);
}

// Função separada para iniciar um novo jogo (reutilizável pelo auto-aposta)
function iniciarNovaRodada() {
  const aposta = parseFloat(document.getElementById('valorAposta').value || 0);
  if (!aposta || aposta <= 0) return alert('Digite um valor válido.');
  if (aposta > saldo) return alert('Saldo insuficiente.');

  saldo -= aposta;
  saldoEl.textContent = formatBR(saldo);
  registrarCrash('perda', aposta);

  crashPoint = (Math.random() * 4 + 1).toFixed(2);
  mult = 1.00;
  elapsed = 0;
  startTime = null;
  jogando = true;

  multDisplay.classList.remove('crashed', 'won');
  multDisplay.textContent = "1.00x";
  multDisplay.style.background = 'rgba(0,0,0,0.25)';
  multDisplay.style.color = '#fff';

  btn.textContent = "Parar Jogo";
  btn.classList.add('btn-stop');

  ctx.clearRect(0, 0, canvas.width, canvas.height);
  drawBackground(10, 2.5);
  markerEl.style.display = 'block';
markerEl.style.opacity = '1';
  requestAnimationFrame(update);
}


function renderHistorico(){
  hist.innerHTML = historico.map(x => 
    `<div class="box ${parseFloat(x) >= 2 ? 'high' : ''}">${x}x</div>`
  ).join('');
}

async function registrarCrash(tipo, valor, mult = null) {
  try {
    await fetch('includes/crash_transacao.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `tipo=${encodeURIComponent(tipo)}&valor=${valor}&mult=${mult ?? ''}`
    });
  } catch (e) {
    console.error('Erro ao registrar no banco:', e);
  }
}

</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const path = window.location.pathname;
  const file = path.substring(path.lastIndexOf('/') + 1);

  // remove qualquer active existente
  document.querySelectorAll('.simulador-dual a').forEach(el => el.classList.remove('active'));

  // marca conforme o arquivo
  if (file.includes('mines')) {
    document.querySelector('.simulador-dual a.mines')?.classList.add('active');
  } else if (file.includes('crash')) {
    document.querySelector('.simulador-dual a.crash')?.classList.add('active');
  }
});
</script>

</body>
</html>
