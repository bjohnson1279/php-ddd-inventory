<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the roles lookup table.
 * Rows are seeded in 02_identity.sql: admin, manager, staff.
 */
class RoleModel extends Model
{
    protected $table = 'roles';

    public $incrementing = false;
    protected $keyType   = 'string';   // PK is VARCHAR(20): 'admin', 'manager', 'staff'
    public $timestamps   = false;

    protected $fillable = ['id', 'name'];
}
