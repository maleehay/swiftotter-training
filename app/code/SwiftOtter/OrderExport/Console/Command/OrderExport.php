<?php
declare(strict_types=1);
/**
 * @by SwiftOtter, Inc.
 * @website https://swiftotter.com
 **/

namespace SwiftOtter\OrderExport\Console\Command;

use DateTime;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use SwiftOtter\OrderExport\Action\CollectOrderData;
use SwiftOtter\OrderExport\Action\ExportOrder;
use SwiftOtter\OrderExport\Model\HeaderData;
use SwiftOtter\OrderExport\Model\HeaderDataFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OrderExport extends Command
{
    const ARG_NAME_ORDER_ID = 'order-id';
    const OPT_NAME_SHIP_DATE = 'ship-date';
    const OPT_NAME_MERCHANT_NOTES = 'notes';

    /** @var HeaderDataFactory */
    private $headerDataFactory;
    /** @var CollectOrderData */
    private $collectOrderData;
    /** @var ExportOrder */
    private $exportOrder;

    private $orderRepository;

    public function __construct(HeaderDataFactory $headerDataFactory, CollectOrderData $collectOrderData, ExportOrder $exportOrder, OrderRepositoryInterface $orderRepository,string $name = null)
    {
        parent::__construct($name);
        $this->headerDataFactory = $headerDataFactory;
        $this->collectOrderData = $collectOrderData;
        $this->exportOrder = $exportOrder;
        $this->orderRepository=$orderRepository;    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('order-export:run')->setDescription('Export order to ERP')->addArgument(self::ARG_NAME_ORDER_ID, InputArgument::REQUIRED, "Order ID")->addOption(self::OPT_NAME_SHIP_DATE, 'd', InputOption::VALUE_OPTIONAL, 'Shipping date in format YYYY-MM-DD')->addOption(self::OPT_NAME_MERCHANT_NOTES, null, InputOption::VALUE_OPTIONAL, 'Merchant notes');
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orderId = (int)$input->getArgument(self::ARG_NAME_ORDER_ID);
        $notes = $input->getOption(self::OPT_NAME_MERCHANT_NOTES);
        $shipDate = $input->getOption(self::OPT_NAME_SHIP_DATE);
        /** @var HeaderData $headerData */
        $headerData = $this->headerDataFactory->create();
        if ($shipDate) {
            $headerData->setShipDate(new DateTime($shipDate));
        }
        if ($notes) {
            $headerData->setMerchantNotes($notes);
        }
        $order=$this->orderRepository->get($orderId);
        $orderData = $this->collectOrderData->execute($order, $headerData);

        $output->writeln(print_r($orderData, true));
        $result = $this->exportOrder->execute((int)$orderId, $headerData);
        $success = $result['success'] ?? false;
        if ($success) {
            $output->writeln(__('Successfully exported order'));
        } else {
            $msg = $result['error'] ?? null;
            if ($msg === null) {
                $msg = __('Unexpected errors occurred');
            }
            $output->writeln($msg);
            return 1;
        }

        return 0;
    }
}
