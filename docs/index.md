# OpenFilter

Layered navigation for Magento 2 and Hyvä. Free and MIT, built on the OpenSearch Magento already ships.

- **Multi-select filters with correct counts**: options of a filter you use are counted as if it were not set, in one search request.
- **AJAX that Varnish can cache**: a filter click fetches the same URL a normal page load would, so the page cache serves it; CSP-clean on Hyvä, no reload, back button and scroll restored.
- **Works without JavaScript**: every option is a real link, sliders are forms.
- **Sliders and built-in filters**: price (display currency and tax aware) and decimal sliders, rating, in stock, on sale, new.
- **Mobile drawer** with "Show 123 products" counted before you apply.
- **SEO rules**: readable URL names, one canonical URL per page (301), robots and canonical tags, nofollow and a limit on combined values.
- **Admin with diagnostics**: Filters grid, a form per filter, "Why is this filter not showing?", `openfilter:doctor`.

Start with [Install](install.md). Questions and bugs: [GitHub issues](https://github.com/iranimij/openfilter/issues) (triaged within 7 days, no SLA).
