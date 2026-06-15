<?php

enum OperationEnum: string {
    case CREATED                    = "created";
    case UPDATED                    = "updated";
    case DELETED                    = "deleted";
    case ATTACH                     = "attach";
    case DETACH                     = "detach";
    case SYNC                       = "sync";
    case SYNC_WITHOUT_DETACHING     = "syncWithoutDetaching";
    case UPDATE_EXISTING_PIVOT      = "updateExistingPivot";
    case SYNC_WITH_PIVOT_VALUES     = "syncWithPivotValues";
}