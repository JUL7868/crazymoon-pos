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

    // =========================
    // QZ SIGNING
    // =========================

    qz.security.setSignatureAlgorithm("SHA512");

    qz.security.setCertificatePromise(function(resolve, reject) {

        fetch('/crazymoon_pos/api/qz-certificate.php')
            .then(res => res.text())
            .then(resolve)
            .catch(reject);

    });


    qz.security.setSignaturePromise(function(toSign) {

        return function(resolve, reject) {

            fetch('/crazymoon_pos/api/qz-sign.php', {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/json'
                },

                body: JSON.stringify({
                    request: toSign
                })

            })
            .then(res => res.text())
            .then(resolve)
            .catch(reject);

        };

    });

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

window.qzBuzzPrinter = async function qzBuzzPrinter(printerName) {

    try {

        const ok = await qzConnectSafe();

        if (!ok) return false;

        const printer = await qz.printers.find(printerName);

        if (!printer) {

            console.error(`Printer '${printerName}' not found for buzzer`);

            return false;
        }

        const config = qz.configs.create(printer, {
            encoding: 'CP437'
        });

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
// BUILD CAJERO TICKET TEXT
// =========================

window.buildCajeroTicketText = function buildCajeroTicketText(
    data,
    activeTableId,
    payMethod
) {

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

    const orderId =
        data?.order_id ??
        data?.orderId ??
        data?.id ??
        null;

    const cashTendered =
        data?.cash_tendered ??
        data?.cash_received ??
        data?.efectivo_recibido ??
        null;

    const cashChange =
        data?.cash_change ??
        data?.change ??
        data?.cambio ??
        null;

    const splitCash =
        data?.split_cash ??
        data?.cash_amount ??
        null;

    const splitCard =
        data?.split_card ??
        data?.card_amount ??
        null;

    const guestCount =
        data?.guest_count ??
        data?.guestCount ??
        data?.personas ??
        null;

    const itemLines = items.map(item => {

        const qty =
            item.qty ??
            item.quantity ??
            item.cantidad ??
            1;

        const name =
            item.name ??
            item.item ??
            item.item_name ??
            item.product_name ??
            item.producto ??
            'Producto';

        const lineTotal =
            item.total ??
            item.line_total ??
            item.subtotal ??
            item.price ??
            item.unit_price ??
            item.precio ??
            0;

        const notes =
            item.notes ??
            item.note ??
            item.nota ??
            '';

        const qtyText = String(qty).padEnd(6).slice(0, 6);
        const nameText = String(name).padEnd(18).slice(0, 18);
        const amountText = qzFormatMoney(lineTotal).padStart(8).slice(-8);

        return `${qtyText}${nameText}${amountText}\n${notes ? `      NOTA: ${String(notes)}\n` : ''}`;

    }).join('');

    return [

        'AGUILAR & LORD S.A. DE C.V.\n',

        'RFC: ALI171108C94\n',

        'AV. MIGUEL HIDALGO NUM 106A SAN PEDRO\n',

        'CHOLULA PUEBLA MEXICO CP 72760\n',

        '========================\n',

        'PRUEBA * PRUEBA * PRUEBA\n',

        '========================\n',

        '--------------------------------\n',

        table ? `MESA: ${String(table)}\n` : '',

        orderId !== null ? `FOLIO: ${String(orderId)}\n` : '',

        `FECHA: ${new Date().toLocaleString('es-MX')}\n`,

        orderId !== null ? `ORDEN: ${String(orderId)}\n` : '',

        guestCount !== null ? `PERSONAS: ${String(guestCount)}\n` : '',

        '--------------------------------\n',

        'CANT  DESCRIPCION        IMPORTE\n',

        '--------------------------------\n',

        itemLines,

        '--------------------------------\n',

        subtotal !== null
            ? `SUBTOTAL: ${qzFormatMoney(subtotal)}\n`
            : '',

        tax !== null
            ? `IVA: ${qzFormatMoney(tax)}\n`
            : '',

        `TOTAL: ${qzFormatMoney(total)}\n`,

        '--------------------------------\n',

        payment ? `PAGO: ${String(payment)}\n` : '',

        cashTendered !== null
            ? `EFECTIVO RECIBIDO: ${qzFormatMoney(cashTendered)}\n`
            : '',

        cashChange !== null
            ? `CAMBIO: ${qzFormatMoney(cashChange)}\n`
            : '',

        splitCash !== null
            ? `SPLIT EFECTIVO: ${qzFormatMoney(splitCash)}\n`
            : '',

        splitCard !== null
            ? `SPLIT TARJETA: ${qzFormatMoney(splitCard)}\n`
            : '',

        '--------------------------------\n',

        'GRACIAS POR SU PREFERENCIA\n',

        'ESTE NO ES UN COMPROBANTE FISCAL\n'

    ].join('');
}


// =========================
// PREVIEW CAJERO TICKET
// =========================

window.previewCajeroTicketWindow = function previewCajeroTicketWindow(
    data,
    activeTableId,
    payMethod
) {

    const ticket = window.buildCajeroTicketText(
        data,
        activeTableId,
        payMethod
    );

    const escaped = String(ticket)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const win = window.open('', 'Vista previa ticket cajero', 'width=420,height=700');

    if (!win) {

        console.log(ticket);

        return ticket;
    }

    win.document.write(`<!doctype html>
<html>
<head>
<title>Vista previa ticket cajero</title>
<style>
body {
  margin: 0;
  padding: 24px;
  background: #fff;
}
.receipt {
  width: 300px;
  margin: 0 auto;
  color: #000;
  font-family: "Courier New", monospace;
  font-size: 12px;
  line-height: 1.35;
  white-space: pre-wrap;
}
</style>
</head>
<body>
<pre class="receipt">${escaped}</pre>
</body>
</html>`);

    win.document.close();

    return ticket;
}


function buildCajeroTicketRaw(data, activeTableId, payMethod) {

    return [

        '\x1B\x40',

        '\x1B\x61\x01',

        window.buildCajeroTicketText(
            data,
            activeTableId,
            payMethod
        ),

        '\x1B\x61\x00',

        '\n\n\n\n',

        '\x1D\x56\x00'

    ].join('');
}


// =========================
// BUILD CUENTA TICKET TEXT
// =========================

window.buildCuentaTicketText = function buildCuentaTicketText(
    data,
    activeTableId
) {

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

    const orderId =
        data?.order_id ??
        data?.orderId ??
        data?.id ??
        null;

    const guestCount =
        data?.guest_count ??
        data?.guestCount ??
        data?.personas ??
        null;

    const itemLines = items.map(item => {

        const qty =
            item.qty ??
            item.quantity ??
            item.cantidad ??
            1;

        const name =
            item.name ??
            item.item ??
            item.item_name ??
            item.product_name ??
            item.producto ??
            'Producto';

        const lineTotal =
            item.total ??
            item.line_total ??
            item.subtotal ??
            item.price ??
            item.unit_price ??
            item.precio ??
            0;

        const notes =
            item.notes ??
            item.note ??
            item.nota ??
            '';

        const qtyText = String(qty).padEnd(6).slice(0, 6);
        const nameText = String(name).padEnd(18).slice(0, 18);
        const amountText = qzFormatMoney(lineTotal).padStart(8).slice(-8);

        return `${qtyText}${nameText}${amountText}\n${notes ? `      NOTA: ${String(notes)}\n` : ''}`;

    }).join('');

    return [

        'AGUILAR & LORD S.A. DE C.V.\n',

        'RFC: ALI171108C94\n',

        'AV. MIGUEL HIDALGO NUM 106A SAN PEDRO\n',

        'CHOLULA PUEBLA MEXICO CP 72760\n',

        '========================\n',

        'PRUEBA * PRUEBA * PRUEBA\n',

        '========================\n',

        'CUENTA\n',

        '--------------------------------\n',

        table ? `MESA: ${String(table)}\n` : '',

        `FECHA: ${new Date().toLocaleString('es-MX')}\n`,

        orderId !== null ? `ORDEN: ${String(orderId)}\n` : '',

        guestCount !== null ? `PERSONAS: ${String(guestCount)}\n` : '',

        '--------------------------------\n',

        'CANT  DESCRIPCION        IMPORTE\n',

        '--------------------------------\n',

        itemLines,

        '--------------------------------\n',

        subtotal !== null
            ? `SUBTOTAL: ${qzFormatMoney(subtotal)}\n`
            : '',

        tax !== null
            ? `IVA: ${qzFormatMoney(tax)}\n`
            : '',

        `TOTAL: ${qzFormatMoney(total)}\n`,

        '--------------------------------\n',

        'GRACIAS POR SU PREFERENCIA\n',

        'ESTE NO ES UN COMPROBANTE FISCAL\n'

    ].join('');
}


function buildCuentaTicketRaw(data, activeTableId) {

    return [

        '\x1B\x40',

        '\x1B\x61\x01',

        window.buildCuentaTicketText(
            data,
            activeTableId
        ),

        '\x1B\x61\x00',

        '\n\n\n\n',

        '\x1D\x56\x00'

    ].join('');
}


window.previewCuentaTicketWindow = function previewCuentaTicketWindow(
    data,
    activeTableId
) {

    const ticket = window.buildCuentaTicketText(
        data,
        activeTableId
    );

    const escaped = String(ticket)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const win = window.open('', 'Vista previa cuenta', 'width=420,height=700');

    if (!win) {

        console.log(ticket);

        return ticket;
    }

    win.document.write(`<!doctype html>
<html>
<head>
<title>Vista previa cuenta</title>
<style>
body {
  margin: 0;
  padding: 24px;
  background: #fff;
}
.receipt {
  width: 300px;
  margin: 0 auto;
  color: #000;
  font-family: "Courier New", monospace;
  font-size: 12px;
  line-height: 1.35;
  white-space: pre-wrap;
}
</style>
</head>
<body>
<pre class="receipt">${escaped}</pre>
</body>
</html>`);

    win.document.close();

    return ticket;
}


window.printCuentaTicket = async function printCuentaTicket(
    data,
    activeTableId
) {

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

        const config = qz.configs.create(printer, {
            encoding: 'CP437'
        });

        const ticket = buildCuentaTicketRaw(
            data,
            activeTableId
        );

        await qz.print(config, [{

            type: 'raw',

            format: 'command',

            data: ticket

        }]);

        console.log("CUENTA PRINT OK");

        return true;

    } catch (err) {

        console.error("CUENTA PRINT ERROR:", err);

        if (typeof toast === "function") {

            toast('Error imprimiendo cuenta');

        }

        return false;
    }
}


// =========================
// PRINT CAJERO TICKET
// =========================

window.printCajeroTicket = async function printCajeroTicket(
    data,
    activeTableId,
    payMethod
) {

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

        const config = qz.configs.create(printer, {
            encoding: 'CP437'
        });

        const ticket = buildCajeroTicketRaw(
            data,
            activeTableId,
            payMethod
        );

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

    // ========================================
    // CATEGORY FILTER
    // ========================================

    const kitchenCategories = [

        'Pizzas',
        'Burgers',
        'Botanas',
        'Postres'

    ];

    if (!kitchenCategories.includes(order?.category)) {

        console.log(
            `KITCHEN SKIPPED: ${order?.category}`
        );

        return true;
    }

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

        const config = qz.configs.create(printer, {
            encoding: 'CP437'
        });

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

            order?.notes
                ? `Nota: ${String(order.notes)}\n`
                : '',

            '------------------------------\n',

            '\n\n\n\n\n\n\n\n\n\n\n\n',

            '\x1D\x56\x00'

        ].join('');

        await qz.print(config, [{

            type: 'raw',

            format: 'command',

            data: ticket

        }]);

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
