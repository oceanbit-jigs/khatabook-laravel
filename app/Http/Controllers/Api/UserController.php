<?php

namespace App\Http\Controllers\Api;

use App\Constants\Enums;
use App\Constants\Keys;
use App\Constants\Columns;
use App\Constants\Messages;
use App\Constants\ResponseCodes;
use App\Constants\Tables;
use App\Http\Controllers\BaseController;
use App\Mail\DeleteAccountOtpMail;
use App\Mail\LoginUserOtpMail;
use App\Mail\TestingMail;
use App\Model\BillSplit;
use App\Model\User;
use App\UserOtp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\InvalidIdToken;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class UserController extends BaseController
{
    private $tokenName = "OceanBit";

    public function register(Request $req)
    {
        /* Validation rules */
        $rules = [
            Columns::name => 'required|string|max:255',
            Columns::email => 'required|email|unique:' . Tables::USERS,
            Columns::phone => 'required|string|min:10|max:12|unique:' . Tables::USERS,
            Columns::password => 'required|string|min:6',
            Columns::confirm_password => 'required|string|same:' . Columns::password,
            Columns::fcm_token => 'nullable|string',
        ];

        /* Perform validation */
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        /* Create user */
        $user = User::create([
            Columns::name => $req->name,
            Columns::email => $req->email,
            Columns::phone => $req->phone,
            Columns::password => Hash::make($req->password),
            Columns::fcm_token => $req->fcm_token ?? null,
            Columns::is_email_verify => false,
            Columns::is_phone_verify => false,
        ]);

        /* Generate access token */
        $token = $user->createToken($this->tokenName)->accessToken;

        /* Response */
        $this->addSuccessResultKeyValue(Keys::DATA, $user);
        $this->addSuccessResultKeyValue(Keys::TOKEN, $token);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::USER_CREATED_SUCCESSFULLY);

        return $this->sendSuccessResult();
    }


public function login(Request $req)
{
    $rules = [
        Columns::email => 'required|string', // email or phone
        Columns::password => 'required|string|min:6',
        Columns::fcm_token => 'nullable|string',
    ];

    /* Validation */
    $validator = Validator::make($req->all(), $rules);
    if ($validator->fails()) {
        return $this->sendValidationError($validator->errors());
    }

    /* Detect login type */
    $loginField = filter_var($req->email, FILTER_VALIDATE_EMAIL)
        ? Columns::email
        : Columns::phone;

    $credentials = [
        $loginField => $req->email,
        Columns::password => $req->password,
    ];

    if (!Auth::attempt($credentials)) {
        $this->addFailResultKeyValue(
            Keys::ERROR,
            Messages::ERROR_INVALID_USER_ID_PASSWORD
        );
        return $this->sendFailResult();
    }

    /** @var User $user */
    $user = Auth::user();

    /* Soft delete check */
    if ($user->deleted_at !== null) {
        Auth::logout();
        $this->addFailResultKeyValue(
            Keys::ERROR,
            Messages::UNAUTHORIZED_USER
        );
        return $this->sendFailResult();
    }

    /* Update FCM token */
    if ($req->filled(Columns::fcm_token)) {
        $user->update([
            Columns::fcm_token => $req->fcm_token
        ]);
    }

    /* Create access token */
    $token = $user->createToken($this->tokenName)->accessToken;

    /* Response */
    $this->addSuccessResultKeyValue(Keys::DATA, $user);
    $this->addSuccessResultKeyValue(Keys::TOKEN, $token);
    $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::LOGIN_SUCCESSFULLY);

    return $this->sendSuccessResult();
}

    function logout(Request $request)
    {
        //        Auth::logout();
        $request->user()->token()->revoke();
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::LOGOUT_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Called When Token Is not Pass in Header Or Token Expire.
     */
    function unauthorised()
    {
        $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
        return $this->sendFailResultWithCode(ResponseCodes::UNAUTHORIZED_USER);
    }

    /**
     * Called When admin Services Access by none Admin User.
     */
    function adminaccess()
    {
        $this->addFailResultKeyValue(Keys::ERROR, Messages::SERVICE_ALLOW_ONLY_ADMIN);
        return $this->sendFailResultWithCode(ResponseCodes::UNAUTHORIZED_USER);
    }

    /**
     * Called When Active User's Services Access by none Un - Active User .
     */
    function activeaccess()
    {
        $this->addFailResultKeyValue(Keys::ERROR, Messages::DONT_HAVE_ACCESS_TO_USE_THIS_SERVICE);
        $this->addFailResultKeyValue(Keys::DATA, Auth::user());
        return $this->sendFailResultWithCode(ResponseCodes::INACTIVE_USER);
    }
}
