# Publishing on thingstodoinparaguay.com

Everything on the site can be written, edited and published from
**https://thingstodoinparaguay.com/admin/** — no code, no FTP, no developer.

This guide is written for whoever is publishing, not for a programmer.

---

## Signing in

Go to `/admin/` and sign in with your email address and password.

- Five wrong passwords in a row lock the sign-in for fifteen minutes. That is
  deliberate — it is what stops someone guessing their way in.
- Forgotten the password? It cannot be emailed to you. Someone with server access
  runs `php bin/create-admin.php` with your email address and sets a new one
  (see **For whoever runs the server** at the end).
- You are signed out automatically after two hours of doing nothing, and after
  twelve hours in any case.

---

## Publish a post in five steps

The editor is numbered 1 to 5 in the order you should work through it. Open
**Content → New post**.

### 1. Title and address

Type the title. The **address** underneath fills itself in — that is the web
address the post will live at, for example `/salto-cristal-in-one-day/`.

Change the address now if you want to; it is much cheaper to get it right before
publishing than after. Once the post is live the editor warns you, because
renaming a live page means anyone with the old link has to be redirected.
(The site handles that for you — see *Renaming a page that is already live*.)

### 2. Write the article

Write in the big box. The bar above it inserts the formatting for you:

| Button | What it does |
|---|---|
| **H2** | A subheading. Every post needs at least two. |
| **H3** | A smaller subheading inside a section. |
| **B** / **I** | Bold and italic. |
| **Link** | Asks for the address, then links the selected words. |
| **List** | Turns the lines into bullet points. |
| **Quote** | Indents a quotation. |
| **Preview** | Shows the article the way a visitor will see it. |

You can also type the formatting directly — it is Markdown:

```
## A subheading

A paragraph with **bold words**, a [link to a tour](/salto-cristal-tour/)
and a [link to somewhere else](https://example.com/).

- a bullet
- another bullet
```

Anything that looks like HTML is shown as plain text rather than run, so pasting
from another site cannot break the page.

### 3. Choose a cover image

On the right, pick an image from the library. Nothing there yet? Click
**Upload a new image**, choose the file, **describe it**, and you are brought
straight back here.

The description ("alt text") is required. It is what a blind visitor hears and
what Google reads, so write what is actually in the picture — *"Salto Cristal
waterfall seen from the pool below"*, not *"waterfall"* or *"IMG_2043"*.

Upload the largest version you have. The site makes 400, 800 and 1600 pixel
copies in two formats and serves whichever fits the visitor's screen, so a big
upload does not mean a slow page. Images are never enlarged.

### 4. Set the focus keyword and check the score

Fill in the **focus keyword** — the phrase you want this page to be found for,
written the way a visitor would type it into Google: `salto cristal tour`, not
`waterfalls`.

The **SEO score** on the right updates as you type. Work down its list until the
red lines are gone. Each one tells you exactly what to change:

| Check | What it wants |
|---|---|
| Focus keyword in the title, address, opening and a subheading | The phrase used naturally in all four places |
| Title 30–60 characters | Long enough to be descriptive, short enough not to be cut off |
| Description 70–155 characters | The sentence under the title in Google's results |
| At least 600 words (posts) | Enough to actually answer the question |
| At least two subheadings | Something a reader can skim |
| No placeholder text | No leftover Lorem Ipsum |
| At least two internal links | Two links to other pages of ours |
| At least one external link | One link to a source worth citing |
| Every image has alt text | Every picture described |
| Cover image set | Step 3 |

**80 or more is the bar for publishing.** 100 is nice, not required — never pad
the article or repeat the keyword awkwardly just to reach it.

### 5. Publish

On the right, set **Status**:

- **draft** — nobody but you can see it. Use **Preview** to look at it.
- **published** — live immediately.
- **scheduled** — set a future date and time (UTC) and it goes live by itself.

Click **Save**. That is it: the page is live, and the sitemap, the blog index
and the RSS feed all update themselves.

---

## The other things you can do

### Tours and services

**Content → New tour** (or service) gives the same editor plus the structured
parts of a tour page: the hook, what you offer, the itinerary, the reasons to
book, the practical facts and the FAQ. Each of those is a list of rows — click
**Add another** for one more, **Remove** to drop one. Rows left blank disappear
when you save.

Leave the price empty if you do not have a firm one. The page then says "Ask for
a quote" instead. **Never invent a price, an opening time or a phone number.**

### Renaming a page that is already live

Change the address and save. The site immediately files a permanent redirect
from the old address to the new one, so every existing link and every Google
result keeps working. Rename a second time and the oldest address is updated to
point at the newest page — no chains.

The same happens if you delete a published page: its address redirects to the
matching index rather than dying.

### Images

**Media** lists everything uploaded, how many pages use each one, and lets you
fix the description or delete an image with all its sizes. An image still in use
on a page can be deleted, but the page then has no cover — check the "used on"
count first.

### Categories, tags and redirects

- **Categories** are the blog's sections. Renaming one redirects its old archive
  address automatically.
- **Tags** are stored on posts but have no pages of their own yet.
- **Redirects** lists every old address and where it now goes. Rows marked
  `map` came from the old WordPress site and hold the site's URL promises
  together — **do not delete those** unless you know exactly why. Rows marked
  `slug-change` the site created for you.

### Settings

Site name, tagline, address, phone, email, the WhatsApp number and the Google
Analytics ID. Saving clears the page cache, so changes show up straight away.

### Backups

**Settings → Download backup** gives you a zip of every post, page, tour,
service and category as text files, plus a list of the images. Take one before
any big change. The uploaded image files are not in the zip — those are copied
separately from the server.

### Leads and subscribers

Enquiries from the contact form and newsletter sign-ups, with a CSV download of
each. (Both forms arrive with the new design in the next phase; the lists are
here and working already.)

---

## If something looks wrong

**A change is not showing on the site.** The site keeps finished pages in a
cache for speed. Publishing clears whatever it needs to, but if something looks
stale, **Dashboard → Clear the page cache**.

**"That form expired before it was submitted."** You left the page open too long
or signed out in another tab. Open the page again and redo the change — nothing
was saved.

**"Another item already uses /that-address/."** Two pages cannot share an
address. Pick a different one, or find the other page under **Content** and
decide which should keep it.

**An upload is refused.** Only JPEG, PNG, WebP and GIF, up to 12 MB. A file
renamed to `.jpg` is not a JPEG — the site checks what the file actually is.

---

## For whoever runs the server

Run these from the project directory over SSH.

```sh
php bin/create-admin.php                 # create an account, or reset a password
php bin/create-admin.php --list          # who has an account
php bin/migrate.php                      # apply the database schema
php bin/seed.php                         # import content/ (never touches admin edits)
php bin/export.php                       # write the database back out to content/
php bin/seo-audit.php --details          # the SEO score for every published page
php bin/cache-clear.php                  # empty the page cache
php bin/publish-due.php                  # publish anything whose scheduled time has passed
```

**Scheduled posts need a cron entry.** Without one they only go live the next
time someone opens the admin panel. In hPanel → Advanced → Cron Jobs, every
15 minutes:

```
/usr/bin/php /home/USER/domains/DOMAIN/bin/publish-due.php --quiet
```

**Permissions.** `data/` (the database) and `cache/` must be writable by PHP, and
so must `public/media/` for uploads.

**Content edited in the panel is never overwritten by `bin/seed.php`** — those
rows are marked `source = 'admin'`. It lives only in the database, so the
backup, or a scheduled `bin/export.php`, is what puts it into git.
