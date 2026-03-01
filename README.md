# Moodoo Marketing Website

Static marketing site for Moodoo with semantic HTML, SCSS architecture, and jQuery-based interactions.

## Current Pages

- `index.html`: landing page structure (desktop + mobile responsive)
- `privacy-policy.html`: privacy policy page with table-of-contents sidebar
- `contact.html`: temporary placeholder page

## Stack

- HTML5 (semantic + accessibility-first)
- SCSS (token/component/page layer architecture)
- JavaScript (jQuery 4)
- npm scripts for CSS build and vendor sync

## Folder Structure

```text
moodoo-marketing-website/
├── assets/
│   ├── css/
│   │   └── styles.css                 # compiled from src/scss/main.scss
│   ├── js/
│   │   └── main.js                    # nav, modal, accordion, carousel, forms
│   └── vendor/
│       └── jquery/
│           └── jquery.min.js
├── scripts/
│   └── sync-jquery.js
├── src/
│   └── scss/
│       ├── _tokens.scss
│       ├── _mixins.scss
│       ├── _base.scss
│       ├── _layout.scss
│       ├── _components.scss
│       ├── _pages.scss
│       └── main.scss
├── index.html
├── privacy-policy.html
├── contact.html
├── package.json
└── package-lock.json
```

## Scripts

- `npm run build`: compile SCSS + sync vendor jQuery
- `npm run build:css`: compile `src/scss/main.scss` to `assets/css/styles.css`
- `npm run watch:css`: watch mode for SCSS development
- `npm run sync:jquery`: copy jQuery from `node_modules` to `assets/vendor`
- `npm run serve`: local static server at `http://localhost:8080`

## Quick Start

1. `npm install`
2. `npm run build`
3. `npm run serve`

## Notes

- Visual palette intentionally uses grayscale tokens only for structure-first development.
- Theme styling can be introduced later by updating `src/scss/_tokens.scss` and component styles.
