<?php

namespace Lolaji\LaravelControllerTrait\Enums;

enum ControllerMethodEnum: string 
{
    case Fetch              = "fetch";
    case Get                = "get";
    case Search             = "search";
    case Upsert             = "upsert";
    case UpsertByForeign    = "upsertByForeign";
    case Deattach           = "deattach";
}