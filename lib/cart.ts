import { cookies } from 'next/headers';
import crypto from 'crypto';
import { prisma } from './prisma';
import { getSession } from './session';
import { validateCoupon, CouponCheck } from './coupons';
import { SettingsMap } from './settings';

// ---------- Cart token cookie (DB-backed carts for serverless) ----------
const CART_COOKIE = 'ab_cart';

/** Returns the visitor's cart token. `issue=true` also sets the cookie (only allowed in Server Actions / Route Handlers). */
export function getCartToken(issue = false): string | null {
  let token = cookies().get(CART_COOKIE)?.value ?? null;
  if (!token || !/^[a-f0-9]{32,64}$/.test(token)) {
    if (!issue) return null;
    token = crypto.randomBytes(24).toString('hex');
    cookies().set(CART_COOKIE, token, {
      httpOnly: true,
      sameSite: 'lax',
      secure: process.env.NODE_ENV === 'production',
      path: '/',
      maxAge: 60 * 60 * 24 * 30,
    });
  }
  return token;
}

async function ensureCart() {
  const token = getCartToken(true)!;
  const session = getSession();
  let cart = await prisma.cart.findUnique({ where: { token } });
  if (!cart) cart = await prisma.cart.create({ data: { token, userId: session?.uid ?? null } });
  else if (session && cart.userId !== session.uid) {
    // merge anonymous cart into user on login: reassign + drop duplicate user-cart lines
    const userCart = await prisma.cart.findFirst({ where: { userId: session.uid, NOT: { id: cart.id } } });
    if (userCart) {
      for (const item of await prisma.cartItem.findMany({ where: { cartId: cart.id } })) {
        const existing = await prisma.cartItem.findFirst({ where: { cartId: userCart.id, productId: item.productId, variantId: item.variantId } });
        if (existing) {
          await prisma.cartItem.update({ where: { id: existing.id }, data: { quantity: existing.quantity + item.quantity } });
        } else {
          await prisma.cartItem.create({ data: { cartId: userCart.id, productId: item.productId, variantId: item.variantId, quantity: item.quantity } });
        }
      }
      await prisma.cart.delete({ where: { id: cart.id } });
      cart = userCart;
    } else {
      cart = await prisma.cart.update({ where: { id: cart.id }, data: { userId: session.uid } });
    }
  }
  return cart;
}

export type CartLine = {
  id: number;
  productId: number;
  variantId: number | null;
  quantity: number;
  name: string;
  slug: string;
  image: string | null;
  unitPrice: number;
  lineTotal: number;
  stockAvailable: number;
  categoryId: number | null;
  variantName: string | null;
};

export type CartView = {
  lines: CartLine[];
  count: number;
  subtotal: number;
  couponCode: string | null;
  coupon: CouponCheck | null;
  discount: number;
  totalAfterDiscount: number;
};

/** Load the cart with live prices/stock and applied-coupon evaluation. Read-only: never issues cookies. */
export async function getCart(s?: SettingsMap): Promise<CartView> {
  const token = getCartToken(false);
  const session = getSession();
  if (!token && !session) return emptyCart();
  let cart = token ? await prisma.cart.findUnique({ where: { token } }) : null;
  if (!cart && session) cart = await prisma.cart.findFirst({ where: { userId: session.uid } });
  if (!cart) return emptyCart();
  const items = await prisma.cartItem.findMany({
    where: { cartId: cart.id },
    include: {
      product: { select: { id: true, name: true, slug: true, price: true, salePrice: true, stockQuantity: true, stockStatus: true, isVariable: true, status: true, categoryId: true, images: { orderBy: { sortOrder: 'asc' }, take: 1 } } },
      variant: true,
    },
    orderBy: { addedAt: 'asc' },
  });

  const lines: CartLine[] = [];
  for (const it of items) {
    const p = it.product;
    if (!p || p.status !== 'published') continue;
    const v = it.variant;
    const basePrice = Number(v ? (v.salePrice ?? v.price) : (p.salePrice ?? p.price));
    const stockAvailable = v ? v.stockQuantity : p.isVariable ? 0 : p.stockQuantity;
    lines.push({
      id: it.id,
      productId: p.id,
      variantId: v?.id ?? null,
      quantity: it.quantity,
      name: v?.name ? `${p.name} — ${v.name}` : p.name,
      slug: p.slug,
      image: v?.image ?? p.images[0]?.image ?? null,
      unitPrice: basePrice,
      lineTotal: Math.round(basePrice * it.quantity * 100) / 100,
      stockAvailable,
      categoryId: p.categoryId,
      variantName: v?.name ?? null,
    });
  }

  const subtotal = Math.round(lines.reduce((a, l) => a + l.lineTotal, 0) * 100) / 100;
  let coupon: CouponCheck | null = null;
  if (cart.couponCode && s) {
    coupon = await validateCoupon(s, cart.couponCode, lines.map(l => ({ productId: l.productId, categoryId: l.categoryId, unitPrice: l.unitPrice, quantity: l.quantity })), cart.userId);
    // NOTE: invalid coupons are ignored at render time; they are cleared in apply/checkout flows.
  }
  const discount = coupon?.ok ? coupon.discount : 0;
  return {
    lines,
    count: lines.reduce((a, l) => a + l.quantity, 0),
    subtotal,
    couponCode: coupon?.ok ? coupon.code ?? null : null,
    coupon,
    discount,
    totalAfterDiscount: Math.round((subtotal - discount) * 100) / 100,
  };
}

function emptyCart(): CartView {
  return { lines: [], count: 0, subtotal: 0, couponCode: null, coupon: null, discount: 0, totalAfterDiscount: 0 };
}

export async function addToCart(productId: number, quantity: number, variantId?: number | null) {
  const cart = await ensureCart();
  const product = await prisma.product.findUnique({ where: { id: productId }, include: { variants: true } });
  if (!product || product.status !== 'published') throw new Error('Product not found');
  if (product.isVariable && !variantId) throw new Error('Please choose options first');
  const variant = variantId ? product.variants.find(v => v.id === variantId) : null;
  if (variantId && !variant) throw new Error('Option not found');
  const stock = variant ? variant.stockQuantity : product.stockQuantity;
  if (stock <= 0) throw new Error('Out of stock');

  const existing = await prisma.cartItem.findFirst({ where: { cartId: cart.id, productId, variantId: variantId ?? null } });
  if (existing) {
    await prisma.cartItem.update({ where: { id: existing.id }, data: { quantity: Math.min(existing.quantity + quantity, stock) } });
  } else {
    await prisma.cartItem.create({ data: { cartId: cart.id, productId, variantId: variantId ?? null, quantity: Math.min(Math.max(1, quantity), stock) } });
  }
  revalidateCart();
  return true;
}

export async function updateCartItem(itemId: number, quantity: number) {
  const cart = await ensureCart();
  const item = await prisma.cartItem.findFirst({ where: { id: itemId, cartId: cart.id }, include: { product: true, variant: true } });
  if (!item) throw new Error('Cart item not found');
  if (quantity <= 0) {
    await prisma.cartItem.delete({ where: { id: item.id } });
  } else {
    const stock = item.variant ? item.variant.stockQuantity : item.product.stockQuantity;
    await prisma.cartItem.update({ where: { id: item.id }, data: { quantity: Math.min(quantity, Math.max(stock, 1)) } });
  }
  revalidateCart();
}

export async function removeCartItem(itemId: number) {
  const cart = await ensureCart();
  await prisma.cartItem.deleteMany({ where: { id: itemId, cartId: cart.id } });
  revalidateCart();
}

export async function clearCart() {
  const cart = await ensureCart();
  await prisma.cartItem.deleteMany({ where: { cartId: cart.id } });
  await prisma.cart.update({ where: { id: cart.id }, data: { couponCode: null } });
  revalidateCart();
}

export async function applyCouponToCart(code: string, s: SettingsMap) {
  const cart = await ensureCart();
  const view = await getCart(s);
  const check = await validateCoupon(
    s,
    code,
    view.lines.map(l => ({ productId: l.productId, categoryId: l.categoryId, unitPrice: l.unitPrice, quantity: l.quantity })),
    cart.userId,
  );
  if (check.ok) await prisma.cart.update({ where: { id: cart.id }, data: { couponCode: code.trim().toUpperCase() } });
  revalidateCart();
  return check;
}

export async function removeCouponFromCart() {
  const cart = await ensureCart();
  await prisma.cart.update({ where: { id: cart.id }, data: { couponCode: null } });
  revalidateCart();
}

function revalidateCart() {
  // Lazy import to avoid cycles; Next revalidates cart-dependent routes.
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const { revalidatePath } = require('next/cache');
    ['/cart', '/checkout'].forEach(p => revalidatePath(p));
  } catch {}
}
