<?php


namespace App\Constants;


class ControllerMethods
{
    // User Auth
    const register = '@register';
    const login = '@login';
    const changePassword = '@changePassword';
    const forgotPassword = '@forgotPassword';
    const forgotPasswordCustom = '@forgotPasswordCustom';
    const resetPassword = '@resetPassword';
    const updatePassword = '@updatePassword';
    const logout = '@logout';
    const user_delete = '@user_delete';
    const softDeleteUser = '@softDeleteUser';

    // Users
    const profile = '@profile';
    const updateProfile = '@updateProfile';
    const adminUsers = '@adminUsers';
    const activeUsers = '@activeUsers';
    const newUsers = '@newUsers';
    const blockUsers = '@blockUsers';
    const getAllUsers = '@getAllUsers';
    const unauthorised = '@unauthorised';
    const adminaccess = '@adminaccess';
    const activeaccess = '@activeaccess';
    const sendEmailOTP = '@sendEmailOTP';
    const verifyEmailOTP = '@verifyEmailOTP';
    const verifyUserDeleteCode = '@verifyUserDeleteCode';
    const verifyFirebaseToken = '@verifyFirebaseToken';
    const verifyFirebaseTokenwithPhone = '@verifyFirebaseTokenwithPhone';

    const deletefromemail = '@deletefromemail';

    // Common

    const create = '@create';
    const add = '@add';
    const update = '@update';
    const delete = '@delete';
    const list = '@list';
    const detail = '@detail';
    const remove = '@remove';
}
