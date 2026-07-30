<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoachStaffPermission extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'id',
        'name',
        'slug',
    ];


       public function roles()
    {
        return $this->belongsToMany(CoachStaffRole::class, 'roles_permissions');
    }
 

        public static function findDynamic($data){
        $result = [];
        $flag = true;
        if(count($data) >0){
            foreach($data as $key=>$value){
                $result=  self::where($key, $value)->first();
            }
        }
        if(isset($result['id']) && $result['id'] !=0){
            $flag = false;
            return $flag;
        }else{
            return $flag;
        }

    }



}
