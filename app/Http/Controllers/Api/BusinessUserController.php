<?php

namespace App\Http\Controllers\Api;

use App\Constants\Columns;
use App\Constants\Enums;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Http\Controllers\BaseController;
use App\Model\Business;
use App\Model\BusinessUser;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BusinessUserController extends BaseController
{
    /**
     * List users of a business
     */
    public function index(Request $request)
    {
        $rules = [
            Columns::business_id => 'required|integer',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        // Only owner can list users
        $ownerAccess = BusinessUser::where(Columns::business_id, $request->business_id)
            ->where(Columns::user_id, Auth::id())
            ->where(Columns::role, Enums::OWNER)
            ->exists();

        if (!$ownerAccess) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
            return $this->sendFailResult();
        }

        $users = BusinessUser::where(Columns::business_id, $request->business_id)
            ->with('user:id,name,email')
            ->get();

        if ($users->isEmpty()) {
            $this->addFailResultKeyValue(Keys::MESSAGE, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $this->addSuccessResultKeyValue(Keys::DATA, $users);
        return $this->sendSuccessResult();
    }

    /**
     * Add user to business (Owner only)
     */
    public function store(Request $request)
    {
        $rules = [
            Columns::business_id => 'required|integer',
            Columns::user_id     => 'required|integer|exists:users,id',
            Columns::role        => 'required|in:' . Enums::OWNER . ',' . Enums::STAFF,
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        // Check owner permission
        $isOwner = Business::where(Columns::id, $request->business_id)
            ->where(Columns::user_id, Auth::id())
            ->exists();

        if (!$isOwner) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
            return $this->sendFailResult();
        }

        // Prevent duplicate entry
        $exists = BusinessUser::where(Columns::business_id, $request->business_id)
            ->where(Columns::user_id, $request->user_id)
            ->exists();

        if ($exists) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::RECORD_ALERADY_EXISTS);
            return $this->sendFailResult();
        }

        $businessUser = BusinessUser::create([
            Columns::business_id => $request->business_id,
            Columns::user_id     => $request->user_id,
            Columns::role        => $request->role,
        ]);

        $this->addSuccessResultKeyValue(Keys::DATA, $businessUser);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::ADDED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Update user role (Owner only)
     */
    public function update(Request $request, $id)
    {
        $rules = [
            Columns::role => 'required|in:' . Enums::OWNER . ',' . Enums::STAFF,
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $businessUser = BusinessUser::find($id);

        if (!$businessUser) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // Owner permission
        $isOwner = Business::where(Columns::id, $businessUser->business_id)
            ->where(Columns::user_id, Auth::id())
            ->exists();

        if (!$isOwner) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
            return $this->sendFailResult();
        }

        $businessUser->update([
            Columns::role => $request->role,
        ]);

        $this->addSuccessResultKeyValue(Keys::DATA, $businessUser);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::UPDATED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Remove user from business (Owner only)
     */
    public function destroy($id)
    {
        $businessUser = BusinessUser::find($id);

        if (!$businessUser) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // Owner permission
        $isOwner = BusinessUser::where(Columns::business_id, $businessUser->business_id)
            ->where(Columns::user_id, Auth::id())
            ->where(Columns::role, Enums::OWNER)
            ->exists();

        if (!$isOwner) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
            return $this->sendFailResult();
        }

        $businessUser->delete();

        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::RECORD_DELETED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }
}
