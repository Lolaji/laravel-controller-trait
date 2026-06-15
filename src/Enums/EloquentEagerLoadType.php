<?php

namespace Lolaji\LaravelControllerTrait\Enums;

enum EloquentEagerLoadType: string
{
    case WITH = "with";
    case LOAD = "load";
}