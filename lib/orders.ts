// =============================================================
// Order placement + status transitions. ALL money math happens
// here on the server; nothing from the client is trusted.
// =============================================================
import { prisma } from './prisma';
import { getSession } from './session';
import { getCart, clearCart } from './cart';
import { validateCoupon } from './coupons';
import { calcShipping, calcTax } from './shipping';
import { SettingsMap } from './settings';
import { generateOrderNumber, round2, normalizePkPhone, isValidPkPhone, isEmail, STOCK_RESTORE_STATUSES, STOCK_REDUCED_STATUSES } from './utils';
import type { PaymentMethod, OrderStatus } from '@prisma/client';

export type CheckoutInput = {
  fullName: string;
  phone: string;
  email?: string;
  province: string;
  city: string;
  area?: string;
  address: string;
  landmark?: string;
  postalCode?: string;
  notes?: string;
  paymentMethod: string;
};

export type CheckoutResult =
  | { ok: true; orderId: bigint; orderNumber: string; grandTotal: number }
  | { ok: false; error: string };

const VALID_METHODS: PaymentMethod[] = ['cod', 'bank_transfer', 'easypaisa', 'jazzcash'];

export async function placeOrder(s: SettingsMap, input: CheckoutInput): Promise<CheckoutResult> {
  // ---- Server-side validation ----
  const fullName = String(input.fullName ?? '').trim();
  if (fullName.length < 2 || fullName.length > 120) return { ok: false, error: 'Please enter your full name.' };
  const phone = normalizePkPhone(String(input.phone ?? ''));
  if (!isValidPkPhone(phone)) return { ok: false, error: 'Please enter a valid Pakistani mobile number (e.g. 03001234567).' };
  const email = String(input.email ?? '').trim();
  if (email && !isEmail(email)) return { ok: false, error: 'Please enter a valid email address.' };
  if (!String(input.province ?? '').trim()) return { ok: false, error: 'Please select your province.' };
  if (!String(input.city ?? '').trim()) return { ok: false, error: 'Please enter your city.' };
  const address = String(input.address ?? '').trim();
  if (address.length < 8) return { ok: false, error: 'Please enter your complete address.' };
  const method = String(input.paymentMethod) as PaymentMethod;
  if (!VALID_METHODS.includes(method)) return { ok: false, error: 'Invalid payment method.' };
  const enabledKey: Record<string, string> = { cod: 'cod_enabled', bank_transfer: 'bank_transfer_enabled', easypaisa: 'easypaisa_enabled', jazzcash: 'jazzcash_enabled' };
  if (s[enabledKey[method]] !== '1') return { ok: false, error: 'This payment method is currently unavailable.' };

  // ---- Load cart with live prices/stock (never trust client totals) ----
  const cart = await getCart(s);
  if (cart.lines.length === 0) return { ok: false, error: 'Your cart is empty.' };

  // Stock re-check at placement time
  for (const line of cart.lines) {
    if (line.stockAvailable < line.quantity) {
      return { ok: false, error: `Only ${line.stockAvailable} left of "${line.name}". Please update your cart.` };
    }
  }

  const subtotal = round2(cart.subtotal);
  let discount = 0;
  let couponId: number | null = null;
  let couponCodeUsed: string | null = null;
  if (cart.coupon?.ok && cart.coupon.couponId) {
    discount = round2(cart.coupon.discount);
    couponId = cart.coupon.couponId;
    couponCodeUsed = cart.coupon.code ?? null;
  }
  const afterDiscount = round2(Math.max(subtotal - discount, 0));
  const session = getSession();
  const loc = { city: String(input.city).trim(), province: String(input.province).trim() };
  const shippingCost = round2(calcShipping(s, afterDiscount, loc));
  const taxAmount = round2(calcTax(s, afterDiscount));
  const grandTotal = round2(afterDiscount + shippingCost + taxAmount);

  const order = await prisma.$transaction(async (tx) => {
    // Re-validate stock inside the transaction to prevent overselling under races.
    const created = await tx.order.create({
      data: {
        orderNumber: generateOrderNumber(),
        userId: session?.uid ?? null,
        customerName: fullName,
        customerPhone: phone,
        customerEmail: email || null,
        province: loc.province,
        city: loc.city,
        area: String(input.area ?? '').trim() || null,
        address,
        landmark: String(input.landmark ?? '').trim() || null,
        postalCode: String(input.postalCode ?? '').trim() || null,
        subtotal,
        discount,
        shippingCost,
        taxAmount,
        grandTotal,
        couponCode: couponCodeUsed,
        paymentMethod: method,
        paymentStatus: method === 'cod' ? 'pending' : 'pending', // manual payments need admin verification
        orderNotes: String(input.notes ?? '').trim() || null,
        items: {
          create: cart.lines.map((l) => ({
            productId: l.productId,
            variantId: l.variantId,
            productName: l.name.split(' — ')[0],
            variantName: l.variantName,
            sku: null,
            image: l.image,
            unitPrice: l.unitPrice,
            quantity: l.quantity,
            lineTotal: l.lineTotal,
          })),
        },
        history: { create: [{ status: 'pending', note: 'Order placed' }] },
        payments: { create: [{ method, amount: grandTotal, status: 'pending' }] },
      },
      include: { items: true },
    });

    // Fill SKUs from DB (server-authoritative) and deduct stock atomically.
    for (const item of created.items) {
      if (item.variantId) {
        const v = await tx.productVariant.findUnique({ where: { id: item.variantId }, select: { sku: true, productId: true, stockQuantity: true } });
        if (!v) throw new Error('Variant no longer exists');
        // Conditional decrement — fails if insufficient stock (prevents overselling).
        const dec = await tx.productVariant.updateMany({
          where: { id: v.id, stockQuantity: { gte: item.quantity } },
          data: { stockQuantity: { decrement: item.quantity } },
        });
        if (dec.count === 0) throw new Error(`Insufficient stock for ${item.productName}`);
        const after = await tx.productVariant.findUnique({ where: { id: v.id }, select: { stockQuantity: true } });
        await tx.inventoryTransaction.create({
          data: { productId: v.productId, variantId: v.id, changeQty: -item.quantity, reason: 'sale', reference: created.orderNumber, actor: 'checkout' },
        });
        await tx.orderItem.update({ where: { id: item.id }, data: { sku: v.sku } });
        void after;
      } else if (item.productId) {
        const p = await tx.product.findUnique({ where: { id: item.productId }, select: { sku: true, stockQuantity: true, lowStockThreshold: true } });
        if (!p) throw new Error('Product no longer exists');
        const dec = await tx.product.updateMany({
          where: { id: p.id, stockQuantity: { gte: item.quantity } },
          data: { stockQuantity: { decrement: item.quantity } },
        });
        if (dec.count === 0) throw new Error(`Insufficient stock for ${item.productName}`);
        await tx.inventoryTransaction.create({
          data: { productId: p.id, changeQty: -item.quantity, reason: 'sale', reference: created.orderNumber, actor: 'checkout' },
        });
        await tx.orderItem.update({ where: { id: item.id }, data: { sku: p.sku } });
      }
    }

    // Coupon usage accounting
    if (couponId) {
      await tx.coupon.update({ where: { id: couponId }, data: { usedCount: { increment: 1 } } });
      await tx.couponUsage.create({ data: { couponId, orderId: created.id, userId: session?.uid ?? null } });
    }

    // Sold counts
    for (const item of created.items) {
      if (item.productId) {
        await tx.product.update({ where: { id: item.productId }, data: { soldCount: { increment: item.quantity } } }).catch(() => {});
      }
    }

    return created;
  });

  await clearCart();
  return { ok: true, orderId: order.id, orderNumber: order.orderNumber, grandTotal };
}

/** Public tracking lookup — must match order number AND phone/email; minimal exposure. */
export async function trackOrder(orderNumber: string, identifier: string) {
  const num = String(orderNumber).trim().toUpperCase();
  const idRaw = String(identifier).trim();
  if (!num || !idRaw) return null;
  const norm = /^\d+$/.test(idRaw.replace(/[^0-9]/g, '')) ? normalizePkPhone(idRaw) : idRaw.toLowerCase();
  const order = await prisma.order.findFirst({
    where: {
      orderNumber: num,
      OR: [
        { customerPhone: norm },
        ...(isEmail(idRaw) ? [{ customerEmail: idRaw.toLowerCase() }] : []),
      ],
    },
    include: { items: true, history: { orderBy: { changedAt: 'asc' } } },
  });
  return order;
}

export type StatusUpdateResult = { ok: boolean; error?: string };

/** Admin/staff status transition with inventory side effects. Idempotent w.r.t. stock. */
export async function setOrderStatus(orderId: bigint, next: OrderStatus, actor: string, note?: string): Promise<StatusUpdateResult> {
  const order = await prisma.order.findUnique({ where: { id: orderId }, include: { items: true } });
  if (!order) return { ok: false, error: 'Order not found' };
  const prev = order.status;
  if (prev === next) return { ok: true };

  const wasReduced = (STOCK_REDUCED_STATUSES as readonly string[]).includes(prev);
  const willRestore = (STOCK_RESTORE_STATUSES as readonly string[]).includes(next);
  const wasRestored = (STOCK_RESTORE_STATUSES as readonly string[]).includes(prev);
  const willReduce = (STOCK_REDUCED_STATUSES as readonly string[]).includes(next);

  await prisma.$transaction(async (tx) => {
    if (willRestore && !wasRestored && wasReduced !== false) {
      // restore stock when moving INTO cancelled/returned/refunded FROM a reduced state
      if (wasReduced) {
        for (const it of order.items) {
          if (it.variantId) {
            await tx.productVariant.update({ where: { id: it.variantId }, data: { stockQuantity: { increment: it.quantity } } });
            const v = await tx.productVariant.findUnique({ where: { id: it.variantId }, select: { productId: true } });
            await tx.inventoryTransaction.create({ data: { productId: v!.productId, variantId: it.variantId, changeQty: it.quantity, reason: next === 'cancelled' ? 'cancel_restore' : 'return', reference: order.orderNumber, actor } });
          } else if (it.productId) {
            await tx.product.update({ where: { id: it.productId }, data: { stockQuantity: { increment: it.quantity } } });
            await tx.inventoryTransaction.create({ data: { productId: it.productId, changeQty: it.quantity, reason: next === 'cancelled' ? 'cancel_restore' : 'return', reference: order.orderNumber, actor } });
          }
        }
      }
    }
    if (willReduce && !wasReduced && (STOCK_RESTORE_STATUSES as readonly string[]).includes(prev)) {
      // re-deduct when reviving a cancelled order into an active state
      for (const it of order.items) {
        if (it.variantId) {
          const dec = await tx.productVariant.updateMany({ where: { id: it.variantId, stockQuantity: { gte: it.quantity } }, data: { stockQuantity: { decrement: it.quantity } } });
          if (dec.count === 0) throw new Error(`Insufficient stock to revive order (${it.productName})`);
          const v = await tx.productVariant.findUnique({ where: { id: it.variantId }, select: { productId: true } });
          await tx.inventoryTransaction.create({ data: { productId: v!.productId, variantId: it.variantId, changeQty: -it.quantity, reason: 'sale', reference: order.orderNumber, actor } });
        } else if (it.productId) {
          const dec = await tx.product.updateMany({ where: { id: it.productId, stockQuantity: { gte: it.quantity } }, data: { stockQuantity: { decrement: it.quantity } } });
          if (dec.count === 0) throw new Error(`Insufficient stock to revive order (${it.productName})`);
          await tx.inventoryTransaction.create({ data: { productId: it.productId, changeQty: -it.quantity, reason: 'sale', reference: order.orderNumber, actor } });
        }
      }
    }
    await tx.order.update({ where: { id: orderId }, data: { status: next } });
    await tx.orderStatusHistory.create({ data: { orderId, status: next, note: note || `${prev} → ${next}` } });
  });

  return { ok: true };
}

export async function setPaymentStatus(orderId: bigint, status: 'paid' | 'failed' | 'refunded', reference?: string, actor?: string): Promise<StatusUpdateResult> {
  const order = await prisma.order.findUnique({ where: { id: orderId } });
  if (!order) return { ok: false, error: 'Order not found' };
  await prisma.$transaction([
    prisma.order.update({ where: { id: orderId }, data: { paymentStatus: status, ...(reference ? { paymentReference: reference } : {}) } }),
    prisma.payment.updateMany({ where: { orderId }, data: { status, ...(reference ? { reference } : {}) } }),
    prisma.orderStatusHistory.create({
      data: { orderId, status: order.status, note: `Payment marked ${status}${reference ? ` (ref ${reference})` : ''} by ${actor ?? 'admin'}` },
    }),
  ]);
  return { ok: true };
}
