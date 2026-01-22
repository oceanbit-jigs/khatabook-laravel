<?php


namespace App\Constants;


class EndPoints
{
    /**
     * ========================================================================
     * Users Services
     * ========================================================================
     */
    const user_register = '/user/register';
    const user_login = '/user/login';
    const user_forgotPassword = '/user/forgotPassword';
    const user_forgotPasswordCustom = '/user/forgotPasswordCustom';
    const user_adminUsers = '/user/adminUsers';
    const user_detail = '/user/detail';
    const user_profile = '/user/profile';
    const user_changePassword = '/user/changePassword';
    const user_updateProfile = '/user/updateProfile';
    const user_logout = '/user/logout';
    const user_delete = '/user/delete';
    const user_delete_verify = '/user/delete/verify';
    const user_list = '/user/list';
    const user_activeUsers = '/user/activeUsers';
    const user_newUsers = '/user/newUsers';
    const user_blockUsers = '/user/blockUsers';
    const notificationToggle = '/user/notificationToggle';
    const user_sendEmailCode = '/user/sendEmailCode';
    const user_verifyEmailCode = '/user/verifyEmailCode';
    const user_verify_firebase_token = '/user/verifyFirebaseToken';
    const user_verify_firebase_token_phone = '/user/verifyFirebaseToken/phone';
    const user_softdelete = '/user/softdelete';
    const user_delete_from_email = '/user/delete/fromemail';
    const admin_register = '/register/admin';


    /**
     * ========================================================================
     * Middeleware Route
     * ========================================================================
     */
    const unauthorised = 'unauthorised';
    const adminaccess = 'adminaccess';
    const activeaccess = 'activeaccess';
    const password_reset = 'password/reset';
    const password_update = 'password/update';
}
