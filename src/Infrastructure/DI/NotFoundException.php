<?php declare(strict_types=1);

namespace WPM\Infrastructure\DI;

defined('ABSPATH') || exit;

/**
 * Exception thrown when a container identifier is not found.
 */
final class NotFoundException extends \Exception implements NotFoundExceptionInterface
{
}
