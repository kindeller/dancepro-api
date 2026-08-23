<?php

namespace App\Features\Customers\Support;

enum UserType: string
{
    case Staff = 'staff';
    case Customer = 'customer';
    case Admin = 'admin';
}
