<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * A redirect that could not be performed, carrying where it meant to go.
 *
 * Thrown only under CLI. Serving a request is the only context in which a redirect
 * means anything: header() does nothing under CLI, so MiscUtility::redirect() there
 * amounted to a silent exit, and a silent exit is never what a caller wanted.
 *
 * It exists so a test can drive one of the procedural helpers to completion. Those
 * files end in a redirect, and the destination is half the outcome -- the same save
 * returns to the grid or back to the form depending on what happened -- so a test
 * that could not see it would be checking half the behaviour.
 */
final class RedirectException extends Exception
{
    public function __construct(private readonly string $url, ?Throwable $previous = null)
    {
        parent::__construct("Redirect to $url", 0, $previous);
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
