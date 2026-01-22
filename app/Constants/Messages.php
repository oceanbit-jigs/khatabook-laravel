<?php


namespace App\Constants;


class Messages
{
    /*Error Messages goes here*/
    const SOMETHING_WENT_WRONG = 'Something went wrong.';
    const ERROR_INVALID_USER_ID_PASSWORD = 'Invalid UserId Password';
    const ERROR_INVALID_EMAIL_PASSWORD = 'Invalid Email Password';
    const ERROR_INVALID_VERIFICATION_CODE = 'Invalid Verification Code';
    const ERROR_VERIFICATION_CODE_EXPIRIED = 'Verification Code Expired';
    const NO_DATA_FOUND = 'No data found';
    const USER_NOT_FOUND = 'User not found';
    const UNAUTHORIZED_USER = 'Unauthorised User';
    const  SERVICE_ALLOW_ONLY_ADMIN = 'Service Allow only for Admin.';
    const  DONT_HAVE_ACCESS_TO_USE_THIS_SERVICE = 'You don\'t have access to use this service.';
    const CURRENT_PASSWORD_INCORRECT = 'Current password is incorrect';
    const INVALID_PHONE_NUMBER = 'Invalid Phone Number';
    const NOTIFICATION_FAILED = 'Notification failed';
    const RECORD_ALERADY_EXISTS = 'Record Alerady Exists';


    /*Success Messages goes here*/
    const RECORD_DELETED_SUCCESSFULLY = 'Record Deleted successfully.';
    const USER_CREATED_SUCCESSFULLY = 'User created successfully.';
    const USER_DELETED_SUCCESSFULLY = 'User deleted successfully.';
    const LOGIN_SUCCESSFULLY = 'Login successfully.';
    const LOGOUT_SUCCESSFULLY = "Logout successfully.";
    const UPDATED_SUCCESSFULLY = "Successfully updated.";
    const ADDED_SUCCESSFULLY = "Successfully Added.";
    const PASSWORD_CHANGED_SUCCESSFULLY = "Password changed successfully.";
    const NOTIFICATION_SEND_SUCCESSFULLY = "Notification send successfully.";
    const MAIL_SEND_SUCCESSFULLY = "Mail send successfully.";
    const EMAIL_VERIFIED_SUCCESSFULLY = "Email verified successfully.";
    const PHONE_VERIFIED_SUCCESSFULLY = "Phone verified successfully.";
    const DATA_FOUND_SUCCESSFULLY = 'Data Found Successfully';
}
