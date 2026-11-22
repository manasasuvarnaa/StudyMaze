<?php
/**
 * WCAG 2.1 Contrast & Accessibility Audit Script for StudyMaze
 */

function sRgbToLinear($c) {
    $c = $c / 255.0;
    return ($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
}

function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    ];
}

function relativeLuminance($hex) {
    list($r, $g, $b) = hexToRgb($hex);
    return 0.2126 * sRgbToLinear($r) + 0.7152 * sRgbToLinear($g) + 0.0722 * sRgbToLinear($b);
}

function contrastRatio($hex1, $hex2) {
    $l1 = relativeLuminance($hex1);
    $l2 = relativeLuminance($hex2);
    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);
    return ($lighter + 0.05) / ($darker + 0.05);
}

echo "=== WCAG 2.1 CONTRAST & ACCESSIBILITY AUDIT ===\n\n";

$checks = [
    // 1. Navigation & Global Controls
    [
        'element' => 'Skip to Content Link (.skip-link)',
        'fg' => '#ffffff',
        'bg' => '#c2410c',
        'min' => 4.5,
        'level' => 'AA'
    ],
    [
        'element' => 'Nav Active Item (.nav-link.active)',
        'fg' => '#c2410c',
        'bg' => '#fef2ee', // approx rgba(234, 88, 12, 0.1) on white
        'min' => 4.5,
        'level' => 'AA'
    ],
    [
        'element' => 'Primary Button (.btn-primary)',
        'fg' => '#ffffff',
        'bg' => '#c2410c',
        'min' => 4.5,
        'level' => 'AA'
    ],
    [
        'element' => 'Primary Button Hover (.btn-primary:hover)',
        'fg' => '#ffffff',
        'bg' => '#9a3412',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],

    // 2. Dashboard Components
    [
        'element' => 'Date Badge (.date-badge)',
        'fg' => '#4b5563',
        'bg' => '#f8f9fa',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Quote Banner Text (.quote-banner-text)',
        'fg' => '#4b5563',
        'bg' => '#f8f9fa',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Metric Subtext (.metric-subtext on card)',
        'fg' => '#4b5563',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Goal Subtext (.goal-subtext)',
        'fg' => '#4b5563',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Goal Percentage (.goal-percentage)',
        'fg' => '#c2410c',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA'
    ],
    [
        'element' => 'Accent Links (.link-accent)',
        'fg' => '#c2410c',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA'
    ],
    [
        'element' => 'Recent Session Time (.session-time)',
        'fg' => '#4b5563',
        'bg' => '#f3f4f6', // --bg-card-subtle
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Chart Footer Stats (#chartFooterStats)',
        'fg' => '#4b5563',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],

    // 3. Analytics & Settings Components
    [
        'element' => 'Analytics Subtitle (.page-subtitle)',
        'fg' => '#4b5563',
        'bg' => '#f8f9fa',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Settings Toggle Descriptions',
        'fg' => '#4b5563',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Settings Stepper Labels (.form-label)',
        'fg' => '#111827',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],

    // 4. Auth Pages
    [
        'element' => 'Auth Subtitle (.auth-header p)',
        'fg' => '#4b5563',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Auth Disclaimer (.auth-disclaimer)',
        'fg' => '#4b5563',
        'bg' => '#f8f9fa',
        'min' => 4.5,
        'level' => 'AA / AAA'
    ],
    [
        'element' => 'Input Placeholders (.form-input::placeholder)',
        'fg' => '#6b7280',
        'bg' => '#ffffff',
        'min' => 4.5,
        'level' => 'AA'
    ]
];

$allPassed = true;
foreach ($checks as $c) {
    $ratio = contrastRatio($c['fg'], $c['bg']);
    $passed = $ratio >= $c['min'];
    if (!$passed) $allPassed = false;
    $status = $passed ? "[PASS]" : "[FAIL]";
    printf("%-7s %-45s: %5.2f:1 (Req: ≥ %.1f:1) -> Target: %s\n", $status, $c['element'], $ratio, $c['min'], $c['level']);
}

echo "\n";
if ($allPassed) {
    echo "========================================================\n";
    echo "AUDIT RESULT: 100% OF CONTRAST TESTS PASSED!\n";
    echo "Target WAVE AIM Accessibility Score: ≥ 9.5 / 10 (Target: 10.0)\n";
    echo "========================================================\n";
} else {
    echo "AUDIT RESULT: FAILURES DETECTED\n";
}
