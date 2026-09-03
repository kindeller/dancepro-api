<?php

namespace App\Features\Auth\Support;

enum CrewMobileLoginStatus: string
{
    case Passed = 'passed';
    case InvalidCredentials = 'invalid_credentials';
    case AccessUnavailable = 'access_unavailable';
    case TwoFactorSetupRequired = 'two_factor_setup_required';
    case TwoFactorRequired = 'two_factor_required';
    case TwoFactorInvalid = 'two_factor_invalid';
}
