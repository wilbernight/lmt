import { MONTHS, WEEKDAYS, persianWeekday, toJalali } from './jalali';

const digits = '۰۱۲۳۴۵۶۷۸۹';

export function faNum(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[0-9]/g, (d) => digits[d]);
}

export function enNum(value) {
    return String(value ?? '')
        .replace(/[۰-۹]/g, (d) => digits.indexOf(d))
        .replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
}

const numberFormat = new Intl.NumberFormat('fa-IR');

export function formatNumber(value) {
    return numberFormat.format(Number(value || 0));
}

export function formatMoney(value) {
    return value ? `${formatNumber(value)} تومان` : '—';
}

export function formatBytes(bytes) {
    if (!bytes) return '۰ بایت';
    const units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${faNum((bytes / 1024 ** i).toFixed(i ? 1 : 0))} ${units[i]}`;
}

const toDate = (v) => (v instanceof Date ? v : new Date(v));

export function jDate(value, { weekday = false, year = true } = {}) {
    if (!value) return '';
    const d = toDate(value);
    const j = toJalali(d);
    const parts = [faNum(j.jd), MONTHS[j.jm - 1]];
    if (year) parts.push(faNum(j.jy));
    const text = parts.join(' ');
    return weekday ? `${WEEKDAYS[persianWeekday(d)]} ${text}` : text;
}

export function jTime(value) {
    if (!value) return '';
    const d = toDate(value);
    return faNum(`${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`);
}

export function jDateTime(value) {
    if (!value) return '';
    return `${jDate(value)}، ${jTime(value)}`;
}

export function isSameDay(a, b) {
    const x = toDate(a);
    const y = toDate(b);
    return x.getFullYear() === y.getFullYear() && x.getMonth() === y.getMonth() && x.getDate() === y.getDate();
}

/** "۳ دقیقه پیش", "دیروز", "۲ روز دیگر"... */
export function relative(value) {
    if (!value) return '';
    const d = toDate(value);
    const diff = (d.getTime() - Date.now()) / 1000;
    const abs = Math.abs(diff);
    const future = diff > 0;
    const wrap = (n, unit) => `${faNum(n)} ${unit} ${future ? 'دیگر' : 'پیش'}`;

    if (abs < 45) return future ? 'چند لحظه دیگر' : 'همین الان';
    if (abs < 3600) return wrap(Math.round(abs / 60), 'دقیقه');
    if (abs < 86400 && isSameDay(d, new Date())) return wrap(Math.round(abs / 3600), 'ساعت');

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const day = new Date(d);
    day.setHours(0, 0, 0, 0);
    const days = Math.round((day - today) / 86400000);

    if (days === -1) return 'دیروز';
    if (days === 1) return 'فردا';
    if (Math.abs(days) < 7) return `${faNum(Math.abs(days))} روز ${days > 0 ? 'دیگر' : 'پیش'}`;

    return jDate(d, { year: d.getFullYear() !== today.getFullYear() });
}

export function dueLabel(value, completed = false) {
    if (!value) return null;
    const d = toDate(value);
    const today = new Date();
    if (!completed && d < today && !isSameDay(d, today)) return { text: relative(d), tone: 'danger' };
    if (isSameDay(d, today)) return { text: `امروز ${jTime(d)}`, tone: 'warning' };
    return { text: relative(d), tone: 'muted' };
}

export function greeting() {
    const h = new Date().getHours();
    if (h < 5) return 'شب بخیر';
    if (h < 12) return 'صبح بخیر';
    if (h < 17) return 'روز بخیر';
    if (h < 20) return 'عصر بخیر';
    return 'شب بخیر';
}

export const PRIORITIES = [
    { value: 0, label: 'کم', tone: 'slate' },
    { value: 1, label: 'معمولی', tone: 'sky' },
    { value: 2, label: 'زیاد', tone: 'amber' },
    { value: 3, label: 'فوری', tone: 'rose' },
];

/** "۳ ساعت و ۲۰ دقیقه" (long) or "۳:۲۰" (short). */
export function formatDuration(seconds, { short = false, withSeconds = false } = {}) {
    const total = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;

    if (short) {
        const parts = [h, String(m).padStart(2, '0')];
        if (withSeconds) parts.push(String(s).padStart(2, '0'));
        return faNum(parts.join(':'));
    }

    if (!h && !m) return total ? 'کمتر از یک دقیقه' : '۰';
    if (!h) return `${faNum(m)} دقیقه`;
    if (!m) return `${faNum(h)} ساعت`;
    return `${faNum(h)} ساعت و ${faNum(m)} دقیقه`;
}

export function hours(seconds) {
    return faNum((Math.round(((seconds || 0) / 3600) * 10) / 10).toString());
}
