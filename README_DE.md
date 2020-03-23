![CardGate](https://cdn.curopayments.net/thumb/200/logos/cardgate.png)

# CardGate Modul für ZenCart

[![Build Status](https://travis-ci.org/cardgate/zencart.svg?branch=master)](https://travis-ci.org/cardgate/zencart)

## Support

Dieses Modul is geeignet für ZenCart Version **1.3.7.1** bis zu Version **1.5.x**.

## Vorbereitung

Um dieses Modul zu verwenden, sind Zugangsdaten zu **CardGate** notwendig.  
Gehen Sie zu [**Mein CardGate**](https://my.cardgate.com/) und fragen Sie Ihre **Site ID** und **Hash Key** an, oder kontaktieren Sie Ihren Accountmanager.

## Installation

1. Downloaden und entpacken Sie den aktuellsten [**cardgate.zip**](https://github.com/cardgate/zencart/releases) Datei auf Ihrem Desktop.

2. Uploaden Sie den **cardgateplus-Ordner**, und den **includes-Ordner** in den **root-Ordner** Ihres Webshops. 

## Konfiguration

1. Loggen Sie sich in den **Adminbereich** Ihres Webshops ein.

2. Wählen Sie **Modules, Payment** aus.

3. Selektieren Sie das **CardGate Module**, dass Sie installieren möchten und klicken Sie auf **Installieren**.

4. Füllen Sie die **Site ID** und den **Hash Key** ein, diesen können Sie unter **Webseite** bei [**Mein CardGate**](https://my.cardgate.com/) finden.

5. Füllen Sie die standart **Gateway-Sprache** ein, z.B. **en** für Englisch oder **de** für Deutsch.

6. Gehen Sie zu [**Mein CardGate**](https://my.cardgate.com/), wählen Sie die richtige Webseite aus.

7. Füllen Sie bei **Schnittstelle** die **Callback URL** ein, zum Beispiel:  
   **http://meinwebshop.com/cardgateplus/cgp_process.php**  
   (Tauschen Sie **http://meinwebshop.com** mit der URL von Ihrem Webshop aus.)  

8. Sorgen Sie dafür, dass Sie **nach dem Testen** von dem **Testmode** in den **Livemode** umschalten und klicken Sie auf **speichern**.

## Anforderungen

Keine weiteren Anforderungen.