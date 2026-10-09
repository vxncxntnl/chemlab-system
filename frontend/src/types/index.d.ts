export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    role: 'admin' | 'faculty' | 'student' | 'lab_staff';
    department?: string;
    student_id?: string;
    course?: string | null;
    employee_id?: string;
    contact_number?: string;
    campus_id?: number;
    campus?: { id: number; campus_name: string };
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        // Main campus (Carmen) admin — also sees system setup menus.
        is_main_admin?: boolean;
    };
};
