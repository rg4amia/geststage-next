<?php
return [
    'smig' => [
        'default' => 0,
    ],
    'qualification' => [
        'budget_aej_effective_date' => '2026-01-01',
    ],
    'pae' => [
        '45000' => [
            'full' => 45000,
            'rules' => [
                '1-5' => [
                    'start' => 45000,
                    'end' => 45000
                ],
                '10-19' => [
                    'start' => 31500,
                    'end' => 13500
                ],
                '20' => [
                    'start' => 16500,
                    'end' => 28500
                ]
            ]
        ],
        '75000' => [
            'full' => 75000,
            'rules' => [
                '1-5' => [
                    'start' => 75000,
                    'end' => 75000
                ],
                '10-19' => [
                    'start' => 50000,
                    'end' => 25000
                ],
                '20' => [
                    'start' => 25000,
                    'end' => 50000
                ]
            ]
        ]
    ],
    'stage_ecole' => [
        'budget_aej_effective_date' => '2026-01-01',
        'budget_aej_periods' => [
            [
                'start' => '2016-01-01',
                'end' => '2026-03-31',
                'public' => 'default',
                'prive' => 'default',
            ],
            [
                'start' => '2026-04-01',
                'end' => null,
                'public' => 'budget_aej_public',
                'prive' => 'budget_aej_prive',
            ],
        ],
        'default' => [
            'base' => 15000,
            'first_month' => [
                '1-5' => 15000,
                '10' => 10500,
                '20' => 5500
            ],
            'last_month' => [
                1 => [
                    '10' => 4500,
                    '20' => 9500
                ],
                'one_point_five' => [
                    '1-5' => 7500,
                    '10' => 12000,
                    '20' => 2000
                ],
                'default' => [
                    '1-5' => 15000,
                    '10' => 4500,
                    '20' => 9500
                ]
            ],
            'middle_adjustment_1_5' => [
                '1-5' => 7500,
                '10' => 12000,
                '20' => 15000
            ]
        ],
        'budget_aej_public' => [
            'base' => 25000,
            'first_month' => [
                '1-5' => 25000,
                '10' => 16600,
                '20' => 8400
            ],
            'last_month' => [
                'default' => [
                    '1-5' => 25000,
                    '10' => 16600,
                    '20' => 8400
                ]
            ],
            'middle_adjustment_1_5' => [
                '1-5' => 25000,
                '10' => 16600,
                '20' => 8400
            ]
        ],
        'budget_aej_prive' => [
            'base' => 25000,
            'first_month' => [
                '1-5' => 25000,
                '10' => 16600,
                '20' => 8400
            ],
            'last_month' => [
                'default' => [
                    '1-5' => 25000,
                    '10' => 16600,
                    '20' => 8400
                ]
            ],
            'middle_adjustment_1_5' => [
                '1-5' => 25000,
                '10' => 16600,
                '20' => 8400
            ]
        ],
        'budget_aej_scad' => [
            'base' => 45000,
            'first_month' => [
                '1-5' => 45000,
                '10' => 31500,
                '20' => 16500
            ],
            'last_month' => [
                'one_point_five' => [
                    '1-5' => 22500,
                    '10' => 36000,
                    '20' => 6000
                ],
                'default' => [
                    '1-5' => 45000,
                    '10' => 13500,
                    '20' => 28500
                ]
            ],
            'middle_adjustment_1_5' => [
                '1-5' => 22500,
                '10' => 36000,
                '20' => 45000
            ]
        ]
    ]
];
