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
        await qzConnectSafe();

        const printer = await qz.printers.find("Cajero");
        const config = qz.configs.create(printer, { encoding: 'CP437' });

        const ticket = [
          '\x1B\x40',
          '\x1B\x61\x01',
          '*** CRAZY MOON ***\n',
          '\x1B\x61\x00',
          `Mesa: ${activeTableId}\n`,
          `Total: $${data.total}\n`,
          `Pago: ${payMethod}\n`,
          '\nGracias!\n\n\n'
        ];

        await qz.print(config, [{
          type: 'raw',
          format: 'command',
          data: ticket
        }]);

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