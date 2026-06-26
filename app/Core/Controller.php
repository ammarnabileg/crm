<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

/**
 * Base controller with the small set of helpers every action needs.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return view($template, $data, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    protected function redirectRoute(string $name, array $params = [], int $status = 302): Response
    {
        return Response::redirect(route_to($name, $params), $status);
    }

    protected function back(): Response
    {
        return back();
    }

    /**
     * Validate request input, returning the validated subset or throwing a
     * ValidationException (handled centrally -> flashes errors + old input).
     */
    protected function validate(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        return Validator::make($request->all(), $rules, $messages, $attributes)->validate();
    }

    /**
     * Flash a success message for the next request.
     */
    protected function withSuccess(string $message): void
    {
        session()->flash('status', $message);
    }

    protected function withError(string $message): void
    {
        session()->flash('error', $message);
    }

    /**
     * Throw a validation error bound to a single field (e.g. failed login).
     */
    protected function fail(string $field, string $message, array $input = []): never
    {
        throw new ValidationException([$field => [$message]], $input);
    }
}
