<?php

namespace App\Features\Media\Support;

enum MediaCollectionVisibility: string
{
    case Private = 'private';
    case Password = 'password';
    case Customer = 'customer';
    case Public = 'public';
}
