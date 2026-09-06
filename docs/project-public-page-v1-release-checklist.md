# Project Public Page v1 Release Checklist

Use this checklist before calling the feature `production-ready`.

## Release gates

### 1. Functional Gate
- `php artisan migrate` succeeds
- `php artisan storage:link` exists and public assets are reachable
- `php artisan db:seed --class=ProjectPublicPageDemoSeeder` succeeds
- admin can open `projects/public-page/wizard/create`
- draft save works
- hero image upload works
- floor plan image upload works
- details PDF upload works
- publish works for a valid project
- preview page opens
- share link opens
- share link revoke/reactivate works
- expiry and max-visits behavior works
- edit and republish works

### 2. UX Gate
- wizard works on phone width
- public share page works on phone width
- publish errors are readable
- missing asset states are readable
- invalid or expired link page is graceful
- no Phase 2 placeholders are visible in v1 UI

### 3. Safety Gate
- existing project create/edit/delete flow still works
- existing project show page still works
- pricing config reads are unaffected
- old project collateral behavior still works
- replacing assets does not leave broken references
- broken uploads do not leave partial visible records

### 4. Test Gate
- `php artisan test tests/Feature/ProjectPublicSharePageTest.php` passes
- publish validation tests pass
- share-link lifecycle tests pass
- preview and published rendering parity test passes

## Scripted localhost acceptance

Run this in order:

```powershell
php artisan migrate
php artisan storage:link
php artisan db:seed --class=ProjectPublicPageDemoSeeder
php artisan serve
```

Then validate this fixed scenario:

1. Login as admin or CRM user.
2. Open `/projects/public-page/wizard/create`.
3. Create a new draft using an existing builder.
4. Upload:
   - hero image
   - one floor plan image
   - one details PDF
5. Add at least:
   - 2 unit types
   - 2 visible size variants
   - 1 brochure or price sheet
6. Save draft.
7. Open preview and verify:
   - hero content
   - unit tabs
   - plan pricing
   - PDF links
   - CTA visibility
8. Publish the page.
9. Open the generated share link.
10. Verify:
    - page loads
    - CTA clicks work
    - PDF download works
    - media links log before redirect
11. Set share link to `revoked` and verify unavailable page.
12. Reactivate the link and verify it works again.
13. Set `max_visits=1`, open once, refresh again, and verify expiry behavior.
14. Edit the project, change one variant price, republish, and confirm preview/live parity.

## Real-content UAT

Before live rollout, test one real-ish project with:
- multiple unit types
- long intro text
- some optional fields intentionally missing
- mixed external and uploaded assets
- mobile testing

## Ready to hand over for testing

The feature can be handed to QA/local testing when:
- all Functional Gate checks pass
- all Test Gate checks pass
- no blocker remains in Safety Gate
