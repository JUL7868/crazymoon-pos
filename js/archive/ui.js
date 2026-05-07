// ========================================
// FILE: /js/ui.js
// ========================================

window.renderCats = function renderCats() {
  document.getElementById('cat-bar').innerHTML = CATS.map(c =>
    `<button class="cat-btn${c===selCat?' active':''}" onclick="setCat('${c}')">${c}</button>`
  ).join('');
};

window.setCat = function setCat(c) {
  selCat = c;
  renderCats();
  renderMenu();
};

window.renderMenu = function renderMenu() {
  if (!selCat) {
    document.getElementById('menu-grid').innerHTML = '';
    return;
  }

  const items = menuItems.filter(m => m.category === selCat && m.active == 1);
  document.getElementById('menu-grid').innerHTML = items.map(m => {
    const pl = m.price_300 ? `$${m.price_300} / $${m.price_500}` : fmt(m.price);

    return `<div class="menu-item" onclick="addItem(${m.id})">
      <span class="item-badge ${m.badge}">${m.label}</span>
      <div class="item-name">${esc(m.name)}</div>
      <div class="item-sub">${esc(m.description || '')}</div>
      <div class="item-price">${pl}</div>
    </div>`;
  }).join('');
};

window.renderTabs = function renderTabs() {
  const openTables = tables.filter(t => activeTabs[t.id]);

  document.getElementById('tab-list').innerHTML = openTables.map(t => {
    const isActive = t.id === activeTableId;
    const hasOrder = !!activeTabs[t.id];

    return `<button class="tab-btn${isActive ? ' active' : ''}${hasOrder && !isActive ? ' has-order' : ''}" 
              onclick="selectTable(${t.id})">${esc(t.name)}</button>`;
  }).join('');
};

window.openTableModal = function openTableModal() {
  document.getElementById('table-grid').innerHTML = tables.map(t => {
    const occupied = !!activeTabs[t.id];
    const isVip = t.type === 'vip';

    return `<div class="table-opt${occupied ? ' occupied' : ''}${isVip && !occupied ? ' vip' : ''}" 
              onclick="selectTableFromModal(${t.id})">
      ${esc(t.name)}${occupied ? ' ●' : ''}
    </div>`;
  }).join('');

  document.getElementById('table-modal').style.display = 'flex';
};

window.selectTableFromModal = async function selectTableFromModal(table_id) {
  closeModal();

  if (!activeTabs[table_id]) {
    showSpinner(true);

    try {
      const res = await fetch(API.orders, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'open', table_id, user_id: userId }),
      });

      const data = await res.json();

      if (!data.success) {
        console.error('OPEN ORDER FAILED:', data);
        toast('Error: ' + (data.error || 'no se pudo abrir'));
        showSpinner(false);
        return;
      }

      activeTabs[table_id] = {
        order_id: data.order_id,
        items: [],
        total: 0
      };

      await loadTables();

    } catch (e) {
      console.error('OPEN ORDER ERROR:', e);
      toast('Error abriendo orden');
      showSpinner(false);
      return;
    }

    showSpinner(false);
  }

  selectTable(table_id);
};

window.selectTable = function selectTable(table_id) {
  activeTableId = table_id;
  payMethod = null;

  ['cash', 'card', 'split'].forEach(m => {
    const btn = document.getElementById('pay-' + m);
    if (btn) btn.classList.remove('sel');
  });

  document.getElementById('cash-input-row').style.display = 'none';
  document.getElementById('change-display').textContent = '';

  renderTabs();
  renderOrder();
};

window.confirmNote = function confirmNote() {
  const notes = document.getElementById('note-input').value.trim();
  document.getElementById('note-modal').style.display = 'none';

  if (!pendingItem) return;

  processItemWithNote(pendingItem, notes);
  pendingItem = null;
};

window.cancelNote = function cancelNote() {
  document.getElementById('note-modal').style.display = 'none';

  if (!pendingItem) return;

  processItemWithNote(pendingItem, '');
  pendingItem = null;
};

window.confirmSize = async function confirmSize(ml) {
  if (!pendingTapItem) return;

  const m = pendingTapItem;
  const price = ml === 300 ? parseFloat(m.price_300) : parseFloat(m.price_500);
  const size = ml + 'ml';

  document.getElementById('size-modal').style.display = 'none';

  await pushItem(
    m.id,
    m.name,
    m.category,
    size,
    price,
    m.notes || ''
  );

  pendingTapItem = null;
};

window.renderOrder = function renderOrder() {
  const tab = activeTabs[activeTableId];

  const titleEl = document.getElementById('order-title');
  const subEl = document.getElementById('order-sub');
  const itemsEl = document.getElementById('order-items');
  const totalsEl = document.getElementById('totals');
  const payEl = document.getElementById('pay-section');

  if (!tab) {
    titleEl.textContent = '';
    subEl.textContent = '';
    itemsEl.innerHTML = '<div class="order-empty">Selecciona una mesa</div>';
    totalsEl.style.display = 'none';
    payEl.style.display = 'none';
    return;
  }

  const table = tables.find(t => t.id === activeTableId);

  titleEl.textContent = table ? table.name.toUpperCase() : '';

  subEl.textContent = tab.items.length
    ? tab.items.reduce((s, i) => s + i.qty, 0) + ' articulo(s)'
    : 'Mesa vacia';

  if (!tab.items.length) {
    itemsEl.innerHTML = '<div class="order-empty">Agrega articulos del menu</div>';
    totalsEl.style.display = 'none';
    payEl.style.display = 'none';
    return;
  }

  itemsEl.innerHTML = tab.items.map(i => `
    <div class="order-row">
      <div class="order-row-name">
        ${esc(i.item_name)}
        <span>${fmt(i.unit_price)} c/u</span>
        ${i.notes ? `<span class="order-note">→ ${esc(i.notes)}</span>` : ''}
      </div>

      <button class="qty-btn" onclick="updateQty(${i.id},${tab.order_id},${i.qty - 1})">−</button>
      <span class="qty-num">${i.qty}</span>
      <button class="qty-btn" onclick="updateQty(${i.id},${tab.order_id},${i.qty + 1})">+</button>

      <div class="order-row-price">${fmt(i.unit_price * i.qty)}</div>
      <button class="remove-btn" onclick="removeItem(${i.id})">✕</button>
    </div>
  `).join('');

  document.getElementById('t-total').textContent = fmt(tab.total);
  totalsEl.style.display = 'block';
  payEl.style.display = 'flex';

  updateChargeBtn();
};

window.selectPay = function selectPay(method) {
  payMethod = method;

  ['cash', 'card', 'split'].forEach(m => {
    const btn = document.getElementById('pay-' + m);
    if (btn) btn.classList.toggle('sel', m === method);
  });

  document.getElementById('cash-input-row').style.display =
    (method === 'cash' || method === 'split') ? 'flex' : 'none';

  updateChargeBtn();
  calcChange();
};

window.calcChange = function calcChange() {
  if (payMethod !== 'cash' && payMethod !== 'split') return;

  const tab = activeTabs[activeTableId];
  const tendered = parseFloat(document.getElementById('cash-tendered').value) || 0;
  const total = tab ? tab.total : 0;
  const change = tendered - total;
  const el = document.getElementById('change-display');

  el.textContent = tendered > 0
    ? (change >= 0 ? 'Cambio: ' + fmt(change) : 'Falta: ' + fmt(Math.abs(change)))
    : '';

  updateChargeBtn();
};

window.updateChargeBtn = function updateChargeBtn() {
  const btn = document.getElementById('charge-btn');
  const tab = activeTabs[activeTableId];
  const total = tab ? tab.total : 0;

  if (!btn) return;

  if (!payMethod || !tab || !tab.items.length) {
    btn.disabled = true;
    btn.textContent = 'COBRAR';
    return;
  }

  if (payMethod === 'cash') {
    const tendered = parseFloat(document.getElementById('cash-tendered').value) || 0;
    btn.disabled = tendered < total;
    btn.textContent = btn.disabled ? 'INGRESA EFECTIVO' : 'COBRAR ' + fmt(total);
  } else {
    btn.disabled = false;
    btn.textContent = 'COBRAR ' + fmt(total);
  }
};

window.clearOrder = async function clearOrder() {
  const tab = activeTabs[activeTableId];
  if (!tab) return;

  showSpinner(true);

  try {
    const res = await fetch(API.orders, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'void', order_id: tab.order_id }),
    });

    const data = await res.json();

    if (data.success) {
      delete activeTabs[activeTableId];
      activeTableId = null;
      payMethod = null;

      await loadTables();
      renderTabs();
      renderOrder();
    }

  } catch (e) {
    toast('Error');
  }

  showSpinner(false);
};

window.closeModal = function closeModal() {
  document.getElementById('table-modal').style.display = 'none';
  document.getElementById('size-modal').style.display = 'none';
  document.getElementById('note-modal').style.display = 'none';

  pendingTapItem = null;
  pendingItem = null;
};

window.showSpinner = function showSpinner(show) {
  const spinner = document.getElementById('spinner');
  if (spinner) spinner.classList.toggle('show', show);
};

window.toast = function toast(msg) {
  const el = document.getElementById('toast');
  if (!el) return;

  el.textContent = msg;
  el.classList.add('show');

  setTimeout(() => {
    el.classList.remove('show');
  }, 2500);
};

window.esc = function esc(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
};

document.addEventListener('DOMContentLoaded', function() {
  const tableModal = document.getElementById('table-modal');
  if (tableModal) {
    tableModal.addEventListener('click', function(e) {
      if (e.target === this) closeModal();
    });
  }

  const sizeModal = document.getElementById('size-modal');
  if (sizeModal) {
    sizeModal.addEventListener('click', function(e) {
      if (e.target === this) closeModal();
    });
  }

  const noteModal = document.getElementById('note-modal');
  if (noteModal) {
    noteModal.addEventListener('click', function(e) {
      if (e.target === this) cancelNote();
    });
  }
});