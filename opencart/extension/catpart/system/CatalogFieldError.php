<?php
declare(strict_types=1);
namespace Catpart;
/** Compatible validation exception with a stable form field/group key. */
final class CatalogFieldError extends \InvalidArgumentException
{
    public function __construct(public readonly string $field,string $message){parent::__construct($message);}
}
