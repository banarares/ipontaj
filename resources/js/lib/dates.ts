export function localDateTime(iso: string, zone: string): string {
    const parts = new Intl.DateTimeFormat('sv-SE', {
        timeZone: zone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(new Date(iso));
    return parts.replace(' ', 'T');
}
export function candidates(local: string, zone: string): string[] {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(local)) return [];
    const wall = Date.parse(local + ':00Z');
    const offsets = new Set<string>();
    for (let h = -36; h <= 36; h += 3) {
        const part =
            new Intl.DateTimeFormat('en', {
                timeZone: zone,
                timeZoneName: 'longOffset',
            })
                .formatToParts(new Date(wall + h * 3600000))
                .find((p) => p.type === 'timeZoneName')?.value || 'GMT';
        offsets.add(part === 'GMT' ? '+00:00' : part.replace('GMT', ''));
    }
    return [...offsets]
        .map((offset) => `${local}:00${offset}`)
        .filter((iso) => localDateTime(iso, zone) === local);
}
export function readableDate(
    date: string,
    options: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'long' },
): string {
    return new Intl.DateTimeFormat('ro-RO', {
        ...options,
        timeZone: 'UTC',
    }).format(new Date(date.slice(0, 10) + 'T12:00:00Z'));
}
export function minutes(value: number): string {
    return `${Math.floor(value / 60)} h${value % 60 ? ` ${value % 60} min` : ''}`;
}
export function nextDate(date: string): string {
    return new Date(Date.parse(date + 'T12:00:00Z') + 86400000)
        .toISOString()
        .slice(0, 10);
}
export function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((n) => n[0])
        .join('');
}
