<?php declare(strict_types=1);

namespace WPM\Infrastructure\DI;

defined('ABSPATH') || exit;

/**
 * Base interface representing a generic exception in a container.
 */
interface ContainerExceptionInterface extends \Throwable
{
}
