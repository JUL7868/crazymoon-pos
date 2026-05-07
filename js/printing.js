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
            if (typeof toast === "function") {
                toast('Error conectando con impresora');
            }
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
// FORMAT MONEY HELPER
// =========================
function qzFormatMoney(value) {
    const num = Number(value || 0);
    return `$${num.toFixed(2)}`;
}


// =========================
// SAFE BUZZER HELPER
// =========================
// Important:
// Some printers do not reliably execute the buzzer command
// when it is embedded inside the same raw print block.
// This sends the buzzer as its own separate raw command.
window.qzBuzzPrinter = async function qzBuzzPrinter(printerName) {
    try {
        const ok = await qzConnectSafe();
        if (!ok) return false;

        const printer = await qz.printers.find(printerName);

        if (!printer) {
            console.error(`Printer '${printerName}' not found for buzzer`);
            return false;
        }

        const config = qz.configs.create(printer, { encoding: 'CP437' });

        const buzzer = String.fromCharCode(27, 66, 2, 3);

        await qz.print(config, [{
            type: 'raw',
            format: 'command',
            data: buzzer
        }]);

        console.log(`BUZZER OK: ${printerName}`);
        return true;

    } catch (err) {
        console.error(`BUZZER ERROR: ${printerName}`, err);
        return false;
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
            if (typeof toast === "function") {
                toast('Impresora de cajero no encontrada');
            }
            return false;
        }

        const config = qz.configs.create(printer, { encoding: 'CP437' });

        const items =
            Array.isArray(data) ? data :
            Array.isArray(data?.items) ? data.items :
            Array.isArray(data?.cart) ? data.cart :
            Array.isArray(data?.lines) ? data.lines :
            Array.isArray(data?.order_items) ? data.order_items :
            [];

        const table =
            activeTableId ||
            data?.table ||
            data?.table_id ||
            data?.mesa ||
            '';

        const payment =
            payMethod ||
            data?.payMethod ||
            data?.payment_method ||
            data?.metodo_pago ||
            '';

        const subtotal =
            data?.subtotal ??
            data?.ventas_subtotal ??
            null;

        const tax =
            data?.tax ??
            data?.iva ??
            data?.ventas_iva ??
            null;

        const total =
            data?.total ??
            data?.ventas_total ??
            data?.grand_total ??
            data?.amount ??
            0;

        const ticket = [
            '\x1B\x40',
            '\x1B\x61\x01',
            '\x1B\x21\x10',
            'CRAZY MOON\n',
            '\x1B\x21\x00',
            'TICKET CAJERO\n',
            '\x1B\x61\x00',
            '------------------------------\n',
            table ? `Mesa: ${String(table)}\n` : '',
            payment ? `Pago: ${String(payment)}\n` : '',
            `Fecha: ${new Date().toLocaleString('es-MX')}\n`,
            '------------------------------\n',

            items.map(item => {
                const qty =
                    item.qty ??
                    item.quantity ??
                    item.cantidad ??
                    1;

                const name =
                    item.name ??
                    item.item ??
                    item.product_name ??
                    item.producto ??
                    'Producto';

                const lineTotal =
                    item.total ??
                    item.line_total ??
                    item.price ??
                    item.precio ??
                    0;

                return `${String(qty)} x ${String(name)}\n${qzFormatMoney(lineTotal)}\n`;
            }).join(''),

            '------------------------------\n',
            subtotal !== null ? `Subtotal: ${qzFormatMoney(subtotal)}\n` : '',
            tax !== null ? `IVA: ${qzFormatMoney(tax)}\n` : '',
            '\x1B\x45\x01',
            `TOTAL: ${qzFormatMoney(total)}\n`,
            '\x1B\x45\x00',
            '------------------------------\n',
            '\x1B\x61\x01',
            'Gracias por su visita\n',
            '\x1B\x61\x00',
            '\n\n\n\n',
            '\x1D\x56\x00'
        ].join('');

        await qz.print(config, [{
            type: 'raw',
            format: 'command',
            data: ticket
        }]);

        console.log("CAJERO PRINT OK");
        return true;

    } catch (err) {
        console.error("CAJERO PRINT ERROR:", err);
        if (typeof toast === "function") {
            toast('Error imprimiendo en cajero');
        }
        return false;
    }
}


// =========================
// PRINT KITCHEN TICKET
// =========================
window.printKitchenTicket = async function printKitchenTicket(order) {

    try {
        const ok = await qzConnectSafe();
        if (!ok) return false;

        const printer = await qz.printers.find("Cocina");

        if (!printer) {
            console.error("Printer 'Cocina' not found");
            if (typeof toast === "function") {
                toast('Impresora de cocina no encontrada');
            }
            return false;
        }

        const config = qz.configs.create(printer, { encoding: 'CP437' });

        const ticket = [
            '\x1B\x40',
            '\x1B\x61\x01',
            '\x1B\x21\x10',
            '*** COCINA ***\n',
            '\x1B\x21\x00',
            '\x1B\x61\x00',
            '------------------------------\n',
            `Mesa: ${String(order?.table ?? '')}\n`,
            `${String(order?.qty ?? 1)} x ${String(order?.item ?? 'Producto')}\n`,
            order?.notes ? `Nota: ${String(order.notes)}\n` : '',
            '------------------------------\n',
            '\n\n\n\n\n\n\n\n\n\n\n\n',
            '\x1D\x56\x00'
        ].join('');

        await qz.print(config, [{
            type: 'raw',
            format: 'command',
            data: ticket
        }]);

        // Buzzer intentionally sent AFTER the ticket,
        // as a separate raw command.
        await window.qzBuzzPrinter("Cocina");

        console.log("KITCHEN PRINT OK");
        return true;

    } catch (err) {
        console.error("KITCHEN PRINT ERROR:", err);
        if (typeof toast === "function") {
            toast('Error imprimiendo en cocina');
        }
        return false;
    }
}