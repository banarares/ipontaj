export type LegalData = {
    version: string;
    date: string;
    operator: {
        name: string;
        cif: string;
        register: string;
        address: string;
        email: string;
        phone: string;
        domain: string;
        vat_registered: boolean;
    };
    hosting: {
        provider: string;
        url: string;
        services: string;
        contract_entity: string | null;
        data_location: string | null;
        backup_retention_days: number;
    };
    retention: { plan_approved: boolean; log_days: number };
    links: { href: string; title: string; icon: string }[];
    sal_asset: string | null;
    session_minutes: number;
    remember_days: number;
};
