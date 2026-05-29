<?php

namespace InventoryApp\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the users table (02_identity.sql).
 *
 * Relationships:
 *  - userRoles() — the role slugs assigned via the user_roles pivot
 */
class UserModel extends Model
{
    protected $table = 'users';

    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'email',
        'password_hash',
        'name',
        'active',
        'created_at',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * The role slugs assigned to this user (via user_roles → roles).
     * Returns a BelongsToMany so we can eager-load and sync.
     */
    public function userRoles()
    {
        return $this->belongsToMany(
            RoleModel::class,
            'user_roles',   // pivot table
            'user_id',      // FK on pivot pointing to users
            'role_id'       // FK on pivot pointing to roles
        );
    }
}
