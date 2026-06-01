# ole-api-dev

Development space for the Open Legal Education Project as the team experiments with lesson API integrations and simple viewers.

## Repository files

- `README.md` — This project overview and file-by-file reference.
- `index.html` — Minimal static HTML test page used to confirm the site is serving content.
- `lessons.php` — Basic PHP script that calls the legacy Drupal 9 lessons API endpoint, dumps the returned JSON, and prints lesson IDs and titles.
- `lessoncards.php` — Bootstrap-based PHP prototype that fetches lesson data from the Drupal 9 HAL endpoint and renders each lesson as a card with a launch link.
- `lessoncardsv2.php` — Second Drupal 9 card-view prototype that uses the version 2 lessons endpoint and displays additional lesson metadata such as author, topics, and completion time.
- `lessoncardsD10.php` — Drupal 10 JSON:API version of the lesson card viewer that filters for `CALI Author` lessons and renders lesson titles and bodies in a cleaner standalone layout.
- `lessons-d10-api.php` — Small Drupal 10 JSON:API test script that fetches lesson records and prints a simple array of lesson titles.
- `lessons_viewer.php` — Main Drupal 10 lesson directory/viewer prototype. It lists lessons and supports a `view_xml` mode for loading and displaying a lesson's XML source document.

## Notes

Most files in this repository are small experiments or prototypes for comparing Drupal 9 and Drupal 10 lesson API responses and display approaches.
