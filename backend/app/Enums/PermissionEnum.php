<?php

namespace App\Enums;

enum PermissionEnum: string
{
    // Business
    case UpdateBusiness = 'business.update';
    case DeleteBusiness = 'business.delete';
    case CreateStore = 'business.create_store';

    // Store
    case UpdateStore = 'store.update';
    case DeactivateStore = 'store.deactivate';
    case ReactivateStore = 'store.reactivate';

    // Store members
    case AssignStoreUser = 'store.assign_user';
    case RemoveStoreUser = 'store.remove_user';

    // Invoice (future)
    case CreateInvoice = 'invoice.create';
    case UpdateInvoice = 'invoice.update';
    case DeleteInvoice = 'invoice.delete';
}