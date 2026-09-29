# Design brief: thingstodoinparaguay.com

Design a set of artboards for **thingstodoinparaguay.com**, a tourism/activities discovery
site for Paraguay. Produce three artboards on one canvas: **Homepage**, **Tour/Activity
Listing Page**, and **Blog Post Page**.

## Business summary
thingstodoinparaguay.com helps travelers figure out what to actually do once they're in
Paraguay: guided tours, restaurants, bars, shopping, souvenirs, and city guides (Asunción and
beyond). It's a content/discovery site, not a booking engine — each tour/activity/service page
sells the reader on an experience and points them to contact/WhatsApp-style lead capture. It
also runs a blog of practical travel guides (best bars in Asunción, where to eat, what to buy,
etc.).

## Target audience
- Foreign tourists researching a Paraguay trip before/during arrival (English-reading,
  independent travelers, some cruise/stopover visitors).
- Expats and long-stay foreigners in Asunción looking for things to do on weekends.
- Secondary: locals looking for date-night/weekend ideas.

Assume mobile-heavy traffic (in-trip lookups on phones) plus desktop research browsing.

## Tone / voice
Fun, warm, and helpful — like a well-traveled local friend giving you the inside scoop, not a
dry municipal tourism directory or a sterile OTA listing page. Confident, a little playful,
never stuffy. Visually this should read as a **vibrant travel blog**, not a government tourism
portal or a generic Bootstrap directory template.

## Pages to design

### 1. Homepage
Must include:
- **Hero** — a bold, welcoming intro ("things to do in Paraguay") with a clear sense of place,
  a primary CTA (e.g. "Explore tours" / "Plan your trip"), and secondary CTA into the blog.
- **Category browsing** — a prominent way to jump into the core verticals: Tours, Food &
  Restaurants, Bars & Nightlife, Shopping & Souvenirs, City Guides. Each category should have
  its own identity (color + icon), since these map directly to the site's real content
  categories.
- **Tour/activity card grid** — a curated set of featured cards pulling from real tours
  already published, e.g. Asunción City Tour, Bars of Asunción, Food of Paraguay tour,
  Restaurants of Asunción guide, Shopping in Asunción, Paraguay Souvenirs, Yerba Mate Tour.
- **Blog/news teaser** — a small strip or grid teasing 3–4 recent guide posts.
- **Contact / newsletter section** — a simple contact/quote block plus a lightweight
  newsletter signup (email field + submit), matching a real anti-spam-protected form
  (no need to design the spam protection, just the visible field + button).
- Footer with basic nav, categories, contact, and social links.

### 2. Tour/Activity Listing Page
A grid/list page for browsing all tours & activities (or one category, e.g. "Tours"):
- Page header with category name, a one-line description, and a category-colored accent.
- Filter/sort affordance if it fits naturally (by category or "popular") — optional, keep
  simple, no complex UI controls.
- A responsive card grid of tour/activity items (title, short excerpt, category eyebrow,
  reading-time-style meta is not needed here but a "view tour" affordance is).
- Pagination or "load more" pattern at the bottom.
- Same header/footer chrome as the homepage.

### 3. Blog Post Page
A single article page:
- Title, category eyebrow, published date, estimated reading time.
- A prominent header/cover treatment (see placeholder strategy below — this is a placeholder
  cover, not a real photo).
- Readable article body with a couple of subheadings, a pull quote or callout box, and an
  inline "related tours" or "you might also like" card strip using the same card component
  as the homepage grid.
- A newsletter or contact CTA block near the end.
- Same header/footer chrome as the homepage.

## Critical constraint: NO real photography exists yet
This is the most important design decision. The live PHP site currently renders **every**
card and cover as a CSS placeholder panel: a type-tinted gradient background with a single
centered line-art SVG icon (a distinct icon per content type: tour, service/activity, post,
page), no photo. `og:image` also falls back to a generated placeholder. Real photography is
planned for a later phase but does not exist today, and this design must work perfectly with
zero photos — indefinitely, if needed.

Because of this, **do not design around empty gray boxes or broken-image aesthetics.** Instead
specify a deliberate, polished illustrated/graphic placeholder system that looks intentional,
e.g.:
- A confident **color-coded category system**: each vertical (Tours, Food, Bars, Shopping,
  Souvenirs, City Guides) gets its own accent color and a simple, bold line icon or flat
  illustration motif (a compass/route icon for tours, a fork-and-plate for food, a cocktail
  glass for bars, a shopping bag for shopping, a mate gourd for souvenirs/yerba mate, a map
  pin for city guides).
- Cover/card treatments built from **gradient fields + icon or simple geometric/pattern
  illustration** (e.g. a subtle repeating line pattern, abstract shapes, or a bold single
  icon centered in a gradient panel) rather than flat single-color blocks — something that
  reads as "designed graphic," not "missing image."
- Category badges/eyebrows using the same color system so users get visual orientation before
  content ever needs a photo (a small pill/tag on each card, colored per category).
- Keep the icon-in-gradient-panel language consistent across hero, cards, and blog cover so
  the whole placeholder system reads as one cohesive design language, and so it can degrade
  gracefully once real photos are dropped in later without a full re-skin.
- Typography and color should carry most of the "vibrant travel" feeling since imagery can't:
  lean into a warm, saturated palette (think Paraguayan sun, yerba mate green, terracotta,
  river blue) and confident display type for headings.

## Style direction
- Vibrant, warm, editorial travel-blog aesthetic — think a boutique travel magazine, not a
  corporate tourism board or an OTA.
- Rounded, friendly card shapes; generous imagery-shaped areas even though they hold
  illustration/gradient placeholders, not photos.
- A clear, distinct color per content category (see above) used consistently for badges,
  card accents, and category page headers.
- Bold, readable display typography for headings; a simple, highly legible body font.
- Iconography should feel hand-picked and consistent (single icon style/weight throughout),
  not mixed emoji or stock-icon-pack chaos.

## Technical / structural constraints
- **Mobile-first.** Most tourist lookups happen on a phone; design the homepage, listing, and
  blog post to work cleanly at phone width first, then scale up.
- Must map cleanly onto the existing card component structure already built in the codebase:
  each card is `cover area` (image OR placeholder gradient+icon) + `category eyebrow` +
  `title` + `excerpt` + optional `meta line` (date · category · reading time). Design within
  that shape rather than inventing an incompatible card layout.
- Four content types exist (tour, service/activity, post, page) and each already has its own
  icon — keep that type distinction visible in the design system alongside the category color
  system.
- **No heavy JS framework** — this is a plain PHP/HTML/CSS site with no build step and no
  SPA framework. Keep interactions to things achievable with vanilla CSS/light vanilla JS
  (hover states, simple accordions, a basic filter toggle) — no assumption of React/Vue-style
  component interactivity.
- Contact and newsletter forms are simple (email/name/message fields + submit button) — no
  need to visually represent spam-protection mechanics, just clean, trustworthy form UI.
- Keep the whole system reusable across the site's 62 published items and multiple categories,
  not bespoke per page.

## Deliverable
Three artboards (Homepage, Tour/Activity Listing, Blog Post) that clearly demonstrate:
1. The overall vibrant travel-guide visual identity.
2. The full color-coded category + icon/gradient placeholder system, shown consistently
   across hero, cards, and blog cover.
3. Mobile-first layouts that would translate cleanly onto the existing PHP card/tour
   templates.
