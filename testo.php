<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    // For Codecov.
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: ['tests/Unit'],
        ),
        new SuiteConfig(
            name: 'Contract',
            location: ['tests/Contract'],
        ),
        new SuiteConfig(
            name: 'Characterization',
            location: ['tests/Characterization'],
        ),
    ],
);
