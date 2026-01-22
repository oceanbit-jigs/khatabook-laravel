<?php

namespace App\Http\Controllers\Api;

use App\Constants\Columns;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Http\Controllers\BaseController;
use App\Model\Business;
use App\Model\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CustomerController extends BaseController
{
    /**
     * List customers (business wise)
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        $rules = [
            Columns::business_id => 'required|integer',
            Columns::page        => 'nullable|integer|min:0',
            Columns::limit       => 'nullable|integer|min:1|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        // Verify business ownership
        $business = Business::where(Columns::id, $request->business_id)
            ->where(Columns::user_id, $userId)
            ->first();

        if (!$business) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $query = Customer::where(Columns::business_id, $business->id)
            ->orderByDesc(Columns::id);

        // page = 0 → fetch all
        if ($request->input(Columns::page) == 0) {
            $customers = $query->get();
        } else {
            $limit = $request->input(Columns::limit, 10);
            $customers = $query->paginate($limit);
        }

        if ($customers->isEmpty()) {
            $this->addFailResultKeyValue(Keys::MESSAGE, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $transformed = $customers->map(function ($customer) {
            return [
                Columns::id              => $customer->id,
                Columns::business_id     => $customer->business_id,
                Columns::name            => $customer->name,
                Columns::phone           => $customer->phone,
                Columns::email           => $customer->email,
                Columns::address         => $customer->address,
                Columns::opening_balance => $customer->opening_balance,
                Columns::created_at      => $customer->created_at,
                Columns::updated_at      => $customer->updated_at,
            ];
        });

        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::DATA_FOUND_SUCCESSFULLY);

        if ($request->input(Columns::page) == 0) {
            $this->addSuccessResultKeyValue(Keys::DATA, $transformed);
            return $this->sendSuccessResult();
        }

        return $this->addPaginationDatainSuccess(
            $customers->setCollection($transformed)
        );
    }

    /**
     * Create customer
     */
    public function store(Request $request)
    {
        $rules = [
            Columns::business_id     => 'required|integer',
            Columns::name            => 'required|string|max:255',
            Columns::phone           => 'required|string|max:15',
            Columns::email           => 'nullable|email',
            Columns::address         => 'nullable|string',
            Columns::opening_balance => 'nullable|numeric',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        // Check business ownership
        $business = Business::where(Columns::id, $request->business_id)
            ->where(Columns::user_id, Auth::id())
            ->first();

        if (!$business) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $customer = Customer::create([
            Columns::business_id     => $request->business_id,
            Columns::name            => $request->name,
            Columns::phone           => $request->phone,
            Columns::email           => $request->email,
            Columns::address         => $request->address,
            Columns::opening_balance => $request->opening_balance ?? 0,
        ]);

        $this->addSuccessResultKeyValue(Keys::DATA, $customer);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::ADDED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Update customer
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::where(Columns::id, $id)->first();

        if (!$customer) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // Validate business ownership
        $business = Business::where(Columns::id, $customer->business_id)
            ->where(Columns::user_id, Auth::id())
            ->first();

        if (!$business) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $rules = [
            Columns::name            => 'required|string|max:255',
            Columns::phone           => 'required|string|max:15',
            Columns::email           => 'nullable|email',
            Columns::address         => 'nullable|string',
            Columns::opening_balance => 'nullable|numeric',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $customer->update($request->only([
            Columns::name,
            Columns::phone,
            Columns::email,
            Columns::address,
            Columns::opening_balance,
        ]));

        $this->addSuccessResultKeyValue(Keys::DATA, $customer);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::UPDATED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Delete customer
     */
    public function destroy($id)
    {
        $customer = Customer::where(Columns::id, $id)->first();

        if (!$customer) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // Check business ownership
        $business = Business::where(Columns::id, $customer->business_id)
            ->where(Columns::user_id, Auth::id())
            ->first();

        if (!$business) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $customer->delete();

        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::RECORD_DELETED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Show single customer
     */
    public function show($id)
    {
        $customer = Customer::where(Columns::id, $id)->first();

        if (!$customer) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // Ownership check
        $business = Business::where(Columns::id, $customer->business_id)
            ->where(Columns::user_id, Auth::id())
            ->first();

        if (!$business) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $this->addSuccessResultKeyValue(Keys::DATA, $customer);
        return $this->sendSuccessResult();
    }
}
