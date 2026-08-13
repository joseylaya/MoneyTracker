export const money = (minor = 0, code = 'PHP') => new Intl.NumberFormat('en-PH', { style: 'currency', currency: code, minimumFractionDigits: 2 }).format(Math.abs(minor) / 100);
export const shortDate = (value) => {
    const datePart = String(value || '').slice(0, 10);
    const date = new Date(`${datePart}T00:00:00`);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('en-PH', { month: 'short', day: 'numeric' }).format(date);
};
