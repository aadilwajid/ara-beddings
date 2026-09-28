import crypto from 'crypto';

export function slugify(input: string): string {
  return String(input)
    .toLowerCase()
    .trim()
    .replace(/['"]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 180) || 'item';
}

/** Public order number like AB-20260929-4F7A2C */
export function generateOrderNumber(): string {
  const d = new Date();
  const ymd = `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`;
  return `AB-${ymd}-${crypto.randomBytes(3).toString('hex').toUpperCase()}`;
}

export function round2(n: number): number {
  return Math.round(n * 100) / 100;
}

export function clamp(n: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, n));
}

/** Parse a form field to a positive int or null. */
export function parseIntOrNull(v: unknown): number | null {
  const n = parseInt(String(v ?? ''), 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

export function parseDecimalOrNull(v: unknown): number | null {
  const s = String(v ?? '').trim();
  if (!s) return null;
  const n = parseFloat(s);
  return Number.isFinite(n) ? n : null;
}

/** Validate Pakistani mobile formats: 03XXXXXXXXX or +923XXXXXXXXX or 923XXXXXXXXX */
export function isValidPkPhone(phone: string): boolean {
  const p = phone.replace(/[\s-]/g, '');
  return /^(?:\+92|0092|92|0)?3\d{9}$/.test(p);
}

export function normalizePkPhone(phone: string): string {
  let p = phone.replace(/[\s-()]/g, '');
  if (p.startsWith('+92')) p = p.slice(3);
  else if (p.startsWith('0092')) p = p.slice(4);
  else if (p.startsWith('0')) p = '92' + p.slice(1);
  return p;
}

/** Loose email check — the real validation is whether the user can read the mail. */
export function isEmail(v: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim());
}

/** Order statuses that restore stock when entered (from a stock-reduced state). */
export const STOCK_RESTORE_STATUSES = ['cancelled', 'returned', 'refunded'] as const;
/** Statuses after which stock has been deducted (confirmed). */
export const STOCK_REDUCED_STATUSES = [
  'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered',
] as const;

export function formatDateTime(d: Date | string): string {
  const dt = typeof d === 'string' ? new Date(d) : d;
  return dt.toLocaleString('en-PK', { dateStyle: 'medium', timeStyle: 'short' });
}

export function formatDate(d: Date | string): string {
  const dt = typeof d === 'string' ? new Date(d) : d;
  return dt.toLocaleDateString('en-PK', { dateStyle: 'medium' });
}

export const ORDER_STATUS_LABELS: Record<string, string> = {
  pending: 'Pending',
  confirmed: 'Confirmed',
  processing: 'Processing',
  packed: 'Packed',
  shipped: 'Shipped',
  out_for_delivery: 'Out for Delivery',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
  returned: 'Returned',
  refunded: 'Refunded',
};

export const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cod: 'Cash on Delivery',
  bank_transfer: 'Bank Transfer',
  easypaisa: 'Easypaisa',
  jazzcash: 'JazzCash',
};

/** Timeline steps shown to customers while an order is progressing. */
export const TRACKING_TIMELINE = [
  'pending', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered',
] as const;
