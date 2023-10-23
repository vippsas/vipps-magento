<?php
namespace Vipps\Payment\GatewayEcomm\Data;

/**
 * Class AggregateFactory
 * @package Vipps\Payment\GatewayEcomm\Data
 */
class AggregateFactory
{
    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager = null;
    /**
     * @var AmountFactory
     */
    private AmountFactory $amountFactory;
    /**
     * Instance name to create
     *
     * @var string
     */
    protected $instanceName = null;

    /**
     * Factory constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param string $instanceName
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        AmountFactory $amountFactory,
        $instanceName = '\\Vipps\\Checkout\\Gateway\\Data\\Aggregate')
    {
        $this->objectManager = $objectManager;
        $this->amountFactory = $amountFactory;
        $this->instanceName = $instanceName;
    }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     * @return \Vipps\Payment\GatewayEcomm\Data\Aggregate
     */
    public function create(array $data = [])
    {
        $objData = [
            'cancelledAmount' =>
                $this->amountFactory->create([
                    'data' => (array)($data['cancelledAmount'] ?? null)
                ]),
            'capturedAmount' =>
                $this->amountFactory->create([
                    'data' => (array)($data['capturedAmount'] ?? null)
                ]),
            'refundedAmount' =>
                $this->amountFactory->create([
                    'data' => (array)($data['refundedAmount'] ?? null)
                ]),
            'authorizedAmount' =>
                $this->amountFactory->create([
                    'data' => (array)($data['authorizedAmount'] ?? null)
                ]),
        ];

        return $this->objectManager->create($this->instanceName, ['data' => $objData]);
    }
}
