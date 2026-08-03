<?php

namespace App\Enums\Filter;

enum DimensionAppliesTo: string
{
    case PROJECT = 'project';
    case RELEASE = 'release';
}
