<?php

namespace Zr\PaidAccess\Tests\Support;

final class ModuleClassLoader
{
    /** @var array<string, string> */
    private static $map = [
        'Zr\PaidAccess\PaidAccessCore' => 'classes/general/PaidAccessCore.php',
        'Zr\PaidAccess\Enum\PaymentStatus' => 'lib/enum/PaymentStatus.php',
        'Zr\PaidAccess\Enum\GatewayEventType' => 'lib/enum/GatewayEventType.php',
        'Zr\PaidAccess\Payment\PaymentCancellationService' => 'lib/payment/PaymentCancellationService.php',
        'Zr\PaidAccess\Enum\SubscriptionStatus' => 'lib/enum/SubscriptionStatus.php',
        'Zr\PaidAccess\Gateway\Dto\InitPaymentRequest' => 'lib/Gateway/Dto/InitPaymentRequest.php',
        'Zr\PaidAccess\Gateway\Dto\InitPaymentResult' => 'lib/Gateway/Dto/InitPaymentResult.php',
        'Zr\PaidAccess\Gateway\Dto\WebhookHandleResult' => 'lib/Gateway/Dto/WebhookHandleResult.php',
        'Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffApiClient' => 'lib/Gateway/Providers/Tinkoff/TinkoffApiClient.php',
        'Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffStatusMapper' => 'lib/Gateway/Providers/Tinkoff/TinkoffStatusMapper.php',
        'Zr\PaidAccess\Gateway\Contract\PaymentGatewayInterface' => 'lib/Gateway/Contract/PaymentGatewayInterface.php',
        'Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffGateway' => 'lib/Gateway/Providers/Tinkoff/TinkoffGateway.php',
        'Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffPaymentUrlResolver' => 'lib/Gateway/Providers/Tinkoff/TinkoffPaymentUrlResolver.php',
        'Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffInitError' => 'lib/Gateway/Providers/Tinkoff/TinkoffInitError.php',
        'Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffDuplicateOrderRecovery' => 'lib/Gateway/Providers/Tinkoff/TinkoffDuplicateOrderRecovery.php',
        'Zr\PaidAccess\Gateway\GatewayTestService' => 'lib/Gateway/GatewayTestService.php',
        'Zr\PaidAccess\PublicUi\AccessStatusPresenter' => 'lib/Public/AccessStatusPresenter.php',
        'Zr\PaidAccess\PublicUi\FundWalletService' => 'lib/Public/FundWalletService.php',
        'Zr\PaidAccess\PublicUi\FundContributorService' => 'lib/Public/FundContributorService.php',
        'Zr\PaidAccess\Fund\FundBalanceService' => 'lib/Fund/FundBalanceService.php',
        'Zr\PaidAccess\Fund\FundMovementService' => 'lib/Fund/FundMovementService.php',
        'Zr\PaidAccess\Enum\FundMovementType' => 'lib/enum/FundMovementType.php',
        'Zr\PaidAccess\Enum\FundMovementSource' => 'lib/enum/FundMovementSource.php',
        'Zr\PaidAccess\Admin\SubscriberAdminService' => 'lib/Admin/SubscriberAdminService.php',
        'Zr\PaidAccess\Admin\StatusBadgeRenderer' => 'lib/Admin/StatusBadgeRenderer.php',
        'Zr\PaidAccess\Admin\GatewayImportExportService' => 'lib/Admin/GatewayImportExportService.php',
        'Zr\PaidAccess\Admin\AdminEntityLinkRenderer' => 'lib/Admin/AdminEntityLinkRenderer.php',
        'Zr\PaidAccess\Admin\AuditContextRenderer' => 'lib/Admin/AuditContextRenderer.php',
        'Zr\PaidAccess\Admin\EventLogContextRenderer' => 'lib/Admin/EventLogContextRenderer.php',
        'Zr\PaidAccess\Admin\PaymentAdminService' => 'lib/Admin/PaymentAdminService.php',
        'Zr\PaidAccess\Admin\FundAdminService' => 'lib/Admin/FundAdminService.php',
        'Zr\PaidAccess\Log\AuditLogService' => 'lib/log/AuditLogService.php',
        'Zr\PaidAccess\Log\ModuleEventLogService' => 'lib/log/ModuleEventLogService.php',
        'Zr\PaidAccess\Notification\NotificationLogRepository' => 'lib/notification/NotificationLogRepository.php',
        'Zr\PaidAccess\Enum\NotificationType' => 'lib/enum/NotificationType.php',
        'Zr\PaidAccess\Tables\NotificationLogTable' => 'lib/tables/NotificationLogTable.php',
        'Zr\PaidAccess\Tools\Logger' => 'lib/tools/Logger.php',
        'Zr\PaidAccess\Payment\GatewayTransactionRepository' => 'lib/payment/GatewayTransactionRepository.php',
        'Zr\PaidAccess\Subscription\BillingPolicy' => 'lib/subscription/BillingPolicy.php',
        'Zr\PaidAccess\Subscription\SubscriptionAmountBreakdown' => 'lib/subscription/SubscriptionAmountBreakdown.php',
        'Zr\PaidAccess\Access\AccessControl' => 'lib/access/AccessControl.php',
        'Zr\PaidAccess\Access\AccessTemplate' => 'lib/access/AccessTemplate.php',
        'Zr\PaidAccess\Options\ModuleOptionsProvider' => 'lib/options/ModuleOptionsProvider.php',
        'Zr\PaidAccess\Options\ModuleOptionsStructure' => 'lib/options/ModuleOptionsStructure.php',
        'Zr\PaidAccess\Gateway\GatewayRepository' => 'lib/Gateway/GatewayRepository.php',
        'Zr\PaidAccess\Gateway\Provider\GatewayProviderRegistry' => 'lib/Gateway/Provider/GatewayProviderRegistry.php',
        'Zr\PaidAccess\Gateway\Provider\AbstractGatewayProvider' => 'lib/Gateway/Provider/AbstractGatewayProvider.php',
        'Zr\PaidAccess\Gateway\Contract\GatewayProviderInterface' => 'lib/Gateway/Contract/GatewayProviderInterface.php',
        'Zr\PaidAccess\Gateway\Providers\Tinkoff\TinkoffProvider' => 'lib/Gateway/Providers/Tinkoff/TinkoffProvider.php',
        'Zr\PaidAccess\Tables\GatewayTable' => 'lib/tables/GatewayTable.php',
    ];

    public static function register(string $moduleRoot): void
    {
        spl_autoload_register(static function (string $class) use ($moduleRoot): void {
            if (!isset(self::$map[$class])) {
                return;
            }

            $path = $moduleRoot . '/' . self::$map[$class];
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
