<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

### CRITICAL: Follow Official Documentation Explicitly
- **ALL Laravel and Laravel ecosystem package documentation is available at laravel.com**
- You MUST follow the official documentation step-by-step when implementing features
- NEVER skip steps or assume you know better than the docs
- When installing packages (Spark, Scout, Sanctum, etc.), follow the installation guide exactly:
    1. Install via composer
    2. Publish configuration/migrations if instructed
    3. Run migrations immediately
    4. Configure as documented
- If you're unsure about implementation, use the `search-docs` tool to get version-specific guidance
- The documentation at laravel.com is the source of truth - not general knowledge or assumptions

- php - 8.4.1
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- laravel/spark-stripe (SPARK) - v5
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval
- Do not change the application's dependencies without approval

### Hosting Environment
- This application is hosted on **Laravel Cloud** with three environments:
    - **Production** - Connected to `main` branch (auto-deploy)
    - **Staging** - Connected to `develop` branch (auto-deploy)
    - **Local** - Development environment on your machine
- Deployments are automatic when pushing to `main` or `develop`
- Database is PostgreSQL (Laravel Cloud managed)
- Queue workers and scheduled tasks run on Laravel Cloud

### Controller & Service Architecture
- **Controllers should be thin** - Only handle HTTP concerns (request/response)
- **Business logic belongs in Services** - Create service classes in `app/Services/`
- **Route closures are acceptable for simple pages** - But extract to controllers as complexity grows
- Pattern: Route → Controller → Service → Model

**Example Structure:**
```
routes/web.php         → Defines routes
Controller             → Validates request, calls service, returns response
Service                → Contains business logic, orchestrates operations
Model/Job/Provider     → Data access, background jobs, external APIs
```

**Good Example:**
```php
// Route
Route::post('images/upload', [ImageController::class, 'upload']);

// Controller - thin, delegates to service
public function upload(ImageUploadRequest $request): JsonResponse
{
    $result = $this->imageService->uploadImages($request->validated());
    return response()->json($result);
}

// Service - contains business logic
public function uploadImages(array $data): array
{
    // Validation, processing, coordination
}
```

**Bad Example:**
```php
// Route with business logic (avoid for complex operations)
Route::post('images', function (Request $request) {
    // 50 lines of business logic here...
});
```

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (vite.config.js).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use `search-docs` for accurate guidance on all things Inertia.

<code-snippet lang="php" name="Inertia::render Example">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>


=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 & v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Polling
- Prefetching
- Deferred props
- Infinite scrolling using merging props and `WhenVisible`
- Lazy loading data on scroll

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing / animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use `search-docs` with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use `search-docs` with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use `search-docs` with a query of 'form component resetting' for guidance.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Package Installation Best Practices
When installing Laravel packages (Spark, Scout, Sanctum, Horizon, etc.), ALWAYS follow this exact sequence:

1. **Read the official docs first** - Use `search-docs` tool to get version-specific installation guide
2. **Install via Composer**:
   ```bash
   composer require laravel/package-name
   ```
3. **Publish assets if instructed** (migrations, config, views):
   ```bash
   php artisan vendor:publish --tag="package-migrations"
   php artisan vendor:publish --tag="package-config"
   ```
4. **Run migrations IMMEDIATELY** - Do not skip this step:
   ```bash
   php artisan migrate
   ```
5. **Configure in .env and config files** - Follow docs for required environment variables
6. **Add traits/interfaces to models** - As instructed by docs (e.g., `Billable` for Spark)
7. **Test the integration** - Write a simple test to verify it works

**CRITICAL: Do not skip steps or do them out of order. This causes issues like:**
- Migrations not run → Database tables missing → Features don't work
- Config not published → Package uses defaults that don't match your setup
- Traits not added → Model methods missing

**Example: Installing Spark (Correct Way)**
```bash
# 1. Read docs
# search-docs(['spark installation'])

# 2. Install
composer require laravel/spark-stripe

# 3. Install Spark (publishes assets, migrations, etc.)
php artisan spark:install

# 4. Run migrations IMMEDIATELY
php artisan migrate

# 5. Add trait to User model
use Laravel\Spark\Billable;

# 6. Configure in .env
STRIPE_KEY=your-key
STRIPE_SECRET=your-secret
SPARK_PADDLE_VENDOR=your-vendor-id  # if using Paddle

# 7. Configure billing plans in config/spark.php

# 8. Test it works
php artisan tinker --execute="User::first()->subscription()"
```

### Laravel Spark Specifics
- Spark is a first-party Laravel billing portal that wraps Cashier (Stripe or Paddle)
- Provides pre-built UI components for subscriptions, team billing, invoices, payment methods
- Billing portal routes are automatically registered at `/billing`
- Use `Billable` trait on User model (or Team model for team billing)
- Configure plans, features, and pricing in `config/spark.php`
- Spark handles webhook registration and processing automatically
- For custom billing logic, extend Spark's controllers or use Cashier methods directly

### Laravel Sanctum Specifics
- Sanctum provides API token authentication for SPAs and mobile applications
- **Sanctum does NOT provide built-in UI or controllers** - you must create your own token management interface
- Token creation: `$user->createToken('token-name')` returns `$token->plainTextToken` (only available once)
- Token retrieval: `$user->tokens` to list all tokens
- Token revocation: `$user->tokens()->where('id', $id)->delete()` or `$token->delete()`
- Token abilities/scopes: `$user->createToken('name', ['ability1', 'ability2'])`
- Middleware: Use `auth:sanctum` to protect API routes
- The plain-text token is only available immediately after creation - must be shown to user once and copied
- Tokens are stored hashed in `personal_access_tokens` table
- For UI implementation: Build custom controllers/pages to create, list, and revoke tokens

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- Every change must be programmatically tested - write or update tests before finalizing
- Use factories when creating models for tests - check for custom states before manual setup
- Use `php artisan make:test --pest <name>` for feature tests, `--unit` for unit tests
- Most tests should be feature tests

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- Custom middleware CAN be created in `app/Http/Middleware/` and registered in `bootstrap/app.php`
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files
- `bootstrap/providers.php` contains application specific service providers
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing Philosophy
- If you need to verify a feature is working, write or update a Unit / Feature test.
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
  <code-snippet name="Basic Pest Test Example" lang="php">
  it('is true', function () {
  expect(true)->toBeTrue();
  });
  </code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.
- **CRITICAL: Run tests in the background to save context tokens** - Always use `run_in_background: true` parameter when running tests via Bash tool. This prevents test output from consuming large amounts of context. Check results with `BashOutput` tool using `filter` parameter to show only "PASS" or "FAIL" lines. Example: `BashOutput(bash_id: "abc123", filter: "PASS|FAIL|Tests:")`

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
  <code-snippet name="Pest Example Asserting postJson Response" lang="php">
  it('returns all', function () {
  $response = $this->postJson('/api/docs', []);

  $response->assertSuccessful();
  });
  </code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>


=== inertia-react/core rules ===

## Inertia + React

- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="react">

import { Link } from '@inertiajs/react'
<Link href="/">Home</Link>

</code-snippet>


=== inertia-react/v2/forms rules ===

## Inertia + React Forms

<code-snippet name="`<Form>` Component Example" lang="react">

import { Form } from '@inertiajs/react'

export default () => (
<Form action="/users" method="post">
{({
errors,
hasErrors,
processing,
wasSuccessful,
recentlySuccessful,
clearErrors,
resetAndClearErrors,
defaults
}) => (
<>
<input type="text" name="name" />

        {errors.name && <div>{errors.name}</div>}

        <button type="submit" disabled={processing}>
            {processing ? 'Creating...' : 'Create User'}
        </button>

        {wasSuccessful && <div>User created successfully!</div>}
        </>
    )}
    </Form>
)

</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== git-workflow rules ===

## Git Workflow & Branch Strategy

### Environments
This project uses a three-environment setup:
1. **Local** - Development on your machine
2. **Staging** - Laravel Cloud environment linked to `develop` branch (auto-deploy)
3. **Production** - Laravel Cloud environment linked to `main` branch (auto-deploy)

### Branch Structure
- `main` - Production-ready code only (protected, stable)
- `develop` - Staging environment, integration branch for completed features
- `feature/*` - Individual feature development (e.g., `feature/spark-subscriptions`, `feature/image-tagging`)
- `hotfix/*` - Emergency production fixes only

### Automated Feature Development Workflow

**CRITICAL: This project uses GitHub Actions to automatically merge feature branches.**
**Claude should ONLY push feature branches - NEVER manually merge to `develop`.**

**1. Start New Feature**
```bash
git checkout develop
git pull origin develop  # Always pull latest before branching
git checkout -b feature/feature-name
```

**2. Work on Feature Locally**
- Make commits with clear, descriptive messages
- Commit frequently with logical, atomic changes
- Run quality checks before each commit (Pint, linting, tests)

**3. Before Pushing Feature Branch**
CRITICAL: You MUST complete ALL these steps locally:

a. **Check if behind develop**:
```bash
git fetch origin
git status  # Shows if behind origin/develop
```

b. **If behind, pull latest develop**:
```bash
git pull origin develop  # Merge latest develop into your feature branch
# Resolve any conflicts if they occur
```

c. **Run all quality checks locally**:
```bash
vendor/bin/pint          # PHP code style
npm run lint             # Frontend linting
php artisan test         # All tests must pass
npm run build            # Ensure build works
```

**4. Push Feature Branch (STOP HERE - Do Not Merge!)**
```bash
git push origin feature/feature-name
```

**IMPORTANT: After pushing, Claude's job is DONE. Do NOT:**
- ❌ `git checkout develop`
- ❌ `git merge feature/feature-name`
- ❌ `git push origin develop`

**GitHub Actions will handle the merge automatically. Just push and wait.**

**5. Automated Merge Process (Handled by GitHub Actions)**
Once you push, GitHub Actions automatically:
- ✅ Runs linter workflow (Pint, Prettier, ESLint)
- ✅ Runs tests workflow (full Pest test suite)
- ✅ **Checks branch is up-to-date** with develop (prevents merge race conditions)
- ✅ **If all checks pass**: Auto-merges to `develop` and deploys to staging
- ✅ **Deletes the feature branch** after successful merge
- ❌ **If workflows fail**: No merge happens, fix issues and push again
- ❌ **If branch is behind develop**: Must update branch and re-test

**You don't need to manually merge - GitHub Actions handles it!**

**Merge Race Condition Protection:**
If another branch merges to develop while your tests are running:
- Auto-merge will detect your branch is out-of-date
- You'll get a notification to update your branch:
  ```bash
  git checkout feature/your-feature
  git pull origin develop
  git push origin feature/your-feature
  ```
- Tests will re-run with the latest develop code
- Auto-merge will succeed if tests still pass

**6. Monitor Automated Merge**
Watch the GitHub Actions tab to see:
- When tests complete
- When auto-merge happens
- Any merge conflicts or failures

Check your GitHub notifications or the Actions tab:
```bash
gh run list --branch feature/your-feature --limit 3
```

**7. Test on Staging**
- After auto-merge, staging automatically deploys
- Test the feature on staging environment
- Verify logs on Laravel Cloud staging
- Test with actual Stripe in test mode, real S3, etc.

**8. Deploy to Production** (only after staging verification)
```bash
git checkout main
git pull origin main
git merge develop
git push origin main  # Auto-deploys to production
```

**9. Clean Up**
Feature branch is automatically deleted after merge. Just update your local:
```bash
git checkout develop
git pull origin develop
git branch -d feature/feature-name  # Delete local branch
```

### Critical Rules for Claude

**❌ NEVER DO THESE:**
- **NEVER commit directly to `main` or `develop`** - Always use feature branches
- **NEVER manually merge feature branches to `develop`** - This is the most common mistake!
- **NEVER push directly to `develop`** - Auto-merge handles this after tests pass
- **NEVER run `git merge` commands** - GitHub Actions does the merging

**✅ ALWAYS DO THESE:**
- **ALWAYS push feature branches and stop** - Let GitHub Actions handle the merge
- **ALWAYS run quality checks before pushing** - Pint, lint, tests, build locally first
- **ALWAYS pull latest `develop` before creating feature branch** - Prevents merge conflicts
- **ALWAYS check if behind before pushing** - Run `git fetch && git status` to check
- **ALWAYS test on staging before production** - Use staging to catch issues

**ℹ️ Auto-merge Process:**
- Auto-merge will reject out-of-date branches - Update and re-test if another branch merges first
- Feature branches are automatically deleted after successful merge
- Develop automatically deploys to staging after merge

### When Behind Develop
If `git status` shows "Your branch is behind 'origin/develop'":
```bash
git pull origin develop  # Incorporates latest changes
# If conflicts occur, resolve them, then:
git add .
git commit -m "Resolve merge conflicts with develop"
```

### Standard Practice: Pulling Before Push
YES, checking if behind and pulling is standard practice:
- Prevents conflicts and failed pushes
- Ensures your feature works with latest code
- Required workflow: `git fetch` → check status → `git pull` if behind → test → push

### Commit Message Standards
Use clear, descriptive commit messages:
- ✅ "Add Spark subscription checkout flow"
- ✅ "Fix N+1 query in image listing endpoint"
- ✅ "Update subscription status checking logic"
- ❌ "fix stuff"
- ❌ "changes"
- ❌ "wip"

### Example Complete Workflow with Auto-Merge
```bash
# Starting new feature
git checkout develop
git pull origin develop
git checkout -b feature/add-webhooks

# ... work on feature, make commits ...
git add .
git commit -m "Add webhook delivery system with HMAC signatures"

# ... more work ...
git commit -m "Add tests for webhook signature validation"

# Ready to push - check if behind
git fetch origin
git status  # If behind...
git pull origin develop

# Run quality checks locally
vendor/bin/pint
npm run lint
php artisan test
npm run build

# All local checks pass! Push feature branch
git push origin feature/add-webhooks

# GitHub Actions automatically:
# 1. Runs linter and tests workflows
# 2. Checks branch is up-to-date with develop
# 3. Auto-merges to develop if all pass
# 4. Deletes feature/add-webhooks
# 5. Deploys to staging

# Monitor progress (optional)
gh run list --branch feature/add-webhooks --limit 3

# Update local after auto-merge
git checkout develop
git pull origin develop
git branch -d feature/add-webhooks  # Clean up local

# Test on staging, then when ready for production:
git checkout main
git merge develop
git push origin main  # Deploys to production
```


=== security rules ===

## Sensitive Files & Git Security

### Never Commit Sensitive Files
The following files must NEVER be committed to git and are protected in `.gitignore`:

**CRITICAL - Never commit:**
- `.env` - Environment variables with secrets
- `.env.backup` - Backup environment files
- `.env.production` - Production environment variables
- `auth.json` - Composer authentication credentials (contains GitHub tokens, private package credentials)

**Why auth.json is critical:**
- Contains GitHub personal access tokens
- Contains credentials for private Composer repositories
- If committed, tokens must be immediately revoked and regenerated
- Already protected in `.gitignore` on line 23

### Verifying Files Are Protected
Before committing, always verify sensitive files are not staged:
```bash
git status
git ls-files | grep -E "(\.env|auth\.json)"  # Should return nothing
```

### If Accidentally Committed
If you accidentally commit sensitive files:
1. **DO NOT PUSH** - Stop immediately
2. Amend the commit: `git commit --amend`
3. Remove the file from staging: `git reset HEAD <file>`
4. Verify it's in `.gitignore`

If already pushed to remote:
1. Revoke all credentials immediately (GitHub tokens, API keys, etc.)
2. Contact the user to discuss repository history rewrite
3. Never force-push to `main` or `develop` without approval


=== ci/cd rules ===

## Continuous Integration & Quality Checks

### Pre-Commit Quality Workflow
Before committing or pushing code, you MUST run these checks locally to ensure GitHub Actions will pass:

1. **PHP Code Style**: `vendor/bin/pint` (or `vendor/bin/pint --dirty` for changed files only)
2. **Frontend Linting**: `npm run lint` (runs ESLint with auto-fix)
3. **Frontend Formatting**: `npm run format` (runs Prettier)
4. **Run Tests**: `php artisan test` (or filter specific tests with `--filter`)
5. **Build Assets**: `npm run build` (ensures no build errors)

### Critical Rule: Local Checks Must Pass Before Push
- NEVER push code without running these checks locally first
- If checks pass locally, they should pass on GitHub Actions
- If GitHub Actions fail but local passes, check for environment differences (missing files, database state, etc.)

### Common CI/CD Failure Causes
1. **Missing files in git**: Files exist locally but weren't committed (e.g., test fixtures, images)
2. **Database state**: Local DB has data/migrations that CI doesn't have
3. **Environment differences**: `.env.example` missing required keys that `.env` has
4. **TypeScript errors**: `any` types, unused variables, missing type definitions
5. **Missing migrations**: Tests reference DB tables that don't exist in CI

### GitHub Actions Integration
The project uses GitHub Actions for automated quality checks on push/PR:

**Lint Workflow** (`.github/workflows/lint.yml`):
- Runs Pint for PHP code style
- Runs Prettier for frontend formatting
- Runs ESLint for TypeScript/React linting
- Triggers on pushes to `develop` and `main` branches

**Tests Workflow** (`.github/workflows/tests.yml`):
- Installs dependencies
- Builds frontend assets with `npm run build`
- Copies `.env.example` to `.env`
- Generates application key
- Runs full Pest test suite
- Triggers on pushes to `develop` and `main` branches

### Fixing Failed CI/CD Checks
When GitHub Actions show failures (red ❌):

1. Click on the failed check to see error details
2. Reproduce the error locally by running the same commands
3. Fix the issues locally
4. Re-run all quality checks
5. Only push when all checks pass locally

### Best Practice: Run All Checks Command
Consider creating these convenience scripts:

**In `composer.json`**:
```json
"scripts": {
    "check": [
        "@pint",
        "@test"
    ],
    "pint": "pint --dirty",
    "test": "pest"
}
```

**In `package.json`**:
```json
"scripts": {
    "check": "npm run lint && npm run format && npm run build"
}
```

Then before pushing: `composer run check && npm run check`

### Test Environment Requirements
When writing tests, ensure they work in CI environments:
- Don't rely on local files that aren't committed
- Use factories to create test data instead of assuming DB state
- Mock external API calls to avoid network dependencies
- Ensure migrations run before tests in CI (check `.github/workflows/tests.yml`)


=== image-processing-api rules ===

# Image Processing API - Laravel 12 Implementation

## Project Overview

This is an implementation plan for a Laravel 12 API that processes images with AI features. The API will serve application clients who integrate the image processing service into their own applications. Multi-tenancy will be implemented in the future using schema-based approach (required by Laravel Cloud limitations).

Core functionality includes:
- Automatic image tagging using AI to identify contents
- Vector embeddings for similarity search
- Smart contextual search with fuzzy matching via Laravel Scout/Meilisearch
- Object detection to find multiple items within images (Pokemon cards, DVDs, books, etc.)
- Visual and tag-based similarity comparison between images

## Complete Package List

### Core Laravel Packages
- `laravel/sanctum` - API authentication
- `laravel/spark-stripe` - Subscription billing portal with pre-built UI
- `laravel/scout` - Search abstraction (with Meilisearch driver)
- `laravel/horizon` - Queue monitoring (optional but recommended)

### Admin and Dev Tools
- `filament/filament` - Admin panel and dashboard
- `pestphp/pest` - Testing framework
- `barryvdh/laravel-debugbar` - Development debugging

### Image Processing
- `intervention/image-laravel` - Image manipulation
- `spatie/laravel-medialibrary` - Media handling with S3 integration

### Database and Search
- `ankane/pgvector` - PostgreSQL vector operations
- `meilisearch/meilisearch-php` - Required for Scout driver
- `tpetry/laravel-postgresql-enhanced` - PostgreSQL specific features

### API Enhancements
- `spatie/laravel-query-builder` - API filtering and sorting
- `spatie/laravel-data` - Data Transfer Objects
- `spatie/laravel-webhook-server` - Webhook delivery

### Performance
- `spatie/laravel-rate-limited-job-middleware` - Rate limit external API calls
- `spatie/laravel-responsecache` - Response caching

### Utilities
- `spatie/laravel-backup` - Database backups
- `spatie/laravel-activitylog` - Audit logging
- `guzzlehttp/guzzle` - HTTP client for external APIs

## Service Architecture

### Domain Services (Business Logic)

**ImageService**: Handles all image operations including upload, storage, thumbnail generation, duplicate checking, and deletion. Coordinates with storage and processing services.

**TagService**: Manages tag operations including generating tags (delegates to AI provider), storing tags with confidence scores, managing tag-image relationships, and triggering embedding regeneration.

**DetectionService**: Handles object detection logic including processing detection requests, extracting sub-images from bounding boxes, creating child image records, and managing detection history.

**SearchService**: Coordinates different search types including smart search via Scout/Meilisearch, similarity search via embeddings, metadata filtering, and result ranking.

**EmbeddingService**: Manages embedding operations including generating embeddings (delegates to provider), storing/retrieving embeddings from database, calculating similarities using pgvector.

### Provider Services (External APIs)

**GeminiProvider**: Low-level Gemini API interaction for text generation and image analysis. Returns raw API responses.

**CohereProvider**: Low-level Cohere API interaction for embedding generation. Handles API authentication and errors.

Note: Laravel Scout handles most Meilisearch operations directly through the Searchable trait on models.

## API Endpoints Detailed Specification

### Unified Array Handling

All endpoints accept arrays for batch operations. Single items should be wrapped in an array of one element. This provides a consistent API interface.

### Images Resource

**GET /api/v1/images**  
Lists all images for the authenticated user with pagination. Supports filtering by parent_id to get detected items, type (original or detected_item), date ranges, and processing status. Can include related data via ?include=tags,embeddings,children.

**GET /api/v1/images/{id}**  
Retrieves single image with all metadata. Returns children array if image has detected items. Optional includes for tags, embeddings, and children details.

**POST /api/v1/images**  
Uploads images (always as array, even for single file). Optional parameters: auto_tag (default true) to generate tags via AI, detect_types array to run detection, webhook_url to override default webhook. Returns immediate response with processing status, queues background jobs.

**DELETE /api/v1/images**  
Deletes images by ID. Always accepts ids array in body. Cascades to delete detected child images. Cleans up S3 files and removes from search index.

### Tag Operations

**POST /api/v1/images/tags**  
Flexible endpoint for tag operations. For manual tags: accepts image_ids array and tags array. For generation: set generate=true with image_ids array. Triggers embedding regeneration automatically.

**DELETE /api/v1/images/tags**  
Removes tags from images. Accepts image_ids array and tags array to remove. Triggers embedding regeneration.

### Detection Operations

**POST /api/v1/images/detect**  
Runs object detection on images. Accepts image_ids array with item_types array. Skips already-detected types and returns info about what was skipped. Creates child images for each detected item. Cannot run on images that are themselves detected items.

### Search Operations

**POST /api/v1/images/smart-search**  
Scout-powered contextual search via Meilisearch. Accepts queries array with fuzzy matching, typo tolerance, and filters. Searches across both original and detected items.

**POST /api/v1/images/similar**  
Finds similar images using embeddings. Accepts image_ids array, type (visual or tags), optional weights for factors like recency, and minimum similarity threshold.

**POST /api/v1/images/status**  
Checks processing status for async operations. Accepts image_ids array. Returns current status, progress percentage, completed tasks, and any errors.

**POST /api/v1/images/duplicates**  
Finds exact duplicates by hash. Accepts image_ids array to check.

### User Operations

**POST /api/v1/export**  
Initiates GDPR-compliant data export. Returns export ID for status checking. Generates ZIP with all images and metadata.

**GET /api/v1/stats**  
Returns usage statistics including image counts, storage used, API calls this month, popular tags, and processing success rates.

**GET /api/v1/health**  
System health check for monitoring. Returns status of database, Redis, S3, Meilisearch, and external APIs.

## Queue Job Specifications

**ProcessUploadedImage**: Generates thumbnails, extracts metadata, triggers tagging and embedding generation.

**GenerateTags**: Calls AI provider for tag generation, stores with confidence scores, updates search index via Scout.

**GenerateEmbeddings**: Creates visual embedding from image, combines tags for tag embedding, stores vectors in database.

**DetectItems**: Runs detection for specified item types, extracts sub-images, creates child records.

**SendWebhook**: Delivers webhooks with HMAC signatures, implements exponential backoff.

## Laravel Scout Integration

Models use the Searchable trait for automatic Meilisearch indexing. The toSearchableArray method defines what gets indexed (tags, metadata, etc.). Scout handles index updates automatically on model changes. No separate Meilisearch service needed for basic operations.

Configure Scout with Meilisearch driver in config/scout.php. Set up index settings for typo tolerance and ranking rules.

## Testing Strategy

### Unit Tests
- Test each service method in isolation
- Mock external API calls
- Verify business logic correctness
- Test array operations with 0, 1, and multiple items

### Feature Tests
- Test complete API endpoints
- Verify authentication and authorization
- Test request validation
- Check response formats match specification

### Integration Tests
- Test actual external API integrations (using test credentials)
- Verify S3 operations
- Test Scout indexing
- Validate pgvector similarity calculations

### Testing Checklist for Each Feature
- Works with empty arrays, single item arrays, and multiple items
- Handles errors gracefully
- Triggers appropriate background jobs
- Sends webhooks when configured
- Updates search indexes via Scout
- Respects rate limits

## Database Schema Decisions

All images (original and detected items) share the same images table, differentiated by type field and parent_id.

Tags are stored in a separate table with many-to-many relationship to images via pivot table.

Vector embeddings are stored directly on images table as pgvector columns (1536 dimensions for Cohere). No separate caching needed as they're persisted.

Detection metadata (bounding boxes, confidence) stored as JSON on detected item records.

Processing status tracked on each image for async operation monitoring.

## Implementation Notes

Start without multi-tenancy but design with tenant_id columns for future migration.

Use Laravel Sanctum for API authentication with bearer tokens.

Always accept arrays for batch operations, even for single items. This provides a consistent API interface.

Tag changes must always trigger embedding regeneration since the tag embedding represents all tags combined.

Detected items are just images with a parent_id - this allows all image operations to work uniformly.

For search operations, use POST even though they're "reads" because of complex query parameters and array inputs.

## Performance Considerations

Add database indexes on commonly queried fields: user_id (future tenant_id), parent_id, type, processing_status, created_at.

pgvector HNSW indexes only needed after 1000+ images since queries will be filtered by user.

Embeddings are stored in database so no separate caching needed. Only regenerate when source data changes.

Use queues for all external API calls to prevent request timeouts.

Implement circuit breakers for external services to handle API outages gracefully.

Use Scout's built-in queueing for search index updates to improve response times.

## Security Considerations

Validate all file uploads for type, size, and content.

Use signed URLs for S3 uploads and downloads.

Implement rate limiting per API token.

Sanitize any user-provided text before sending to AI services.

Never expose internal IDs in API responses where possible.

## Development Priorities

1. Core image upload and storage functionality
2. Tagging with Gemini integration
3. Scout/Meilisearch setup for smart search
4. Embeddings and similarity search with pgvector
5. Object detection for multiple items
6. Webhook system for async notifications
7. Filament dashboard for management
8. Multi-tenancy when first real client onboards

Remember: This is a draft plan that will evolve as you build and learn what works best for your specific use case.

</laravel-boost-guidelines>
