=== LibreCookieConsent ===
Contributors: MusilTech
Tags: cookie consent, gdpr, eu cookie law, google consent mode, privacy
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

EU-ready cookie banner with CookieConsent v3, Google Consent Mode v2, support for GA4/Meta/Clarity, both direct and GTM modes.

== Description ==

LibreCookieConsent je právně vyhovující řešení pro správu cookies na webových stránkách podle evropského nařízení GDPR. Plugin používá knihovnu CookieConsent v3 a podporuje Google Consent Mode v2.

**Klíčové funkce:**

* **Dva režimy nasazení:**
  * Direct režim - blokuje skripty třetích stran až do souhlasu uživatele
  * GTM režim - integruje se s Google Tag Manager pomocí Consent Mode v2

* **Předkonfigurované služby:**
  * Google Analytics 4
  * Meta Pixel (Facebook)
  * Microsoft Clarity
  * Google Tag Manager

* **Kategorie cookies:**
  * Nezbytné (vždy povolené)
  * Analytické
  * Marketingové

* **Compliance funkce:**
  * Default consent: denied pro všechny kategorie kromě security_storage
  * Automatické mazání cookies při odmítnutí
  * Wait for update: 500ms pro načtení consent stavu

* **Uživatelské rozhraní:**
  * Plně lokalizované do češtiny
  * Přizpůsobitelné texty
  * Volitelné barevné schéma
  * Shortcode `[cookie_revisit]` pro změnu nastavení

== Installation ==

1. Nahrajte složku pluginu do `/wp-content/plugins/`
2. Aktivujte plugin v administraci WordPressu
3. Přejděte na Nastavení → EU Cookie Consent
4. Zvolte režim nasazení (Direct/GTM)
5. Vyplňte ID služeb, které používáte
6. Přizpůsobte texty podle potřeby

== Frequently Asked Questions ==

= Jak nastavit GTM režim? =

1. V nastavení pluginu zvolte "GTM režim"
2. Vyplňte GTM Container ID (GTM-XXXXXXX)
3. V Google Tag Manager nastavte u všech tagů Consent Requirements:
   - Pro analytické tagy: analytics_storage
   - Pro reklamní tagy: ad_storage, ad_user_data, ad_personalization

= Jak přidat podporu pro další služby? =

Plugin je navržen extensibilně. Pro přidání nové služby:

1. V Direct režimu: Přidejte metodu do `ECCM\Frontend\Services` třídy
2. Použijte `type="text/plain" data-category="analytics|marketing"`
3. Pro GTM režim: Nakonfigurujte consent requirements v GTM

= Podporuje plugin další jazyky? =

Plugin je připraven pro lokalizaci. Texty lze přeložit pomocí .po/.mo souborů nebo přímo v admin rozhraní.

= Je plugin kompatibilní s cache pluginy? =

Ano, plugin je navržen pro práci s cache. JavaScript se načítá až po načtení stránky.

== Screenshots ==

1. Admin nastavení - obecná konfigurace
2. Cookie banner na frontendu
3. Modal s preferencemi cookies
4. Shortcode pro změnu nastavení

== Changelog ==

= 0.1.0 =
* Initial release
* Support for Direct and GTM modes
* Google Consent Mode v2 integration
* Pre-configured GA4, Meta Pixel, Clarity support
* Czech localization
* Shortcode support

== Third-party Services ==

This plugin integrates with third-party services when configured:

* **CookieConsent v3** - Cookie consent library (MIT License)
  * Used for: Cookie consent UI and management
  * Privacy policy: https://github.com/orestbida/cookieconsent

* **Google Analytics** - When GA4 ID is provided
  * Privacy policy: https://policies.google.com/privacy

* **Meta Pixel** - When Pixel ID is provided
  * Privacy policy: https://www.facebook.com/privacy/policy/

* **Microsoft Clarity** - When Project ID is provided
  * Privacy policy: https://privacy.microsoft.com/privacystatement

* **Google Tag Manager** - When GTM ID is provided
  * Privacy policy: https://policies.google.com/privacy

== License ==

This plugin is licensed under GPL v3 or later.

Third-party libraries:
* CookieConsent v3 - MIT License (included in /assets/vendor/)