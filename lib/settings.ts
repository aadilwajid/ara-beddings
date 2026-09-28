import { prisma } from './prisma';

export type SettingsMap = Record<string, string>;

const DEFAULTS: SettingsMap = {
  store_name: 'Ara Beddings',
  store_description: 'Quality bedsheets, bedding & home essentials delivered across Pakistan.',
  logo_text: 'Ara Beddings',
  logo_image: '',
  favicon: '/favicon.svg',
  currency_code: 'PKR',
  currency_symbol: 'Rs.',
  country: 'Pakistan',
  language: 'en',
  contact_phone: '+92 300 0000000',
  whatsapp_number: '923000000000',
  contact_email: 'info@example.com',
  store_address: 'Main Bazaar, Lahore, Pakistan',
  facebook_url: '',
  instagram_url: '',
  tiktok_url: '',
  youtube_url: '',
  twitter_url: '',
  shipping_mode: 'flat', // flat | free_threshold | city | province
  shipping_flat_cost: '250',
  free_shipping_threshold: '5000',
  shipping_city_rates: '{"Karachi":200,"Lahore":200,"Islamabad":250,"Rawalpindi":250,"Faisalabad":250,"Multan":300,"Peshawar":350,"Quetta":400,"Sialkot":300,"Gujranwala":250}',
  shipping_province_rates: '{"Punjab":250,"Sindh":250,"Khyber Pakhtunkhwa":350,"Balochistan":400,"Gilgit-Baltistan":450,"Azad Jammu & Kashmir":350}',
  tax_enabled: '0',
  tax_rate: '0',
  cod_enabled: '1',
  bank_transfer_enabled: '1',
  easypaisa_enabled: '1',
  jazzcash_enabled: '1',
  bank_account_title: '',
  bank_account_name: '',
  bank_account_number: '',
  bank_iban: '',
  bank_name: '',
  easypaisa_number: '',
  easypaisa_title: '',
  jazzcash_number: '',
  jazzcash_title: '',
  return_policy_days: '7',
  return_policy:
    "Unused items in original packaging may be returned within {days} days of delivery. Customized/sale items are non-returnable. Return shipping is customer's responsibility unless the item is defective or wrong.",
  terms_conditions:
    'By placing an order you agree to product prices, delivery timelines and our return policy as published on this store.',
  privacy_policy: 'We collect only the information needed to process and deliver your order. We never sell your data.',
  hero_title: 'Comfort That Feels Like Home',
  hero_subtitle: 'Premium bedsheets & bedding — Cash on Delivery across Pakistan',
  hero_image: '/hero.svg',
  hero_button_text: 'Shop Now',
  show_home_sections: 'featured,new,bestsellers,categories,reviews,trust,payment,newsletter',
  products_per_page: '12',
  low_stock_default: '5',
};

let cache: SettingsMap | null = null;
let cacheAt = 0;
const TTL = 30_000; // 30s in-memory cache (per warm lambda)

export async function getSettings(): Promise<SettingsMap> {
  const now = Date.now();
  if (cache && now - cacheAt < TTL) return cache;
  try {
    const rows = await prisma.setting.findMany();
    const map: SettingsMap = { ...DEFAULTS };
    for (const r of rows) if (r.value !== null) map[r.key] = r.value;
    cache = map;
    cacheAt = now;
    return map;
  } catch {
    // DB unavailable during early boot — fall back to defaults so pages still render.
    return { ...DEFAULTS };
  }
}

export function invalidateSettingsCache() {
  cache = null;
  cacheAt = 0;
}

export function setting(s: SettingsMap, key: string, fallback = ''): string {
  return s[key] ?? DEFAULTS[key] ?? fallback;
}

export function waLink(s: SettingsMap, message?: string): string {
  const num = String(s.whatsapp_number || '').replace(/[^0-9]/g, '');
  if (!num) return '#';
  const text = message ? `?text=${encodeURIComponent(message)}` : '';
  return `https://wa.me/${num}${text}`;
}

export function money(amount: number | string, s?: SettingsMap): string {
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;
  const sym = s?.currency_symbol ?? DEFAULTS.currency_symbol;
  return `${sym} ${Number.isFinite(n) ? n.toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : '0'}`;
}
