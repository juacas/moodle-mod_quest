# Questournament website

This is a standalone static site. Its English pages are at the root and its Spanish pages are in `es/`. It uses local `site.css`, `site.js`, fonts and images; no Mobirise, Bootstrap, jQuery or build step is required. Open `index.html` locally or serve this directory with any static web server.

The structure follows the original project site: Home, Create, Usage and About, with additional Install and Teaching evidence pages (`evidence.html` and `es/evidence.html`). The evidence pages expand the original “QUESTOURnament basis” with its three academic references and explain which findings concern the older QUEST tool or particular courses. The guides describe the current Questournament release, including question-bank challenge composition, mixed grading, scheduling and related improvements. These features are not independently validated by the cited studies.

Publishing is separate from the Moodle plugin package. Review links and screenshots against the current release before copying this directory to a static host. Moodle plugin archives exclude `website/` via `.gitattributes`.

## Design previews

`previews/index.html` compares three Spanish hybrid designs: Editorial, Studio and Atlas. Each has a visual home page and an interior guide page. They reuse the site's screenshots and the supplied background and institutional logos, while the other navigation links lead to the current Spanish site. The previews are isolated from the existing website and are not a complete replacement or a separate release. To view them locally, serve this directory with a static web server and open `/previews/index.html`.
