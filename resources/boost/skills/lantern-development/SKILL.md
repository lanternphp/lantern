---
name: lantern-development
description: Build and work with Lantern features and actions for organizing domain logic with declarative authorization.
---

# Lantern Development

## When to use this skill

Use this skill when working with the `lanternphp/lantern` package — creating Features, Actions, availability checks, constraints, or integrating Lantern's authorization into controllers and Blade views.

## Core concepts

- **Feature** — groups related Actions and sub-Features. Defines system-level constraints. Cannot be executed directly.
- **Action** — single unit of business logic. Has `perform()`, optional `prepare()`, availability checks, and constraints.
- **ActionProxy** — transparent wrapper returned by `Action::make()`. Enforces authorization before execution.
- **ActionResponse** — result of `perform()` or `prepare()`. Has `successful()`, `unsuccessful()`, `data()`, `errors()`.

## File structure

```
app/Features/
├── AppFeatures.php              # Top-level Feature (registered in service provider)
├── Todos/
│   ├── TodosFeature.php
│   ├── CreateTodoAction.php
│   └── UpdateTodoAction.php
└── Users/
    ├── UsersFeature.php
    └── GetCurrentUserAction.php
```

Always use the `App\Features` namespace.

## Creating a Feature

```php
<?php
namespace App\Features\Todos;

use Lantern\Features\Feature;

class TodosFeature extends Feature
{
    const ACTIONS = [
        CreateTodoAction::class,
        UpdateTodoAction::class,
    ];

    // Optional: nest sub-features
    const FEATURES = [
        TodoCategoriesFeature::class,
    ];
}
```

A Feature must contain at least one Action or sub-Feature.

## Creating an Action

```php
<?php
namespace App\Features\Todos;

use App\Models\Todo;
use Lantern\Features\Action;
use Lantern\Features\ActionResponse;
use Lantern\Features\AvailabilityBuilder;

class UpdateTodoAction extends Action
{
    const GUEST_USERS = false; // default; set true to allow unauthenticated users

    public function __construct(
        private TodoRepository $repo,
        private Todo $todo
    ) {}

    public function perform(string $title): ActionResponse
    {
        $updated = $this->repo->update($this->todo, compact('title'));
        return $this->success($updated);
    }

    public function prepare(): ActionResponse
    {
        return $this->success(['todo' => $this->todo]);
    }

    protected function availability(AvailabilityBuilder $builder)
    {
        $builder->userCan('update', $this->todo);
    }
}
```

- Dependencies go in the **constructor** (resolved by Laravel's container via `make()`).
- Input data goes in **`perform()` parameters**.
- Always return `ActionResponse` from `perform()` and `prepare()` using `$this->success()` or `$this->failure()`.

## Availability (user authorization)

Defined on Actions only. Runs every time (not cached).

```php
protected function availability(AvailabilityBuilder $builder)
{
    $builder->userCan('create', Todo::class);
    $builder->userCannot('delete', $this->todo);
    $builder->assertTrue($condition, 'Failure message');
    $builder->assertFalse($condition, 'Failure message');
    $builder->assertNull($value, 'Failure message');
    $builder->assertNotNull($value, 'Failure message');
    $builder->assertEqual($a, $b, 'Failure message');
    $builder->assertNotEqual($a, $b, 'Failure message');

    $user = $builder->user(); // never use Auth::user() here
}
```

## Constraints (system requirements)

Defined on Features or Actions. Cached per request.

```php
protected function constraints(ConstraintsBuilder $constraints)
{
    $constraints->classExists(\ZipArchive::class);
    $constraints->extensionIsLoaded('gd');
    $constraints->executableIsInstalled('pdftk');
}
```

If a Feature's constraints fail, all its Actions become unavailable.

## Calling Actions

```php
// Always use make() — never instantiate directly
$response = CreateTodoAction::make($repo)->perform('Buy milk');

// With prepare
$data = UpdateTodoAction::make($repo, $todo)->prepare();

// Check availability before calling
$action = DeleteTodoAction::make($repo, $todo);
if ($action->available()) {
    $response = $action->perform();
}

// Handle response
if ($response->successful()) {
    $item = $response->data();        // all data
    $title = $response->data('title'); // specific key
} else {
    $errors = $response->errors();
}
```

## Registration

In `AppServiceProvider::boot()`:

```php
use Lantern\Lantern;

public function boot()
{
    Lantern::register(AppFeatures::class);
}
```

For multi-stack setups, register multiple top-level Features:

```php
Lantern::register(AppFeatures::class, VendorFeatures::class);
```

Set `const STACK = 'vendor-name'` on vendor top-level Features to namespace action IDs.

## Authorization in Blade

Each registered Action auto-creates a Laravel Gate:

```blade
@can('todos.create-todo')
    <button>Create Todo</button>
@endcan

@can('todos.update-todo', [$todo])
    <a href="#">Edit</a>
@endcan
```

Action IDs are auto-generated from class names in kebab-case, prefixed by the parent Feature ID.

## Artisan commands

```bash
php artisan lantern:make-feature Todos/TodosFeature
php artisan lantern:make-action Todos/CreateTodoAction
```

## Critical rules

1. **Never** instantiate an Action with `new` — always use `Action::make()`.
2. **Always** return `ActionResponse` from `perform()` and `prepare()`.
3. **Register** every Action in a Feature's `ACTIONS` constant.
4. **Use availability** for user/context checks, **constraints** for system checks — never mix them.
5. **Use `$builder->user()`** inside availability, not `Auth::user()`.
6. **Action IDs cannot contain periods** — use kebab-case (`todos-create`, not `todos.create`).
7. **`GUEST_USERS`** defaults to `false`. Set to `true` explicitly for public Actions.
8. **Only declare `const STACK`** on top-level Features, never on nested ones.
