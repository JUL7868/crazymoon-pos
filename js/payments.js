// FILE: payments.js

window.chargeOrder = async function chargeOrder() {
  const tab = activeTabs[activeTableId];
  if (!tab || !payMethod) return;

  const tendered = parseFloat(document.getElementById('cash-tendered').value) || 0;

  showSpinner(true);

  try {
    const res = await fetch(API.orders, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'pay',
        order_id: tab.order_id,
        payment_method: payMethod,
        cash_tendered: tendered,
        user_id: userId,
      }),
    });

    const data = await res.json();

    if (data.success) {

      // =========================
      // 🔥 PRINT CAJERO TICKET
      // =========================
      try {
        await printCajeroTicket({
          order_id: data.order_id ?? tab.order_id,
          guest_count: tab.guest_count,
          items: tab.items.map(item => ({
            qty: item.qty,
            name: item.item_name,
            line_total: item.subtotal ?? (item.unit_price * item.qty),
            notes: item.notes
          })),
          total: data.total,
          table: activeTableId,
          payment_method: payMethod,
          cash_tendered: tendered,
          cash_change: data.cash_change
        }, activeTableId, payMethod);

      } catch (err) {
        console.error("PRINT ERROR:", err);
      }

      // =========================
      // NORMAL FLOW
      // =========================
      toast(`Cobrado ${fmt(data.total)} · ${payMethod}`);

      delete activeTabs[activeTableId];
      activeTableId = null;
      payMethod = null;

      await loadTables();
      renderTabs();
      renderOrder();

    } else {
      toast('Error: ' + (data.error || 'intenta de nuevo'));
    }

  } catch (e) {
    toast('Error de conexion');
  }

  showSpinner(false);
}
