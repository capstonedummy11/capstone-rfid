# Reusable Landing Pages

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

These files are intended to be stashed and moved to another branch:

- `resources/js/pages/ReusableLandingIndex.vue`
- `resources/js/pages/ReusableAboutPage.vue`

Add these routes in `routes/web.php` on the target branch:

```php
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'ReusableLandingIndex')->name('landingPage');
Route::inertia('/about', 'ReusableAboutPage')->name('about');
```

If you do not want to replace the existing root page, use separate paths instead:

```php
Route::inertia('/landing', 'ReusableLandingIndex')->name('reusableLanding');
Route::inertia('/landing/about', 'ReusableAboutPage')->name('reusableAbout');
```

The public reusable navigation uses these default paths:

- Home: `/`
- About Us: `/about`

If the target branch uses different URLs, update the path constants near the bottom of each Vue page.

Do not add login, staff login, or attendance panel buttons to public Student/Parent navigation. Keep panel access in authenticated staff/admin navigation only, and place Student/Parent login inside the landing-page content.
