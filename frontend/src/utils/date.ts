// Date + time in Philippine time, e.g. "Oct 8, 2026, 2:35 PM" — the same on
// every device, whatever its own timezone setting.
export const formatDateTime = (value?: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
