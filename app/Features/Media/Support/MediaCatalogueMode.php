<?php

namespace App\Features\Media\Support;

enum MediaCatalogueMode: string
{
    case Storage = 'storage';
    case Managed = 'managed';
    case Hybrid = 'hybrid';
    case Manifest = 'manifest';
}
