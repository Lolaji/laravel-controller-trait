<?php

namespace Lolaji\LaravelControllerTrait\Enums;

enum ControllerHookMethodEnum: string
{
    case Create                 = "created";
    case Update                 = "updated";
    case Delete                 = "deleted";
    case Attach                 = "attach";
    case Detach                 = "detach";
    case Sync                   = "sync";
    case SyncWithoutDetaching   = "syncWithoutDetaching";
    case UpdateExistingPivot    = "updateExistingPivot";
    case SyncWithPivotValues    = "syncWithPivotValues";
}