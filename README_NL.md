![CardGate](https://cdn.curopayments.net/thumb/200/logos/cardgate.png)

# CardGate module voor ZenCart

[![Build Status](https://travis-ci.org/cardgate/zencart.svg?branch=master)](https://travis-ci.org/cardgate/zencart)

## Support

Deze plugin is geschikt voor ZenCart versies **1.3.7.1** tot en met **1.5.x**.

## Voorbereiding

Voor het gebruik van deze module zijn CardGate inloggegevens noodzakelijk.

Ga a.u.b. naar [**Mijn CardGate**](https://my.cardgate.com/) en kopieer de  site ID and hash key,  
of vraag deze gegevens aan uw accountmanager.

## Installatie

1. Download en unzip de meest recente [**source code**](https://github.com/cardgate/zencart/releases) op je bureaublad.

2. Upload de map **cardgateplus** en de map **includes** naar de **root** map van je webshop.

## Configuratie

1. Ga naar het **admin** gedeelte van je webshop.

2. Selecteer **Modules, Payment**.

3. Selecteer de **CardGate module** die je wenst te installeren en klik rechts op **Install**.

4. Vul de **site ID** en de **hash key** in, deze kun je vinden bij **Sites** op [**Mijn CardGate**](https://my.cardgate.com/).

5. Vul de standaard **gateway taal** in, bijvoorbeeld **en** voor Engels of **nl** voor Nederlands.

6. Ga naar [**Mijn CardGate**](https://my.cardgate.com/), kies **Sites** en selecteer de juiste site.

7. Vul bij **Technische koppeling** de **Callback URL** in, bijvoorbeeld:  
   **http://mijnwebshop.com/cardgateplus/cgp_process.php**  
  (Vervang **http://mijnwebshop.com** met de URL van je webshop.)

8. Zorg ervoor dat je na het testen omschakelt van **Test mode** naar **Active mode** en sla het op (**Save**).

## Vereisten

Geen verdere vereisten.
