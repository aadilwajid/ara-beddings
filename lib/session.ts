// =============================================================
// Stateless, signed-cookie session for Vercel serverless.
// Cookie value: base64url(JSON payload) + "." + HMAC-SHA256 signature
// Uses APP_KEY from the environment; sessions survive across
// stateless function invocations without server-side storage.
// =============================================================
import { cookies } from 'next/headers';
import crypto from 'crypto';

export type SessionData = {
  uid: number;
  role: 'customer' | 'staff' | 'admin' | 'super_admin';
  name: string;
};

const COOKIE_NAME = 'ab_session';
const MAX_AGE = 60 * 60 * 24 * 7; // 7 days

function key(): string {
  const k = process.env.APP_KEY;
  if (!k || k.length < 16) {
    throw new Error('APP_KEY is not configured (set a long random secret in .env)');
  }
  return k;
}

function sign(data: string): string {
  return crypto.createHmac('sha256', key()).update(data).digest('base64url');
}

export function createSession(s: SessionData) {
  const payload = Buffer.from(JSON.stringify({ ...s, exp: Date.now() + MAX_AGE * 1000 })).toString('base64url');
  const token = `${payload}.${sign(payload)}`;
  cookies().set(COOKIE_NAME, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
    maxAge: MAX_AGE,
  });
}

export function getSession(): SessionData | null {
  const raw = cookies().get(COOKIE_NAME)?.value;
  if (!raw) return null;
  const [payload, sig] = raw.split('.');
  if (!payload || !sig) return null;
  try {
    const expected = sign(payload);
    const a = Buffer.from(sig);
    const b = Buffer.from(expected);
    if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return null;
    const data = JSON.parse(Buffer.from(payload, 'base64url').toString());
    if (typeof data.exp !== 'number' || data.exp < Date.now()) return null;
    if (typeof data.uid !== 'number') return null;
    return { uid: data.uid, role: data.role ?? 'customer', name: data.name ?? '' };
  } catch {
    return null;
  }
}

export function destroySession() {
  cookies().delete(COOKIE_NAME);
}

export function isAdminRole(role?: string): boolean {
  return role === 'admin' || role === 'super_admin' || role === 'staff';
}

export function canManage(role?: string): boolean {
  // staff can operate orders/inventory; only admin+ can edit settings/products delete etc.
  return role === 'admin' || role === 'super_admin';
}
