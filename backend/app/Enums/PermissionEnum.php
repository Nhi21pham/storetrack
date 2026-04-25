<?php

namespace App\Enums;

enum PermissionEnum: string
{
    // Business
    case UPDATE_BUSINESS = 'business.update';
    case DELETE_BUSINESS = 'business.delete';
    case CREATE_STORE    = 'business.create_store';

    // Store
    case UPDATE_STORE     = 'store.update';
    case DEACTIVATE_STORE = 'store.deactivate';
    case REACTIVATE_STORE = 'store.reactivate';

    // Store members
    case ASSIGN_STORE_USER = 'store.assign_user';
    case REMOVE_STORE_USER = 'store.remove_user';

    // Invoice (future)
    case CREATE_INVOICE = 'invoice.create';
    case UPDATE_INVOICE = 'invoice.update';
    case DELETE_INVOICE = 'invoice.delete';
}
