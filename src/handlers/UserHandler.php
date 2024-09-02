<?php
namespace src\handlers;

use src\models\User;
use src\handlers\PermissionsHandler;

class UserHandler {

    public static function listAllUsers(){

        $data = User::select()->get();
        // print_r($data);
        // exit;
        if($data>0){
            $all = [];
            foreach($data as $dt){
                $users = new User();
                $users->id = $dt['id'];
                $users->name = $dt['name'];
                $users->mail = $dt['email'];
                $users->avatar = $dt['avatar'];
                $users->type = $dt['type'];
                $users->id_permission = $dt['id_permission'];
                $users->active = $dt['active'];

                switch($users->type){
                    case 1: 
                        $users->type = "Administrador";
                        break;

                    case 2: 
                        $users->type = "Redator";
                        break;
                    }

                switch($users->active){
                    case 1: 
                        $users->active = "Ativo";
                        break;

                    case 2: 
                        $users->active = "Inativo";
                        break;
                    }
                $all[]=$users;
            }return $all;
        }return false;

    }

    public static function delUser($id){
       
        User::delete()
        ->where('id', $id)
        ->execute();

        return true;
    }

    public static function addUser($name, $mail, $pass, $newNameAvatar, $active, $type, $id_permission){
        // echo "<pre>";
        // var_dump($avatar);
        // exit;
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $token = md5(time().rand(0,9999).time());
        $id = User::insert([
            'name'      => $name,
            'email'     => $mail,
            'password'  => $hash,
            'avatar'  => $newNameAvatar,
            'type' => $type,
            'id_permission' => $id_permission,
            'active' => $active,
            'token'     => $token,
            'created_at'=> date('Y-m-d H:i:s')
        ])->execute();

        return $id;
    }

    public static function editUser($name, $mail, $pass, $type, $id_permission, $avatar, $active, $id){
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        User::update()
                ->set('name', $name)
                ->set('email', $mail)
                ->set('password', $hash)
                ->set('type', $type)
                ->set('id_permission', $id_permission)
                ->set('avatar', $avatar)
                ->set('active', $active)
                // ->set('created_at', date('Y-m-d H:i:s'))
                ->where('id', $id)
                ->execute();

                return true;
            
                
    }



   
}
