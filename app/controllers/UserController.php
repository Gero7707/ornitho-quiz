<?php 
require_once __DIR__ . '/../models/UserModel.php';


class UserController{
    private UserModel $user;

    public function __construct(){
        $this->user = new UserModel();
    }

    public function showLogin(){
        require_once __DIR__ . '/../views/auth/login.php';
    }
}