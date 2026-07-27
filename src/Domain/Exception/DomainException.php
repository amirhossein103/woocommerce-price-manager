<?php declare(strict_types=1);

namespace WPM\Domain\Exception;

defined('ABSPATH') || exit;

/**
 * General domain exception implementation.
 */
class DomainException extends \Exception implements DomainExceptionInterface
{
}
