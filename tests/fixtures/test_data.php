<?php

/**
 * Test Data Fixtures للاختبارات
 * 
 * يحتوي هذا الملف على بيانات اختبار ثابتة يمكن استخدامها في الاختبارات المختلفة
 */

return [
    'users' => [
        'manager' => [
            'id' => 1,
            'name' => 'أحمد محمد',
            'email' => 'ahmed@example.com',
            'role' => 'manager',
            'supervisor_id' => null,
            'manager_id' => null,
        ],
        'supervisor' => [
            'id' => 2,
            'name' => 'فاطمة علي',
            'email' => 'fatima@example.com',
            'role' => 'supervisor',
            'supervisor_id' => null,
            'manager_id' => 1,
        ],
        'employee_with_supervisor' => [
            'id' => 3,
            'name' => 'محمد خالد',
            'email' => 'mohammed@example.com',
            'role' => 'employee',
            'supervisor_id' => 2,
            'manager_id' => 1,
        ],
        'employee_without_supervisor' => [
            'id' => 4,
            'name' => 'سارة أحمد',
            'email' => 'sarah@example.com',
            'role' => 'employee',
            'supervisor_id' => null,
            'manager_id' => 1,
        ],
        'employee_no_email' => [
            'id' => 5,
            'name' => 'عمر حسن',
            'email' => null,
            'role' => 'employee',
            'supervisor_id' => 2,
            'manager_id' => 1,
        ],
        'employee_invalid_email' => [
            'id' => 6,
            'name' => 'خالد يوسف',
            'email' => 'invalid-email',
            'role' => 'employee',
            'supervisor_id' => 2,
            'manager_id' => 1,
        ],
    ],

    'evaluation_cycles' => [
        'active_cycle' => [
            'id' => 1,
            'year' => 2024,
            'status' => 'active',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ],
        'inactive_cycle' => [
            'id' => 2,
            'year' => 2023,
            'status' => 'inactive',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
        ],
    ],

    'evaluations' => [
        'manager_only' => [
            'employee_id' => 3,
            'cycle_id' => 1,
            'evaluator_role' => 'manager',
            'total_score' => 85.0,
        ],
        'supervisor_only' => [
            'employee_id' => 3,
            'cycle_id' => 1,
            'evaluator_role' => 'supervisor',
            'total_score' => 90.0,
        ],
        'both_evaluations' => [
            [
                'employee_id' => 3,
                'cycle_id' => 1,
                'evaluator_role' => 'manager',
                'total_score' => 85.0,
            ],
            [
                'employee_id' => 3,
                'cycle_id' => 1,
                'evaluator_role' => 'supervisor',
                'total_score' => 90.0,
            ],
        ],
    ],

    'system_settings' => [
        'smtp' => [
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '587',
            'smtp_user' => 'test@example.com',
            'smtp_pass' => 'password123',
            'smtp_secure' => 'tls',
            'smtp_from_email' => 'noreply@example.com',
            'smtp_from_name' => 'نظام تقييم الأداء',
        ],
        'email_settings' => [
            'auto_send_eval' => '1',
            'evaluation_method' => 'average_complete',
            'evaluation_email_manager_only_enabled' => '1',
            'evaluation_email_available_score_mode' => 'any',
            'evaluation_email_average_complete_mode' => 'waiting_supervisor_plus_final',
        ],
    ],

    'email_templates' => [
        'evaluation_complete' => [
            'type' => 'evaluation_complete',
            'subject' => 'تم استكمال تقييمك - النتيجة: {score}',
            'body' => '<p>السلام عليكم {employee_name}</p><p>تم استكمال تقييمك بنجاح.</p><p>النتيجة النهائية: {score}/100</p>',
        ],
        'evaluation_waiting' => [
            'type' => 'evaluation_waiting',
            'subject' => 'تقييمك قيد الانتظار',
            'body' => '<p>السلام عليكم {employee_name}</p><p>تم تقييمك ولكن لا يزال بانتظار تقييم آخر.</p>',
        ],
    ],

    'expected_email_types' => [
        EmailService::TYPE_MANAGER_EVALUATED,
        EmailService::TYPE_SUPERVISOR_EVALUATED,
        EmailService::TYPE_AVAILABLE_ANY,
        EmailService::TYPE_FINAL_COMPLETE,
        EmailService::TYPE_WAITING_SUPERVISOR,
        EmailService::TYPE_WAITING_MANAGER,
    ],

    'test_scenarios' => [
        'manager_only_method' => [
            'evaluation_method' => 'manager_only',
            'enabled_setting' => 'evaluation_email_manager_only_enabled',
            'employee_id' => 3,
            'evaluations' => [
                ['role' => 'manager', 'score' => 85.0, 'should_send' => true],
                ['role' => 'supervisor', 'score' => 90.0, 'should_send' => false],
            ],
        ],
        'available_score_method_any' => [
            'evaluation_method' => 'available_score',
            'mode_setting' => 'evaluation_email_available_score_mode',
            'mode_value' => 'any',
            'employee_id' => 3,
            'evaluations' => [
                ['role' => 'manager', 'score' => 85.0, 'should_send' => true],
                ['role' => 'supervisor', 'score' => 90.0, 'should_send' => true],
            ],
        ],
        'available_score_method_both' => [
            'evaluation_method' => 'available_score',
            'mode_setting' => 'evaluation_email_available_score_mode',
            'mode_value' => 'both',
            'employee_id' => 3,
            'evaluations' => [
                ['role' => 'manager', 'score' => 85.0, 'should_send' => false],
                ['role' => 'supervisor', 'score' => 90.0, 'should_send' => true],
            ],
        ],
        'average_complete_method' => [
            'evaluation_method' => 'average_complete',
            'mode_setting' => 'evaluation_email_average_complete_mode',
            'mode_value' => 'waiting_supervisor_plus_final',
            'employee_id' => 3,
            'has_supervisor' => true,
            'evaluations' => [
                ['role' => 'manager', 'score' => 85.0, 'should_send_waiting' => true, 'should_send_complete' => false],
                ['role' => 'supervisor', 'score' => 90.0, 'should_send_waiting' => false, 'should_send_complete' => true],
            ],
        ],
        'average_complete_no_supervisor' => [
            'evaluation_method' => 'average_complete',
            'mode_setting' => 'evaluation_email_average_complete_mode',
            'mode_value' => 'waiting_supervisor_plus_final',
            'employee_id' => 4,
            'has_supervisor' => false,
            'evaluations' => [
                ['role' => 'manager', 'score' => 85.0, 'should_send_complete' => true],
            ],
        ],
    ],

    'error_scenarios' => [
        'no_smtp_host' => [
            'smtp_host' => '',
            'expected_error' => 'SMTP configuration',
        ],
        'no_smtp_credentials' => [
            'smtp_user' => '',
            'smtp_pass' => '',
            'expected_error' => 'authentication',
        ],
        'invalid_employee_email' => [
            'employee_email' => 'invalid-email',
            'expected_status' => 'failure',
        ],
        'null_employee_email' => [
            'employee_email' => null,
            'expected_status' => 'failure',
            'expected_error' => 'غير متوفر',
        ],
        'non_existent_employee' => [
            'employee_id' => 9999,
            'expected_behavior' => 'no_email_sent',
        ],
    ],
];
