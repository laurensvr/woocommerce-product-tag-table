# WooCommerce Product Tag Table

Deze repository bevat de WordPress plugin **WooCommerce Product Tag Table** samen met een kant-en-klare Docker-omgeving om de plugin te testen tegen de laatste WooCommerce-versie.

## Vereisten

* [Docker Desktop](https://www.docker.com/products/docker-desktop/) of een compatibele Docker-installatie
* `docker compose` CLI

## Demo-omgeving starten

1. Voer het setup-script uit:

   ```bash
   bin/setup.sh
   ```

   Het script start de database en WordPress containers, installeert de laatste WooCommerce plugin, activeert deze plugin en voegt demo-producten toe.

2. Open [http://localhost:8080](http://localhost:8080) in je browser. Log in met:

   * **Gebruikersnaam:** `admin`
   * **Wachtwoord:** `admin`

3. Navigeer naar de shortcode pagina of voeg de shortcode toe aan een nieuwe pagina: `[product_tag_table tag="wijn"]`.

## Inhoud van de demo

* WooCommerce 8.x op WordPress 6.5 (PHP 8.2)
* Extra taxonomieën `region`, `country` en `vendors` via een meegeleverde mu-plugin
* Drie voorbeeldproducten met verschillende voorraadinstellingen en backorderopties

## Handige commando's

* Containers stoppen:

  ```bash
  docker compose down
  ```

* Demo-gegevens opnieuw vullen:

  ```bash
  docker compose run --rm cli wp --allow-root eval-file /var/www/html/mock-data.php
  ```

* WooCommerce of WordPress CLI-commando uitvoeren:

  ```bash
  docker compose run --rm cli wp --allow-root plugin list
  ```

## Opschonen

Gebruik het volgende commando om alle containers en volumes te verwijderen:

```bash
docker compose down -v
```

Daarna kun je het `wordpress_data` volume eventueel in Docker Desktop verwijderen indien gewenst.
