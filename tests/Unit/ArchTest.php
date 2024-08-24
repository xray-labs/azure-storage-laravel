<?php

arch('it should not use dumping functions')
    ->expect(['dd', 'dump', 'die', 'exit', 'var_dump', 'var_export'])
    ->not->toBeUsed();

arch('should use strict types everywhere')
    ->expect('Xray\\AzureStorageLaravel')
    ->toUseStrictTypes();
