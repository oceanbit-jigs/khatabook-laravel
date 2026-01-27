<?php

namespace App\Http\Controllers\Api;

use App\Constants\Columns;
use App\Constants\Enums;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Constants\Tables;
use App\Http\Controllers\BaseController;
use App\Model\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BusinessController extends BaseController
{
    /**
     * List all businesses of authenticated user
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        // Validation
        $rules = [
            Columns::page => 'nullable|integer|min:0',
            Columns::limit => 'nullable|integer|min:1|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        // Base query
        $query = Business::where(Columns::user_id, $userId)
        ->withSum(
            ['transactions as total_income' => function ($q) {
                $q->where(Columns::transaction_type, Enums::INCOME);
            }],
            Columns::amount
        )
        ->withSum(
            ['transactions as total_expense' => function ($q) {
                $q->where(Columns::transaction_type, Enums::EXPENSE);
            }],
            Columns::amount
        )
            ->orderByDesc(Columns::id);

        // page = 0 → fetch all (no pagination)
        if ($request->input(Columns::page) == 0) {
            $businesses = $query->get();
        } else {
            $limit = $request->input(Columns::limit, 10);
            $businesses = $query->paginate($limit);
        }

        // Empty check (works for Collection & Paginator)
        if ($businesses->isEmpty()) {
            $this->addFailResultKeyValue(Keys::MESSAGE, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // Transform response (optional but recommended)
        $transformed = $businesses->map(function ($business) {
            return [
                Columns::id => $business->id,
                Columns::business_name => $business->business_name,
                Columns::phone => $business->phone,
                Columns::email => $business->email,
                Columns::address => $business->address,
                Columns::currency => $business->currency,
                Columns::created_at => $business->created_at,
                Columns::updated_at => $business->updated_at,
                // 👇 Aggregates
            'total_income'         => (float) ($business->total_income ?? 0),
            'total_expense'        => (float) ($business->total_expense ?? 0),
            'net_balance'          => (float) (
                ($business->total_income ?? 0) -
                ($business->total_expense ?? 0)
            ),
            ];
        });

        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::DATA_FOUND_SUCCESSFULLY);

        // Return response
        if ($request->input(Columns::page) == 0) {
            $this->addSuccessResultKeyValue(Keys::DATA, $transformed);
            return $this->sendSuccessResult();
        }

        // Paginated response (reuse BaseController helper)
        return $this->addPaginationDatainSuccess(
            $businesses->setCollection($transformed)
        );
    }

    /**
     * Create new business
     */
    public function store(Request $request)
    {
        $rules = [
            Columns::business_name => 'required|string|max:255',
            Columns::phone => 'required|string|max:15',
            Columns::email => 'nullable|email',
            Columns::address => 'nullable|string',
            Columns::currency => 'nullable|string|max:10',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $business = Business::create([
            Columns::user_id => Auth::id(),
            Columns::business_name => $request->business_name,
            Columns::phone => $request->phone,
            Columns::email => $request->email,
            Columns::address => $request->address,
            Columns::currency => $request->currency,
        ]);

        $this->addSuccessResultKeyValue(Keys::DATA, $business);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::ADDED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Update business
     */
    public function update(Request $request, $id)
    {
        $business = Business::where(Columns::id, $id)
            ->where(Columns::user_id, Auth::id())
            ->first();

        if (!$business) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $rules = [
            Columns::business_name => 'required|string|max:255',
            Columns::phone => 'required|string|max:15',
            Columns::email => 'nullable|email',
            Columns::address => 'nullable|string',
            Columns::currency => 'nullable|string|max:10',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $business->update($request->only([
            Columns::business_name,
            Columns::phone,
            Columns::email,
            Columns::address,
            Columns::currency,
        ]));

        $this->addSuccessResultKeyValue(Keys::DATA, $business);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::UPDATED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Delete business
     */
    public function destroy($id)
    {
        $business = Business::where(Columns::id, $id)
            ->where(Columns::user_id, Auth::id())
            ->first();

        if (!$business) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $business->delete();

        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::RECORD_DELETED_SUCCESSFULLY);
        return $this->sendSuccessResult();

    }

    /**
     * Show single business
     */
    public function show($id)
    {
        $business = Business::where(Columns::id, $id)
            ->where(Columns::user_id, Auth::id())
            ->first();

        if (!$business) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $this->addSuccessResultKeyValue(Keys::DATA, $business);
        return $this->sendSuccessResult();
    }
}
