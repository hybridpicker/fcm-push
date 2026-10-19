<?php

namespace humhub\modules\fcmPush\helpers;

/**
 * Helpers for log messages of the module.
 */
class LogHelper
{
    /**
     * Short, non-reversible identifier of a device token for log messages.
     *
     * A push token identifies a device (and so a person), the full value does not belong into the
     * log file. The identifier still allows to match the "send failure" and the "removing token"
     * lines of the same token.
     *
     * @since 2.4.0
     */
    public static function tokenId(string $token): string
    {
        return substr(sha1($token), 0, 8);
    }

    /**
     * The error code of a failed FCM request for log messages, e.g. `UNREGISTERED`,
     * `THIRD_PARTY_AUTH_ERROR`, `SENDER_ID_MISMATCH` or `QUOTA_EXCEEDED`: the `errorCode` of the
     * FCM error details, else the `status` of the error, else the short class name of the exception.
     * The result is made of letters, digits and underscores only, it never contains message data.
     *
     * @see https://firebase.google.com/docs/reference/fcm/rest/v1/ErrorCode
     * @since 2.4.0
     */
    public static function errorCode(?\Throwable $error): string
    {
        if ($error === null) {
            return 'UNKNOWN';
        }

        $errors = method_exists($error, 'errors') ? $error->errors() : [];
        $details = is_array($errors) ? ($errors['error'] ?? $errors) : [];
        $details = is_array($details) ? $details : [];

        foreach ((array)($details['details'] ?? []) as $detail) {
            $code = is_array($detail) ? ($detail['errorCode'] ?? null) : null;
            if (is_string($code) && preg_match('/^[A-Z][A-Z0-9_]{2,60}$/', $code)) {
                return $code;
            }
        }

        $status = $details['status'] ?? null;
        if (is_string($status) && preg_match('/^[A-Z][A-Z0-9_]{2,60}$/', $status)) {
            return $status;
        }

        return (string)preg_replace('/[^A-Za-z0-9_]/', '', (new \ReflectionClass($error))->getShortName());
    }
}
