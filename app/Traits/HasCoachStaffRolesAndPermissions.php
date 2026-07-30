<?php

namespace App\Traits;

use App\Models\CoachStaffPermission;
use App\Models\CoachStaffRole;  
trait HasCoachStaffRolesAndPermissions
{

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isAdmin()
    {
        if($this->roles->contains('slug', 'admin')){
            return true;
        }
    }
    /**
     * @return mixed
     */
    public function roles()
    {
        return $this->belongsToMany(CoachStaffRole::class,'users_roles');
    }

    /**
     * @return mixed
     */
    public function permissions()
    {
        return $this->belongsToMany(CoachStaffPermission::class,'users_permissions');
    }

    /**
     * Check if the user has Role
     *
     * @param [type] $role
     * @return boolean
     */
    public function hasRole($role)
    {        
        if( strpos($role, ',') !== false ){//check if this is an list of roles
            
            $listOfRoles = explode(',',$role);
            
            foreach ($listOfRoles as $role) {  
                if ($this->roles->contains('slug', $role)) {
                    return true;
                }
            }
        }else{                               
            if ($this->roles->contains('slug', $role)) {
                return true;
            }
        }

        return false;
    }
}