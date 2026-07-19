<?php

namespace App\Policies;

use App\Models\Family;
use App\Models\User;

class FamilyPolicy
{

    public function before(User $user, $ability)
    {
        if(in_array($user->role,['admin','manager'])){
            return true;
        }
    }


    /**
     * عرض قائمة الأسر
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role,[
            'data_entry',
            'manager',
            'admin'
        ]);
    }



    /**
     * مشاهدة أسرة معينة
     */
    public function view(User $user, Family $family): bool
    {

        // data entry فقط داخل مخيمه
        if($user->role === 'data_entry'){

            return $user->camp_id == $family->camp_id;

        }


        return false;
    }



    /**
     * إنشاء أسرة
     */
    public function create(User $user): bool
    {
        return $user->role === 'data_entry';
    }



    /**
     * تعديل بيانات الأسرة
     */
    public function update(User $user, Family $family): bool
    {

        if($user->role === 'data_entry'){

            return $user->camp_id == $family->camp_id;

        }


        return false;
    }




    /**
     * حذف الأسرة
     */
    public function delete(User $user, Family $family): bool
    {

        if($user->role === 'data_entry'){

            return $user->camp_id == $family->camp_id;

        }


        return false;
    }



    public function restore(User $user, Family $family): bool
    {
        return false;
    }


    public function forceDelete(User $user, Family $family): bool
    {
        return false;
    }

}