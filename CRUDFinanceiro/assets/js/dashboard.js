document.addEventListener('DOMContentLoaded', async function () {
  let transacoes = [];

  // normaliza linha retornada pelo backend
  function normalizeRow(row){
    return {
      id: parseInt(row.id, 10),
      data: row.data_transacao || row.data || '',
      descricao: row.descricao || '',
      categoria: row.categoria || 'Sem categoria',
      tipo: row.tipo || 'despesa',
      valor: parseFloat(row.valor) || 0
    };
  }

  async function fetchTransacoes() {
    try {
      console.log("Buscando transações do backend...");
      const res = await fetch('./transacoes_crud.php?acao=listar', { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Falha ao buscar transações');
      console.log("Status da resposta:", res.status);
      const json = await res.json();
      console.log("JSON recebido:", json);
      transacoes = json.map(normalizeRow);
      renderAll();
    } catch (err) {
      console.error("Erro no fetchTransacoes:", err);
      showToast('Erro ao carregar transações do banco de dados.');
    }
  }

  // --- Funções auxiliares ---
  const tbody = document.getElementById('transacoes-body');
  const saldoEl = document.getElementById('saldo-atual');
  const receitasEl = document.getElementById('receitas-mes');
  const despesasEl = document.getElementById('despesas-mes');
  const saldoProjEl = document.getElementById('saldo-projetado');
  const alertsList = document.getElementById('alerts-list');
  const toastContainer = document.getElementById('toast-container');

  function formatMoney(v){
    return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  function calcularResumo(){
    let receitas = transacoes.filter(t => t.tipo === 'receita').reduce((s,a)=>s+a.valor,0);
    let despesas = transacoes.filter(t => t.tipo === 'despesa').reduce((s,a)=>s+a.valor,0);
    let saldo = receitas - despesas;
    saldoEl.textContent = formatMoney(saldo);
    receitasEl.textContent = formatMoney(receitas);
    despesasEl.textContent = formatMoney(despesas);
    saldoProjEl.textContent = formatMoney(saldo + 200);
  }

  function renderTable(){
    tbody.innerHTML = '';
    transacoes.forEach(t => {
      const tr = document.createElement('tr');
      const tipoClass = t.tipo === 'receita' ? 'receita' : 'despesa';
      tr.innerHTML = `
        <td>${t.data}</td>
        <td>${t.descricao}</td>
        <td>${t.categoria}</td>
        <td><span class="tx-tipo ${tipoClass}">${t.tipo}</span></td>
        <td class="align-right">${formatMoney(t.valor)}</td>
        <td>
          <div class="table-action">
            <button class="btn-mini btn-edit" data-id="${t.id}">Editar</button>
            <button class="btn-mini btn-delete" data-id="${t.id}">Excluir</button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  function renderAlerts(){
    alertsList.innerHTML = '';
    const totalDespesas = transacoes.filter(t=>t.tipo==='despesa').reduce((s,a)=>s+a.valor,0);
    const li = document.createElement('li');
    li.className = 'alert-item';
    li.textContent = totalDespesas > 500
      ? 'Atenção: suas despesas deste mês ultrapassam R$ 500'
      : 'Sem alertas no momento';
    alertsList.appendChild(li);
  }

  function showToast(msg){
    const div = document.createElement('div');
    div.className = 'toast';
    div.textContent = msg;
    toastContainer.appendChild(div);
    setTimeout(()=>{ div.style.opacity=0; setTimeout(()=>div.remove(),400); }, 3000);
  }

  function renderAll(){
    calcularResumo();
    renderTable();
    renderAlerts();
  }

  // 🔥 carrega os dados reais do banco
  await fetchTransacoes();

  
});
