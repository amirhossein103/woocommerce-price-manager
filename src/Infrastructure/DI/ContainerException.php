<?php declare(strict_types=1);

namespace WPM\Infrastructure\DI;

defined('ABSPATH') || exit;

/**
 * General container exception implementation.
 */
final class ContainerException extends \Exception implements ContainerExceptionInterface
{
}
