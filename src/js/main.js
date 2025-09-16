import "vanilla-cookieconsent/dist/cookieconsent.css";
import * as CookieConsent from "vanilla-cookieconsent";

window.CookieConsent = CookieConsent;

document.addEventListener('DOMContentLoaded', function() {
    const CONFIG = window.CCM_CONFIG;
    
    if (!CONFIG) {
        console.error('CCM Cookie Consent: Configuration not found');
        return;
    }

    function generateConsentId() {
        return Array.from(crypto.getRandomValues(new Uint8Array(32)), b => 
            b.toString(16).padStart(2, '0')).join('');
    }

    function getOrCreateConsentId() {
        let consentId = getCookie('ccm_consent_id');
        if (!consentId) {
            consentId = generateConsentId();
            setCookie('ccm_consent_id', consentId, 365); 
        }
        return consentId;
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    function setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${name}=${value}; expires=${expires}; path=/; secure; SameSite=Lax`;
    }

    function logConsentToServer() {
        setTimeout(() => {
            const categories = ['analytics', 'marketing', 'functionality'].filter(cat => CookieConsent.acceptedCategory(cat));
            const consentId = getOrCreateConsentId();
            fetch(CONFIG.restUrl + 'eccm/v1/consent', {
                method: 'POST',
                body: JSON.stringify({
                    consent_id: consentId,
                    categories: categories,
                    version_hash: CONFIG.version || '1.0',
                    source: 'accept',
                    _wpnonce: CONFIG.nonce
                }),
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .catch(error => {
                console.error('Error logging consent:', error);
            });
        }, 100);
    }
    function defaultConsent() {
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'default', {
                ad_storage: 'denied',
                analytics_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
                security_storage: 'granted',
                wait_for_update: 500
            });
        }
        if (typeof dataLayer !== 'undefined') {
            dataLayer.push({
                event: 'cookie_consent_default',
                ad_storage: 'denied',
                analytics_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied'
            });
        }
    }

    function updateConsentMode() {
        if (typeof gtag === 'undefined') return;
        var consentUpdate = {
            ad_storage: CookieConsent.acceptedCategory('marketing') ? 'granted' : 'denied',
            analytics_storage: CookieConsent.acceptedCategory('analytics') ? 'granted' : 'denied',
            ad_user_data: CookieConsent.acceptedCategory('marketing') ? 'granted' : 'denied',
            ad_personalization: CookieConsent.acceptedCategory('marketing') ? 'granted' : 'denied',
            security_storage: 'granted'
        };
        gtag('consent', 'update', consentUpdate);
        if (typeof dataLayer !== 'undefined') {
            dataLayer.push({
                event: 'cookie_consent_update',
                ad_storage: consentUpdate.ad_storage,
                analytics_storage: consentUpdate.analytics_storage,
                ad_user_data: consentUpdate.ad_user_data,
                ad_personalization: consentUpdate.ad_personalization,
                security_storage: consentUpdate.security_storage,
                timestamp: Date.now()
            });
        }
    }
    function executeScriptsForCategory(category) {
        if (CONFIG.mode === 'direct') {
            document.querySelectorAll(`script[data-category="${category}"]`).forEach(runBlockedScript);
        }
        
        if (CONFIG.categoryScripts && CONFIG.categoryScripts[category]) {
            try {
                const scriptElement = document.createElement('script');
                scriptElement.textContent = CONFIG.categoryScripts[category];
                document.head.appendChild(scriptElement);
            } catch (error) {
                console.error(`Error executing ${category} script:`, error);
            }
        }
    }

    function enableBlockedScripts(acceptedCategories = null) {
        const categories = acceptedCategories || ['analytics', 'marketing', 'functionality'];
        
        categories.forEach(category => {
            if (CookieConsent.acceptedCategory(category)) {
                executeScriptsForCategory(category);
            }
        });
    }

    function runBlockedScript(script) {
        const newScript = document.createElement('script');
        if (script.dataset.src) {
            newScript.src = script.dataset.src;
        } else {
            newScript.textContent = script.textContent;
        }
        newScript.type = 'text/javascript';
        script.parentNode.insertBefore(newScript, script.nextSibling);
    }

    function pushGTMEvent() {
        if (CONFIG.mode !== 'gtm' || typeof dataLayer === 'undefined') return;
        dataLayer.push({
            event: 'cookie_consent_update',
            analytics: CookieConsent.acceptedCategory('analytics'),
            marketing: CookieConsent.acceptedCategory('marketing'),
            functionality: CookieConsent.acceptedCategory('functionality')
        });
    }

    function eraseRevokedCookies() {
        if (!CookieConsent.acceptedCategory('analytics') || !CookieConsent.acceptedCategory('marketing')) {
            const cookiesToErase = CONFIG.cookiesToErase || '_ga,_gid,_gat,_gcl_,__utm,_fbp,fr,_uet,_ttp,_pin_';
            
            const cookieNames = cookiesToErase.split(',').map(name => name.trim()).filter(name => name.length > 0);
            if (cookieNames.length > 0) {
                const regexPattern = '^(' + cookieNames.map(name => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + ')';
                CookieConsent.eraseCookies(new RegExp(regexPattern));
            }
        }
    }

    function initializeCookieConsent() {
        defaultConsent();
        
        const configWithCallbacks = {
            ...CONFIG,
            onFirstConsent: function(user_preferences) {
                updateConsentMode();
                enableBlockedScripts();
                pushGTMEvent();
                logConsentToServer();
            },
            onConsent: function(user_preferences) {
                updateConsentMode();
                enableBlockedScripts();
                pushGTMEvent();
            },
            onChange: function(user_preferences, changed_categories) {
                updateConsentMode();
                eraseRevokedCookies();
                if (changed_categories) {
                    changed_categories.forEach(category => {
                        if (CookieConsent.acceptedCategory(category)) {
                            executeScriptsForCategory(category);
                        }
                    });
                }
                pushGTMEvent();
                logConsentToServer();
            }
        };
        
        CookieConsent.run(configWithCallbacks);
    }

    initializeCookieConsent();
});