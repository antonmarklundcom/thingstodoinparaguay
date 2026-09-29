# Facts to verify before launch

Most of the copy was rebuilt from the old site's scan and written by AI sessions. Nothing below
was invented on purpose, but only the business owner can confirm it. Tick each line, or fix it in
the admin (or in `content/`) before the cutover.

## Contact details (shown on every page, in the footer, in JSON-LD and on `/contact/`)

- [ ] Phone / WhatsApp `+595 995 628 862` (`config/config.php`, WhatsApp button, JSON-LD `telephone`)
- [ ] Email `hello@thingstodoinparaguay.com` (also the address lead notifications go to; set
      `LEAD_EMAIL_TO` in `.env` if it should go elsewhere)
- [ ] Office "Edificio Skytower, Asunción" (`content/page/contact.md`, `src/Seo.php`). The contact page
      says meetings are by arrangement; confirm that wording, and add a street address or postal code
      if you want a full PostalAddress in the markup.
- [ ] Opening hours / response time promises, if any are stated on `/contact/`

## Who you are (about page, home hero, FAQ)

- [ ] "Anton and Yanina": a Swedish marketing strategist and a Paraguayan photographer
      (`content/page/about.md`, home hero, several FAQ answers)
- [ ] Languages: English, Spanish and Swedish (home FAQ)
- [ ] Anything about how long you have operated, or team size

## Money

No tour or service has a price set (`price_usd: null`), so the site says "ask for a quote". The only
dollar amounts in the copy are general cost information, not offers:

- [ ] School fees "$300 to $1,500+ USD per month" (`content/service/school-placement.md`)
- [ ] Cocktails "$6 to $10 USD" (`content/tour/bars-asuncion-tour.md`)
- [ ] Three-course dinner "$30 to $60 USD per person" (`content/tour/restaurants-asuncion-guide.md`)

## Operational claims worth a read

- [ ] Itaipú: "we visit the Paraguayan side", passport needed, no visa (`content/tour/itaipu-dam-tour.md`)
- [ ] Refund policy answer on `/paraguay-tourism-guide/` ("Do you offer refunds?")
- [ ] Private-driver and airport-transfer terms: what is included, waiting time, luggage
      (`content/service/private-driver.md`, `content/service/airport-transfer.md`)
- [ ] Residency service: what you actually do versus what a lawyer must do
      (`content/service/paraguay-residency-service.md`, `content/service/healthcare-paraguay.md`)
- [ ] Tour durations, departure points and inclusions (each tour's "practical" list)

## Images

Cover images are illustrative (see `docs/imagery-manifest.json`). The photos reused from the old site
(`public/media/harvested/`) are the ones you or a contributor already published; if any came from
stock sites (the scan mentions Pexels files), check their licences.
