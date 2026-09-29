type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'primary' | 'default';

const statusLabels: Record<string, string> = {
    pending_faculty: 'Pending Faculty Approval',
    pending_admin:   'Pending Admin Approval',
    approved:        'Approved',
    borrowed:        'Currently Borrowed',
    returned:        'Returned',
    overdue:         'Overdue',
    cancelled:       'Cancelled',
    rejected:        'Rejected',
};

const statusVariants: Record<string, BadgeVariant> = {
    pending_faculty: 'warning',
    pending_admin:   'info',
    approved:        'info',
    borrowed:        'primary',
    returned:        'success',
    overdue:         'danger',
    cancelled:       'default',
    rejected:        'danger',
};

export function useBorrowingStatus() {
    const formatStatus = (status: string): string =>
        statusLabels[status] ?? status.replace(/_/g, ' ');

    const statusVariant = (status: string): BadgeVariant =>
        statusVariants[status] ?? 'default';

    return { formatStatus, statusVariant };
}
