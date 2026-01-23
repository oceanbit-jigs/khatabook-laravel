<?php

namespace App\Http\Controllers\Api;

use App\Constants\Columns;
use App\Constants\Enums;
use App\Constants\Keys;
use App\Constants\Messages;
use App\Http\Controllers\BaseController;
use App\Model\Business;
use App\Model\BusinessUser;
use App\Model\Customer;
use App\Model\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TransactionController extends BaseController
{
    /**
     * List transactions with filters
     */
    public function index(Request $request)
    {
        $rules = [
            Columns::business_id => 'required|integer',
            Columns::customer_id => 'nullable|integer',
            Columns::transaction_type => 'nullable|in:' . Enums::INCOME . ',' . Enums::EXPENSE,
            "from_date" => 'nullable|date',
            "to_date" => 'nullable|date|after_or_equal:from_date',
            Columns::page => 'nullable|integer|min:0',
            Columns::limit => 'nullable|integer|min:1|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        // // Check business access (owner or staff)
        // $hasAccess = BusinessUser::where(Columns::business_id, $request->business_id)
        //     ->where(Columns::user_id, Auth::id())
        //     ->exists();

        // if (!$hasAccess) {
        //     $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
        //     return $this->sendFailResult();
        // }

        $query = Transaction::where(Columns::business_id, $request->business_id)
            ->orderByDesc(Columns::transaction_date);

        // Customer wise
        if ($request->filled(Columns::customer_id)) {
            $query->where(Columns::customer_id, $request->customer_id);
        }

        // Type wise
        if ($request->filled(Columns::transaction_type)) {
            $query->where(Columns::transaction_type, $request->transaction_type);
        }

        // Date wise
        if ($request->filled("from_date")) {
            $query->whereDate(Columns::transaction_date, '>=', $request->from_date);
        }

        if ($request->filled("to_date")) {
            $query->whereDate(Columns::transaction_date, '<=', $request->to_date);
        }

        // Pagination
        if ($request->input(Columns::page) == 0) {
            $transactions = $query->get();
        } else {
            $limit = $request->input(Columns::limit, 10);
            $transactions = $query->paginate($limit);
        }

        if ($transactions->isEmpty()) {
            $this->addFailResultKeyValue(Keys::MESSAGE, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $transformed = $transactions->map(function ($txn) {
            return [
                Columns::id => $txn->id,
                Columns::business_id => $txn->business_id,
                Columns::customer_id => $txn->customer_id,
                Columns::transaction_type => $txn->transaction_type,
                Columns::amount => $txn->amount,
                Columns::description => $txn->description,
                Columns::transaction_date => $txn->transaction_date,
                Columns::payment_mode => $txn->payment_mode,
                Columns::created_at => $txn->created_at,
                Columns::updated_at => $txn->updated_at,
            ];
        });

        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::DATA_FOUND_SUCCESSFULLY);

        if ($request->input(Columns::page) == 0) {
            $this->addSuccessResultKeyValue(Keys::DATA, $transformed);
            return $this->sendSuccessResult();
        }

        return $this->addPaginationDatainSuccess(
            $transactions->setCollection($transformed)
        );
    }

    /**
     * Create transaction
     */
    public function store(Request $request)
    {
        $rules = [
            Columns::business_id => 'required|integer',
            Columns::customer_id => 'required|integer',
            Columns::transaction_type => 'required|in:' . Enums::INCOME . ',' . Enums::EXPENSE,
            Columns::amount => 'required|numeric|min:0.01',
            Columns::transaction_date => 'required|date',
            Columns::payment_mode => 'required|in:' . Enums::CASH . ',' . Enums::ONLINE . ',' . Enums::CARD,
            Columns::description => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        // // Business access check
        // $hasAccess = BusinessUser::where(Columns::business_id, $request->business_id)
        //     ->where(Columns::user_id, Auth::id())
        //     ->exists();

        // if (!$hasAccess) {
        //     $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
        //     return $this->sendFailResult();
        // }

        // Validate customer belongs to business
        $customer = Customer::where(Columns::id, $request->customer_id)
            ->where(Columns::business_id, $request->business_id)
            ->first();

        if (!$customer) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        $transaction = Transaction::create([
            Columns::business_id => $request->business_id,
            Columns::customer_id => $request->customer_id,
            Columns::transaction_type => $request->transaction_type,
            Columns::amount => $request->amount,
            Columns::description => $request->description,
            Columns::transaction_date => $request->transaction_date,
            Columns::payment_mode => $request->payment_mode,
        ]);

        $this->addSuccessResultKeyValue(Keys::DATA, $transaction);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::ADDED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Show transaction
     */
    public function show($id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // // Access check
        // $hasAccess = BusinessUser::where(Columns::business_id, $transaction->business_id)
        //     ->where(Columns::user_id, Auth::id())
        //     ->exists();

        // if (!$hasAccess) {
        //     $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
        //     return $this->sendFailResult();
        // }

        $this->addSuccessResultKeyValue(Keys::DATA, $transaction);
        return $this->sendSuccessResult();
    }

    /**
     * Update transaction
     */
    public function update(Request $request, $id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // // Access check
        // $hasAccess = BusinessUser::where(Columns::business_id, $transaction->business_id)
        //     ->where(Columns::user_id, Auth::id())
        //     ->exists();

        // if (!$hasAccess) {
        //     $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
        //     return $this->sendFailResult();
        // }

        $rules = [
            Columns::transaction_type => 'required|in:' . Enums::INCOME . ',' . Enums::EXPENSE,
            Columns::amount => 'required|numeric|min:0.01',
            Columns::transaction_date => 'required|date',
            Columns::payment_mode => 'required|in:' . Enums::CASH . ',' . Enums::ONLINE . ',' . Enums::CARD,
            Columns::description => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $transaction->update($request->only([
            Columns::transaction_type,
            Columns::amount,
            Columns::transaction_date,
            Columns::payment_mode,
            Columns::description,
        ]));

        $this->addSuccessResultKeyValue(Keys::DATA, $transaction);
        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::UPDATED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }

    /**
     * Delete transaction
     */
    public function destroy($id)
    {
        $transaction = Transaction::find($id);

        if (!$transaction) {
            $this->addFailResultKeyValue(Keys::ERROR, Messages::NO_DATA_FOUND);
            return $this->sendFailResult();
        }

        // // Access check
        // $hasAccess = BusinessUser::where(Columns::business_id, $transaction->business_id)
        //     ->where(Columns::user_id, Auth::id())
        //     ->exists();

        // if (!$hasAccess) {
        //     $this->addFailResultKeyValue(Keys::ERROR, Messages::UNAUTHORIZED_USER);
        //     return $this->sendFailResult();
        // }

        $transaction->delete();

        $this->addSuccessResultKeyValue(Keys::MESSAGE, Messages::RECORD_DELETED_SUCCESSFULLY);
        return $this->sendSuccessResult();
    }
}
