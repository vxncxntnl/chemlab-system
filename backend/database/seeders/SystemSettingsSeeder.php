<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'system_name',
                'value' => 'PHINMA COC Chemistry Lab Equipment Borrowing System',
                'group' => 'general',
                'type' => 'text',
                'description' => 'The name of the system displayed in the header',
            ],
            [
                'key' => 'school_name',
                'value' => 'PHINMA – Cagayan de Oro College (COC)',
                'group' => 'general',
                'type' => 'text',
                'description' => 'Name of the school or institution',
            ],
            [
                'key' => 'contact_email',
                'value' => 'admin@phinmacoc.edu.ph',
                'group' => 'general',
                'type' => 'text',
                'description' => 'Contact email for support',
            ],
            [
                'key' => 'contact_phone',
                'value' => '09171234567',
                'group' => 'general',
                'type' => 'text',
                'description' => 'Contact phone number',
            ],

            // Borrowing Settings
            [
                'key' => 'max_borrow_days',
                'value' => '7',
                'group' => 'borrowing',
                'type' => 'number',
                'description' => 'Maximum number of days a user can borrow equipment',
            ],
            [
                'key' => 'max_items_per_borrowing',
                'value' => '5',
                'group' => 'borrowing',
                'type' => 'number',
                'description' => 'Maximum number of items per borrowing request',
            ],
            [
                'key' => 'daily_fine_rate',
                'value' => '10.00',
                'group' => 'borrowing',
                'type' => 'number',
                'description' => 'Daily fine rate for overdue items (in pesos)',
            ],
            [
                'key' => 'allow_weekend_borrowing',
                'value' => 'false',
                'group' => 'borrowing',
                'type' => 'boolean',
                'description' => 'Allow borrowing on weekends',
            ],
            [
                'key' => 'require_approval',
                'value' => 'true',
                'group' => 'borrowing',
                'type' => 'boolean',
                'description' => 'Require admin approval for borrowing requests',
            ],

            // Notification Settings
            [
                'key' => 'send_email_notifications',
                'value' => 'true',
                'group' => 'notifications',
                'type' => 'boolean',
                'description' => 'Send email notifications to users',
            ],
            [
                'key' => 'notify_before_due_days',
                'value' => '1',
                'group' => 'notifications',
                'type' => 'number',
                'description' => 'Number of days before due date to send reminder',
            ],
            [
                'key' => 'notify_overdue',
                'value' => 'true',
                'group' => 'notifications',
                'type' => 'boolean',
                'description' => 'Send notifications for overdue items',
            ],

            // System Settings
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
                'group' => 'system',
                'type' => 'boolean',
                'description' => 'Put the system in maintenance mode',
            ],
            [
                'key' => 'allow_registration',
                'value' => 'true',
                'group' => 'system',
                'type' => 'boolean',
                'description' => 'Allow new user registration',
            ],
            [
                'key' => 'default_user_role',
                'value' => 'student',
                'group' => 'system',
                'type' => 'text',
                'description' => 'Default role for new registrations',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
