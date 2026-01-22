<?php

namespace App\Http\Controllers;

use App\Constants\Keys;
use App\Constants\ResponseCodes;
use App\Traits\Constant;

abstract class BaseController extends Controller
{
    use Constant;

    private $successResult = [
        Keys::STATUS => true,
        Keys::MESSAGE => 'Request Success',
    ];
    private $failResult = [
        Keys::STATUS => false,
        Keys::MESSAGE => 'Request Failed',
    ];

    public function setFailMessage(string $message)
    {
        $this->failResult[Keys::MESSAGE] = $message;
    }

    public function setSuccessMessage(string $message)
    {
        $this->successResult[Keys::MESSAGE] = $message;
    }

    public function addFailResultKeyValue(string $field, $value)
    {
        $this->failResult[$field] = $value;
    }

    public function addSuccessResultKeyValue(string $field, $value)
    {
        $this->successResult[$field] = $value;
    }

    public function getSuccessResult(): array
    {
        return $this->successResult;
    }

    public function getFailResult(): array
    {
        return $this->failResult;
    }

    private $errorResponseType = 1;

    /**
     * Send <b>Validation Errors</b> in List of Error Object.
     *
     * @param $errArray : array of Validation Errors need to format
     * @return  :  return fail result with formated list of Error object with <b>key</b> and <b>error</b> keys.
     */
    public function sendValidationError($errArray)
    {
        $this->addFailResultKeyValue(Keys::ERROR, $this->getValidationErrorInFormat($errArray));
        return $this->sendFailResultWithCode(ResponseCodes::VALIDATION_ERROR);
    }

    /**
     * Format <b>Validation Errors</b> in List of Error Object.
     *
     * @param $errArray : array of Validation Errors need to format
     * @return  :  return formated list of Error object with <b>field</b> and <b>error</b> keys.
     */
    public function getValidationErrorInFormat($errArray)
    {
        if ($this->errorResponseType == 1) {
            return $this->validationErrorsToListOfErrorModel($errArray);
        } else {
            return $this->validationErrorsSingleModel($errArray);
        }
    }


    public function validationErrorsToListOfErrorModel($errArray)
    {
        $valArr = [];
        foreach ($errArray->toArray() as $key => $value) {
            $errStr = [
                Keys::KEY => $key,
                Keys::ERROR => $value[0],
            ];
            array_push($valArr, $errStr);
        }
        return $valArr;
    }

    public function validationErrorsSingleModel($errArray)
    {
        $valArr = [];
        foreach ($errArray->toArray() as $key => $value) {
            $valArr[$key] = $value[0];
        }
        return $valArr;
    }

    /**
     * Add <b>Pagination Response</b> in Success Result
     *
     * @param $paginationArray : Pagination responce need to add
     */
    public function addPaginationDatainSuccess($paginationArray)
    {
        foreach ($paginationArray->toArray() as $key => $value) {
            $this->successResult[$key] = $value;
        }
        return $this->successResult;
    }

    public function getResponce($returnData, int $code)
    {
        return response()->json($returnData, $code);
    }

    public function sendSuccessResult()
    {
        return $this->getResponce($this->getSuccessResult(), $this->successCode);
    }

    public function sendFailResult()
    {
        return $this->sendFailResultWithCode($this->failCode);
    }

    public function sendFailResultWithCode(int $code)
    {
        return $this->getResponce($this->getFailResult(), $code);
    }

}
