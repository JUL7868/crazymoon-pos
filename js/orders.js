// ========================================
// FILE: /js/orders.js
// ========================================


window.removeItem = async function removeItem(item_id) {
  const tab = activeTabs[activeTableId];
  if (!tab) return;

  showSpinner(true);

  try {
    const res = await fetch(API.orders, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'remove_item',
        item_id,
        order_id: tab.order_id
      }),
    });

    const data = await res.json();

    if (data.success) {

      tab.items = tab.items.filter(i => i.id !== item_id);

      tab.total = data.total;

      renderOrder();
    }

  } catch (e) {

    toast('Error eliminando articulo');

  }

  showSpinner(false);
};


// ========================================
// UPDATE QTY
// ========================================

window.updateQty = async function updateQty(item_id, order_id, qty) {

  showSpinner(true);

  try {

    const res = await fetch(API.orders, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },

      body: JSON.stringify({
        action: 'update_qty',
        item_id,
        order_id,
        qty
      }),
    });

    const data = await res.json();

    if (data.success) {

      const tab = activeTabs[activeTableId];

      const item = tab.items.find(i => i.id === item_id);

      if (qty <= 0) {

        tab.items = tab.items.filter(i => i.id !== item_id);

      } else if (item) {

        item.qty = qty;
        item.subtotal = item.unit_price * qty;

      }

      tab.total = data.total;

      renderOrder();

    }

  } catch (e) {

    toast('Error actualizando cantidad');

  }

  showSpinner(false);
};


// ========================================
// PUSH ITEM
// ========================================

window.pushItem = async function pushItem(
  menu_item_id,
  name,
  category,
  size,
  unit_price,
  notes = ''
) {

  const tab = activeTabs[activeTableId];

  if (!tab) return;

  const key = `${menu_item_id}-${size}-${notes}`;

  const existing = tab.items.find(i => i._key === key);

  if (existing) {

    await updateQty(
      existing.id,
      tab.order_id,
      existing.qty + 1
    );

    return;
  }

  showSpinner(true);

  try {

    const res = await fetch(API.orders, {

      method: 'POST',

      headers: {
        'Content-Type': 'application/json'
      },

      body: JSON.stringify({

        action: 'add_item',

        order_id: tab.order_id,

        menu_item_id,

        item_name: name + (size ? ' ' + size : ''),

        category,

        size,

        unit_price,

        qty: 1,

        notes,

        user_id: userId,

      }),
    });

    const data = await res.json();

    if (data.success) {

      tab.items.push({

        id: data.item_id,

        _key: key,

        menu_item_id,

        item_name: name + (size ? ' ' + size : ''),

        category,

        unit_price,

        qty: 1,

        subtotal: unit_price,

        notes,

      });

      tab.total = data.total;

      renderOrder();

      // ========================================
      // KITCHEN PRINT
      // ========================================

      if (data.item_id) {

        await printKitchenTicket({

          table: activeTableId,

          item: name + (size ? ' ' + size : ''),

          category: category,

          qty: 1,

          notes: notes

        });

      }

    }

  } catch (e) {

    toast('Error agregando articulo');

  }

  showSpinner(false);
};


// ========================================
// ADD ITEM
// ========================================

window.addItem = function addItem(id) {

  if (!activeTableId) {

    toast('Abre o selecciona una mesa primero');

    return;
  }

  const m = menuItems.find(x => x.id == id);

  if (!m) return;

  pendingItem = m;

  document.getElementById('note-input').value = '';

  document.getElementById('note-modal').style.display = 'flex';
};


// ========================================
// PROCESS ITEM WITH NOTE
// ========================================

window.processItemWithNote = function processItemWithNote(m, notes) {

  if (m.price_300) {

    pendingTapItem = {
      ...m,
      notes
    };

    document.getElementById('size-modal-name').textContent = m.name;

    document.getElementById('size-300-btn').textContent =
      `300ml — ${fmt(m.price_300)}`;

    document.getElementById('size-500-btn').textContent =
      `500ml — ${fmt(m.price_500)}`;

    document.getElementById('size-modal').style.display = 'flex';

    return;
  }

  pushItem(
    m.id,
    m.name,
    m.category,
    '',
    parseFloat(m.price),
    notes
  );
};