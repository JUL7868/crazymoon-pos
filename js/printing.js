// ========================================
// FILE: /js/printing.js
// ========================================


// =========================
// SAFE CONNECTION HANDLER
// =========================
window.qzConnectSafe = async function qzConnectSafe() {
    if (typeof qz === "undefined") {
        console.error("QZ not loaded");
        return false;
    }

    if (!qz.websocket.isActive()) {
        try {
            await qz.websocket.connect();
            console.log("QZ Connected");
        } catch (err) {
            console.error("QZ Connection failed:", err);
            return false;
        }
    }

    return true;
}


// =========================
// LIST PRINTERS
// =========================
window.qzListPrinters = async function qzListPrinters() {
    const ok = await qzConnectSafe();
    if (!ok) return;

    try {
        const printers = await qz.printers.find();
        console.log("PRINTERS:", printers);
        return printers;
    } catch (err) {
        console.error("Printer lookup failed:", err);
    }
}


// =========================
// PRINT CAJERO TICKET
// =========================
window.printCajeroTicket = async function printCajeroTicket(data, activeTableId, payMethod) {

  try {
    const ok = await qzConnectSafe();
    if (!ok) return false;

    const printer = await qz.printers.find("Cajero");

    if (!printer) {
        console.error("Printer 'Cajero' not found");
        return false;
    }

    const config = qz.configs.create(printer, { encoding: 'CP437' });

    const ticket = [
      '\x1B\x40',                // init
      '\x1B\x61\x01',           // center
      '*** CRAZY MOON ***\n',
      '\x1B\x61\x00',           // left
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

    console.log("PRINT OK");
    return true;

  } catch (err) {
    console.error("PRINT ERROR:", err);
    return false;
  }
}