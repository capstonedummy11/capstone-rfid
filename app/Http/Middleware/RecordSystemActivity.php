<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordSystemActivity
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    // @function handle: Pinoproseso ang request o event para sa Record System Activity.
    // @useIn handle: Laravel web middleware pipeline
    /**
     * @feature   Audit Logging
     *
     * @actor     Shared / Core
     *
     * @flow      Dito nilolog ang mutating requests at selected exports kahit may audit storage error.
     *
     * @uses      resources/js/pages/Admin/ActivityLogs/ActivityLogsPage.vue; app/Http/Middleware/RecordSystemActivity.php: RecordSystemActivity::handle
     *
     * @related   Authentication, Attendance, Reports
     *
     * @disable   1) Alisin ang RecordSystemActivity::class registration sa bootstrap/app.php.
     * @disable   2) Itago ang activity-log link sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Middleware/RecordSystemActivity.php: handle. Side effect: mawawala ang automatic system activity trail; may module-specific logs pa rin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shouldAudit = $this->shouldAudit($request);
        $user = $request->user();

        try {
            $response = $next($request);
            // Keep routine page views out of the audit table, but capture failed ones.
            if ($shouldAudit || $response->getStatusCode() >= 400) {
                $this->record($request, $response->getStatusCode(), $request->user() ?? $user, $this->hasFormErrors($response));
            }

            return $response;
        } catch (Throwable $exception) {
            $status = $this->exceptionStatus($exception);
            $this->record($request, $status, $user);

            throw $exception;
        }
    }

    private function exceptionStatus(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof ValidationException => $exception->status,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => $exception->status() ?? 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof TokenMismatchException => 419,
            default => 500,
        };
    }

    private function hasFormErrors(Response $response): bool
    {
        $session = $response instanceof RedirectResponse ? $response->getSession() : null;

        // Only errors flashed by this response count; older session errors belong to a previous request.
        return $session !== null && in_array('errors', $session->get('_flash.new', []), true);
    }

    // @function shouldAudit: Sinusuri kung audit para sa Record System Activity.
    // @useIn shouldAudit: RecordSystemActivity::handle (app/Http/Middleware/RecordSystemActivity.php)
    private function shouldAudit(Request $request): bool
    {
        if (in_array($request->method(), self::MUTATING_METHODS, true)) {
            return true;
        }

        $routeName = (string) $request->route()?->getName();

        return $request->isMethod('GET')
            && ($routeName === 'admin.activity-logs.index' || Str::endsWith($routeName, '.export'));
    }

    // @function record: Nagtatala ng ang record system activity sa Record System Activity flow.
    // @useIn record: RecordSystemActivity::handle (app/Http/Middleware/RecordSystemActivity.php)
    private function record(Request $request, int $status, mixed $user, bool $hasFormErrors = false): void
    {
        try {
            $routeName = $request->route()?->getName();
            $routeParameters = $request->route()?->parameters() ?? [];
            [$subjectType, $subjectId] = $this->subject($routeParameters);
            $outcome = $status >= 400 || $hasFormErrors ? 'failure' : 'success';
            $module = $this->module($routeName, $request->path());

            ActivityLog::query()->create([
                'event_id' => (string) Str::uuid(),
                'user_id' => $user?->user_id,
                'user_name' => $user?->name,
                'user_role' => $user?->role,
                'action' => $this->action($request->method(), $routeName),
                'table_name' => $module,
                'module' => $module,
                'outcome' => $outcome,
                'severity' => $status >= 500 ? 'error' : ($outcome === 'failure' ? 'warning' : 'info'),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'route_name' => $routeName,
                'http_method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'status_code' => $status,
                'description' => $hasFormErrors
                    ? sprintf('%s %s request failed with form errors.', $request->method(), $module)
                    : sprintf('%s %s request %s.', $request->method(), $module, $outcome),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Audit storage can fail independently of the request; retain its cause in the application log.
            try {
                report($exception);
            } catch (Throwable) {
                // Logging failure must never replace the original request result.
            }
        }
    }

    // @function module: Binubuo ang module string para sa Record System Activity.
    // @useIn module: RecordSystemActivity::record (app/Http/Middleware/RecordSystemActivity.php)
    private function module(?string $routeName, string $path): string
    {
        $parts = explode('.', (string) $routeName);
        if (($parts[0] ?? null) === 'admin' && isset($parts[1])) {
            return Str::of($parts[1])->replace('-', '_')->singular()->toString();
        }

        if (($parts[0] ?? null) !== '') {
            return Str::of($parts[0])->replace('-', '_')->singular()->toString();
        }

        return Str::of(explode('/', $path)[0] ?: 'system')->replace('-', '_')->singular()->toString();
    }

    // @function action: Binubuo ang action string para sa Record System Activity.
    // @useIn action: RecordSystemActivity::record (app/Http/Middleware/RecordSystemActivity.php)
    private function action(string $method, ?string $routeName): string
    {
        $routeAction = Str::afterLast((string) $routeName, '.');
        if ($routeAction !== '' && ! in_array($routeAction, ['index', 'show'], true)) {
            return Str::snake(str_replace('-', '_', $routeAction));
        }

        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => strtolower($method),
        };
    }

    // @function subject: Kinukuha ang subject result para sa Record System Activity.
    // @useIn subject: RecordSystemActivity::record (app/Http/Middleware/RecordSystemActivity.php)
    private function subject(array $parameters): array
    {
        foreach (array_reverse($parameters, true) as $name => $value) {
            if ($value instanceof Model) {
                return [Str::snake(class_basename($value)), (string) $value->getKey()];
            }

            if (is_scalar($value) && $value !== '') {
                return [Str::snake((string) $name), (string) $value];
            }
        }

        return [null, null];
    }
}
