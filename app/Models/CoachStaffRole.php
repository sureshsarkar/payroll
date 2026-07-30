<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoachStaffRole extends Model
{
    use HasFactory;

protected $fillable = [
    'role_name',
    'role_slug',
    'added_by',
    'status',
];
    


// protected function roleName(): Attribute
// {
//     return Attribute::make(
//         set: fn ($value) => strtolower(trim($value)),
//     );
// }



 public function permissions()
    {
        return $this->belongsToMany(CoachStaffPermission::class, 'roles_permissions');
    }

    public function allRolePermissions()
    {
        return $this->belongsToMany(CoachStaffPermission::class, 'roles_permissions');
    }



}
