![CardGate](https://cdn.curopayments.net/thumb/200/logos/cardgate.png)

# CardGate Module for ZenCart

[![Build Status](https://travis-ci.org/cardgate/zencart.svg?branch=master)](https://travis-ci.org/cardgate/zencart)

## Support

This plugin supports ZenCart version **1.3.7.1** to **1.5.x**.

## Preparation

The usage of this module requires that you have obtained CardGate security credentials.  
Please visit [**My CardGate**](https://my.cardgate.com/) and retrieve your **site ID** and **hash key**, or contact your accountmanager.

## Installation

1. Download and unzip the most recent [**cardgate.zip**](https://github.com/cardgate/zencart/releases) file on your desktop.

2. Upload the **cardgateplus** folder and the **includes** folder to the **root** folder of your webshop.

## Configuration

1. Go to the **admin** section of your webshop.

2. Select **Modules, Payment**.

3. Select the **CardGate module** you wish to activate and click on **Install** on the right side.

4. Enter the **site ID** and the **hash key**, which you can find at **Sites** on [**My CardGate**](https://my.cardgate.com/).

5. Enter the default **gateway language**, for example **en** for English or **nl** for Dutch.

6. Go to [**My CardGate**](https://my.cardgate.com/), choose **Sites** and select the appropriate site.

7. Go to **Connection to the website** and enter the **Callback URL**, for example:  
   **http://mywebshop.com/cardgateplus/cgp_process.php**  
   (Replace **http://mywebshop.com** with the URL of your webshop)

8. When you are **finished testing** make sure that you switch from **Test mode** to **Active mode** and save it (**Save**).

## Requirements

No further requirements.