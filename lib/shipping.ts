import { SettingsMap } from './settings';

export const PAKISTAN_PROVINCES = [
  'Punjab',
  'Sindh',
  'Khyber Pakhtunkhwa',
  'Balochistan',
  'Gilgit-Baltistan',
  'Azad Jammu & Kashmir',
  'Islamabad Capital Territory',
];

export const PAKISTAN_CITIES = [
  'Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Peshawar',
  'Quetta', 'Sialkot', 'Gujranwala', 'Hyderabad', 'Bahawalpur', 'Sargodha', 'Sukkur',
  'Larkana', 'Abbottabad', 'Mardan', 'Dera Ghazi Khan', 'Gujrat', 'Kasur',
];

function parseRates(json?: string): Record<string, number> {
  try {
    const obj = JSON.parse(json || '{}');
    return typeof obj === 'object' && obj ? obj : {};
  } catch {
    return {};
  }
}

/**
 * Compute shipping cost based on configured mode.
 * Modes: flat | free_threshold | city | province
 */
export function calcShipping(s: SettingsMap, subtotalAfterDiscount: number, loc: { city?: string; province?: string }): number {
  const mode = s.shipping_mode || 'flat';
  if (mode === 'free_threshold') {
    const threshold = parseFloat(s.free_shipping_threshold || '0');
    return subtotalAfterDiscount >= threshold ? 0 : parseFloat(s.shipping_flat_cost || '0');
  }
  if (mode === 'city') {
    const rates = parseRates(s.shipping_city_rates);
    const city = (loc.city || '').trim();
    if (rates[city] !== undefined) return Number(rates[city]);
    return parseFloat(s.shipping_flat_cost || '0'); // fallback for unlisted cities
  }
  if (mode === 'province') {
    const rates = parseRates(s.shipping_province_rates);
    const prov = (loc.province || '').trim();
    if (rates[prov] !== undefined) return Number(rates[prov]);
    return parseFloat(s.shipping_flat_cost || '0');
  }
  return parseFloat(s.shipping_flat_cost || '0');
}

export function calcTax(s: SettingsMap, base: number): number {
  if (s.tax_enabled !== '1') return 0;
  const rate = parseFloat(s.tax_rate || '0');
  return Math.round(base * (rate / 100) * 100) / 100;
}

export function enabledPaymentMethods(s: SettingsMap): { id: string; label: string }[] {
  const list: { id: string; label: string }[] = [];
  if (s.cod_enabled === '1') list.push({ id: 'cod', label: 'Cash on Delivery' });
  if (s.bank_transfer_enabled === '1') list.push({ id: 'bank_transfer', label: 'Bank Transfer' });
  if (s.easypaisa_enabled === '1') list.push({ id: 'easypaisa', label: 'Easypaisa' });
  if (s.jazzcash_enabled === '1') list.push({ id: 'jazzcash', label: 'JazzCash' });
  return list;
}
