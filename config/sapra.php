<?php

return [
    /*
    | Nilai default sistem. Dipakai sebagai fallback saat GeneralSettings
    | belum tersedia — misal di console, seeder, atau sebelum migrasi settings.
    */

    'institution_name' => env('SAPRA_INSTITUTION', 'Politeknik Aisyiyah'),
    'institution_short' => env('SAPRA_INSTITUTION_SHORT', 'Poltek Aisyiyah'),

    'asset_code_prefix' => env('SAPRA_ASSET_PREFIX', 'PA'),

    'colors' => [
        'primary' => '#14315C',
        'accent' => '#2E90FA',
    ],

    'qr' => [
        'base_path' => '/a',
        'error_correction' => 'Q',
        'margin' => 1,
    ],

    'label' => [
        'width_mm' => 60,
        'height_mm' => 40,
        'qr_size_mm' => 26,
        'per_sheet' => [
            'columns' => 2,
            'rows' => 5,
            'margin_mm' => 10,
        ],
    ],

    'depreciation' => [
        'default_useful_life' => 4,
        'repair_threshold_percent' => 50,
    ],

    'loan' => [
        'max_days_internal' => 14,
        'max_days_external' => 7,
        'reminder_days_before' => 1,
    ],

    'themes' => [
        'navy' => [
            'label' => 'Navy Institusional',
            'primary' => '#14315C',
            'hover' => '#0E2444',
            'soft' => '#EEF3F9',
            'accent' => '#2E90FA',
            'bg' => '#F5F7FA',
            'border' => '#E3E8EF',
            'text' => '#101828',
            'muted' => '#667085',
        ],
        'teal' => [
            'label' => 'Teal Akademik',
            'primary' => '#0F5E63',
            'hover' => '#0A4649',
            'soft' => '#E8F2F2',
            'accent' => '#14B8A6',
            'bg' => '#F6F8F8',
            'border' => '#E1E8E8',
            'text' => '#0F1F20',
            'muted' => '#64757A',
        ],
        'slate' => [
            'label' => 'Slate Profesional',
            'primary' => '#334155',
            'hover' => '#1E293B',
            'soft' => '#F1F5F9',
            'accent' => '#3B82F6',
            'bg' => '#F8FAFC',
            'border' => '#E2E8F0',
            'text' => '#0F172A',
            'muted' => '#64748B',
        ],
        'navy' => [
            'label' => 'Navy Institusional',
            'primary' => '#14315C',
            'hover' => '#0E2444',
            'soft' => '#EEF3F9',
            'accent' => '#2E90FA',
            'bg' => '#F5F7FA',
            'border' => '#E3E8EF',
            'text' => '#101828',
            'muted' => '#667085',
            'hero_bg' => '#14315C',
            'hero_text' => '#FFFFFF',
            'hero_muted' => '#B6C6DC',
        ],
        'teal' => [
            'label' => 'Teal Akademik',
            'primary' => '#0F5E63',
            'hover' => '#0A4649',
            'soft' => '#E8F2F2',
            'accent' => '#14B8A6',
            'bg' => '#F6F8F8',
            'border' => '#E1E8E8',
            'text' => '#0F1F20',
            'muted' => '#64757A',
            'hero_bg' => '#0F5E63',
            'hero_text' => '#FFFFFF',
            'hero_muted' => '#A9D3D3',
        ],
        'slate' => [
            'label' => 'Slate Profesional',
            'primary' => '#334155',
            'hover' => '#1E293B',
            'soft' => '#F1F5F9',
            'accent' => '#3B82F6',
            'bg' => '#F8FAFC',
            'border' => '#E2E8F0',
            'text' => '#0F172A',
            'muted' => '#64748B',
            'hero_bg' => '#1E293B',
            'hero_text' => '#FFFFFF',
            'hero_muted' => '#CBD5E1',
        ],
    ],
];
