<?php

namespace App\Features\Auth\Services;

enum ApiLoginTwoFactorResult: string
{
    case Passed = 'passed';
    case Required = 'required';
    case Invalid = 'invalid';
    case SetupRequired = 'setup_required';
}
