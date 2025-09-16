# LibreCookieConsent

**EU-ready cookie consent plugin for WordPress**

Modern, GDPR-compliant WordPress plugin built on [vanilla-cookieconsent v3.1.0](https://github.com/orestbida/cookieconsent) with REST API architecture, anonymous consent tracking, and Google Consent Mode v2 support.

> **Built on:** [orestbida/cookieconsent](https://github.com/orestbida/cookieconsent) - the best open-source cookie consent library

## Key Features

✅ **Anonymous consent tracking** - no IP address storage  
✅ **REST API architecture** - modern, secure communication  
✅ **GDPR/ePrivacy compliance** - full legislative compliance  
✅ **Google Consent Mode v2** - proper GA4/GTM integration  
✅ **Audit & export** - complete consent overview for audits  
✅ **Automatic retention** - cleanup old logs based on settings  
✅ **Proof shortcode** - `[eccm_consent_proof]` for consent verification  
✅ **Security** - HMAC hash, nonce protection, origin validation  

## Installation

1. Place plugin in `/wp-content/plugins/librecookieconsent/`
2. Run build process:
```bash
cd wp-content/plugins/librecookieconsent
npm install
npm run build
```
3. Activate plugin in WordPress admin
4. Configure in **Cookie Consent** menu


## Režimy fungování

### **GTM Režim** (doporučený pro profesionální weby)

```javascript
// 1. Consent Mode v2 se nastaví na "denied" PŘED načtením GTM
gtag('consent', 'default', {
    'analytics_storage': 'denied',
    'ad_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied'
});

// 2. GTM kontejner se načte ihned, ale tagy čekají na consent
gtag('config', 'GTM-XXXXXXX');

// 3. Po udělení souhlasu se consent aktualizuje a tagy se spustí
gtag('consent', 'update', {
    'analytics_storage': 'granted',  // pokud uživatel souhlasil
    'ad_storage': 'granted'         // pokud uživatel souhlasil
});
```

**Výhody GTM režimu:**
- Všechny tagy spravované centrálně v GTM
- Google Consent Mode v2 nativní podpora
- Enhanced Conversions a lepší data quality
- Pokročilé targeting bez ztráty dat

**Setup pro GTM:**
1. Vyplňte GTM Container ID v nastavení pluginu
2. V GTM nastavte "Consent Requirements" pro všechny tagy
3. Plugin automaticky aktivuje GTM režim s Consent Mode v2

### **Direct Režim** (jednoduchý pro menší weby)

```javascript
// Žádné tracking skripty se nenačítají předem
// Až po souhlasu se vkládají:
if (CookieConsent.acceptedCategory('analytics')) {
    gtag('config', 'GA_MEASUREMENT_ID');
}
```

**Výhody Direct režimu:**
- Jednodušší setup bez nutnosti GTM
- Přímá kontrola nad všemi skripty
- Vhodné pro menší weby s základními potřebami

**Setup pro Direct:**
1. Nechte GTM Container ID prázdné
2. Vyplňte přímo GA4 ID, Meta Pixel ID, Clarity ID
3. Plugin automaticky přepne na Direct režim

## Shortcodes pro uživatele

```php
[eccm_consent_proof]
// Zobrazí: čas souhlasu, kategorie, anonymní identifikátor

[eccm_consent_proof show_hash="false"]
// Bez zobrazení technických detailů

[eccm_consent_form]
// Tlačítko "Změnit nastavení cookies"
```

## Best Practices

### Pro GTM režim
1. Nastavte Built-in Variables: "Consent State - Analytics", "Consent State - Ad Storage"
2. Použijte Consent Requirements pro všechny tracking tagy
3. Implementujte Enhanced Conversions pro lepší data quality

### Pro Direct režim  
1. Definujte vlastní skripty v "Skripty podle kategorií"
2. Používejte `data-category` atributy pro blokování skriptů
3. Implementujte fallback pro uživatele bez JS


## Odkazy a zdroje

- **Vanilla-cookieconsent:** https://github.com/orestbida/cookieconsent
- **Google Consent Mode v2:** https://developers.google.com/tag-platform/security/guides/consent

---

*Plugin je připraven pro produkční nasazení s plnou GDPR compliance a modern security standards.*  
*Postaveno na [vanilla-cookieconsent](https://github.com/orestbida/cookieconsent) od Orest Bida.*