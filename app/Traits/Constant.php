<?php

namespace App\Traits;

use App\Constants\ResponseCodes;

trait Constant
{
    public $successCode = ResponseCodes::SUCCESS;
    public $clientErrorCode = ResponseCodes::CLIENT_ERROR;
    public $serverErrorCode = ResponseCodes::SERVER_ERROR;
    public $failCode = 400;

    /*
     * Images Paths
     * */
//    public $imageParentDirectory = '/api';
    public $imageParentDirectory = '';

    public $default_user_image = 'def_user.png';
    public $user_image_directory = '/images/users/';


}
