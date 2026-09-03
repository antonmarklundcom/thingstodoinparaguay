# Planning Brief — thingstodoinparaguay.com (WordPress scan facts only)

Scanned live 2026-09-02 via browser automation (fetch/DOM parse), cross-checked with WP sitemaps. No fabricated content.

## 1. Site purpose, language, audience, tone, monetization

- Purpose: Paraguay tourism/travel + expat-relocation info & booking-lead site. Two personas behind the brand: **Yanina Alvarez** (Photographer) and **Anton Marklund** (Marketing Director) — named bios/quotes on the homepage.
- Language: English only (no hreflang/other-language versions found). One leftover Spanish-guide product mention ("Spanish version available separately") but no live Spanish pages.
- Audience: international tourists visiting Paraguay + foreigners considering relocation (apartment hunting, schools, residency, healthcare).
- Tone: warm/aspirational marketing copy on the 18 real tour pages ("Journey Into the Soul of Paraguay", "Escape the City. Touch the Tradition."); generic Lorem Ipsum on all 33 blog posts.
- Monetization: no ads. Contact/quote is the only conversion path — all CTAs route to `/contact/`. One paid-product page (`/paraguay-tourism-guide/`, a $-unspecified downloadable PDF guide) but its "Buy"/"Download" buttons are broken/non-functional (no checkout, "Download Now" href is empty). No live pricing anywhere (tour cards show bare "$" or "$0" placeholders). No WhatsApp link anywhere. Contact "form" is a third-party GoHighLevel iframe (`api.leadconnectorhq.com/widget/form/...`), not self-hosted. Only real first-party form is a Mailchimp email-signup widget (footer + `/contact/`), unverified if the API key is live.

## 2. Full URL inventory (121 URLs, all HTTP 200 per WP sitemaps)

Flat slug structure everywhere: `/<slug>/` (no `/blog/<slug>/` prefix even for posts).

| Type | Count | Pattern |
|---|---|---|
| Homepage | 2 (competing) | `/` (root, richer) and `/home/` (alt homepage) |
| Blog posts | 33 | `/<slug>/` |
| Pages | 42 | `/<slug>/` |
| Category archives | 7 | `/category/<slug>/` |
| Tag archives | 39 | `/tag/<slug>/` |
| MetForm stub (CPT) | 1 | `/metform-form/new-form-1770073113/` |
| Sitemaps | 6 sub-sitemaps | `/wp-sitemap.xml` → posts-post, pages, categories, post_tag, metform-form, etc. |
| Feeds | not explicitly checked in scan | (gap — see §7) |
| Images | served from `/wp-content/uploads/2025/07/...` etc., no CDN path noted beyond Hostinger `hcdn` edge | — |

### Blog posts (33) — table (slug — title — status)
All 33 use identical Lorem-Ipsum body copy under H3 "Giving You The Best Services Experiences"; only title/category/tags/(sometimes) featured image differ. 12 of 33 have a real featured image; 21 have none.

1. /exploring-ciudad-del-este/ — Exploring the Soul of Ciudad del Este (has real image)
2. /paraguayan-cuisine/ — A food lover's Guide to Paraguayan Cuisine
3. /top-places-paraguay/ — Top Places to Discover in Paraguay
4. /jesuit-missions-paraguay/ — The Jesuit Missions of Paraguay
5. /cost-of-living-paraguay/ — Cost of Living in Paraguay (2025)
6. /best-neighborhoods-to-live-in-asuncion-where-should-you-settle-down-in-2025/ — Best Neighborhoods to Live in Asunción
7. /renting-apartment-asuncion/ — Renting an Apartment in Asunción
8. /remote-work-life-in-asuncion-cafes-coworking-internet-you-can-rely-on/ — Remote Work Life in Asunción
9. /cerro-cora-park/ — Cerro Corá National Park (has real image)
10. /filadelfia-chaco/ — Filadelfia, Chaco
11. /caacupe/ — Caacupé – Spiritual Soul of Paraguay
12. /san-bernardino/ — Discover San Bernardino, Paraguay
13. /is-paraguay-safe/ — Is Paraguay Safe?
14. /essential-guide-paraguay/ — Essential Guide to Paraguay
15. /paraguay-tourism-guide/ — Paraguay Tourism Guide (POST shadowed by PAGE at same slug — see §7)
16. /what-to-do-in-asuncion/ — What to Do in Asunción
17. /airport-transfer-paraguay/ — Airport Transfer in Paraguay
18. /shopping-in-asuncion/ — Shopping in Asunción
19. /food-park-mburucuya-a-culinary-oasis-in-asuncion/ — Food Park Mburucuyá
20. /villa-morra-food-park/ — Villa Morra Food Park in Asunción
21. /paraguay-travel-advice/ — Paraguay Travel Advice
22. /paraguay-sightseeing/ — Paraguay Sightseeing
23. /atlantic-forest-paraguay/ — Discover Paraguay's hidden jungle: the Atlantic forest
24. /mbatovi-ecoadventure/ — Explore Eco‑Reserva Mbatoví (has real image)
25. /pantanal-paraguayo/ — Exploring the Pantanal Paraguayo (has real image)
26. /shopping-beaches/ — Shopping, Culture & Beaches (has real image)
27. /complejo-ecologico-techapyra/ — Complejo Ecológico Techapyrã (has real image)
28. /serrania-san-luis-national-park/ — Serranía San Luis National Park
29. /paraguay-national-parks/ — Exploring Paraguay's National Parks (has real image)
30. /waterfalls-paraguay/ — The Most Beautiful Waterfalls in Paraguay (has real image)
31. /iguazu-falls/ — Iguazú Falls: A Must-See Adventure Near Paraguay
32. /paraguay-beaches/ — Paraguay's Hidden Beach Paradises (has real image)
33. /asuncion-bus-terminal/ — Asunción Bus Terminal Guide

### Pages (42) — table (slug — title — status)
1. /home/ — Home — REAL, alt homepage
2. /about/ — About — REAL, thin (dupe of /about2/)
3. /services/ — Services — REAL (relocation/logistics; CTAs dead `href="#"`)
4. /service/ — Tours — REAL (10 tour cards, "$0" placeholders)
5. /blog/ — Blog — REAL index, footer CTA still Lorem Ipsum
6. /team/ — Team — EMPTY, not nav-linked
7. /pricing/ — Pricing — EMPTY, not nav-linked
8. /tourist-attractions-paraguay/ — Tourist Attractions in Paraguay — EMPTY, but nav-linked (broken link live today)
9. /day-trips-asuncion/ — Day Trips from Asunción — REAL, rich
10. / (root) — (no title tag) — REAL, rich, the true front page
11. /about2/ — About2 — REAL, richer than /about/ (has team bios)
12. /destination-detail/ — Destination Detail2 — PLACEHOLDER (Bali/Indonesia theme demo)
13. /destinations/ — Destinations — PLACEHOLDER (Bangkok/Tokyo/Bali demo), nav-linked, broken
14. /faq2/ — FAQ2 — REAL, rich
15. /faq/ — FAQ2 (same title) — byte-identical to /faq2/
16. /contact/ — Contact — REAL but thin; GoHighLevel iframe "form"
17. /asuncion-city-tour/ — Asuncion City Tour — REAL, buggy (dup paragraph, stray Lorem line)
18. /restaurants-asuncion-guide/ — Restaurants Asuncion Guide — REAL, complete
19. /bars-asuncion-tour/ — Bars Asuncion Tour — REAL, complete
20. /food-paraguay-tour/ — Food Paraguay Tour — REAL, 6/8 FAQ answers Lorem Ipsum
21. /shopping-asuncion/ — Shopping Asuncion — REAL, complete
22. /paraguay-souvenirs/ — Paraguay Souvenirs — REAL, complete (also duplicated wholesale inside /yerba-mate-tour/)
23. /paraguay-culture-tour/ — Paraguay Culture Tour — REAL, complete
24. /jesuit-ruins-tour/ — Jesuit Ruins Tour — REAL, complete
25. /salto-cristal-tour/ — Salto Cristal Tour — REAL (mismatched food-photo image)
26. /itaipu-dam-tour/ — Itaipu Dam Tour — REAL, clean
27. /yerba-mate-tour/ — Yerba Mate Tour — REAL but BUGGED (stacks entire Souvenirs page content beneath it)
28. /san-bernardino-trip/ — San Bernardino Trip — REAL, clean
29. /bird-watching/ — Bird Watching — REAL, one stray FAQ line from Souvenirs page
30. /fishing-charters/ — Fishing Charters — REAL, complete
31. /iguazu-falls-from-asuncion/ — Iguazu Falls from Asuncion — REAL, minor copy-paste bug (Souvenirs FAQ leftover)
32. /paraguay-tour/ — Paraguay Tour — REAL
33. /apartment-hunting/ — Apartment Hunting — REAL
34. /school-placement/ — School Placement — REAL
35. /paraguay-residency-service/ — Paraguay Residency Service — REAL
36. /private-driver/ — Private Driver — REAL
37. /paraguay-real-estate-tour/ — Paraguay Real Estate Tour — REAL, buggy (wrong FAQ + wrong images)
38. /airport-transfer/ — Airport Transfer — REAL, distinct from post /airport-transfer-paraguay/
39. /healthcare-paraguay/ — Healthcare Paraguay — REAL
40. /travel-planner/ — Travel Planner — REAL
41. /paraguay-tourism-guide/ — Paraguay Tourism Guide — REAL PAGE (PDF sales copy, wins over the post at same slug)
42. /anton-single-service-test/ — anton single service test — TEST/JUNK (Lorem Ipsum, exposed personal email, mismatched photos)
43. /rent-car/ — Rent Car — THIN/BROKEN, live+nav-linked, empty body

(Note: source lists 42 "pages" but 43 rows above because /faq/ + /faq2/ are both counted as pages in the master inventory while the site totals state 42 — the duplicate-slug and near-duplicate pages create the discrepancy; treat as approximate per the scan's own count.)

### Taxonomy archives (46)
7 categories: uncategorized, activities, tips, cities, food, nature, living — `/category/<slug>/`
39 tags (capital, city-guide, family-dining, street-food, nightlife, outdoor, day-trip, wonder-of-world, summer, river, villa-florida, monday-falls, salto-cristal, ybycui, hiking, cerrado, wilderness, eco-park, dinosaurs, border-cities, commerce, wetlands, wildlife, adventure-park, zipline, biodiversity, conservation, planning, money, safety, security, relocation, customs, itinerary, overview, logistics, transport, malls, markets) — `/tag/<slug>/`
**All 46 render an identical, unfiltered list of all 33 posts** (byte-diff confirmed) — the archive template's category/tag filter isn't wired up. None have meta descriptions.

### Meta titles/descriptions quality
- Titles: WP default pattern `{Page/Post Title} – thingstodoinparaguay.com`; homepage has no title text at all (bare domain).
- Meta descriptions: **zero across the entire site** (all 121 URLs checked return null for `meta[name="description"]`).
- JSON-LD structured data: **none anywhere** on the site.

## 3. Site structure

- **Main nav** (header, Elementor template id 644): Home | Destinations ▾ (mega-menu, 29 items) | FAQ (→`/faq2/`) | About (→`/about2/`) | Contact | Tours (→`/service/`)
- Destinations mega-menu lists 29 destination/service pages (Airport Transfer, Apartment Hunting, Asuncion City Tour, Bars Asuncion Tour, Bird Watching, Day Trips, Fishing Charters, Food Paraguay Tour, Healthcare Paraguay, Iguazu Falls from Asuncion, Itaipu Dam Tour, Jesuit Ruins Tour, Paraguay Culture Tour, Paraguay Real Estate Tour, Paraguay Residency Service, Paraguay Souvenirs, Paraguay Tourism Guide, Paraguay Tour, Private Driver, Rent Car, Restaurants Asuncion Guide, Salto Cristal Tour, San Bernardino Trip, School Placement, Shopping Asuncion, Tourist Attractions in Paraguay [broken/empty], Travel Planner, Yerba Mate Tour) plus `/destinations/` itself (placeholder demo content).
- **Footer** (Elementor template id 646), 4 columns + bottom bar: (1) Logo column — logo file literally named `matour-logo_1.png` (leftover generic template asset, not the real brand name), "Asunción, Paraguay" text, 4 social icons (Instagram/X/Facebook/YouTube) all with empty hrefs; (2) "Page" column (unedited placeholder heading) — About Us/Services/FAQ/Contact Us, all plain `<span>` text, no hrefs; (3) "Important Link" column (unedited placeholder heading) — Privacy Policy/Career/Blog/Term & Condition, same non-functional issue; (4) "Our Newsletter" — Mailchimp signup widget. Bottom bar: "Things to do in Paraguay" | "Copyright © 2025" (stale year).
- No sidebar widgets found/mentioned.
- Internal linking: nearly all in-content CTAs point only to `/contact/`; homepage tour cards link out to individual tour pages; no other cross-linking pattern noted.
- No hreflang/other languages.
- Contact info (only on `/contact/`, not header/footer): address "Edificio Skytower, Asunción, Paraguay", phone `+595 995 628 862` (plain text, no `tel:`), email `hello@thingstodoinparaguay.com` (plain text, no `mailto:`), Google Map iframe generic-queried to "Asuncion" (not the actual address). No WhatsApp link anywhere on the site.

## 4. Design notes

- No hex colors, font names, or measurable layout specs were captured in this scan — it extracted DOM/text content, not computed CSS. This is a **gap** (see §7).
- Theme: Hello Elementor (Elementor's minimal starter theme) + Elementor Pro Theme Builder (header id 644, footer id 646, archive id 650, single ~652) + jeg-elementor-kit ("JNews Elementor Kit" widget add-on, standalone, not the JNews theme).
- Homepage H1: "Discover. Wander. Be Inspired." / subhead "Paraguay: A Land of Surprises, Culture, and Natural Beauty". Root `/` and `/home/` are two different, competing homepage designs (both real content, not resolved which is canonical).
- Visual language across tour pages: hero banner, card grids for destinations/tours, accordion FAQs, testimonial/quote blocks with named staff (Yanina Alvarez – Photographer, Anton Marklund – Marketing Director).
- Homepage briefly references brand name "Trexplore" ("Let Trexplore Handle Every Part of Your Travel") — a leftover/mismatched brand-name artifact, not the site's actual name.
- Icon set noted: numbered icon PNGs ("17_Ticket", "16_Hotel", "81_Bus", "1_Map", "38_Compass", "52_Lifebuoy") used somewhere on a destinations/practical-info page.
- Many sections use scroll-triggered fade-in animation (`elementor-invisible` class, standard Elementor feature).
- Imagery: mostly stock/Pexels-style photography and Unsplash-style filler photos on placeholder posts (e.g. "woman in Komodo National Park Indonesia", "lone person in Bagan Myanmar") reused generically and irrelevantly; some tour pages have topic-accurate images (Jesuit ruins, Itaipu, San Bernardino, Iguazú); several pages have mismatched images (e.g. food photos on Salto Cristal Tour and Paraguay Real Estate Tour).

## 5. Technical facts

- WordPress 6.9.7, Elementor 4.1.4, Elementor Pro 4.2.2, theme `hello-elementor` (body classes: `wp-theme-hello-elementor`, `elementor-default`, `elementor-kit-532`).
- Plugins identified: jeg-elementor-kit (JNews Elementor Kit widgets: post-block, headings, mailchimp, off-canvas menu, nav-menu), MetForm (installed but its native builder unused — visible "contact form" is a raw iframe to GoHighLevel/leadconnectorhq.com, submissions bypass WordPress entirely).
- Hosting: Hostinger shared/managed (`platform: hostinger`, `panel: hpanel` response headers), LiteSpeed cache (`X-Litespeed-Cache: hit`), PHP 8.2.30, Hostinger `hcdn` edge/CDN.
- No JSON-LD structured data anywhere; no meta descriptions anywhere.
- No redirects/canonical issues found among the 10 sampled internal links (all HTTP 200); a control 404 test confirmed the checking methodology works. However the duplicate-slug conflict at `/paraguay-tourism-guide/` (Page shadows Post) is effectively an unresolved routing conflict.
- Nearly all images site-wide have empty `alt` attributes — confirmed sitewide SEO/accessibility gap (a small number of on-topic photos are the only exceptions, e.g. `cerro-cora.jpg` alt="cerro cora").
- Category/tag archive filtering is broken (see §2) — a functional/technical bug, not just thin content.
- Footer newsletter Mailchimp integration status (live API key or not) is unverified from outside.
- Site-wide client-side bug: a script (source not isolated — suspected jeg-elementor-kit AJAX "instant navigation," related-posts, or carousel misconfiguration) auto-navigates the visible browser tab to a random unrelated post/tour URL 1–3 seconds after page load, reproduced repeatedly across many pages during interactive testing but not via raw `curl` (so it's client-side JS, not a server redirect). Confirmed as a real live-site UX bug independent of content-migration concerns.
- Stale copyright year in footer ("© 2025") despite site content dated into 2026.

## 6. Content formats seen (for a content-block system)

From the 18 uniform "Tour/Service" pages template (this is the reusable pattern):
1. Hero — H1 name, H3 tagline, short intro paragraph, primary CTA button ("BOOK MY X")
2. Problem/Hook section — H3 emotional headline + 2 paragraphs
3. Solution section — H3 + 1–2 paragraphs
4. Itinerary/Breakdown section ("The Journey"/"The Treasures"/"The Process"/"What's Inside") — H3 label + 3–4 H2 sub-items each with a paragraph (core unique content block)
5. "Why Choose This Tour?" — H1 (inconsistent heading level, CMS quirk) + 3 H2/H3 sub-benefits with paragraphs
6. "Practical Information" — bullet-style key facts (Duration, Departure, Transport, Requirements, etc.; labels vary)
7. FAQ — H2 "FAQ" + intro paragraph + 7–9 accordion Q&As (only first expanded in scan; rest collapsed, most answers not captured — see §7)
8. Closing CTA banner — H2 emotional closer + 1–2 paragraphs + final CTA button
9. Shared footer

Other formats observed:
- Blog posts (33): simple template — H1 title, byline (author "Yanina", timestamp), category badge, single H3 body heading, body paragraphs, "Tags:" line, "Share This Post:" row, WP default comment form.
- Homepage: hero, "why choose Paraguay" feature list, service/category cards (Tour & Travel / Adventure / Group Travel / Local Experiences), philosophy/mission/vision/motto block, named staff testimonial quotes, destination tour-card carousel with star-style "Based on 10 reviews" text and bare "$" price placeholders, FAQ accordion.
- Sales/landing page (`/paraguay-tourism-guide/`): product hero, feature bullets ("What's Inside", "Why Buy This Guide"), practical-info spec block (duplicated verbatim in the HTML — a bug), FAQ, closing CTA. Contains one unfinished placeholder artifact: "Pages: 150+ (Example)".
- Google Map iframe embed present on `/contact/` (generic "Asuncion" query, not exact address).
- No tables, no video embeds, and no galleries were reported in the scan.

## 7. Incomplete / could-not-fetch / open questions in the scan

- Collapsed FAQ accordion answers (questions 2–9 on each of the 18 tour pages) were **not fully captured** — only the first Q&A per page was expanded during the scan; recommend a direct WP DB/CMS export or manual click-through.
- No computed CSS/design tokens (colors, fonts, spacing) were extracted — content/SEO extraction only, not a visual/CSS audit.
- Google Analytics / Search Console / real ranking-traffic data was **not captured** — scan is on-page content/structure only.
- Mailchimp API key / newsletter live-status unverified.
- The exact source script causing the auto-navigation bug was not isolated.
- Duplicate-slug resolution at `/paraguay-tourism-guide/` (Page vs. Post) not resolved — whether an orphaned Post still exists in the DB at that slug is unconfirmed from the front end.
- Canonical-homepage decision needed: `/` vs `/home/` — both real, different content; scan author leans toward `/` (root) as more complete/polished but this wasn't resolved.
- `/about/` vs `/about2/`: `/about2/` confirmed richer (has named team bios); not resolved which is canonical.
- `/faq/` vs `/faq2/`: byte-identical; just needs one slug picked.
- `/service/` (singular, actually "Tours") vs `/services/` (plural, actually relocation "Services") naming is confusing in the source site itself.
- RSS/Atom feeds were not explicitly checked/listed in the scan (only the 6 XML sitemaps are documented).
- No feed URLs, no explicit list of image-asset URLs beyond the ones cited inline per page.

## 8. Current git repo state

- Repo `/home/user/thingstodoinparaguay` is on branch `claude/wordpress-to-html-php-migration-yqi5ze` with **zero commits** (`git log` reports "does not have any commits yet").
- `ls -la` shows only `.git/` — the working tree is completely empty; no README, no PLAN file, no source files of any kind exist yet.
