<?php declare(strict_types=1);

namespace Vipps\Payment\GatewayEcomm\Model;

use Magento\Quote\Api\CartRepositoryInterface;
use Vipps\Payment\Api\CheckoutCommandManagerInterface;
use Vipps\Payment\Api\QuoteRepositoryInterface;
use Vipps\Payment\GatewayEcomm\Data\InitSession;
use Vipps\Payment\GatewayEcomm\Data\InitSessionBuilder;
use Vipps\Payment\GatewayEcomm\Data\Session;
use Vipps\Payment\GatewayEcomm\Data\SessionBuilder;
use Magento\Quote\Model\Quote;

class SessionManager
{
    /**
     * @var CheckoutCommandManagerInterface
     */
    private $checkoutCommandManager;
    /**
     * @var QuoteRepositoryInterface
     */
    private $vippsQuoteRepository;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;
    /**
     * @var InitSessionBuilder
     */
    private $initSessionBuilder;
    /**
     * @var SessionBuilder
     */
    private $sessionBuilder;

    /**
     * SessionManager constructor.
     *
     * @param CheckoutCommandManagerInterface $checkoutCommandManager
     * @param QuoteRepositoryInterface $vippsQuoteRepository
     * @param CartRepositoryInterface $cartRepository
     * @param InitSessionBuilder $initSessionBuilder
     * @param SessionBuilder $sessionBuilder
     */
    public function __construct(
        CheckoutCommandManagerInterface $checkoutCommandManager,
        QuoteRepositoryInterface $vippsQuoteRepository,
        CartRepositoryInterface $cartRepository,
        InitSessionBuilder $initSessionBuilder,
        SessionBuilder $sessionBuilder
    ) {
        $this->checkoutCommandManager = $checkoutCommandManager;
        $this->vippsQuoteRepository = $vippsQuoteRepository;
        $this->cartRepository = $cartRepository;
        $this->initSessionBuilder = $initSessionBuilder;
        $this->sessionBuilder = $sessionBuilder;
    }

    public function initSession(Quote $quote): InitSession
    {
        $quote->removeAllAddresses();
        $quote->getExtensionAttributes()->setShippingAssignments(null);
        $this->cartRepository->save($quote);

        $response = $this->checkoutCommandManager->initSession($quote->getPayment());

        return $this->initSessionBuilder->setData($response)->build();
    }

    public function getSession($reference): Session
    {
        $response = $this->checkoutCommandManager->getSession($reference);

        return $this->sessionBuilder->setData($response)->build();
    }

    public function getSessionToken(Quote $quote): ?string
    {
        $initSession = $this->initSession($quote);

        return $initSession->getToken();
    }
}
