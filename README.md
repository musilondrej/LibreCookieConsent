# Cookie Consent for WordPress

**EU-ready cookie consent plugin for WordPress**

Modern, GDPR-compliant WordPress plugin built on [vanilla-cookieconsent v3.1.0](https://github.com/orestbida/cookieconsent) with REST API architecture, anonymous consent tracking, and Google Consent Mode v2 support.

> **Built on:** [orestbida/cookieconsent](https://github.com/orestbida/cookieconsent) - the best open-source cookie consent library

## Key Features

- **Anonymous consent tracking** - no IP address storage  
- **REST API architecture** - modern, secure communication  
- **GDPR/ePrivacy compliance** - full legislative compliance  
- **Google Consent Mode v2** - proper GA4/GTM integration  
- **Audit & export** - complete consent overview for audits  
- **Automatic retention** - cleanup old logs based on settings  
- **Proof shortcode** - `[eccm_consent_proof]` for consent verification  
- **Security** - HMAC hash, nonce protection, origin validation  

## Installation

1. Place the plugin in `/wp-content/plugins/librecookieconsent/`
2. Run the build process:
```bash
cd wp-content/plugins/librecookieconsent
npm install
npm run build
```
3. Activate the plugin in WordPress admin
4. Configure it in the **Cookie Consent** menu


## Operating Modes

### **GTM Mode** (recommended for professional websites)

```javascript
// 1. Consent Mode v2 is set to "denied" BEFORE loading GTM
gtag('consent', 'default', {
    'analytics_storage': 'denied',
    'ad_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied'
});

// 2. GTM container is loaded immediately, but tags wait for consent
gtag('config', 'GTM-XXXXXXX');

// 3. After consent is given, consent is updated and tags are fired
gtag('consent', 'update', {
    'analytics_storage': 'granted',  // if the user has consented
    'ad_storage': 'granted'         // if the user has consented
});
```

**Advantages of GTM mode:**
- All tags managed centrally in GTM
- Native support for Google Consent Mode v2
- Enhanced Conversions and better data quality
- Advanced targeting without data loss

**Setup for GTM:**
1. Enter GTM Container ID in the plugin settings
2. In GTM, set "Consent Requirements" for all tags
3. The plugin automatically activates GTM mode with Consent Mode v2

### **Direct Mode** (simple for smaller websites)

```javascript
// No tracking scripts are loaded in advance
// Only after consent are they inserted:
if (CookieConsent.acceptedCategory('analytics')) {
    gtag('config', 'GA_MEASUREMENT_ID');
}
```

**Advantages of Direct mode:**
- Simpler setup without the need for GTM
- Direct control over all scripts
- Suitable for smaller websites with basic needs

**Setup for Direct:**
1. Leave GTM Container ID empty
2. Enter GA4 ID, Meta Pixel ID, Clarity ID directly
3. The plugin automatically switches to Direct mode

## Shortcodes for users

```php
[eccm_consent_proof]
// Displays: consent time, categories, anonymous identifier

[eccm_consent_proof show_hash="false"]
// Without displaying technical details

[eccm_consent_form]
// "Change cookie settings" button
```

## Best Practices

### For GTM mode
1. Set up Built-in Variables: "Consent State - Analytics", "Consent State - Ad Storage"
2. Use Consent Requirements for all tracking tags
3. Implement Enhanced Conversions for better data quality

### For Direct mode  
1. Define custom scripts in "Scripts by category"
2. Use `data-category` attributes to block scripts
3. Implement fallback for users without JS


## Links and Resources

- **Vanilla-cookieconsent:** https://github.com/orestbida/cookieconsent
- **Google Consent Mode v2:** https://developers.google.com/tag-platform/security/guides/consent

---

*The plugin is ready for production deployment with full GDPR compliance and modern security standards.*  
*Built on [vanilla-cookieconsent](https://github.com/orestbida/cookieconsent) by Orest Bida.*