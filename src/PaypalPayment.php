<?php
namespace Xanweb\Paypal;

use Exception;
use Concrete\Core\Http\Request;
use Concrete\Core\Error\ErrorList\ErrorList;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api as PayPalApi;
use PayPal\Rest\ApiContext;

class PaypalPayment
{
    private $paypalClientID;
    private $paypalClientSecret;

    private $paymentDescription;
    private $itemList;
    private $urlOK;
    private $urlFail;
    private $devMode;
    private $shipping = 0;
    private $tax;
    private $subtotal;
    private $total;
    private $currency = 'EUR';
    private $paymentMethod = 'paypal';
    private $invoiceNumber;

    /** @var ErrorList */
    private $error;

    public function __construct($paypalClientID, $paypalClientSecret)
    {
        $this->paypalClientID = $paypalClientID;
        $this->paypalClientSecret = $paypalClientSecret;
        $this->itemList = [];
        $this->error = app('error');
    }

    public function getError()
    {
        return $this->error;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaypalClientID($clientID): void
    {
        $this->paypalClientID = $clientID;
    }

    public function setPaypalClientSecret($clientSecret): void
    {
        $this->paypalClientSecret = $clientSecret;
    }

    /**
     * @default 'paypal'
     *
     * @param string $paymentMethod
     *
     * @return PaypalPayment
     */
    public function setPaymentMethod($paymentMethod): PaypalPayment
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function enableDevMode(): void
    {
        $this->devMode = 1;
    }

    public function getInvoiceNumber(): string
    {
        if (!$this->invoiceNumber) {
            $this->invoiceNumber = uniqid();
        }

        return $this->invoiceNumber;
    }

    public function getShipping(): int
    {
        return $this->shipping;
    }

    public function getPaymentDescription()
    {
        return $this->paymentDescription;
    }

    public function getItemList(): array
    {
        return $this->itemList;
    }

    public function getSuccessURL()
    {
        return $this->urlOK;
    }

    public function getFailUrl()
    {
        return $this->urlFail;
    }

    public function getTax()
    {
        return $this->tax;
    }

    public function getSubtotal()
    {
        return $this->subtotal;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @param string $invoiceNumber
     *
     * @return PaypalPayment
     */
    public function setInvoiceNumber($invoiceNumber): PaypalPayment
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    /**
     * @required
     *
     * @param float $total
     *
     * @return PaypalPayment
     */
    public function setTotal($total): PaypalPayment
    {
        $this->total = $total;

        return $this;
    }

    /**
     * @param float $tax
     *
     * @return PaypalPayment
     */
    public function setTax($tax): PaypalPayment
    {
        $this->tax = $tax;

        return $this;
    }

    /**
     * @param string $paymentDescription
     *
     * @return PaypalPayment
     */
    public function setPaymentDescription($paymentDescription): PaypalPayment
    {
        $this->paymentDescription = $paymentDescription;

        return $this;
    }

    /**
     * @param string $urlOK
     *
     * @return PaypalPayment
     */
    public function setSuccessURL($urlOK): PaypalPayment
    {
        $this->urlOK = $urlOK;

        return $this;
    }

    /**
     * @param string $urlFail
     *
     * @return PaypalPayment
     */
    public function setFailUrl($urlFail): PaypalPayment
    {
        $this->urlFail = $urlFail;

        return $this;
    }

    /**
     * Structure of Item array of ('name'=> $prodName, 'quantity' => $qty, 'price' => $dblNumber).
     *
     * @param array $itemValues
     *
     * @return PaypalPayment
     */
    public function setItemList(array $itemValues): PaypalPayment
    {
        $itemList = [];
        foreach ($itemValues as $itemValue) {
            $item = new PayPalApi\Item();
            $item->setName($itemValue['name'])
                ->setCurrency($this->getCurrency())
                ->setQuantity($itemValue['quantity'])
                ->setPrice($itemValue['price']);
            $itemList[] = $item;
        }
        $this->itemList = $itemList;

        return $this;
    }

    /**
     * Structure of Item array of ('name'=> $prodName, 'quantity' => $qty, 'price' => $dblNumber).
     *
     * @param array $itemValue
     * @return PaypalPayment
     */
    public function addItem(array $itemValue): PaypalPayment
    {
        $item = new PayPalApi\Item();
        $item->setName($itemValue['name'])
            ->setCurrency($this->getCurrency())
            ->setQuantity($itemValue['quantity'])
            ->setPrice($itemValue['price']);
        $this->itemList[] = $item;

        return $this;
    }

    /**
     * @param float $shipping
     *
     * @return PaypalPayment
     */
    public function setShipping($shipping): PaypalPayment
    {
        $this->shipping = $shipping;

        return $this;
    }

    /**
     * @requried
     *
     * @param float $subtotal
     *
     * @return PaypalPayment
     */
    public function setSubtotal($subtotal): PaypalPayment
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    /**
     * @default 'EUR'
     *
     * @param string $currency 'EUR', 'USD' ...
     *
     * @return PaypalPayment
     */
    public function setCurrency($currency): PaypalPayment
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return ErrorList|mixed|string|null
     */
    public function configure()
    {
        try {
            $apiContext = $this->connection();

            if ($this->error->has()) {
                return $this->error;
            }

            $payer = new PayPalApi\Payer();
            $payer->setPaymentMethod($this->getPaymentMethod());

            $itemList = new PayPalApi\ItemList();
            $itemList->setItems($this->getItemList());

            $details = new PayPalApi\Details();
            if ($this->getTax() > 0) {
                $details->setTax($this->getTax());
            }
            if ($this->getShipping() > 0) {
                $details->setShipping($this->getShipping());
            }
            $details->setSubtotal($this->getSubtotal());

            $amount = new PayPalApi\Amount();
            $amount->setCurrency($this->getCurrency())
                ->setTotal($this->getTotal())
                ->setDetails($details);

            $transaction = new PayPalApi\Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription($this->getPaymentDescription())
                ->setInvoiceNumber(uniqid());

            $redirectUrls = new PayPalApi\RedirectUrls();
            $redirectUrls->setReturnUrl($this->getSuccessURL())
                ->setCancelUrl($this->getFailUrl());

            $payment = new PayPalApi\Payment();
            $payment->setIntent('sale')
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions([$transaction]);

            $payment->create($apiContext);

            return $payment->getApprovalLink();
        } catch (Exception $exc) {
            $this->error->add($exc);

            return $this->error;
        }
    }

    /**
     * @return PayPalApi\Payment|ErrorList
     */
    public function executePayment()
    {
        $apiContext = $this->connection();

        if ($this->error->has()) {
            return $this->error;
        }

        $request = Request::getInstance();
        $paymentId = $request->get('paymentId');
        $payerID = $request->get('PayerID');
        if (!$paymentId || !$payerID) {
            $this->error->add(t('Invalid Payment Request'));

            return $this->error;
        }

        $payment = PayPalApi\Payment::get($paymentId, $apiContext);
        $execute = new PayPalApi\PaymentExecution();
        $execute->setPayerId($payerID);

        try {
            return $payment->execute($execute, $apiContext);
        } catch (Exception $exc) {
            $data = json_decode($exc->getData(), false);
            $this->error->add(new Exception(t($data->message) . ' (' . $data->name . ') ', $exc->getCode()));

            return $this->error;
        }
    }

    /**
     * @return ErrorList|ApiContext
     */
    private function connection()
    {
        try {
            $apiContext = new ApiContext(
                new OAuthTokenCredential(
                $this->paypalClientID,
                $this->paypalClientSecret
            ));

            if (!$this->devMode) {
                $apiContext->setConfig(['mode' => 'live']);
            }

            return $apiContext;
        } catch (Exception $exc) {
            $this->error->add($exc);

            return $this->error;
        }
    }
}
