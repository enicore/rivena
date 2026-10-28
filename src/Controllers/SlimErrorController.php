<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\ErrorLog;
use Enicore\Rivena\Classes\Http;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Exceptions\InputException;
use Illuminate\Database\QueryException;
use Slim\Exception\HttpException;
use Throwable;

/**
 * Slim's error handler: sends errors as JSON or as an error page, and logs the unexpected ones.
 *
 * @package Enicore\Rivena
 */
class SlimErrorController extends Controller
{
    /**
     * Slim's error handler: returns the error as JSON for requests that ask for it, the error page otherwise. An
     * InputException that a controller didn't catch (e.g. an array posted for a string) is a 400. Other errors than
     * these are logged and shown as "Server error." (with the details in dev mode).
     *
     * @param Request $request
     * @param Throwable $exception
     * @param bool $displayErrorDetails
     * @param bool $logErrors
     * @param bool $logErrorDetails
     * @return Response
     */
    public function __invoke(Request $request, Throwable $exception, bool $displayErrorDetails, bool $logErrors,
                             bool $logErrorDetails): Response
    {
        $isHttpError = $exception instanceof HttpException && $exception->getCode() >= 400;
        $isInputError = $exception instanceof InputException;
        $code = $isHttpError ? $exception->getCode() : ($isInputError ? 400 : 500);

        // a query's SQL without its values, which can be secrets (e.g. a password hash)
        $details = $exception instanceof QueryException ?
            $exception->getPrevious()?->getMessage() . " (SQL: {$exception->getSql()})" : $exception->getMessage();

        if (!$isHttpError && !$isInputError) {
            $error = get_class($exception) . ": $details in {$exception->getFile()}:{$exception->getLine()}";
            error_log("$error\n{$exception->getTraceAsString()}");
            ErrorLog::add('error', $error);
        }

        // HTTP and input errors have a message for the user ("Not found."), the other errors' are for the log only
        $message = match (true) {
            $isHttpError => $exception->getMessage() ?: $exception->getTitle(),
            $isInputError => $exception->getMessage(),
            default => 'Server error.',
        };

        if ($displayErrorDetails && $code === 500) {
            $message .= " $details";
        }

        $response = new Response();

        if (Http::wantsJson($request)) {
            return $response->sendError($message, $code);
        }

        return $this->sendErrorPage($response, $code, $message);
    }
}
