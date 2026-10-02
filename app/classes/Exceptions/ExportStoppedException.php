<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Thrown inside a running background export to stop it once its job has been failed from outside. */
final class ExportStoppedException extends RuntimeException
{
}
