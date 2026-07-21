<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordSystemActivity
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldAudit($request)) {
            return $next($request);
        }

        $user = $request->user();

        try {
            $response = $next($request);
            $this->record($request, $response->getStatusCode(), $request->user() ?? $user);

            return $response;
        } catch (Throwable $exception) {
            $status = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;
            $this->record($request, $status, $user);

            throw $exception;
        }
    }

    private function shouldAudit(Request $request): bool
    {
        if (in_array($request->method(), self::MUTATING_METHODS, true)) {
            return true;
        }

        $routeName = (string) $request->route()?->getName();

        return $request->isMethod('GET')
            && ($routeName === 'admin.activity-logs.index' || Str::endsWith($routeName, '.export'));
    }

    private function record(Request $request, int $status, mixed $user): void
    {
        try {
            $routeName = $request->route()?->getName();
            $routeParameters = $request->route()?->parameters() ?? [];
            [$subjectType, $subjectId] = $this->subject($routeParameters);
            $outcome = $status >= 400 ? 'failure' : 'success';
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
                'severity' => $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info'),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'route_name' => $routeName,
                'http_method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'status_code' => $status,
                'description' => sprintf('%s %s request %s.', $request->method(), $module, $outcome),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Auditing must never break the user-facing operation.
        }
    }

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
