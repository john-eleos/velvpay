# Introduction
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)


This package provides an expressive and convenient way to interact with the [Velvpay API](https://bit.ly/velvapi).

## Installation

You can install the package via composer:

```bash
composer require digikraaft/velvpay-php
```

## Usage

All APIs documented in Velvpay's [Developer Reference](https://bit.ly/velvapi) are currently supported by this package.

## Authentication
You must first authenticate with Velvpay before any of the endpoints and available methods can be used. To do so, set your secret, public and encryption keys:

```php
<?php 

use Digikraaft\VelvPay\VelvPay;

VelvPay::setKeys('SK_123456', 'PK_23456', 'digikraaft234');

```

### A note on Request Reference and Idempotency
VelvPay requires that each request made to their endpoint has a unique reference. This package automatically generates this unique reference for each request. If you need to access the reference that is generated, do this after each call:

```php
<?php

use Digikraaft\VelvPay\VelvPay;
use Digikraaft\VelvPay\Bank;

VelvPay::setKeys('SK_123456', 'PK_23456', 'digikraaft234');
$bankDetails = Bank::accountDetails('0123456789', '003');

$requestReference = VelvPay::getRequestReference();

```

If you however want to generate your own unique reference, just call the `setRequestReference` method like this before calling the endpoint:
```php
<?php

use Digikraaft\VelvPay\VelvPay;
use Digikraaft\VelvPay\Bank;

VelvPay::setKeys('SK_123456', 'PK_23456', 'digikraaft234');

$requestReference = VelvPay::setRequestReference("REQF_12345add");

$bankDetails = Bank::accountDetails('0123456789', '003');

```

Some endpoints also require an Idempotency key. This package automatically generates this for you. You can get the key that was generated for the request by calling the `getIdempotencyKey` function immediately after making the request  like this:

```php
<?php

use Digikraaft\VelvPay\VelvPay;
use Digikraaft\VelvPay\Payment;

// set keys

$parameters = [
    'transaction_id' = 'FR-IM5N2MKNJ40H'
];
$paymentDetails = Payment::details($parameters);
$idempotencyKey = Payment::getIdempotencyKey();

```

## API Responses
This package returns exact responses from the Velvpay API but as the `stdClass` type such that responses can be accessed like this:

```php
<?php

use Digikraaft\VelvPay\VelvPay;
use Digikraaft\VelvPay\Bank;

VelvPay::setKeys('SK_123456', 'PK_23456', 'digikraaft234');
$bankDetails = Bank::accountDetails('0123456789', '003');

if ($bankDetails->status == 'success') {
    echo $bankDetails->data->accountName;
}

```
Please check the [API documentation](https://bit.ly/velvapi) at VelvPay to know what response values are returned. Each of the available methods below have been linked to the exact documentation page on their site.

## Available Methods
A list of the available methods are documented below:

#### Bank
* `accountDetails(string $bankAccountNumber, string $bankCode) : Array|Object`
* `list() : Array|Object`

#### Bill
* `categoryList(string $bankAccountNumber, string $bankCode) : Array|Object`
* `details(string $category = null) : Array|Object`
* `list(string $billerId, string $divisionId, string $productId) : Array|Object`
* `pay(string $billerId, string $amount, string $productId, string $paymentItem, string $category, string $billId, string $division) : Array|Object`
* `status(string $reference) : Array|Object`

#### CashCraft
* `confirmFee() : Array|Object`
* `details(string $transactionId) : Array|Object`
* `initiate(string $amount, string $email, array $beneficiaries, string $description, int $validityTime = 20, bool $isNaira = false) : Array|Object`
* `manualResolve(string $transactionId) : Array|Object`

#### Payment
* `createVirtualAccount(int|float $amount, string $customerEmail, int $validityTime = 20, bool $isNaira = false) : Array|Object`
* `initiatePayment( int|float $amount, bool $isNaira = false, string $title, string $description, bool $chargeCustomer = false, string $postPaymentInstructions) : Array|Object`
* `details(array $parameters) : Array|Object`
* `requestFund(int|float $amount, string $type, string $senderEmail, string $receiverEmail, string $paymentName, string $description, array $metadata = null, string $idempotencykey = null, bool $isNaira = false) : Array|Object`
* `requestFundStatus(string $reference) : Array|Object`

#### Payout
* `balance() : Array|Object`
* `resolveStatus(string $transactionId) : Array|Object`
* `transfer(int|float $amount, string $bankCode, string $bankName, string $accountNumber, string $accountName, string $description, int $transactionPin, string $idempotencykey = null, bool $isNaira = false) : Array|Object`

#### VelvPay
* `authorizationToken() : string`
* `getEncryptionKey() : string`
* `getPublicKey() : string`
* `getRequestReference() : string`
* `getSecretKey() : string`
* `setEncryptionKey(string $encryptionKey) : void`
* `setKeys(string $secretKey, string $publicKey, string $encryptionKey) : void`
* `setPublicKey(string $publicKey) : void`
* `setRequestReference(string $requestReference) : void`
* `setSecretKey(string $secretKey) : void`

#### Wallet
* `accountDetails(string $accountId) : Array|Object`
* `create(string $email, string $bvn, string $username, string $gender, string $dateOfBirth, bool $referralCode = false) : Array|Object`
* `customers(array $parameters = null) : Array|Object`
* `transactions(array $parameters = null) : Array|Object`


## Testing

``` bash
composer test
```
## More Good Stuff
Check [here](https://github.com/digikraaft) for more awesome free stuff!

## Changelog
Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security
If you discover any security related issues, please email hello@digikraaft.ng instead of using the issue tracker.

## Credits
- [Tim Oladoyinbo](https://github.com/timoladoyinbo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
