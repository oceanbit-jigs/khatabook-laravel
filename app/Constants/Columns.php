<?php


namespace App\Constants;


class Columns
{

    /**
     * Common Columns
     */
    const id = 'id';
    const status = 'status';
    const image = 'image';
    const image_url = 'image_url';
    const record_deleted = 'record_deleted';
    const name = 'name';
    const is_active = 'is_active';
    const value = 'value';
    const old_password = "old_password";
    const new_password = 'new_password';
    const confirm_password = 'confirm_password';
    const remember_token = 'remember_token';
    const updated_at = 'updated_at';
    const created_at = 'created_at';
    const deleted_at = 'deleted_at';
    const page = 'page';
    const limit = 'limit';
    const total_counts = 'total_counts';
    const current_page = 'current_page';
    const filter = 'filter';
    const device_token = 'device_token';
    const action = 'action';
    const succedded_count = 'succedded_count';
    const failed_count = 'failed_count';
    const body = 'body';
    const click_action = 'click_action';
    const code = 'code';
    const code_expire_time = 'code_expire_time';
    const description = 'description';

    /**
     * Foreigns Key Columns
     */
    const user_id = 'user_id';
    const business_id = 'business_id';
    const customer_id = 'customer_id';
    
    /**
     * Tables::USERS Table Columns
     */
    const first_name = 'first_name';
    const middle_name = 'middle_name';
    const last_name = 'last_name';
    const contact_name = 'contact_name';
    const email = 'email';
    const phone = 'phone';
    const is_phone_verify = 'is_phone_verify';
    const is_email_verify = 'is_email_verify';
    const email_verified_at = 'email_verified_at';
    const password = 'password';
    const fcm_token = 'fcm_token';
    const is_admin = 'is_admin';
    const dob = 'dob';
    const is_notification_enabled = 'is_notification_enabled';
    const notification_unread_count = 'notification_unread_count';
    const device_type = 'device_type';


    /**
     * Tables::PASSWORD_RESET Table Columns
     */
    const token = 'token';

    /**
     * Tables::Business Table Columns
     */
    const business_name = 'business_name';
    const address = 'address';
    const currency = 'currency';

    /**
     * Tables::Customers Table Columns
     */
    const opening_balance = 'opening_balance';

    /**
     * Tables::transactions table Columns
     */
    const transaction_type = 'transaction_type';
    const amount = 'amount';
    const transaction_date = 'transaction_date';
    const payment_mode = 'payment_mode';

    /**
     * Tables::business_users table Columns
     */
    const role = 'role';
}
