<?php

namespace RealexPayments\HPP\Model\API;

use RealexPayments\HPP\Model\Config\Source\SettleMode;

class RemoteXML implements \RealexPayments\HPP\API\RemoteXMLInterface
{
    /**
     * @var \RealexPayments\HPP\Helper\Data
     */
    private $_helper;

    /**
     * @var \RealexPayments\HPP\Logger\Logger
     */
    private $_logger;

    /**
     * @var \RealexPayments\HPP\Model\API\Request\RequestFactory
     */
    private $_requestFactory;

    /**
     * @var \RealexPayments\HPP\Model\API\Response\ResponseFactory
     */
    private $_responseFactory;

    /**
     * @var \Magento\Sales\Model\Order\Status\HistoryFactory
     */
    private $_orderHistoryFactory;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    private $_transactionRepository;

    /**
     * RemoteXML constructor.
     *
     * @param \RealexPayments\HPP\Helper\Data                        $helper
     * @param \RealexPayments\HPP\Logger\Logger                      $logger
     * @param \RealexPayments\HPP\Model\API\Request\RequestFactory   $requestFactory
     * @param \RealexPayments\HPP\Model\API\Response\ResponseFactory $responseFactory
     * @param \Magento\Sales\Api\TransactionRepositoryInterface      $transactionRepository
     */
    public function __construct(
        \RealexPayments\HPP\Helper\Data $helper,
        \RealexPayments\HPP\Logger\Logger $logger,
        \RealexPayments\HPP\Model\API\Request\RequestFactory $requestFactory,
        \RealexPayments\HPP\Model\API\Response\ResponseFactory $responseFactory,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
    ) {
        $this->_helper = $helper;
        $this->_logger = $logger;
        $this->_requestFactory = $requestFactory;
        $this->_responseFactory = $responseFactory;
        $this->_transactionRepository = $transactionRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function settle($payment, $amount)
    {
        $additional = $payment->getAdditionalInformation();
        $request = $this->_requestFactory->create()
                    ->setType(Request\Request::TYPE_SETTLE)
                    ->setMerchantId($additional['MERCHANT_ID'])
                    ->setOrderId($additional['ORDER_ID'])
                    ->setPasref($additional['PASREF'])
                    ->setAmount($amount)
                    ->build();

        return $this->_sendRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function multisettle($payment, $amount)
    {
        $additional = $payment->getAdditionalInformation();
        $request = $this->_requestFactory->create()
                    ->setType(Request\Request::TYPE_MULTISETTLE)
                    ->setMerchantId($additional['MERCHANT_ID'])
                    ->setOrderId($additional['ORDER_ID'])
                    ->setPasref($additional['PASREF'])
                    ->setAccount($additional['ACCOUNT'])
                    ->setAuthCode($additional['AUTHCODE'])
                    ->setAmount($amount)
                    ->build();

        return $this->_sendRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function rebate($payment, $amount, $comments)
    {
        $refundhash = sha1($this->_helper->getEncryptedConfigData('rebate_secret'));
        $transaction = $this->_getTransaction($payment);
        $additional = $payment->getAdditionalInformation();
        if ($additional['AUTO_SETTLE_FLAG'] == SettleMode::SETTLEMODE_MULTI) {
            $orderId = '_multisettle_'.$additional['ORDER_ID'];
            $rawFields = $transaction->getAdditionalInformation(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS
            );
            $pasref = $rawFields['PASREF'];
        } else {
            $orderId = $additional['ORDER_ID'];
            $pasref = $additional['PASREF'];
        }
        $request = $this->_requestFactory->create()
                  ->setType(Request\Request::TYPE_REBATE)
                  ->setMerchantId($additional['MERCHANT_ID'])
                  ->setAccount($additional['ACCOUNT'])
                  ->setOrderId($orderId)
                  ->setPasref($pasref)
                  ->setAuthCode($additional['AUTHCODE'])
                  ->setAmount($amount)
                  ->setCurrency($payment->getOrder()->getBaseCurrencyCode())
                  ->setComments($comments)
                  ->setRefundHash($refundhash)
                  ->build();

        return $this->_sendRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function void($payment, $comments)
    {
        $transaction = $this->_getTransaction($payment);
        $additional = $payment->getAdditionalInformation();
        $orderId = $additional['ORDER_ID'];
        if ($additional['AUTO_SETTLE_FLAG'] == SettleMode::SETTLEMODE_MULTI) {
            $rawFields = $transaction->getAdditionalInformation(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS
            );
            $pasref = $rawFields['PASREF'];
        } else {
            $pasref = $additional['PASREF'];
        }
        $request = $this->_requestFactory->create()
                  ->setType(Request\Request::TYPE_VOID)
                  ->setMerchantId($additional['MERCHANT_ID'])
                  ->setAccount($additional['ACCOUNT'])
                  ->setOrderId($orderId)
                  ->setPasref($pasref)
                  ->setComments($comments)
                  ->build();

        return $this->_sendRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function payerEdit($merchantId, $account, $payerRef, $customer)
    {
        $request = $this->_requestFactory->create()
                  ->setType(Request\Request::TYPE_PAYER_EDIT)
                  ->setMerchantId($merchantId)
                  ->setAccount($account)
                  ->setOrderId(uniqid())
                  ->setPayerRef($payerRef)
                  ->setPayer($customer)
                  ->build();

        return $this->_sendRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function releasePayment($payment, $comments)
    {
        $additional = $payment->getAdditionalInformation();
        $request = $this->_requestFactory->create()
                    ->setType(Request\Request::TYPE_RELEASE)
                    ->setMerchantId($additional['MERCHANT_ID'])
                    ->setAccount($additional['ACCOUNT'])
                    ->setOrderId($additional['ORDER_ID'])
                    ->setPasref($additional['PASREF'])
                    ->setComments($comments)
                    ->build();

        return $this->_sendRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function holdPayment($payment, $comments)
    {
        $additional = $payment->getAdditionalInformation();
        $request = $this->_requestFactory->create()
                    ->setType(Request\Request::TYPE_HOLD)
                    ->setMerchantId($additional['MERCHANT_ID'])
                    ->setAccount($additional['ACCOUNT'])
                    ->setOrderId($additional['ORDER_ID'])
                    ->setPasref($additional['PASREF'])
                    ->setComments($comments)
                    ->build();

        return $this->_sendRequest($request);
    }

    /**
     * @desc Send the request to the remote xml api
     *
     * @param \RealexPayments\HPP\Model\API\Request\Request $request
     *
     * @return \RealexPayments\HPP\Model\API\Response\Response
     */
    private function _sendRequest($request)
    {
        $url = $this->_helper->getRemoteApiUrl();
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        if (!empty($request)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($httpStatus != '200') {
            $this->_helper->logDebug(print_r(['status' => $httpStatus, 'body' => $response], true));

            return false;
        }

        return $this->_responseFactory->create()->parse($response);
    }

    private function _getTransaction($payment)
    {
        $transaction = $this->_transactionRepository->getByTransactionId(
            $payment->getParentTransactionId(),
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        return $transaction;
    }
}
