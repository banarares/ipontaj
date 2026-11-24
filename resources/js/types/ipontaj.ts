export interface Company {
    customer_type: 'business' | 'consumer';
    cif: string | null;
    registry_verified_at: string | null;
    id: number;
    name: string;
    timezone: string;
    is_demo: boolean;
    reminders: boolean;
    accountant_email: string | null;
    accountant_confirmed_at: string | null;
}
export interface Member {
    id: number;
    user_id: number | null;
    managed: boolean;
    role: 'owner' | 'employee';
    active: boolean;
    tracked: boolean;
    reminders: boolean;
    user: {
        id: number | null;
        name: string;
        first_name: string;
        last_name: string;
        email: string | null;
    };
    joined_on: string;
}
export interface Interval {
    id?: number;
    kind: 'work' | 'break';
    start_at: string;
    end_at: string;
}
export interface Entry {
    id: number;
    membership_id: number;
    date: string;
    minutes: number;
    absence: string | null;
    confirmed_at: string | null;
    approved_at: string | null;
    intervals: Interval[];
    note?: string;
}
export interface Day {
    date: string;
    planned: number;
    missing: boolean;
    entry: Entry | null;
}
export interface SummaryRow {
    membership: Member;
    days: Day[];
    planned: number;
    confirmed: number;
    missing: number;
    pending: number;
}
export interface Issue {
    membership_id: number;
    name: string;
    date: string;
    type: string;
    entry_id?: number;
}
export interface WorkSchedule {
    id: number;
    membership_id: number | null;
    weekdays: number[];
    start_time: string;
    end_time: string;
    effective_from: string;
}
export interface Invite {
    first_name: string;
    last_name: string;
    id: number;
    email: string;
    name: string;
    expires_at: string;
}
export interface Audit {
    id: number;
    action: string;
    reason: string | null;
    created_at: string;
    user: { name: string } | null;
    after?: { month?: string; date?: string };
}
