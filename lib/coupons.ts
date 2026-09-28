import { prisma } from './prisma';
import { SettingsMap } from './settings';

// ---------- Coupon validation & discount ----------
export type CouponCheck = { ok: boolean; couponId?: number; code?: string; reason?: string; discount: number };

/** Validate a coupon against cart lines (product/category scoped coupons supported). */
export async function validateCoupon(
  s: SettingsMap,
  codeRaw: string,
  lines: { productId: number; categoryId: number | null; unitPrice: number; quantity: number }[],
  userId: number | null,
): Promise<CouponCheck> {
  const code = codeRaw.trim().toUpperCase();
  if (!code) return { ok: false, discount: 0, reason: 'No coupon code' };
  const now = new Date();
  const coupon = await prisma.coupon.findUnique({ where: { code } });
  if (!coupon || !coupon.isActive) return { ok: false, discount: 0, reason: 'Invalid coupon code' };
  if (coupon.startsAt && coupon.startsAt > now) return { ok: false, discount: 0, reason: 'Coupon not active yet' };
  if (coupon.expiresAt && coupon.expiresAt < now) return { ok: false, discount: 0, reason: 'Coupon expired' };
  if (coupon.usageLimit !== null && coupon.usedCount >= coupon.usageLimit)
    return { ok: false, discount: 0, reason: 'Coupon usage limit reached' };
  if (userId && coupon.perUserLimit !== null) {
    const mine = await prisma.couponUsage.count({ where: { couponId: coupon.id, userId } });
    if (mine >= coupon.perUserLimit) return { ok: false, discount: 0, reason: 'You have already used this coupon' };
  }

  // Eligible subtotal (respect product/category restrictions)
  let eligible = 0;
  for (const l of lines) {
    if (coupon.productId !== null && coupon.productId !== l.productId) continue;
    if (coupon.categoryId !== null && coupon.categoryId !== l.categoryId) continue;
    eligible += l.unitPrice * l.quantity;
  }
  if (eligible <= 0) return { ok: false, discount: 0, reason: 'Coupon does not apply to items in your cart' };
  if (eligible < Number(coupon.minOrderAmount))
    return { ok: false, discount: 0, reason: `Minimum order ${coupon.minOrderAmount} required` };

  let discount =
    coupon.discountType === 'percent'
      ? Math.round(eligible * (Number(coupon.discountValue) / 100) * 100) / 100
      : Number(coupon.discountValue);
  if (coupon.maxDiscount !== null && discount > Number(coupon.maxDiscount)) discount = Number(coupon.maxDiscount);
  if (discount > eligible) discount = eligible;
  return { ok: true, couponId: coupon.id, code, discount, reason: undefined };
}
