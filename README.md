# PayPal RESTful API Payment Module
This Zen Cart payment module (`paypalr`) combines the processing for the **PayPal Payments Pro** (`paypaldp`) and **PayPal Express Checkout** (`paypalwpp`) payment modules that are currently built into Zen Cart distributions.  Instead of using the older NVP (**N**ame **V**alue **P**air) methods to communicate with PayPal, this payment module uses PayPal's now-current [REST APIs](https://developer.paypal.com/api/rest/) and combines the two legacy methods into one.

Zen Cart Support Thread: https://www.zen-cart.com/showthread.php/229886-PayPal-RESTful-API-Payment-Module

Zen Cart Plugin Download Link: https://www.zen-cart.com/downloads.php?do=file&id=2382

**Note:** This version of the payment module supports Zen Cart versions 1.5.8a through 2.1.0 **only**! If your site uses Zen Cart 2.2.0 or later, the payment module is  provided as an encapsulated plugin as part of the Zen Cart distribution.

The module's operation has been validated …

1. With PHP versions 7.0 through 8.5; **PHP versions prior to 7.0 will result in fatal PHP errors!**
2. In Zen Cart's 3-page checkout environment (v1.5.8a, v2.0.x and v2.1.0).
3. With One-Page Checkout  (OPC), v2.5.4-v2.6.2
   1. Using *OPC*'s guest-checkout feature.
   2. Both requiring confirmation and not!
4. With both the built-in responsive_classic and [ZCA Bootstrap](https://www.zen-cart.com/downloads.php?do=file&id=2191) (v3.7.8-3.8.0) templates.

For additional information, refer to the payment-module's [wiki articles](https://github.com/lat9/paypalr/wiki).
