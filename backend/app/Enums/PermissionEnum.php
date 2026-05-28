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

    // Supplier
    case CREATE_SUPPLIER = 'supplier.create';
    case UPDATE_SUPPLIER = 'supplier.update';
    case DELETE_SUPPLIER = 'supplier.delete';

    // Customer
    case CREATE_CUSTOMER = 'customer.create';
    case UPDATE_CUSTOMER = 'customer.update';
    case DELETE_CUSTOMER = 'customer.delete';

    // Invoice (future)
    case CREATE_INVOICE = 'invoice.create';
    case UPDATE_INVOICE = 'invoice.update';
    case DELETE_INVOICE = 'invoice.delete';

    // Bank
    case CREATE_BANK = 'bank.create';
    case UPDATE_BANK = 'bank.update';
    case DELETE_BANK = 'bank.delete';

    // Bank account
    case CREATE_BANK_ACCOUNT = 'bank_account.create';
    case UPDATE_BANK_ACCOUNT = 'bank_account.update';
    case DELETE_BANK_ACCOUNT = 'bank_account.delete';

    // Unit
    case CREATE_UNIT = 'unit.create';
    case UPDATE_UNIT = 'unit.update';
    case DELETE_UNIT = 'unit.delete';

    // Product
    case CREATE_PRODUCT = 'product.create';
    case UPDATE_PRODUCT = 'product.update';
    case DELETE_PRODUCT = 'product.delete';
}
