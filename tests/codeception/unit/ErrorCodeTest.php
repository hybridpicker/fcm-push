<?php

namespace tests\codeception\unit;

use humhub\modules\fcmPush\drivers\Fcm;
use humhub\modules\fcmPush\helpers\LogHelper;
use humhub\modules\fcmPush\models\ConfigureForm;
use humhub\modules\fcmPush\Module;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\AuthenticationError;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport as FirebaseSendReport;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

/**
 * The FCM error code in the log, see {@see LogHelper::errorCode()}.
 *
 * @since 2.4.0
 */
class ErrorCodeTest extends HumHubDbTestCase
{
    public function testErrorCodeOfTheFcmErrorDetails()
    {
        Module::registerAutoloader();

        // the error body of the FCM v1 API for a missing APNs key / Web Push credentials
        $error = (new AuthenticationError('Third party auth error'))->withErrors([
            'error' => [
                'code' => 401,
                'message' => 'Auth error from APNS or Web Push Service',
                'status' => 'UNAUTHENTICATED',
                'details' => [
                    ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'THIRD_PARTY_AUTH_ERROR'],
                ],
            ],
        ]);

        $this->assertSame('THIRD_PARTY_AUTH_ERROR', LogHelper::errorCode($error));
    }

    public function testErrorCodeFallsBackToStatusAndClassName()
    {
        Module::registerAutoloader();

        $withStatus = (new NotFound('x'))->withErrors(['error' => ['status' => 'NOT_FOUND']]);
        $this->assertSame('NOT_FOUND', LogHelper::errorCode($withStatus));

        $this->assertSame('NotFound', LogHelper::errorCode(new NotFound('Requested entity was not found.')));
        $this->assertSame('RuntimeException', LogHelper::errorCode(new \RuntimeException('boom')));
        $this->assertSame('UNKNOWN', LogHelper::errorCode(null));
    }

    public function testErrorCodeIsRestrictedToSafeCharacters()
    {
        Module::registerAutoloader();

        $error = (new NotFound('x'))->withErrors(['error' => ['details' => [['errorCode' => "UNREGISTERED\nsecret text"]]]]);

        $this->assertSame('NotFound', LogHelper::errorCode($error));
    }

    public function testSendFailureLogContainsTheErrorCode()
    {
        Module::registerAutoloader();

        $error = (new AuthenticationError('Third party auth error'))->withErrors([
            'error' => ['details' => [['errorCode' => 'THIRD_PARTY_AUTH_ERROR']]],
        ]);
        $failure = FirebaseSendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'synthetic-token'), $error);
        $messaging = $this->createMock(Messaging::class);
        $messaging->method('sendMulticast')->willReturn(MulticastSendReport::withItems([$failure]));

        $driver = new Fcm(new ConfigureForm());
        (new \ReflectionProperty(Fcm::class, 'messaging'))->setValue($driver, $messaging);

        Yii::getLogger()->getAndClearLog();
        $driver->processCloudMessage(['synthetic-token'], 'Title', 'Body', 'https://example.com/n', null, 1);

        $this->assertStringContainsString(
            'FCM send failure THIRD_PARTY_AUTH_ERROR for token ' . LogHelper::tokenId('synthetic-token') . ': Third party auth error',
            Yii::getLogger()->getAndClearLog(),
        );
    }
}
